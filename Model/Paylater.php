<?php

namespace Zaver\Payment\Model;

/**
 * Payment method of orders placed before 2.0.0, kept for their order view, captures and refunds. New orders use
 * the Zaver payment method (zaver_checkout), so this one is not offered in the checkout.
 */
class Paylater extends \Zaver\Payment\Model\Payment
{
  const METHOD_CODE = 'zaver_paylater';

  /**
   * Payment code
   *
   * @var string
   */
  protected $_code = self::METHOD_CODE;

  protected $_canUseCheckout = false;
}
