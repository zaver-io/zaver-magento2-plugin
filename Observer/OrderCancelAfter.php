<?php

namespace Zaver\Payment\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\App\RequestInterface;
use Psr\Log\LoggerInterface;
use Magento\Checkout\Model\Session;
use Zaver\SDK\Checkout;
use Zaver\SDK\Object\PaymentUpdateRequest;
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
        $oPaymentUpReq = PaymentUpdateRequest::create()
          ->setPaymentStatus(PaymentStatus::CANCELLED);

        $oCheckout = new \Zaver\SDK\Checkout($zaverApiKey, $zaverTestMode);
        $zvStatusPmRes = $oCheckout->getPaymentStatus($paymentId);
        $zvStatusPm = $zvStatusPmRes->getPaymentStatus();

        if ($zvStatusPm != PaymentStatus::SETTLED && $zvStatusPm != PaymentStatus::CANCELLED) {
          $oPaymentUpRes = $oCheckout->updatePayment($paymentId, $oPaymentUpReq);
        }
      }
    }
    catch
    (\Exception $e) {
    }
  }
}