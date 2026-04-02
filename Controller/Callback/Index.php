<?php

namespace Zaver\Payment\Controller\Callback;

use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Exception;

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

      $dataHelper = $this->getHelper();
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
      $payment = $order->getPayment();
      //$ZvStatus = array("paymentStatus" => $resCaptureStatus);

      if ($strPaymentStatus == \Zaver\SDK\Config\PaymentStatus::SETTLED) {
          $setOrderStatus = \Magento\Sales\Model\Order::STATE_COMPLETE;
          $order->setState($setOrderStatus);
          $order->setStatus($setOrderStatus);
          $order->setData('zaver_payment_status', $strPaymentStatus);
          $order->setData('zaver__status', 1);
          $order->save();
        }
        elseif ($strPaymentStatus == \Zaver\SDK\Config\PaymentStatus::PENDING) {
          $setOrderStatus = \Magento\Sales\Model\Order::STATE_PROCESSING;
          $order->setState($setOrderStatus);
          $order->setStatus($setOrderStatus);
          
          $bOrderHasInvoice = $order->hasInvoices();

          if (!$bOrderHasInvoice) {
            $invoice = $this->_invoiceService->prepareInvoice($order);
            $invoice->setTransactionId($transactionId);
            $invoice->setRequestedCaptureCase(\Magento\Sales\Model\Order\Invoice::CAPTURE_ONLINE);
            $invoice->setState(\Magento\Sales\Model\Order\Invoice::STATE_PAID);
            $invoice->register();

            $savedTransaction = $this->_dbTransaction->addObject($invoice)->addObject($invoice->getOrder());
            $savedTransaction->save();
          }
          
          $order->setData('zaver_payment_status', $strPaymentStatus);
          $order->setData('zaver__status', 1);
          $order->save();
        }
        elseif ($strPaymentStatus == \Zaver\SDK\Config\PaymentStatus::CANCELLED) {
          $setOrderStatus = \Magento\Sales\Model\Order::STATE_CANCELED;
          $order->setState($setOrderStatus);
          $order->setStatus($setOrderStatus);
          $order->setData('zaver_payment_status', $strPaymentStatus);
          $order->setData('zaver__status', 1);
          $order->save();
        }
      elseif ($strPaymentStatus == \Zaver\SDK\Config\PaymentStatus::CANCELLED || $strPaymentStatus == \Zaver\SDK\Config\PaymentStatus::ERROR) {
        // Payment failed
        $setOrderStatus = \Magento\Sales\Model\Order::STATE_CANCELED;
        $order->setState($setOrderStatus)->setStatus($setOrderStatus);
        $payment->setAdditionalInformation('zaver_payment_status', $strPaymentStatus);
        $order->setData('zaver__status', 0);
        $order->save();
      }

      $this->getResponse()->setHttpResponseCode(200);
    }
    catch (\Exception $e) {
      $this->getLogger()->critical($e);
      $this->getResponse()->setHttpResponseCode(500);
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
