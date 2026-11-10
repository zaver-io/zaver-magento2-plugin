<?php

namespace Zaver\Payment\Plugin;

use Magento\Sales\Model\Order;

/**
 * Magento does not let a merchant cancel an order in payment review. A Zaver order is in payment review while Zaver
 * waits for a confirmation that may never come (e.g. a bank transfer), so the merchant may cancel it; cancelling
 * also cancels the Zaver payment (Observer\OrderCancelAfter).
 */
class OrderCanCancel
{
  const ZAVER_METHODS = [
    \Zaver\Payment\Model\Checkout::METHOD_CODE,
    \Zaver\Payment\Model\Paylater::METHOD_CODE,
    \Zaver\Payment\Model\Installments::METHOD_CODE
  ];

  /**
   * @param Order $subject
   * @param bool $result
   * @return bool
   */
  public function afterCanCancel(Order $subject, $result) {
    if ($result || !$subject->isPaymentReview() || $subject->hasInvoices()) {
      return $result;
    }

    $payment = $subject->getPayment();

    return $payment && in_array($payment->getMethod(), self::ZAVER_METHODS, true);
  }
}
