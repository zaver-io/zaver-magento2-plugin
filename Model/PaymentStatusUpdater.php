<?php

namespace Zaver\Payment\Model;

use Magento\Sales\Model\Order;
use Zaver\SDK\Config\PaymentStatus;

/**
 * Applies the status of an order's Zaver payment to the order, from a Zaver callback or from "Refresh Zaver status"
 * in the admin. Only orders still waiting for the payment are moved:
 *
 *   PENDING_CONFIRMATION      Zaver waits for confirmation (e.g. an incoming bank transfer) => Payment Review, which
 *                             blocks invoicing and shipping and is not cancelled by Magento's pending order cleanup
 *   PENDING_MERCHANT_CAPTURE  paid, waiting for capture => Processing (accepts a payment in review)
 *   SETTLED                   paid and captured => Complete (accepts a payment in review)
 *   CANCELLED, ERROR          => Canceled (denies a payment in review)
 *   CREATED                   the payer has not finished yet => unchanged
 *
 * The Zaver status is recorded on every order.
 */
class PaymentStatusUpdater
{
  /** Order states that still wait for the Zaver payment */
  const WAITING_STATES = [Order::STATE_NEW, Order::STATE_PENDING_PAYMENT, Order::STATE_PAYMENT_REVIEW];

  /**
   * @var \Zaver\Payment\Helper\Data
   */
  private $helper;

  /**
   * @var \Psr\Log\LoggerInterface
   */
  private $logger;

  public function __construct(\Zaver\Payment\Helper\Data $helper,
                              \Psr\Log\LoggerInterface $logger) {
    $this->helper = $helper;
    $this->logger = $logger;
  }

  /**
   * The current status of the order's Zaver payment, asked from Zaver.
   *
   * @param Order $order
   * @return string
   * @throws \Magento\Framework\Exception\LocalizedException
   */
  public function fetchStatus(Order $order) {
    $paymentId = (string)$order->getData('zaver_payment_id');

    if ($paymentId === '') {
      throw new \Magento\Framework\Exception\LocalizedException(__('This order has no Zaver payment.'));
    }

    $checkout = new \Zaver\SDK\Checkout($this->helper->getZVApiKey(), (bool)$this->helper->getZVTestMode());

    return $checkout->getPaymentStatus($paymentId)->getPaymentStatus();
  }

  /**
   * Apply a Zaver payment status to the order and save it.
   *
   * @param Order $order
   * @param string $zaverStatus
   * @return string What happened to the order, for the admin
   */
  public function apply(Order $order, $zaverStatus) {
    $state = $order->getState();
    $waiting = in_array($state, self::WAITING_STATES, true);
    $result = (string)__('Order unchanged.');

    $order->setData('zaver_payment_status', $zaverStatus);

    if ($waiting) {
      switch ($zaverStatus) {
        case PaymentStatus::PENDING_CONFIRMATION:
          if ($state != Order::STATE_PAYMENT_REVIEW) {
            $this->setState($order, Order::STATE_PAYMENT_REVIEW,
              __('Zaver is waiting for the payment to be confirmed (e.g. an incoming bank transfer).'));
            $result = (string)__('Order is in payment review until Zaver confirms the payment.');
          }
          break;

        case PaymentStatus::PENDING:
          $this->setState($order, Order::STATE_PROCESSING, __('Zaver accepted the payment; it awaits capture.'));
          $order->setData('zaver__status', 1);
          $result = (string)__('Payment accepted, order is processing.');
          break;

        case PaymentStatus::SETTLED:
          $this->setState($order, Order::STATE_COMPLETE, __('Zaver settled the payment.'));
          $order->setData('zaver__status', 1);
          $result = (string)__('Payment settled, order is complete.');
          break;

        case PaymentStatus::CANCELLED:
        case PaymentStatus::ERROR:
          $order->setData('zaver__status', 0);
          try {
            // registerCancellation() also cancels orders in payment review and releases their items.
            $order->registerCancellation((string)__('Zaver reported the payment as %1.', $zaverStatus), false);
            $result = (string)__('Payment denied, order is canceled.');
          }
          catch (\Magento\Framework\Exception\LocalizedException $e) {
            // E.g. an order that has already been invoiced; the merchant has to look at it.
            $order->addCommentToStatusHistory((string)__('Zaver reported the payment as %1, but the order could not be canceled: %2',
              $zaverStatus, $e->getMessage()));
            $result = (string)__('Payment denied, but the order could not be canceled: %1', $e->getMessage());
          }
          break;
      }
    }
    elseif ($zaverStatus == PaymentStatus::CANCELLED || $zaverStatus == PaymentStatus::ERROR) {
      $order->setData('zaver__status', 0);
    }

    $order->save();

    $this->logger->info('Zaver payment status applied', [
      'order' => $order->getIncrementId(), 'zaverStatus' => $zaverStatus, 'previousState' => $state,
      'state' => $order->getState()
    ]);

    return $result;
  }

  /**
   * @param Order $order
   * @param string $state
   * @param \Magento\Framework\Phrase $comment
   */
  private function setState(Order $order, $state, $comment) {
    $order->setState($state)
      ->setStatus($order->getConfig()->getStateDefaultStatus($state));
    $order->addCommentToStatusHistory((string)$comment);
  }
}
