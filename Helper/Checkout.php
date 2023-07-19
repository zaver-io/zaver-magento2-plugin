<?php

namespace Zaver\Payment\Helper;

use Magento\Sales\Model\Order;

/**
 * Checkout workflow helper
 *
 * Class Checkout
 * @package Zaver\Payment\Helper
 */
class Checkout
{
  /**
   * @var \Magento\Checkout\Model\Session
   */
  protected $_checkoutSession;

  /**
   * @param \Magento\Checkout\Model\Session $checkoutSession
   */
  public function __construct(
    \Magento\Checkout\Model\Session $checkoutSession
  ) {
    $this->_checkoutSession = $checkoutSession;
  }

  /**
   * Get an Instance of the Magento Checkout Session
   * @return \Magento\Checkout\Model\Session
   */
  protected function getCheckoutSession() {
    return $this->_checkoutSession;
  }

  /**
   * Cancel last placed order with specified comment message
   *
   * @param string $comment Comment appended to order history
   * @return bool True if order cancelled, false otherwise
   */
  public function cancelCurrentOrder($comment) {
    $order = $this->getCheckoutSession()->getLastRealOrder();
    if ($order->getId() && $order->getState() != Order::STATE_CANCELED) {
      $order->registerCancellation($comment)->save();
      return true;
    }
    return false;
  }

  /**
   * Restores quote
   *
   * @return bool
   */
  public function restoreQuote() {
    return $this->getCheckoutSession()->restoreQuote();
  }
}
