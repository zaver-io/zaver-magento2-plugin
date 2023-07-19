<?php

namespace Zaver\Payment\Controller\Checkout;

/**
 * Return Action Controller (used to handle Redirects from the Payment Gateway)
 *
 * Class Redirect
 * @package Zaver\Payment\Controller\Checkout
 */
class Redirect extends \Zaver\Payment\Controller\AbstractCheckoutRedirectAction
{
  /**
   * Handle the result from the Payment Gateway
   *
   * @return void
   */
  public function execute() {
    switch ($this->getReturnAction()) {
      case 'success':
        $this->executeSuccessAction();
        break;

      case 'cancel':
        $this->getMessageManager()->addWarning(
          __("You have canceled your order")
        );
        $this->executeCancelAction();
        break;

      default:
        $this->getResponse()->setHttpResponseCode(
          \Magento\Framework\Webapi\Exception::HTTP_UNAUTHORIZED
        );
    }
  }
}
