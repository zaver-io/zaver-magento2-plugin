<?php

namespace Zaver\Payment\Model;

use Magento\Framework\Exception\LocalizedException;
use Zaver\SDK\Config\ItemType;
use Zaver\SDK\Config\RefundStatus;
use Zaver\SDK\Object\MerchantUrls;
use Zaver\SDK\Object\RefundCreationRequest;
use Zaver\SDK\Object\RefundLineItem;

/**
 * Refunds a credit memo at Zaver. Runs from the payment method's refund(), i.e. for an online refund ("Refund" on the
 * credit memo) before Magento saves the credit memo, so a refund Zaver rejects stops the credit memo.
 *
 * The credit memo's items and shipping are refunded as Zaver line items. A credit memo with an adjustment refund or
 * fee, or with an item Zaver does not know, is refunded as a fixed amount (total and tax) instead. The refund is
 * approved right away.
 */
class RefundService
{
  /**
   * @var \Zaver\Payment\Helper\Data
   */
  private $helper;

  /**
   * @var \Psr\Log\LoggerInterface
   */
  private $logger;

  /**
   * @var \Magento\Framework\Message\ManagerInterface
   */
  private $messageManager;

  public function __construct(\Zaver\Payment\Helper\Data $helper,
                              \Psr\Log\LoggerInterface $logger,
                              \Magento\Framework\Message\ManagerInterface $messageManager) {
    $this->helper = $helper;
    $this->logger = $logger;
    $this->messageManager = $messageManager;
  }

  /**
   * @param \Magento\Sales\Model\Order\Payment $payment
   * @param float $amount The credit memo total in the base currency
   * @return string The Zaver refund id
   * @throws LocalizedException
   */
  public function refund($payment, $amount) {
    /** @var \Magento\Sales\Model\Order $order */
    $order = $payment->getOrder();
    /** @var \Magento\Sales\Model\Order\Creditmemo $creditmemo */
    $creditmemo = $payment->getCreditmemo();
    $paymentId = (string)$order->getData('zaver_payment_id');
    $amount = round((float)$amount, 2);

    if ($paymentId === '') {
      throw new LocalizedException(__('This order has no Zaver payment to refund.'));
    }

    $request = RefundCreationRequest::create()
      ->setPaymentId($paymentId)
      ->setInvoiceReference($this->getInvoiceReference($order, $creditmemo))
      ->setRefundAmount($amount)
      ->setDescription((string)__('Refund for order %1', $order->getIncrementId()))
      ->setMerchantUrls(MerchantUrls::create()->setCallbackUrl($this->helper->getRefundCallbackUrl($order->getIncrementId())));

    $lineItems = $creditmemo ? $this->getLineItems($order, $creditmemo, $amount) : null;
    if ($lineItems !== null) {
      foreach ($lineItems as $lineItem) {
        $request->addLineItem($lineItem);
      }
    }
    else {
      $tax = $creditmemo ? round((float)$creditmemo->getBaseTaxAmount(), 2) : 0.0;
      $request->setRefundTaxAmount($tax);
      if ($tax > 0 && $amount > $tax) {
        $request->setRefundTaxPercent(round($tax / ($amount - $tax) * 100, 2));
      }
    }

    try {
      $api = new \Zaver\SDK\Refund($this->helper->getZVApiKey(), (bool)$this->helper->getZVTestMode());
      $refund = $api->createRefund($request);
      $refundId = $refund->getRefundId();

      if ($refund->getStatus() == RefundStatus::PENDING_MERCHANT_APPROVAL) {
        $refund = $api->approveRefund($refundId);
      }
    }
    catch (\Exception $e) {
      $this->logger->error('Zaver refund failed', [
        'order' => $order->getIncrementId(), 'amount' => $amount, 'error' => $e->getMessage()
      ]);
      $this->fail(__('Zaver did not accept the refund: %1', $e->getMessage()));
    }

    if (!in_array($refund->getStatus(), [RefundStatus::PENDING_EXECUTION, RefundStatus::EXECUTED], true)) {
      $this->fail(__('Zaver did not accept the refund (status %1).', $refund->getStatus()));
    }

    $order->setData('zaver_refund_id', $refundId);
    $order->addCommentToStatusHistory((string)__('Zaver refund %1 of %2: %3.', $refundId,
      $order->getBaseCurrency()->formatTxt($amount), $refund->getStatus()));

    $this->logger->info('Zaver refund created', [
      'order' => $order->getIncrementId(), 'refundId' => $refundId, 'amount' => $amount, 'status' => $refund->getStatus()
    ]);

    return $refundId;
  }

  /**
   * Stop the credit memo. Magento replaces the exception's message with a generic one ("If the invoice was created
   * offline, ..."), so Zaver's reason is shown to the merchant as an admin message too.
   *
   * @param \Magento\Framework\Phrase $message
   * @throws LocalizedException
   */
  private function fail($message) {
    $this->messageManager->addErrorMessage($message);
    throw new LocalizedException($message);
  }

  /**
   * Zaver line items for the credit memo's items and shipping, or null when the credit memo has to be refunded as a
   * fixed amount (an adjustment, an item without a Zaver line item id, or totals that do not add up).
   *
   * @param \Magento\Sales\Model\Order $order
   * @param \Magento\Sales\Model\Order\Creditmemo $creditmemo
   * @param float $amount
   * @return RefundLineItem[]|null
   */
  private function getLineItems($order, $creditmemo, $amount) {
    if (abs((float)$creditmemo->getBaseAdjustmentPositive()) > 0.0001 || abs((float)$creditmemo->getBaseAdjustmentNegative()) > 0.0001) {
      return null;
    }

    $zaverIds = $this->getZaverLineItemIds($order);
    $lineItems = [];
    $total = 0.0;

    /** @var \Magento\Sales\Model\Order\Creditmemo\Item $item */
    foreach ($creditmemo->getAllItems() as $item) {
      $orderItem = $item->getOrderItem();
      $qty = (int)$item->getQty();

      // Bundles are refunded through their children; items not refunded in this credit memo are skipped.
      if ($qty <= 0 || $orderItem->getParentItem() || $orderItem->getProductType() == 'bundle') {
        continue;
      }

      // The payment keeps configurable products under their child (simple) item, the credit memo prices the parent.
      $zaverId = $zaverIds[$item->getOrderItemId()] ?? null;
      foreach ($orderItem->getChildrenItems() ?: [] as $childItem) {
        $zaverId = $zaverId ?? ($zaverIds[$childItem->getItemId()] ?? null);
      }

      if ($zaverId === null) {
        return null;
      }

      $rowTotal = round((float)$item->getBaseRowTotalInclTax() - (float)$item->getBaseDiscountAmount(), 2);
      $lineItems[] = RefundLineItem::create()
        ->setLineItemId($zaverId)
        ->setRefundQuantity($qty)
        ->setRefundUnitPrice(round($rowTotal / $qty, 2))
        ->setRefundTotalAmount($rowTotal)
        ->setRefundTaxAmount(round((float)$item->getBaseTaxAmount(), 2))
        ->setRefundTaxRatePercent((float)$orderItem->getTaxPercent());
      $total += $rowTotal;
    }

    $shipping = round((float)$creditmemo->getBaseShippingInclTax(), 2);
    if ($shipping > 0) {
      if (!isset($zaverIds[ItemType::SHIPPING])) {
        return null;
      }

      $shippingTax = round((float)$creditmemo->getBaseShippingTaxAmount(), 2);
      $lineItems[] = RefundLineItem::create()
        ->setLineItemId($zaverIds[ItemType::SHIPPING])
        ->setRefundQuantity(1)
        ->setRefundUnitPrice($shipping)
        ->setRefundTotalAmount($shipping)
        ->setRefundTaxAmount($shippingTax)
        ->setRefundTaxRatePercent($shipping > $shippingTax && $shippingTax > 0
          ? round($shippingTax / ($shipping - $shippingTax) * 100, 2) : 0.0);
      $total += $shipping;
    }

    // Zaver refunds the sum of the line items; fall back to a fixed amount when it differs from the credit memo.
    if (empty($lineItems) || abs($total - $amount) > 0.01) {
      return null;
    }

    return $lineItems;
  }

  /**
   * Magento order item id (and "SHIPPING") => Zaver line item id, stored on the order when the payment was created.
   *
   * @param \Magento\Sales\Model\Order $order
   * @return array
   */
  private function getZaverLineItemIds($order) {
    $ids = [];

    foreach (explode(';', (string)$order->getData('zaver_order_items_id')) as $piece) {
      if (strpos($piece, ':') !== false) {
        list($key, $value) = explode(':', $piece, 2);
        $ids[$key] = $value;
      }
    }

    return $ids;
  }

  /**
   * The increment id of the refunded invoice, else of the order's last invoice.
   *
   * @param \Magento\Sales\Model\Order $order
   * @param \Magento\Sales\Model\Order\Creditmemo|null $creditmemo
   * @return string
   */
  private function getInvoiceReference($order, $creditmemo) {
    if ($creditmemo && $creditmemo->getInvoice()) {
      return (string)$creditmemo->getInvoice()->getIncrementId();
    }

    $reference = '';
    foreach ($order->getInvoiceCollection() as $invoice) {
      $reference = (string)$invoice->getIncrementId();
    }

    return $reference !== '' ? $reference : (string)$order->getIncrementId();
  }
}
