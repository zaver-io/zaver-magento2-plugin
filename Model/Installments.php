<?php

namespace Zaver\Payment\Model;

class Installments extends \Zaver\Payment\Model\Payment
{
  const METHOD_CODE = 'zaver_installments';

  /**
   * Payment code
   *
   * @var string
   */
  protected $_code = self::METHOD_CODE;
}
