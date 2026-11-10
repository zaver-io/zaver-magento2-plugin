<?php

namespace Zaver\Payment\Controller\Checkout;

use Magento\Framework\App\Action\HttpGetActionInterface;

/**
 * The Zaver payment methods offered for the current cart with their texts, for the checkout's payment step.
 * The market follows the cart's billing address, which the customer enters after the checkout page has loaded.
 *
 * Class Methods
 * @package Zaver\Payment\Controller\Checkout
 */
class Methods implements HttpGetActionInterface
{
  /**
   * @var \Magento\Framework\Controller\Result\JsonFactory
   */
  private $resultJsonFactory;

  /**
   * @var \Magento\Checkout\Model\Session
   */
  private $checkoutSession;

  /**
   * @var \Zaver\Payment\Model\PaymentMethods
   */
  private $paymentMethods;

  public function __construct(\Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory,
                              \Magento\Checkout\Model\Session $checkoutSession,
                              \Zaver\Payment\Model\PaymentMethods $paymentMethods) {
    $this->resultJsonFactory = $resultJsonFactory;
    $this->checkoutSession = $checkoutSession;
    $this->paymentMethods = $paymentMethods;
  }

  /**
   * @return \Magento\Framework\Controller\Result\Json
   */
  public function execute() {
    $quote = $this->checkoutSession->getQuote();

    return $this->resultJsonFactory->create()->setData([
      'market' => $this->paymentMethods->getMarket($quote),
      'methods' => $quote->getId() ? $this->paymentMethods->getOffered($quote) : []
    ]);
  }
}
