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
    \Zaver\Payment\Helper\Checkout $checkoutHelper,
    \Magento\Sales\Model\Order\Payment\Transaction\Builder $tranBuilder
  ) {
    parent::__construct($context, $logger, $checkoutSession, $orderFactory, $helper, $tranBuilder);
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
      $this->applyZaverStatus();
      $this->getMessageManager()->addSuccess(__("Your payment is complete"));
      $this->redirectToCheckoutOnePageSuccess();
    }
  }

  /**
   * Apply the Zaver payment status to the order like a callback would, in case the callback is late or cannot reach
   * the shop. The order is the one of this checkout session, and its status is asked from Zaver, so the return URL
   * cannot change orders by itself. Failures only get logged: the customer is sent to the success page regardless.
   */
  private function applyZaverStatus() {
    $order = $this->getOrder();

    if (!$order || !$order->getData('zaver_payment_id')) {
      return;
    }

    try {
      $statusUpdater = $this->getObjectManager()->get(\Zaver\Payment\Model\PaymentStatusUpdater::class);
      $statusUpdater->apply($order, $statusUpdater->fetchStatus($order));
    }
    catch (\Exception $e) {
      $this->getLogger()->warning('Zaver payment status not applied on return', [
        'order' => $order->getIncrementId(), 'error' => $e->getMessage()
      ]);
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
