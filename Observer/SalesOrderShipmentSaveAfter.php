<?php

namespace Zaver\Payment\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\App\RequestInterface;
use Psr\Log\LoggerInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\OrderRepository;
use Magento\Catalog\Model\Product\Type as ProductType;
use Zaver\SDK\Checkout;
use Zaver\SDK\Object\PaymentCaptureRequest;
use Zaver\SDK\Config\PaymentStatus;
use Zaver\SDK\Object\LineItem;
use Zaver\SDK\Config\ItemType;

class SalesOrderShipmentSaveAfter implements ObserverInterface
{
  /**
   * @var LoggerInterface
   */
  private $_logger;

  /**
   * @var \Magento\Framework\Message\ManagerInterface
   */
  private $messageManager;

  /**
   * @var \Zaver\Payment\Helper\Data
   */
  private $_helper;

  /**
   * @var \Zaver\Payment\Model\Shipments
   */
  private $_shipments;

  /**
   * @var \Magento\Sales\Model\Service\InvoiceService
   */
  protected $_invoiceService;

  /**
   * @var \Magento\Framework\DB\Transaction
   */
  protected $_dbTransaction;

  /**
   * @var \Magento\Sales\Model\Order\Payment\Transaction\Builder
   */
  protected $_tranBuilder;

  public function __construct(\Psr\Log\LoggerInterface $logger,
                              \Magento\Framework\Message\ManagerInterface $messageManager,
                              \Zaver\Payment\Helper\Data $data,
                              \Zaver\Payment\Model\Shipments $shipments,
                              \Magento\Sales\Model\Service\InvoiceService $invoiceService,
                              \Magento\Framework\DB\Transaction $transaction,
                              \Magento\Sales\Model\Order\Payment\Transaction\Builder $tranBuilder) {
    $this->_logger = $logger;
    $this->messageManager = $messageManager;
    $this->_helper = $data;
    $this->_shipments = $shipments;
    $this->_invoiceService = $invoiceService;
    $this->_dbTransaction = $transaction;
    $this->_tranBuilder = $tranBuilder;
  }

  /**
   * Active only for zaver payment methods
   *
   * @param \Magento\Framework\Event\Observer $observer
   */
  public function execute(\Magento\Framework\Event\Observer $observer) {
    $helper = $this->_helper;
    $event = $observer->getEvent();
    $method = $event->getMethodInstance();
    $result = $event->getResult();

    /** @var \Magento\Sales\Model\Order\Shipment $shipment */
    $shipment = $observer->getEvent()->getShipment();

    /** @var \Magento\Sales\Model\Order $order */
    $order = $shipment->getOrder();
    $orderId = $order->getIncrementId();
    $shipId = $shipment->getIncrementId();

    $paymentId = (string)$order->getData('zaver_payment_id');

    // Only orders paid with Zaver
    if ($paymentId === '') {
      return $this;
    }

    try {
      $oCheckout = new \Zaver\SDK\Checkout($helper->getZVApiKey(), $helper->getZVTestMode());
      $zvStatusPm = $oCheckout->getPaymentStatus($paymentId)->getPaymentStatus();

      // Only a payment awaiting capture can be captured (API v2 rejects captures in any other status). A settled
      // payment has nothing left to capture.
      if ($zvStatusPm != PaymentStatus::PENDING) {
        if ($zvStatusPm != PaymentStatus::SETTLED) {
          $this->messageManager->addWarningMessage(
            __('The Zaver payment cannot be captured yet (status %1), so no invoice was created for this shipment.', $zvStatusPm)
          );
        }
        return $this;
      }

      // Every capture gets its own paid invoice for exactly the shipped items (Magento adds the shipping costs to the
      // first invoice), so Magento's invoices add up to what Zaver captured and each can be refunded online.
      $invoice = $this->prepareShipmentInvoice($order, $shipment);
      if (!$invoice || $invoice->getBaseGrandTotal() <= 0) {
        return $this;
      }

      $totalAmount = round((float)$invoice->getBaseGrandTotal(), 2);
      $oPaymentCapReq = PaymentCaptureRequest::create()
        ->setCurrency($order->getOrderCurrencyCode())
        ->setAmount($totalAmount);

      // Line items when they add up to the invoice; otherwise (e.g. bundles) Zaver captures the amount alone.
      $lineItems = $this->getCaptureLineItems($invoice, $this->getZaverLineItemIds($order));
      foreach ($lineItems ?? [] as $lineItem) {
        $oPaymentCapReq->addLineItem($lineItem);
      }

      // API v2 answers a capture with the payment status: SETTLED once the whole amount is captured,
      // still PENDING_MERCHANT_CAPTURE after a partial capture. It returns no capture id.
      $captureResp = $oCheckout->capturePayment($paymentId, $oPaymentCapReq);
      $resCaptureStatus = $captureResp->getPaymentStatus();

      if (($resCaptureStatus != PaymentStatus::SETTLED && $resCaptureStatus != PaymentStatus::PENDING)
        || $captureResp->getCapturedAmount() <= 0) {
        $this->messageManager->addWarningMessage(
          __('Zaver did not capture the payment (status %1), so no invoice was created for this shipment.', $resCaptureStatus)
        );
        return $this;
      }

      // One transaction id per capture; the authorization already uses the payment id itself.
      $transactionId = $paymentId . '-capture-' . ($order->getInvoiceCollection()->getSize() + 1);

      // Captured at Zaver already: register the invoice as paid without calling the payment method again.
      $invoice->setTransactionId($transactionId);
      $invoice->setRequestedCaptureCase(\Magento\Sales\Model\Order\Invoice::CAPTURE_OFFLINE);
      $invoice->register();

      if (in_array($order->getState(), [\Magento\Sales\Model\Order::STATE_NEW,
        \Magento\Sales\Model\Order::STATE_PENDING_PAYMENT, \Magento\Sales\Model\Order::STATE_PAYMENT_REVIEW], true)) {
        $order->setState(\Magento\Sales\Model\Order::STATE_PROCESSING)
          ->setStatus($order->getConfig()->getStateDefaultStatus(\Magento\Sales\Model\Order::STATE_PROCESSING));
      }
      $order->setData('zaver__status', 1);

      // Magento completes the order on save once everything is shipped and invoiced.
      $this->_dbTransaction->addObject($invoice)->addObject($order)->save();

      // The capture transaction the invoice refers to; online refunds of the invoice use it as their parent.
      $this->createTransaction($order, array("id" => $transactionId, "capturedAmount" => $totalAmount,
        "paymentStatus" => $resCaptureStatus, "parentid" => $paymentId), true);
    }
    catch
    (\Exception $e) {
      $this->messageManager->addWarningMessage(
        __($e->getMessage())
      );
    }

    return $this;
  }

  /**
   * An invoice for the shipment's quantities, or null when nothing in it can be invoiced (e.g. already invoiced).
   *
   * @param Order $order
   * @param Order\Shipment $shipment
   * @return \Magento\Sales\Model\Order\Invoice|null
   */
  private function prepareShipmentInvoice(Order $order, Order\Shipment $shipment) {
    $qtys = [];

    /** @var \Magento\Sales\Model\Order\Shipment\Item $item */
    foreach ($shipment->getItemsCollection() as $item) {
      $orderItem = $item->getOrderItem();
      $qty = min((float)$item->getQty(), (float)$orderItem->getQtyToInvoice());

      if ($qty > 0) {
        $qtys[$item->getOrderItemId()] = $qty;
      }
    }

    // An empty list would make Magento invoice the whole order.
    if (empty($qtys) || !$order->canInvoice()) {
      return null;
    }

    $invoice = $this->_invoiceService->prepareInvoice($order, $qtys);

    return $invoice->getTotalQty() > 0 ? $invoice : null;
  }

  /**
   * Zaver line items for the invoice's items and shipping, or null when they do not add up to the invoice total
   * (e.g. bundles, or an item Zaver does not know); Zaver then captures the amount alone.
   *
   * @param \Magento\Sales\Model\Order\Invoice $invoice
   * @param array $zaverIds
   * @return \Zaver\SDK\Object\LineItem[]|null
   */
  private function getCaptureLineItems($invoice, array $zaverIds) {
    $lineItems = [];
    $total = 0.0;

    /** @var \Magento\Sales\Model\Order\Invoice\Item $item */
    foreach ($invoice->getAllItems() as $item) {
      $orderItem = $item->getOrderItem();
      $qty = (int)$item->getQty();

      // Children of configurable products are priced on their parent's invoice item.
      if ($qty <= 0 || $orderItem->getParentItem()) {
        continue;
      }

      // The payment keeps configurable products under their child (simple) item.
      $zaverId = $zaverIds[$orderItem->getItemId()] ?? null;
      foreach ($orderItem->getChildrenItems() ?: [] as $childItem) {
        $zaverId = $zaverId ?? ($zaverIds[$childItem->getItemId()] ?? null);
      }

      if ($zaverId === null) {
        return null;
      }

      $rowTotal = round((float)$item->getBaseRowTotalInclTax() - (float)$item->getBaseDiscountAmount(), 2);
      $lineItems[] = \Zaver\SDK\Object\LineItem::create()
        ->setId($zaverId)
        ->setName((string)$orderItem->getName())
        ->setMerchantReference((string)$orderItem->getSku())
        ->setQuantity($qty)
        ->setUnitPrice(round($rowTotal / $qty, 2))
        ->setTotalAmount($rowTotal)
        ->setTaxRatePercent((float)$orderItem->getTaxPercent())
        ->setTaxAmount(round((float)$item->getBaseTaxAmount(), 2));
      $total += $rowTotal;
    }

    $shipping = round((float)$invoice->getBaseShippingInclTax(), 2);
    if ($shipping > 0) {
      if (!isset($zaverIds[ItemType::SHIPPING])) {
        return null;
      }

      $shippingTax = round((float)$invoice->getBaseShippingTaxAmount(), 2);
      $lineItems[] = \Zaver\SDK\Object\LineItem::create()
        ->setId($zaverIds[ItemType::SHIPPING])
        ->setName((string)$invoice->getOrder()->getShippingDescription())
        ->setMerchantReference(ItemType::SHIPPING)
        ->setQuantity(1)
        ->setUnitPrice($shipping)
        ->setTotalAmount($shipping)
        ->setTaxRatePercent($shipping > $shippingTax && $shippingTax > 0
          ? round($shippingTax / ($shipping - $shippingTax) * 100, 2) : 0.0)
        ->setTaxAmount($shippingTax);
      $total += $shipping;
    }

    if (empty($lineItems) || abs($total - round((float)$invoice->getBaseGrandTotal(), 2)) > 0.01) {
      return null;
    }

    return $lineItems;
  }

  /**
   * Magento order item id (and "SHIPPING") => Zaver line item id, stored on the order when the payment was created.
   *
   * @param Order $order
   * @return array
   */
  private function getZaverLineItemIds(Order $order) {
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
   * This code checks if all products in the order are going to be shipped. This used the qty_shipped column
   * so it works with partial shipments as well.
   * Examples:
   * - You have an order with 2 items. You are shipping both items. This function will return true.
   * - You have an order with 2 items. The first shipments contains 1 items, the second shipment also. The first
   *   time this function returns false, the second time true as it is shipping all remaining items.
   *
   * @param Order $order
   * @param Order\Shipment $shipment
   * @return bool
   */
  private function isShippingAllItems(Order $order, Order\Shipment $shipment) {
    /**
     * First build an array of all products in the order like this:
     * [item ID => quantiy]
     * [123 => 2]
     * [124 => 1]
     *
     * The method `getOrigData('qty_shipped')` is used as the value of `getQtyShipped()` is somewhere adjusted
     * and invalid, so not reliable to use for our case.
     */
    $shippableOrderItems = [];
    /** @var Order\Item $item */
    foreach ($order->getAllVisibleItems() as $item) {
      if (($item->getProducttype() != ProductType::TYPE_BUNDLE ||
          !$item->isShipSeparately()) &&
        !$item->getIsVirtual()
      ) {
        $quantity = $item->getQtyOrdered() - $item->getOrigData('qty_shipped');
        $shippableOrderItems[$item->getId()] = $quantity;
        continue;
      }

      /** @var Order\Item $childItem */
      foreach ($item->getChildrenItems() as $childItem) {
        if ((float)$childItem->getQtyShipped() === (float)$childItem->getOrigData('qty_shipped')) {
          continue;
        }

        $quantity = $childItem->getQtyOrdered() - $childItem->getOrigData('qty_shipped');
        $shippableOrderItems[$childItem->getId()] = $quantity;
      }
    }

    /**
     * Now subtract the number of items to ship in this shipment.
     *
     * Before:
     * [123 => 2]
     *
     * Shipping 1 item
     *
     * After:
     * [123 => 1]
     */
    /** @var Order\Shipment\Item $item */
    foreach ($shipment->getAllItems() as $item) {
      /**
       * Some extensions create shipments for all items, but that causes problems, so ignore them.
       */
      if (!isset($shippableOrderItems[$item->getOrderItemId()])) {
        continue;
      }

      if ($item->getOrderItem()->getProductType() == ProductType::TYPE_BUNDLE &&
        $item->getOrderItem()->isShipSeparately()
      ) {
        continue;
      }


      $shippableOrderItems[$item->getOrderItemId()] -= $item->getQty();
    }

    /**
     * Count the total number of items in the array. If it equals 0 then all (remaining) items in the order
     * are shipped.
     */
    return array_sum($shippableOrderItems) == 0;
  }

  public function createTransaction($order = null, $paymentData = array(), $bTranClosed = false) {
    try {
      // Get payment object from order object
      $payment = $order->getPayment();
      $payment->setLastTransId($paymentData['id']);
      $payment->setTransactionId($paymentData['id']);
      $payment->setIsTransactionClosed($bTranClosed);
      $payment->setParentTransactionId($paymentData['parentid']);
      // Only this key: the whole array would replace the chosen Zaver payment method stored on the payment.
      $payment->setAdditionalInformation(
        \Magento\Sales\Model\Order\Payment\Transaction::RAW_DETAILS, (array)$paymentData
      );
      $formatedPrice = $order->getBaseCurrency()->formatTxt(
        $paymentData["capturedAmount"]
      );

      $paymentData["capturedAmount"] = $formatedPrice;

      $message = __('The captured amount is %1.', $formatedPrice);
      // Get the object of builder class
      $trans = $this->_tranBuilder;
      $transaction = $trans->setPayment($payment)
        ->setOrder($order)
        ->setTransactionId($paymentData['id'])
        ->setAdditionalInformation(
          [\Magento\Sales\Model\Order\Payment\Transaction::RAW_DETAILS => (array)$paymentData]
        )
        ->setFailSafe(true)
        // Build method creates the transaction and returns the object
        ->build(\Magento\Sales\Model\Order\Payment\Transaction::TYPE_CAPTURE);

      $payment->addTransactionCommentsToOrder(
        $transaction,
        $message
      );

      $payment->save();
      $order->save();

      return $transaction->save()->getTransactionId();
    }
    catch (Exception $e) {
    }
  }
}