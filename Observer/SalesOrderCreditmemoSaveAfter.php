<?php

namespace Zaver\Payment\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\App\RequestInterface;
use Psr\Log\LoggerInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\OrderRepository;
use Magento\Framework\Message\ManagerInterface;
use Magento\Catalog\Model\Product\Type as ProductType;
use Zaver\SDK\Config\RefundStatus;
use Zaver\SDK\Refund;
use Zaver\SDK\Object\RefundCreationRequest;
use Zaver\SDK\Object\RefundResponse;
use Zaver\SDK\Config\PaymentStatus;
use Zaver\SDK\Object\RefundLineItem;
use Zaver\SDK\Config\ItemType;
use Zaver\SDK\Object\MerchantUrls;

class SalesOrderCreditmemoSaveAfter implements ObserverInterface
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
   * @var ManagerInterface
   */
  private $messageManager;

  /**
   * @var \Zaver\Payment\Model\Creditmemo
   */
  private $_creditmemo;

  public function __construct(\Psr\Log\LoggerInterface $logger,
                              \Magento\Framework\Message\ManagerInterface $messageManager,
                              \Zaver\Payment\Helper\Data $data,
                              \Zaver\Payment\Model\Creditmemo $creditmemo) {
    $this->_logger = $logger;
    $this->messageManager = $messageManager;
    $this->_helper = $data;
    $this->_creditmemo = $creditmemo;
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

    /** @var \Magento\Sales\Model\Order\Creditmemo $creditmemo */
    $creditmemo = $observer->getEvent()->getCreditmemo();

    /** @var \Magento\Sales\Model\Order $order */
    $order = $creditmemo->getOrder();
    $orderId = $order->getIncrementId();
    $creditMemoId = $creditmemo->getIncrementId();

    try {
      // Check if the Payment Method Zaver module is enabled
      $isInstallmentsEnabled = $this->_helper->getZVInstallmentsActive();
      $isPayLaterEnabled = $this->_helper->getZVPayLaterActive();

      $this->_logger->log(\Psr\Log\LogLevel::INFO, "IN OBSERVER: SalesOrderCreditmemoSaveAfter() INIT");
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
      $aProdsCreditmemo = $this->_creditmemo->getProductsCreditMemo($orderId);

      $this->_logger->log(\Psr\Log\LogLevel::INFO, "IN OBSERVER: paymentId:$paymentId, amount:$amount, orderItemsId:$strOrderItemsId");
      $this->_logger->log(\Psr\Log\LogLevel::INFO, "IN OBSERVER: aZaverOrderItemsIds:" . print_r($aZaverOrderItemsIds, true));

      // If the order was paid with Zaver
      if (!empty($paymentId)) {
        $oPaymentRefReq = RefundCreationRequest::create();
        $oRefund = new \Zaver\SDK\Refund($zaverApiKey, $zaverTestMode);

        $totalAmount = 0;

        /** @var CreditmemoItemInterface $item */
        foreach ($creditmemo->getAllItems() as $item) {
          $orderItemId = $item->getOrderItemId();

          if ($item->getOrderItem()->getProductType() == 'bundle') {
            continue;
          }

          $orderItem = $item->getOrderItem();
          $iQty = (int)$item->getQty();

          $this->_logger->log(\Psr\Log\LogLevel::INFO, "IN OBSERVER: orderItemId:$orderItemId, iQty:$iQty");


          $productPriceTax = $orderItem->getBasePriceInclTax() * $roundPow;

          if ($item->getBaseDiscountAmount()) {
            if ($productPriceTax > 0) {
              $productPrice = $orderItem->getBaseRowTotalInclTax() - $orderItem->getBaseDiscountAmount();
            }
            else {
              $productPrice = $orderItem->getBasePrice() - $orderItem->getBaseDiscountAmount();
            }
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
          $iPriceTax = 1;//$orderItem->getTaxAmount() * $roundPow;
          $iVatRate = 1;//$orderItem->getTaxPercent();

          $this->_logger->log(\Psr\Log\LogLevel::INFO, "IN OBSERVER: iPrice:$iPrice, iPriceTax:$iPriceTax, iVatRate:$iVatRate");

          $totalAmount += $iQty * $iPrice;

          $this->_logger->log(\Psr\Log\LogLevel::INFO, "IN OBSERVER: value-$orderItemId:" . $aZaverOrderItemsIds[$orderItemId]);

          $refundItem = RefundLineItem::create()
            ->setLineItemId($aZaverOrderItemsIds[$orderItemId])
            ->setRefundTotalAmount($iQty * $iPrice)
            ->setRefundTaxAmount($iPriceTax)
            ->setRefundTaxRatePercent($iVatRate)
            ->setRefundQuantity($iQty)
            ->setRefundUnitPrice($iPrice);

          $oPaymentRefReq->addLineItem($refundItem);
          $this->_creditmemo->addCreditMemoProducts($orderId, $creditMemoId, $orderItemId, $iQty, $iPrice, $aZaverOrderItemsIds[$orderItemId]);
        }

        foreach ($order->getInvoiceCollection() as $invoice) {
          $invoiceId = $invoice->getIncrementId();
        }
        $amountAdjustment = $creditmemo->getAdjustment();
        $amountAdjustmentNeg = $creditmemo->getAdjustmentNegative();

        // Send the shipping to zaver the first time
        $amountShipping = $creditmemo->getShippingAmount();
        if ($amountShipping > 0 && count($aProdsCreditmemo) == 0) {
          $refundItem = RefundLineItem::create()
            ->setLineItemId($aZaverOrderItemsIds[ItemType::SHIPPING])
            ->setRefundTotalAmount($amountShipping)
            ->setRefundTaxAmount(1)
            ->setRefundTaxRatePercent(1)
            ->setRefundQuantity(1)
            ->setRefundUnitPrice($amountShipping);
          $oPaymentRefReq->addLineItem($refundItem);
          $totalAmount += $amountShipping;

          $this->_creditmemo->addCreditMemoProducts($orderId, $creditMemoId, ItemType::SHIPPING, 1,
            $amountShipping, $aZaverOrderItemsIds[ItemType::SHIPPING]);
        }

        $this->_logger->log(\Psr\Log\LogLevel::INFO, "IN OBSERVER: amountAdjustment:$amountAdjustment, amountAdjustmentNeg:$amountAdjustmentNeg, amountShipping:$amountShipping");

        $urlCallback = $this->_helper->getRefundCallbackUrl($orderId);
        $this->_logger->log(\Psr\Log\LogLevel::INFO, "IN OBSERVER: urlCallback:$urlCallback");

        $urls = MerchantUrls::create()
          ->setCallbackUrl($urlCallback);

        $oPaymentRefReq->setPaymentId($paymentId)
          ->setRefundAmount($totalAmount)
          ->setInvoiceReference($invoiceId)
          ->setDescription('Refunded')
          ->setMerchantUrls($urls);

        $refund = $oRefund->createRefund($oPaymentRefReq);

        $this->_logger->log(\Psr\Log\LogLevel::INFO, "IN OBSERVER: refund->getStatus():" . $refund->getStatus());

        if ($refund->getStatus() == RefundStatus::PENDING_MERCHANT_APPROVAL && $totalAmount > 0) {
          $appRefunRes = $oRefund->approveRefund($refund->getRefundId());
          $this->_logger->log(\Psr\Log\LogLevel::INFO, "IN OBSERVER: appRefunRes->getStatus():" . $appRefunRes->getStatus());

          $order->setData('zaver_refund_id', $refund->getRefundId());
          $order->save();

          if ($appRefunRes->getStatus() != RefundStatus::PENDING_EXECUTION) {
            $this->messageManager->addWarningMessage(
              __('The refund can not been processed. Please try again later.')
            );
            return $this;
          }
          else {
            $this->_creditmemo->setProductsCapture($refund->getRefundId(), $orderId);
          }
        }
      }

      $this->_logger->log(\Psr\Log\LogLevel::INFO, "IN OBSERVER: SalesOrderCreditmemoSaveAfter() END");
    }
    catch
    (\Exception $e) {
      $this->_logger->log(\Psr\Log\LogLevel::INFO, "IN OBSERVER: SalesOrderCreditmemoSaveAfter ERROR:" . $e->getMessage());
      $this->messageManager->addWarningMessage(
        __($e->getMessage())
      );
      return $this;
    }
  }
}