<?php

namespace Zaver\Payment\Controller\Callback;

use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;

/**
 *
 * Class Refund
 * @package Zaver\Payment\Controller\Callback
 */
class Refund extends \Zaver\Payment\Controller\AbstractCallbackAction implements CsrfAwareActionInterface
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
      $shipments = $this->getShipments();
      $orderId = $_GET['orderId'];

      $api = new \Zaver\SDK\Refund($dataHelper->getZVApiKey(), $dataHelper->getZVTestMode());
      $strCallBkToken = $dataHelper->getZVCallbackToken();
      $refundZv = $api->receiveCallback($strCallBkToken);

      $strRefundStatus = $refundZv->getStatus();

      $order = $this->getOrderFactory()->create()->loadByIncrementId($orderId);
      $strOrderStatus = $order->getStatus();
      $strOrderState = $order->getState();
      $successOrderState = \Magento\Sales\Model\Order::STATE_PROCESSING;
      $failedOrderState = \Magento\Sales\Model\Order::STATE_CANCELED;


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
