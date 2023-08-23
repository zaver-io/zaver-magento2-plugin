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
use Zaver\SDK\Object\PaymentCaptureResponse;
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

    try {
      // Check if the Payment Method Zaver module is enabled
      $isInstallmentsEnabled = $this->_helper->getZVInstallmentsActive();
      $isPayLaterEnabled = $this->_helper->getZVPayLaterActive();

      $zaverApiKey = $helper->getZVApiKey();
      $zaverTestMode = $helper->getZVTestMode();

      $strOrderStatus = $order->getStatus();
      $paymentId = $order->getData("zaver_payment_id");
      $roundPow = pow(10, 0);
      $amount = $order->getGrandTotal() * $roundPow;
      $amount = round($amount, 2);

      $strOrderItemsId = $order->getData("zaver_order_items_id");
      $aZaverOrderItemsIds = array();

      if (!empty($strOrderItemsId)) {
        $aPieces = explode(';', $strOrderItemsId);

        foreach ($aPieces as $piece) {
          if (!empty($piece)) {
            list($key, $value) = explode(':', $piece);
            $aZaverOrderItemsIds[$key] = $value;
          }
        }
      }

      $aProdsShipped = $this->_shipments->getProductsShipped($orderId);

      // If the order was paid with Zaver
      if (!empty($paymentId)) {
        /*if ($this->isShippingAllItems($order, $shipment)) {
          $this->_logger->log(\Psr\Log\LogLevel::INFO, "IN OBSERVER: isShippingAllItems() true");
          $oPaymentCapReq = PaymentCaptureRequest::create()
            ->setCurrency($order->getOrderCurrencyCode())
            ->setAmount($amount);
          $this->_logger->log(\Psr\Log\LogLevel::INFO, "IN OBSERVER: oPaymentCapReq:" . print_r($oPaymentCapReq, true));

          $oCheckout = new \Zaver\SDK\Checkout($zaverApiKey, $zaverTestMode);
          $zvStatusPmRes = $oCheckout->getPaymentStatus($paymentId);
          $zvStatusPm = $zvStatusPmRes->getPaymentStatus();

          $this->_logger->log(\Psr\Log\LogLevel::INFO, "IN OBSERVER: zvStatusPmRes:" . print_r($zvStatusPmRes, true));

          if ($zvStatusPm != PaymentStatus::SETTLED && $zvStatusPm != PaymentStatus::CANCELLED) {
            $oCheckout->capturePayment($paymentId, $oPaymentCapReq);
          }
        }
        else {*/
        $orderHasDiscount = abs($order->getDiscountAmount() ?? 0) > 0;
        $oPaymentCapReq = PaymentCaptureRequest::create();

        $oCheckout = new \Zaver\SDK\Checkout($zaverApiKey, $zaverTestMode);
        $zvStatusPmRes = $oCheckout->getPaymentStatus($paymentId);
        $zvStatusPm = $zvStatusPmRes->getPaymentStatus();

        $totalAmount = 0;

        // Send the shipping to zaver the first time
        if (count($aProdsShipped) == 0) {
          $shippingName = $order->getShippingDescription();
          $shippingVatRate = 1;
          $shippingVatAmount = 1;
          $shippingAmountTax = $order->getBaseShippingInclTax() * $roundPow;

          if ($shippingAmountTax > 0) {
            $shippingAmount = $order->getBaseShippingInclTax() * $roundPow;
          }
          else {
            $shippingAmount = $order->getBaseShippingAmount() * $roundPow;
          }

          $shippingAmount = round($shippingAmount, 2);
          $totalAmount += $shippingAmount;

          $item = \Zaver\SDK\Object\LineItem::create()
            ->setId($aZaverOrderItemsIds[ItemType::SHIPPING])
            ->setName($shippingName)
            ->setMerchantReference(ItemType::SHIPPING)
            ->setQuantity(1)
            ->setUnitPrice($shippingAmount)
            ->setTotalAmount($shippingAmount)
            ->setTaxRatePercent($shippingVatRate)
            ->setTaxAmount($shippingVatAmount);

          $oPaymentCapReq->addLineItem($item);
          $this->_shipments->addShippedProducts($orderId, $shipId, ItemType::SHIPPING, 1, $shippingAmount, $aZaverOrderItemsIds[ItemType::SHIPPING]);
        }

        /** @var \Magento\Sales\Model\Order\Shipment\Item $item */
        foreach ($shipment->getItemsCollection() as $item) {
          if (!$item->getQty()) {
            continue;
          }

          $orderItemId = $item->getOrderItemId();
          $orderItem = $item->getOrderItem();
          $iQty = (int)$item->getQty();
          $strSku = $orderItem->getSku();
          $strName = $orderItem->getName();
          $parentId = $item->getParentId();

          $productPriceTax = $orderItem->getBasePriceInclTax() * $roundPow;

          if ($orderHasDiscount) {
            if ($productPriceTax > 0) {
              $rowTotal = $orderItem->getBaseRowTotalInclTax() - $orderItem->getBaseDiscountAmount();
            }
            else {
              $rowTotal = $orderItem->getBasePrice() - $orderItem->getBaseDiscountAmount();
            }

            $value = (($rowTotal) / $orderItem->getQtyOrdered()) * $item->getQty();
            $productPrice = $value * $roundPow;
          }
          else {
            if ($productPriceTax > 0) {
              $productPrice = $orderItem->getBasePriceInclTax() * $roundPow;
            }
            else {
              $productPrice = $orderItem->getBasePrice() * $roundPow;
            }
          }

          $productPrice = round($productPrice, 2);
          $iPrice = round($productPrice, 2);
          $iPriceTax = $orderItem->getTaxAmount() * $roundPow;
          $iVatRate = $orderItem->getTaxPercent();

          $totalAmount += $iQty * $iPrice;

          $item = \Zaver\SDK\Object\LineItem::create()
            ->setId($aZaverOrderItemsIds[$orderItemId])
            ->setName($strName)
            ->setMerchantReference($strSku)
            ->setQuantity($iQty)
            ->setUnitPrice($iPrice)
            ->setTotalAmount($iQty * $iPrice)
            ->setTaxRatePercent($iVatRate)
            ->setTaxAmount($iPriceTax);
          $oPaymentCapReq->addLineItem($item);
          $this->_shipments->addShippedProducts($orderId, $shipId, $orderItemId, $iQty, $iPrice, $aZaverOrderItemsIds[$orderItemId]);
        }

        $oPaymentCapReq->setCurrency($order->getOrderCurrencyCode())
          ->setAmount($totalAmount);

        if ($zvStatusPm != PaymentStatus::SETTLED && $zvStatusPm != PaymentStatus::CANCELLED) {
          $captureResp = $oCheckout->capturePayment($paymentId, $oPaymentCapReq);
          $resCaptureStatus = $captureResp->getPaymentStatus();
          $capturedAmount = $captureResp->getAmount();
          $captureTransactionId = $captureResp->getPaymentCaptureId();

          if ($resCaptureStatus == $helper::ZAVER_CAPTURE_STATUS_PENDING ||
            $resCaptureStatus == $helper::ZAVER_CAPTURE_STATUS_FULLY
          ) {
            // Successfull capture in Zaver
            $bOrderHasInvoice = $order->hasInvoices();

            if ($capturedAmount > 0) {
              if (!empty($captureTransactionId)) {
                $transactionId = $captureTransactionId;
              } else {
                $transactionId = $this->_shipments->getNextTransaction($orderId, $paymentId,
                  \Magento\Sales\Model\Order\Payment\Transaction::TYPE_CAPTURE);
              }

              $paymentData = array("id" => $transactionId, "capturedAmount" => $capturedAmount,
                "paymentStatus" => $resCaptureStatus, "parentid" => $paymentId);

              if ($resCaptureStatus == $helper::ZAVER_CAPTURE_STATUS_FULLY) {
                $this->_shipments->setProductsCapture($transactionId, $shipId);

                $this->createTransaction($order, $paymentData, true);

                if (!$bOrderHasInvoice) {
                  $invoice = $this->_invoiceService->prepareInvoice($order);
                  $invoice->setTransactionId($transactionId);
                  $invoice->setRequestedCaptureCase(\Magento\Sales\Model\Order\Invoice::CAPTURE_ONLINE);
                  $invoice->setState(\Magento\Sales\Model\Order\Invoice::STATE_PAID);
                  $invoice->register();

                  $savedTransaction = $this->_dbTransaction->addObject($invoice)->addObject($invoice->getOrder());
                  $savedTransaction->save();
                }

                if ($strOrderStatus != \Magento\Sales\Model\Order::STATE_COMPLETE) {
                  $order->setState(\Magento\Sales\Model\Order::STATE_COMPLETE)
                    ->setStatus(\Magento\Sales\Model\Order::STATE_COMPLETE);
                }

                // Payment success
                $order->setData('zaver__status', 1);
                $order->save();
              }
              elseif ($resCaptureStatus == $helper::ZAVER_CAPTURE_STATUS_PENDING) {
                $this->_shipments->setProductsCapture($transactionId, $shipId);

                $this->createTransaction($order, $paymentData, false);

                if (!$bOrderHasInvoice) {
                  $invoice = $this->_invoiceService->prepareInvoice($order);
                  $invoice->setTransactionId($transactionId);
                  $invoice->setRequestedCaptureCase(\Magento\Sales\Model\Order\Invoice::CAPTURE_ONLINE);
                  $invoice->setState(\Magento\Sales\Model\Order\Invoice::STATE_OPEN);
                  $invoice->register();

                  $savedTransaction = $this->_dbTransaction->addObject($invoice)->addObject($invoice->getOrder());
                  $savedTransaction->save();
                }

                if ($strOrderStatus == \Magento\Sales\Model\Order::STATE_PENDING_PAYMENT) {
                  $order->setState(\Magento\Sales\Model\Order::STATE_PROCESSING)
                    ->setStatus(\Magento\Sales\Model\Order::STATE_PROCESSING);
                }

                // Payment success
                $order->setData('zaver__status', 1);
                $order->save();
              }
            }
          }
          else {
            $this->messageManager->addWarningMessage(
              __('The shipment can not been processed. Please try again later.')
            );
            return $this;
          }
        }
      }
    }
    catch
    (\Exception $e) {
      $this->messageManager->addWarningMessage(
        __($e->getMessage())
      );
      return $this;
    }
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
      $payment->setAdditionalInformation(
        [\Magento\Sales\Model\Order\Payment\Transaction::RAW_DETAILS => (array)$paymentData]
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