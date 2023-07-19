<?php

namespace Zaver\Payment\Controller;

/**
 * Base Checkout Redirect Controller Class
 * Class AbstractCheckoutRedirectAction
 * @package Zaver\Payment\Controller
 */
abstract class AbstractCheckoutRedirectAction extends \Zaver\Payment\Controller\AbstractCheckoutAction
{
  /**
   * @var \Zaver\Payment\Helper\Checkout
   */
  private $_checkoutHelper;

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
    \Zaver\Payment\Helper\Checkout $checkoutHelper
  ) {
    parent::__construct($context, $logger, $checkoutSession, $orderFactory, $helper);
    $this->_checkoutHelper = $checkoutHelper;
  }

  /**
   * Get an Instance of the Magento Checkout Helper
   * @return \Zaver\Payment\Helper\Checkout
   */
  protected function getCheckoutHelper() {
    return $this->_checkoutHelper;
  }

  /**
   * Handle Success Action
   * @return void
   */
  protected function executeSuccessAction() {
    if ($this->getCheckoutSession()->getLastRealOrderId()) {
      $this->getMessageManager()->addSuccess(__("Your payment is complete"));
      $this->redirectToCheckoutOnePageSuccess();
    }
  }

  /**
   * Handle Cancel Action from Payment Gateway
   */
  protected function executeCancelAction() {
    $this->getCheckoutHelper()->cancelCurrentOrder('');
    $this->getCheckoutHelper()->restoreQuote();
    $this->redirectToCheckoutCart();
  }

  /**
   * Get the redirect action
   *      - success
   *      - cancel
   *      - callback
   *
   * @return string
   */
  protected function getReturnAction() {
    return $this->getRequest()->getParam('action');
  }
}
