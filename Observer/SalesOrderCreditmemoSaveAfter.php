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
use Magento\Sales\Api\TransactionRepositoryInterface;

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

  /**
   * @var \Magento\Sales\Model\Order\Payment\Transaction\Builder
   */
  protected $_tranBuilder;

  /**
   * @var \Magento\Sales\Api\TransactionRepositoryInterface
   */
  private $_tranRepository;

  public function __construct(\Psr\Log\LoggerInterface $logger,
                              \Magento\Framework\Message\ManagerInterface $messageManager,
                              \Zaver\Payment\Helper\Data $data,
                              \Zaver\Payment\Model\Creditmemo $creditmemo,
                              \Magento\Sales\Model\Order\Payment\Transaction\Builder $tranBuilder,
                              \Magento\Sales\Api\TransactionRepositoryInterface $tranRepository
  ) {
    $this->_logger = $logger;
    $this->messageManager = $messageManager;
    $this->_helper = $data;
    $this->_creditmemo = $creditmemo;
    $this->_tranBuilder = $tranBuilder;
    $this->_tranRepository = $tranRepository;
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

      // If the order was paid with Zaver
      if (!empty($paymentId) && $isInstallmentsEnabled) {
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

          $totalAmount += $iQty * $iPrice;

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

        $urlCallback = $this->_helper->getRefundCallbackUrl($orderId);

        $urls = MerchantUrls::create()
          ->setCallbackUrl($urlCallback);

        $oPaymentRefReq->setPaymentId($paymentId)
          ->setRefundAmount($totalAmount)
          ->setInvoiceReference($invoiceId)
          ->setDescription('Refunded')
          ->setMerchantUrls($urls);

        $refund = $oRefund->createRefund($oPaymentRefReq);

        if ($refund->getStatus() == RefundStatus::PENDING_MERCHANT_APPROVAL && $totalAmount > 0) {
          $refundId = $refund->getRefundId();
          $appRefunRes = $oRefund->approveRefund($refundId);

          $order->setData('zaver_refund_id', $refundId);
          $order->save();

          if ($appRefunRes->getStatus() != RefundStatus::PENDING_EXECUTION) {
            $this->messageManager->addWarningMessage(
              __('The refund can not been processed. Please try again later.')
            );
            return $this;
          }
          else {
            $this->_creditmemo->setProductsCapture($refundId, $orderId);

            $payment = $order->getPayment();

            $lastPaymentTransactionId = $payment->getLastTransId();
            $transaction = $this->_helper->getObjectManager()->create(
              "\\Magento\\Sales\\Model\\Order\\Payment\\Transaction"
            )->load(
              $lastPaymentTransactionId,
              'txn_id'
            );

            //$transactionId = $this->_creditmemo->getNextTransaction($orderId, $lastPaymentTransactionId,
            //  \Magento\Sales\Model\Order\Payment\Transaction::TYPE_REFUND);

            $transactionId = $transaction->getTransactionId();
            $txnId = $transaction->getTxnId();
            $parentTxnId = $transaction->getParentTxnId();
            $txnType = $transaction->getTxnType();

            if (!isset($transaction)) {
              $this->messageManager->addWarningMessage(
                __('The refund can not been processed. Please try again later.')
              );
              return $this;
            }
            else {
              try {
                $transaction = $this->_tranRepository->get($transactionId);
                if ($transaction) {
                  $transaction->setParentTxnId($paymentId);
                  $transaction->setTxnId($refundId);
                  $saveTransaction = $this->_tranRepository->save($transaction);
                }
              } catch (NoSuchEntityException $ex) {
              }

              $formatedPrice = $order->getBaseCurrency()->formatTxt($refund->getRefundAmount());
              $paymentData = array("id" => $refundId, "refundAmount" => $formatedPrice,
                "refundStatus" => $refund->getStatus(), "parentid" => $paymentId);
              //$this->createTransaction($order, $paymentData, false);
            }
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
        $paymentData["refundAmount"]
      );

      $paymentData["refundAmount"] = $formatedPrice;

      $message = __('The refunded amount is %1.', $formatedPrice);
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
        ->build(\Magento\Sales\Model\Order\Payment\Transaction::TYPE_REFUND);

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