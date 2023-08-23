<?php

namespace Zaver\Payment\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\App\RequestInterface;
use Psr\Log\LoggerInterface;
use Magento\Checkout\Model\Session;
use Zaver\Payment\Model\Payment;
use Zaver\SDK\Object\PaymentMethodsRequest;
use Zaver\SDK\Checkout;

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
    $event = $observer->getEvent();
    $method = $event->getMethodInstance();
    $result = $event->getResult();

    try {
      // Check if the Payment Method Zaver module is enabled
      $isInstallmentsEnabled = $this->_helper->getZVInstallmentsActive();
      $isPayLaterEnabled = $this->_helper->getZVPayLaterActive();

      $quote = $this->_checkoutSession->getQuote();
      $currencyCode = $quote->getStore()->getCurrentCurrencyCode();

      $zaverApiKey = $this->_helper->getZVApiKey();
      $zaverTestMode = $this->_helper->getZVTestMode();
      $amount = $quote->getGrandTotal();
      $bisPayment = false;


      if ($amount > 0 && ($method->getCode() == 'zaver_installments' ||
          $method->getCode() == 'zaver_paylater')
      ) {

        $oCheckout = new \Zaver\SDK\Checkout($zaverApiKey, $zaverTestMode);
        $oPaymentReq = PaymentMethodsRequest::create()
          ->setCurrency($currencyCode)
          ->setAmount($amount);

        $oPaymentRes = $oCheckout->getPaymentMethods($oPaymentReq);

        if ($method->getCode() == 'zaver_installments') {
          if ($isInstallmentsEnabled && $currencyCode == 'EUR') {
            if (count($oPaymentRes["paymentMethods"]) > 0) {
              foreach ($oPaymentRes["paymentMethods"] as $method) {
                $methodCode = $method["paymentMethod"];

                if ($methodCode == $this->_helper->getZaverInstallmentsCode()) {
                  $bisPayment = true;
                }
              }
            }

            if ($bisPayment) {
              $result->setData('is_available', true);
            }
            else {
              $result->setData('is_available', false);
            }
          }
          else {
            $result->setData('is_available', false);
          }
        }
        elseif ($method->getCode() == 'zaver_paylater') {
          if ($isPayLaterEnabled && $currencyCode == 'EUR') {
            if (count($oPaymentRes["paymentMethods"]) > 0) {
              foreach ($oPaymentRes["paymentMethods"] as $method) {
                $methodCode = $method["paymentMethod"];

                if ($methodCode == $this->_helper->getZaverPayLaterCode()) {
                  $bisPayment = true;
                }
              }
            }

            if ($bisPayment) {
              $result->setData('is_available', true);
            }
            else {
              $result->setData('is_available', false);
            }
          }
          else {
            $result->setData('is_available', false);
          }
        }
      }
      else {
        $result->setData('is_available', $method->getIsActive());
      }
    }
    catch
    (\Exception $e) {
    }
  }
}