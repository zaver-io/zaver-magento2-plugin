<?php

namespace Zaver\Payment\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\App\RequestInterface;
use Psr\Log\LoggerInterface;
use Magento\Checkout\Model\Session;
use Zaver\SDK\Checkout;
use Zaver\SDK\Config\PaymentStatus;

class OrderCancelAfter implements ObserverInterface
{
  /**
   * @var LoggerInterface
   */
  private $_logger;

  /**
   * Checkout session
   *
   * @var \Magento\Checkout\Model\Session
   */
  protected $_checkoutSession;

  /**
   * @var \Zaver\Payment\Helper\Data
   */
  public $_helper;

  public function __construct(\Psr\Log\LoggerInterface $logger,
                              \Magento\Checkout\Model\Session $checkoutSession,
                              \Zaver\Payment\Helper\Data $data) {
    $this->_logger = $logger;
    $this->_checkoutSession = $checkoutSession;
    $this->_helper = $data;
  }

  /**
   * Active only for zaver payment methods
   *
   * @param \Magento\Framework\Event\Observer $observer
   */
  public function execute(\Magento\Framework\Event\Observer $observer) {
    /** @var \Magento\Sales\Model\Order $order */
    $order = $observer->getEvent()->getorder();

    try {
      // Check if the Payment Method Zaver module is enabled
      $isInstallmentsEnabled = $this->_helper->getZVInstallmentsActive();
      $isPayLaterEnabled = $this->_helper->getZVPayLaterActive();

      $zaverApiKey = $this->_helper->getZVApiKey();
      $zaverTestMode = $this->_helper->getZVTestMode();

      $paymentId = $order->getData("zaver_payment_id");

      // If the order was paid with Zaver
      if (!empty($paymentId)) {
        $oCheckout = new \Zaver\SDK\Checkout($zaverApiKey, $zaverTestMode);
        $zvStatusPmRes = $oCheckout->getPaymentStatus($paymentId);
        $zvStatusPm = $zvStatusPmRes->getPaymentStatus();

        if ($zvStatusPm != PaymentStatus::SETTLED && $zvStatusPm != PaymentStatus::CANCELLED) {
          $oCheckout->cancelPayment($paymentId);
        }
      }
    }
    catch
    (\Exception $e) {
      // Stop Magento's cancellation (the event fires before the order is saved): a canceled order whose Zaver
      // payment is still open could get paid, e.g. by a bank transfer that arrives later.
      $this->_logger->error('Zaver payment could not be cancelled', [
        'order' => $order->getIncrementId(), 'error' => $e->getMessage()
      ]);
      throw new \Magento\Framework\Exception\LocalizedException(
        __('The Zaver payment could not be cancelled, so the order was not canceled: %1', $e->getMessage())
      );
    }
  }
}