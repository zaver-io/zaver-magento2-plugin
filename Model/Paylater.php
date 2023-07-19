<?php

namespace Zaver\Payment\Model;

class Paylater extends \Zaver\Payment\Model\Payment
{
  const METHOD_CODE = 'zaver_paylater';

  /**
   * Payment code
   *
   * @var string
   */
  protected $_code = self::METHOD_CODE;
}
