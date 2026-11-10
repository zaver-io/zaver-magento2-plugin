<?php

namespace Zaver\Payment\Controller\Adminhtml\Order;

use Magento\Framework\App\Action\HttpGetActionInterface;

/**
 * "Refresh Zaver status": asks Zaver for the order's payment status and applies it like a callback would.
 */
class Refresh extends \Magento\Backend\App\Action implements HttpGetActionInterface
{
  /** Anyone who may view orders may refresh their Zaver status */
  const ADMIN_RESOURCE = 'Magento_Sales::actions_view';

  /**
   * @var \Magento\Sales\Api\OrderRepositoryInterface
   */
  private $orderRepository;

  /**
   * @var \Zaver\Payment\Model\PaymentStatusUpdater
   */
  private $statusUpdater;

  public function __construct(\Magento\Backend\App\Action\Context $context,
                              \Magento\Sales\Api\OrderRepositoryInterface $orderRepository,
                              \Zaver\Payment\Model\PaymentStatusUpdater $statusUpdater) {
    parent::__construct($context);
    $this->orderRepository = $orderRepository;
    $this->statusUpdater = $statusUpdater;
  }

  /**
   * @return \Magento\Framework\Controller\Result\Redirect
   */
  public function execute() {
    $orderId = (int)$this->getRequest()->getParam('order_id');
    $redirect = $this->resultRedirectFactory->create()->setPath('sales/order/view', ['order_id' => $orderId]);

    try {
      /** @var \Magento\Sales\Model\Order $order */
      $order = $this->orderRepository->get($orderId);
      $zaverStatus = $this->statusUpdater->fetchStatus($order);
      $result = $this->statusUpdater->apply($order, $zaverStatus);

      $this->messageManager->addSuccessMessage(__('Zaver payment status: %1. %2', $zaverStatus, $result));
    }
    catch (\Exception $e) {
      $this->messageManager->addErrorMessage(__('The Zaver payment status could not be refreshed: %1', $e->getMessage()));
    }

    return $redirect;
  }
}
