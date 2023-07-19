<?php

namespace Zaver\Payment\Controller\Callback;

use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;

/**
 *
 * Class Index
 * @package Zaver\Payment\Controller\Callback
 */
class Index extends \Zaver\Payment\Controller\AbstractCallbackAction implements CsrfAwareActionInterface
{
  /**
   *
   * @return void
   */
  public function execute() {
    if (!$this->getRequest()->isPost()) {
      return;
    }

    try {
      $logger = $this->getLogger();
      $logger->log(\Psr\Log\LogLevel::INFO, "Callback\Index.php");
      $logger->log(\Psr\Log\LogLevel::INFO, '_GET:' . print_r($_GET, true));

      $dataHelper = $this->getHelper();
      $shipments = $this->getShipments();
      $orderId = $_GET['orderId'];

      $api = new \Zaver\SDK\Checkout($dataHelper->getZVApiKey(), $dataHelper->getZVTestMode());
      $strCallBkToken = $dataHelper->getZVCallbackToken();
      $paymentZv = $api->receiveCallback($strCallBkToken);
      $strPaymentStatus = $paymentZv->getPaymentStatus();
      $transactionId = $paymentZv->getPaymentId();
      $capturedAmount = $paymentZv->getCapturedAmount();

      $order = $this->getOrderFactory()->create()->loadByIncrementId($orderId);
      $strOrderStatus = $order->getStatus();
      $strOrderState = $order->getState();
      $successOrderState = \Magento\Sales\Model\Order::STATE_PROCESSING;
      $failedOrderState = \Magento\Sales\Model\Order::STATE_CANCELED;
      $bOrderHasInvoice = $order->hasInvoices();

      $logger->log(\Psr\Log\LogLevel::INFO, "In Mg2 strOrderStatus:$strOrderStatus, strOrderState:$strOrderState");
      $logger->log(\Psr\Log\LogLevel::INFO, "In Zaver transactionId:$transactionId, strPaymentStatus:$strPaymentStatus, capturedAmount:$capturedAmount");

      $logger->log(\Psr\Log\LogLevel::INFO, "paymentZv:" . print_r($paymentZv, true));
      $logger->log(\Psr\Log\LogLevel::INFO, \Magento\Sales\Model\Order::STATE_PENDING_PAYMENT);

      $transactionId = $this->_shipments->getNextTransaction($orderId, $transactionId,
        \Magento\Sales\Model\Order\Payment\Transaction::TYPE_CAPTURE);

      $paymentData = array("id" => $transactionId, "capturedAmount" => $capturedAmount,
        "paymentStatus" => $strPaymentStatus);

      //if ($oOrder->oxorder__zaver__status != "" && $strOrderStatus != $dataHelper::ORDER_IN_PAYMENT) {
      if ($strOrderStatus == \Magento\Sales\Model\Order::STATE_PENDING_PAYMENT) {
        $logger->log(\Psr\Log\LogLevel::INFO, "Line 62");
        if ($strPaymentStatus == \Zaver\SDK\Config\PaymentStatus::SETTLED) {
          $logger->log(\Psr\Log\LogLevel::INFO, "Line 64");
          $order->setState($successOrderState)
            ->setStatus($successOrderState);

          if (!$bOrderHasInvoice) {
            $this->createTransaction($order, $paymentData, true);
            $order->setData('zaver_payment_status', $strPaymentStatus);
            $order->save();

            $invoice = $this->_invoiceService->prepareInvoice($order);
            $invoice->setTransactionId($transactionId);
            $invoice->setRequestedCaptureCase(\Magento\Sales\Model\Order\Invoice::CAPTURE_ONLINE);
            $invoice->setState(\Magento\Sales\Model\Order\Invoice::STATE_OPEN);
            $invoice->register();

            $savedTransaction = $this->_dbTransaction->addObject($invoice)->addObject($invoice->getOrder());
            $savedTransaction->save();
          }
        }
        elseif ($strPaymentStatus == \Zaver\SDK\Config\PaymentStatus::PENDING) {
          $logger->log(\Psr\Log\LogLevel::INFO, "Line 84");
          if ($capturedAmount > 0) {
            $this->createTransaction($order, $paymentData, false);

            $order->setState($successOrderState)
              ->setStatus($successOrderState);

            if (!$bOrderHasInvoice) {
              $order->setData('zaver_payment_status', $strPaymentStatus);
              $order->save();

              $invoice = $this->_invoiceService->prepareInvoice($order);
              $invoice->setTransactionId($transactionId);
              $invoice->setRequestedCaptureCase(\Magento\Sales\Model\Order\Invoice::CAPTURE_ONLINE);
              $invoice->setState(\Magento\Sales\Model\Order\Invoice::STATE_OPEN);
              $invoice->register();

              $savedTransaction = $this->_dbTransaction->addObject($invoice)->addObject($invoice->getOrder());
              $savedTransaction->save();
            }

            $shipments->setProductsCapture($transactionId, $orderId);
          }
          else {
            $order->setState($successOrderState)
              ->setStatus($successOrderState);
            $order->setData('zaver_payment_status', $strPaymentStatus);
            $order->save();
          }
        }
        elseif ($strPaymentStatus == \Zaver\SDK\Config\PaymentStatus::CANCELLED) {
          $order->setState($failedOrderState)
            ->setStatus($failedOrderState)
            ->addStatusToHistory($failedOrderState, "Order rejected in Zaver");
          $order->setData('zaver_payment_status', $strPaymentStatus);
          $order->cancel()->save();
        }
      }
      elseif ($strPaymentStatus == \Zaver\SDK\Config\PaymentStatus::PENDING) {
        $logger->log(\Psr\Log\LogLevel::INFO, "Line 123");

        if ($capturedAmount > 0) {
          $order->setState($successOrderState)
            ->setStatus($successOrderState);

          $this->createTransaction($order, $paymentData, false);

          if (!$bOrderHasInvoice) {
            $order->setData('zaver_payment_status', $strPaymentStatus);
            $order->save();

            $invoice = $this->_invoiceService->prepareInvoice($order);
            $invoice->setTransactionId($transactionId);
            $invoice->setRequestedCaptureCase(\Magento\Sales\Model\Order\Invoice::CAPTURE_ONLINE);
            $invoice->setState(\Magento\Sales\Model\Order\Invoice::STATE_OPEN);
            $invoice->register();

            $savedTransaction = $this->_dbTransaction->addObject($invoice)->addObject($invoice->getOrder());
            $savedTransaction->save();
          }

          $shipments->setProductsCapture($transactionId, $orderId);
        }
      }
      elseif ($strPaymentStatus == \Zaver\SDK\Config\PaymentStatus::SETTLED) {
        $logger->log(\Psr\Log\LogLevel::INFO, "Line 149");

        if ($capturedAmount > 0) {
          $shipments->setProductsCapture($transactionId, $orderId);

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

          // Payment success
          $order->setData('zaver_payment_status', $strPaymentStatus);
          $order->setData('zaver__status', 1);
          $order->save();
        }
      }
      elseif ($strPaymentStatus == \Zaver\SDK\Config\PaymentStatus::CANCELLED || $strPaymentStatus == \Zaver\SDK\Config\PaymentStatus::ERROR) {
        $logger->log(\Psr\Log\LogLevel::INFO, "Line 173");
        // Payment failed
        $order->setData('zaver_payment_status', $strPaymentStatus);
        $order->setData('zaver__status', 0);
        $order->save();
      }

      $this->getResponse()->setHttpResponseCode(200);
    }
    catch (\Exception $e) {
      $logger->log(\Psr\Log\LogLevel::INFO, 'ERROR Callback\Index.php:' . $e->getMessage());
      $this->getLogger()->critical($e);
      $this->getResponse()->setHttpResponseCode(500);
    }
  }

  public function createTransaction($order = null, $paymentData = array(), $bTranClosed = false) {
    try {
      $logger = $this->getLogger();

      // Get payment object from order object
      $payment = $order->getPayment();
      $payment->setLastTransId($paymentData['id']);
      $payment->setTransactionId($paymentData['id']);
      $payment->setIsTransactionClosed($bTranClosed);
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
      $payment->setParentTransactionId(null);
      $payment->save();
      $order->save();
      return $transaction->save()->getTransactionId();
    }
    catch (Exception $e) {
      $logger->log(\Psr\Log\LogLevel::INFO, 'ERROR Callback\Index.php createTransaction():' . $e->getMessage());
    }
  }

  /**
   * @inheritDoc
   */
  public
  function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException {
    return null;
  }

  /**
   * @inheritDoc
   */
  public
  function validateForCsrf(RequestInterface $request): ?bool {
    return true;
  }
}
