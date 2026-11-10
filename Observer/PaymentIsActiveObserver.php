<?php

namespace Zaver\Payment\Observer;

use Magento\Framework\Event\ObserverInterface;
use Psr\Log\LoggerInterface;

class PaymentIsActiveObserver implements ObserverInterface
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
   * @var \Zaver\Payment\Model\PaymentMethods
   */
  private $_paymentMethods;

  public function __construct(\Psr\Log\LoggerInterface $logger,
                              \Magento\Checkout\Model\Session $checkoutSession,
                              \Zaver\Payment\Model\PaymentMethods $paymentMethods) {
    $this->_logger = $logger;
    $this->_checkoutSession = $checkoutSession;
    $this->_paymentMethods = $paymentMethods;
  }

  /**
   * The Zaver payment method is available when Zaver offers at least one payment method for the cart's market,
   * currency and amount. Other payment methods are left alone.
   *
   * @param \Magento\Framework\Event\Observer $observer
   */
  public function execute(\Magento\Framework\Event\Observer $observer) {
    $event = $observer->getEvent();
    $result = $event->getResult();

    if ($event->getMethodInstance()->getCode() != \Zaver\Payment\Model\Checkout::METHOD_CODE || !$result->getData('is_available')) {
      return;
    }

    try {
      // The event carries the cart for REST and GraphQL checkouts too, where there is no checkout session.
      $quote = $event->getQuote() ?: $this->_checkoutSession->getQuote();

      $result->setData('is_available', !empty($this->_paymentMethods->getOffered($quote)));
    }
    catch
    (\Exception $e) {
      $this->_logger->warning('Zaver payment method availability could not be checked', ['error' => $e->getMessage()]);
      $result->setData('is_available', false);
    }
  }
}
