<?php

namespace Zaver\Payment\Model;

class Banktransfer extends \Zaver\Payment\Model\Payment
{
  const METHOD_CODE = 'zaver_banktransfer';
  const CODE = 'zaver_banktransfer';

  /**
   * Payment code
   *
   * @var string
   */
  protected $_code = self::METHOD_CODE;
  protected $_canUseInternal = false;
}
