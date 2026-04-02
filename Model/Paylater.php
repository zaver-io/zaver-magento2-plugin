<?php

namespace Zaver\Payment\Model;

use Magento\Payment\Model\InfoInterface;
use Magento\Sales\Api\Data\OrderInterface;

class Paylater extends \Zaver\Payment\Model\Payment
{
  const METHOD_CODE = 'zaver_paylater';
  const CODE = 'zaver_paylater';

  /**
   * Payment code
   *
   * @var string
   */
  protected $_code = self::METHOD_CODE;
  protected $_canUseInternal = false;

  public function authorize(InfoInterface $payment, $amount)
  {
      // Make sure the transaction is marked as pending so we don't get the wrong order state.
      $payment->setIsTransactionPending(true);

      /** @var OrderInterface $order */
      $order = $payment->getOrder();

      // Don't send the email just yet.
      $order->setCanSendNewEmailFlag(false);

      return parent::authorize($payment, $amount);
  }
}
