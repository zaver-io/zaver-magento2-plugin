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

      if ($strOrderStatus == \Magento\Sales\Model\Order::STATE_PENDING_PAYMENT) {
        $setOrderStatus = \Magento\Sales\Model\Order::STATE_PROCESSING;

        if ($strPaymentStatus == \Zaver\SDK\Config\PaymentStatus::SETTLED) {
          $setOrderStatus = \Magento\Sales\Model\Order::STATE_COMPLETE;
        }
        elseif ($strPaymentStatus == \Zaver\SDK\Config\PaymentStatus::PENDING) {
          $setOrderStatus = \Magento\Sales\Model\Order::STATE_PROCESSING;
        }
        elseif ($strPaymentStatus == \Zaver\SDK\Config\PaymentStatus::CANCELLED) {
          $setOrderStatus = \Magento\Sales\Model\Order::STATE_CANCELED;
        }
        $order->setState($setOrderStatus)
          ->setStatus($setOrderStatus);
        $order->setData('zaver_payment_status', $strPaymentStatus);
        $order->setData('zaver__status', 1);
        $order->save();
      }
      elseif ($strPaymentStatus == \Zaver\SDK\Config\PaymentStatus::CANCELLED || $strPaymentStatus == \Zaver\SDK\Config\PaymentStatus::ERROR) {
        // Payment failed
        $order->setData('zaver_payment_status', $strPaymentStatus);
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
