<?php

namespace Zaver\Payment\Controller;

/**
 * Base Checkout Controller Class
 * Class AbstractCheckoutAction
 * @package Zaver\Payment\Controller
 */
abstract class AbstractCheckoutAction extends \Zaver\Payment\Controller\AbstractAction
{
  /**
   * @var \Magento\Checkout\Model\Session
   */
  private $_checkoutSession;

  /**
   * @var \Magento\Sales\Model\OrderFactory
   */

  private $_orderFactory;

  /**
   * @var \Zaver\Payment\Helper\Data
   */
  private $_helper;

  /**
   * @var \Magento\Sales\Model\Order\Payment\Transaction\Builder
   */
  protected $_tranBuilder;

  /**
   * @param \Magento\Framework\App\Action\Context $context
   * @param \Magento\Checkout\Model\Session $checkoutSession
   */
  public function __construct(
    \Magento\Framework\App\Action\Context $context,
    \Psr\Log\LoggerInterface $logger,
    \Magento\Checkout\Model\Session $checkoutSession,
    \Magento\Sales\Model\OrderFactory $orderFactory,
    \Zaver\Payment\Helper\Data $helper,
    \Magento\Sales\Model\Order\Payment\Transaction\Builder $tranBuilder
  ) {
    parent::__construct($context, $logger);
    $this->_checkoutSession = $checkoutSession;
    $this->_orderFactory = $orderFactory;
    $this->_helper = $helper;
    $this->_tranBuilder = $tranBuilder;
  }

  /**
   * Get an Instance of the Magento Checkout Session
   * @return \Magento\Checkout\Model\Session
   */
  protected function getCheckoutSession() {
    return $this->_checkoutSession;
  }

  /**
   * Get an Instance of the Magento Order Factory
   * It can be used to instantiate an order
   * @return \Magento\Sales\Model\OrderFactory
   */
  protected function getOrderFactory() {
    return $this->_orderFactory;
  }

  /**
   * Get an Instance of helper data
   * It can be used to instantiate an order
   * @return \Zaver\Payment\Helper\Data
   */
  protected function getHelper() {
    return $this->_helper;
  }

  /**
   * Get an Instance of transaction builder
      * @return \Magento\Sales\Model\Order\Payment\Transaction\Builder
   */
  protected function getTransactionBuilder() {
    return $this->_tranBuilder;
  }

  /**
   * Get an Instance of the current Checkout Order Object
   * @return \Magento\Sales\Model\Order
   */
  protected function getOrder() {
    $orderId = $this->getCheckoutSession()->getLastRealOrderId();

    if (!isset($orderId)) {
      return null;
    }

    $order = $this->getOrderFactory()->create()->loadByIncrementId(
      $orderId
    );

    if (!$order->getId()) {
      return null;
    }

    return $order;
  }

  /**
   * Does a redirect to the Checkout Payment Page
   * @return void
   */
  protected function redirectToCheckoutFragmentPayment() {
    $this->_redirect('checkout', ['_fragment' => 'payment']);
  }

  /**
   * Does a redirect to the Checkout Success Page
   * @return void
   */
  protected function redirectToCheckoutOnePageSuccess() {
    $this->_redirect('checkout/onepage/success');
  }

  /**
   * Does a redirect to the Checkout Cart Page
   * @return void
   */
  protected function redirectToCheckoutCart() {
    $this->_redirect('checkout/cart');
  }
}
