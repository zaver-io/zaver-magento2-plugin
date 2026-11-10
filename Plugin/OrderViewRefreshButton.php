<?php

namespace Zaver\Payment\Plugin;

/**
 * "Refresh Zaver status" on the admin order view of Zaver orders: asks Zaver for the payment status and applies it,
 * for when a callback is late or cannot reach the shop.
 */
class OrderViewRefreshButton
{
  /**
   * @param \Magento\Sales\Block\Adminhtml\Order\View $subject
   * @param \Magento\Framework\View\LayoutInterface $layout
   * @return null
   */
  public function beforeSetLayout(\Magento\Sales\Block\Adminhtml\Order\View $subject, $layout) {
    $order = $subject->getOrder();
    $payment = $order ? $order->getPayment() : null;

    if ($payment && in_array($payment->getMethod(), OrderCanCancel::ZAVER_METHODS, true)
      && $order->getData('zaver_payment_id')) {
      $subject->addButton('zaver_refresh_status', [
        'label' => __('Refresh Zaver status'),
        'onclick' => 'setLocation(\'' . $subject->getUrl('zaver_payment/order/refresh', ['order_id' => $order->getId()]) . '\')'
      ]);
    }

    return null;
  }
}
