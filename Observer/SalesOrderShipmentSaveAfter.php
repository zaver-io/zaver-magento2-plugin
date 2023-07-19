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

  public function __construct(\Psr\Log\LoggerInterface $logger,
                              \Magento\Framework\Message\ManagerInterface $messageManager,
                              \Zaver\Payment\Helper\Data $data,
                              \Zaver\Payment\Model\Shipments $shipments) {
    $this->_logger = $logger;
    $this->messageManager = $messageManager;
    $this->_helper = $data;
    $this->_shipments = $shipments;
  }

  /**
   * Active only for zaver payment methods
   *
   * @param \Magento\Framework\Event\Observer $observer
   */
  public function execute(\Magento\Framework\Event\Observer $observer) {
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

      $this->_logger->log(\Psr\Log\LogLevel::INFO, "IN OBSERVER: SalesOrderShipmentSaveAfter() INIT");
      $this->_logger->log(\Psr\Log\LogLevel::INFO, "IN OBSERVER: isInstallmentsEnabled:$isInstallmentsEnabled, isPayLaterEnabled:$isPayLaterEnabled");

      $zaverApiKey = $this->_helper->getZVApiKey();
      $zaverTestMode = $this->_helper->getZVTestMode();

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
      $this->_logger->log(\Psr\Log\LogLevel::INFO, "IN OBSERVER: getProductsShipped():" . print_r($aProdsShipped, true));
      $this->_logger->log(\Psr\Log\LogLevel::INFO, "IN OBSERVER: paymentId:$paymentId, amount:$amount, orderItemsId:$strOrderItemsId");
      $this->_logger->log(\Psr\Log\LogLevel::INFO, "IN OBSERVER: aZaverOrderItemsIds:" . print_r($aZaverOrderItemsIds, true));

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

        $this->_logger->log(\Psr\Log\LogLevel::INFO, "IN OBSERVER: zvStatusPmRes:" . print_r($zvStatusPmRes, true));
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

          $this->_logger->log(\Psr\Log\LogLevel::INFO, "IN OBSERVER: orderItemId:$orderItemId, iQty:$iQty");
          $this->_logger->log(\Psr\Log\LogLevel::INFO, "IN OBSERVER: strName:$strName, parentId:$parentId");

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

          $this->_logger->log(\Psr\Log\LogLevel::INFO, "IN OBSERVER: iPrice:$iPrice, iPriceTax:$iPriceTax, iVatRate:$iVatRate");

          $totalAmount += $iQty * $iPrice;

          $this->_logger->log(\Psr\Log\LogLevel::INFO, "IN OBSERVER: value-$orderItemId:" . $aZaverOrderItemsIds[$orderItemId]);

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
        $this->_logger->log(\Psr\Log\LogLevel::INFO, "IN OBSERVER: oPaymentCapReq:" . print_r($oPaymentCapReq, true));
        if ($zvStatusPm != PaymentStatus::SETTLED && $zvStatusPm != PaymentStatus::CANCELLED) {
          $captureResp = $oCheckout->capturePayment($paymentId, $oPaymentCapReq);
          $this->_logger->log(\Psr\Log\LogLevel::INFO, "IN OBSERVER: oPaymentCapReq->getPaymentStatus():" . $captureResp->getPaymentStatus());
        }
        /*if ($zaverOrder->status == 'shipping' && !$this->itemsAreShippable($order, $orderLines)) {
          $this->messageManager->addWarningMessage(
            __('All items in this order where already marked as shipped in the Zaver dashboard.')
          );
          return $this;
        }*/
        //}
      }

      $this->_logger->log(\Psr\Log\LogLevel::INFO, "IN OBSERVER: SalesOrderShipmentSaveAfter() END");
    }
    catch
    (\Exception $e) {
      $this->_logger->log(\Psr\Log\LogLevel::INFO, "IN OBSERVER: SalesOrderShipmentSaveAfter ERROR:" . $e->getMessage());
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
}