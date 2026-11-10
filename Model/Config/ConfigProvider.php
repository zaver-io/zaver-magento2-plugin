<?php

namespace Zaver\Payment\Model\Config;

use Magento\Checkout\Model\ConfigProviderInterface;
use Psr\Log\LoggerInterface;
use Zaver\Payment\Model\Payment;

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
   * @var \Zaver\Payment\Model\PaymentMethods
   */
  private $paymentMethods;

  /**
   * @var \Magento\Framework\UrlInterface
   */
  private $urlBuilder;

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
    \Psr\Log\LoggerInterface $logger,
    \Zaver\Payment\Model\PaymentMethods $paymentMethods,
    \Magento\Framework\UrlInterface $urlBuilder
  ) {
    $this->escaper = $escaper;
    $this->paymentHelper = $paymentHelper;
    $this->zvDataHelper = $zvDataHelper;
    $this->checkoutSession = $checkoutSession;
    $this->logger = $logger;
    $this->paymentMethods = $paymentMethods;
    $this->urlBuilder = $urlBuilder;
  }

  /**
   * The texts of the Zaver payment methods offered for the cart as it is when the checkout loads. The payment step
   * reloads them from methodsUrl once the customer has entered an address, as the market follows its country.
   *
   * {@inheritdoc}
   */
  public function getConfig() {
    $methods = [];

    try {
      $methods = $this->paymentMethods->getOffered($this->checkoutSession->getQuote());
    }
    catch (\Exception $e) {
      $this->logger->warning('Zaver payment methods could not be loaded for the checkout', ['error' => $e->getMessage()]);
    }

    return [
      'payment' => [
        'zaver' => [
          'methods' => $methods,
          'methodsUrl' => $this->urlBuilder->getUrl('zaver/checkout/methods')
        ]
      ]
    ];
  }
}
