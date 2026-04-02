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
use Exception;

class SalesInvoiceCapture implements ObserverInterface
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
    //$this->messageManager = $messageManager;
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
    $order = $observer->getData('order');


    $orderId = $order->getData("increment_id");

    try {
      // Check if the Payment Method Zaver module is enabled
      $isInstallmentsEnabled = $this->_helper->getZVInstallmentsActive();
      $isPayLaterEnabled = $this->_helper->getZVPayLaterActive();
      $isBanktransferEnabled = $this->_helper->getZVBanktransferActive();

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

      // If the order was paid with Zaver
      if (!empty($paymentId)) {

        $orderHasDiscount = abs($order->getDiscountAmount() ?? 0) > 0;
        $oPaymentCapReq = PaymentCaptureRequest::create();

        $oCheckout = new \Zaver\SDK\Checkout($zaverApiKey, $zaverTestMode);
        $zvStatusPmRes = $oCheckout->getPaymentStatus($paymentId);
        $zvStatusPm = $zvStatusPmRes->getPaymentStatus();

        $totalAmount = $order->getBaseGrandTotal();

        $oPaymentCapReq->setCurrency($order->getOrderCurrencyCode())
          ->setAmount($totalAmount);

        if ($zvStatusPm != PaymentStatus::SETTLED && $zvStatusPm != PaymentStatus::CANCELLED) {
          $captureResp = $oCheckout->capturePayment($paymentId, $oPaymentCapReq);
          $resCaptureStatus = $captureResp->getPaymentStatus();
          $capturedAmount = $captureResp->getAmount();
          $captureTransactionId = $captureResp->getPaymentCaptureId();

          if ($resCaptureStatus == $helper::ZAVER_CAPTURE_STATUS_PENDING ||
            $resCaptureStatus == $helper::ZAVER_CAPTURE_STATUS_FULLY
          ) 
            {
              if ($capturedAmount > 0) {
                if (!empty($captureTransactionId)) {
                  $transactionId = $captureTransactionId;
                } else {
                  $transactionId = $this->_shipments->getNextTransaction($orderId, $paymentId,
                    \Magento\Sales\Model\Order\Payment\Transaction::TYPE_CAPTURE);
                }
              }

              $paymentData = array("id" => $transactionId, "capturedAmount" => $capturedAmount,
              "paymentStatus" => $resCaptureStatus, "parentid" => $paymentId);

                if ($strOrderStatus != \Magento\Sales\Model\Order::STATE_COMPLETE) {
                  $order->setState(\Magento\Sales\Model\Order::STATE_COMPLETE)
                    ->setStatus(\Magento\Sales\Model\Order::STATE_COMPLETE);
                }

                $this->createTransaction($order, $paymentData, false);

            }
           }
          }
          else {
            return; 
          }
        }
    catch
    (\Exception $e) {
      $this->_logger->critical($e);
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