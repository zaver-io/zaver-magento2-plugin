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
      $dataHelper = $this->getHelper();
      $orderId = $_GET['orderId'];

      $api = new \Zaver\SDK\Refund($dataHelper->getZVApiKey(), $dataHelper->getZVTestMode());
      $strCallBkToken = $dataHelper->getZVCallbackToken();
      $refundZv = $api->receiveCallback($strCallBkToken);

      $order = $this->getOrderFactory()->create()->loadByIncrementId($orderId);
      if (!$order->getId()) {
        $this->getResponse()->setHttpResponseCode(404);
        return;
      }

      // The credit memo exists since the refund was created; the callback tells how the refund ended at Zaver.
      $refundId = $refundZv->getRefundId();
      $amount = $order->getBaseCurrency()->formatTxt($refundZv->getRefundAmount());
      switch ($refundZv->getStatus()) {
        case \Zaver\SDK\Config\RefundStatus::EXECUTED:
          $order->addCommentToStatusHistory((string)__('Zaver refund %1 of %2 was paid out.', $refundId, $amount));
          $order->save();
          break;

        case \Zaver\SDK\Config\RefundStatus::CANCELLED:
          $order->addCommentToStatusHistory((string)__('Zaver cancelled refund %1 of %2: the money was not paid back, although the credit memo exists.',
            $refundId, $amount));
          $order->save();
          break;
      }

      $this->getLogger()->info('Zaver refund callback', [
        'order' => $orderId, 'refundId' => $refundId, 'status' => $refundZv->getStatus()
      ]);

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
