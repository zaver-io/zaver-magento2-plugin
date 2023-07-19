<?php

namespace Zaver\Payment\Model\Config;

use Magento\Checkout\Model\ConfigProviderInterface;
use Psr\Log\LoggerInterface;
use Zaver\Payment\Model\Payment;
use Zaver\SDK\Object\PaymentMethodsRequest;
use Zaver\SDK\Checkout;

class ConfigProvider implements ConfigProviderInterface
{
  /**
   * @var \Magento\Payment\Helper\Data
   */
  protected $paymentHelper;

  /**
   * @var \Magento\Framework\Escaper
   */
  protected $escaper;

  /**
   * @var \Zaver\Payment\Helper\Data
   */
  protected $zvDataHelper;

  /**
   * Checkout session
   *
   * @var \Magento\Checkout\Model\Session
   */
  protected $checkoutSession;

  /**
   * @var LoggerInterface
   */
  private $logger;

  /**
   * @var AbstractMethod[]
   */
  protected $aMethodInstances;

  /**
   * Array with all zaver payment methods
   *
   * @var array
   */
  protected $allZaverPayMethods = [
    \Zaver\Payment\Model\Paylater::METHOD_CODE,
    \Zaver\Payment\Model\Installments::METHOD_CODE
  ];

  /**
   * @param \Magento\Payment\Helper\Data $paymentHelper
   * @param \Magento\Framework\Escaper $escaper
   * @param \Zaver\Payment\Helper\Data $zvDataHelper
   * @param \Magento\Checkout\Model\Session $checkoutSession
   */
  public function __construct(
    \Magento\Payment\Helper\Data $paymentHelper,
    \Magento\Framework\Escaper $escaper,
    \Zaver\Payment\Helper\Data $zvDataHelper,
    \Magento\Checkout\Model\Session $checkoutSession,
    \Psr\Log\LoggerInterface $logger
  ) {
    $this->escaper = $escaper;
    $this->paymentHelper = $paymentHelper;
    $this->zvDataHelper = $zvDataHelper;
    $this->checkoutSession = $checkoutSession;
    $this->logger = $logger;
  }

  /**
   * {@inheritdoc}
   */
  public function getConfig() {
    $strpaylaterTitle = 'Rechnung';
    $strinstallmentsTitle = 'Ratenzahlung';
    $strpaylaterLogoSrc = 'https://cdn.zaver.com/DE/paymentmethod/icon-pay-later.svg';
    $strinstallmentsLogoSrc = 'https://cdn.zaver.com/DE/paymentmethod/icon-installments.svg';

    try {
      $zaverApiKey = $this->zvDataHelper->getZVApiKey();
      $zaverTestMode = $this->zvDataHelper->getZVTestMode();
      $languageCode = 'EUR';

      $amount = $this->checkoutSession->getQuote()->getGrandTotal();

      $oCheckout = new \Zaver\SDK\Checkout($zaverApiKey, $zaverTestMode);
      $oPaymentReq = PaymentMethodsRequest::create()
        ->setCurrency($languageCode)
        ->setAmount($amount);

      $oPaymentRes = $oCheckout->getPaymentMethods($oPaymentReq);

      if (count($oPaymentRes["paymentMethods"]) > 0) {

        foreach ($oPaymentRes["paymentMethods"] as $method) {
          if ($method["paymentMethod"] == $this->zvDataHelper->getZaverPayLaterCode()) {
            $strpaylaterLogoSrc = $method["iconSvgSrc"];
            $strpaylaterTitle = $method["title"];
          }
          if ($method["paymentMethod"] == $this->zvDataHelper->getZaverInstallmentsCode()) {
            $strinstallmentsLogoSrc = $method["iconSvgSrc"];
            $strinstallmentsTitle = $method["title"];
          }
        }
      }
    }
    catch (Exception $e) {
      $this->logger->log(\Psr\Log\LogLevel::INFO, "ERROR:" . $e->getMessage());
    }

    return [
      'payment' => [
        'zaver' => [
          'paylaterTitle' => $strpaylaterTitle,
          'installmentsTitle' => $strinstallmentsTitle,
          'paylaterLogoSrc' => $strpaylaterLogoSrc,
          'installmentsLogoSrc' => $strinstallmentsLogoSrc,
          'paylaterDesc' => 'Spater bezahlen',
          'installmentsDesc' => 'Zahlen Sie in Ihrem Tempo'
        ]
      ]
    ];
  }
}