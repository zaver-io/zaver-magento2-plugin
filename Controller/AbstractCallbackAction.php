<?php

namespace Zaver\Payment\Controller;

/**
 * Base Checkout Controller Class
 * Class AbstractCallbackAction
 * @package Zaver\Payment\Controller
 */
abstract class AbstractCallbackAction extends \Zaver\Payment\Controller\AbstractAction
{
  /**
   * @var \Magento\Sales\Model\OrderFactory
   */
  private $_orderFactory;

  /**
   * @var \Zaver\Payment\Helper\Data
   */
  private $_helper;

  /**
   * @var \Magento\Sales\Model\Service\InvoiceService
   */
  protected $_invoiceService;

  /**
   * @var \Magento\Framework\DB\Transaction
   */
  protected $_dbTransaction;

  /**
   * @var \Zaver\Payment\Model\Shipments
   */
  protected $_shipments;

  /**
   * @var \Magento\Sales\Model\Order\Payment\Transaction\Builder
   */
  protected $_tranBuilder;

  /**
   * @param \Magento\Framework\App\Action\Context $context
   * @param \Magento\Checkout\Model\Session $checkoutSession
   */
  public function __construct(
    \Magento\Framework\App\Action\Context $context,
    \Psr\Log\LoggerInterface $logger,
    \Magento\Sales\Model\OrderFactory $orderFactory,
    \Zaver\Payment\Helper\Data $helper,
    \Magento\Sales\Model\Service\InvoiceService $invoiceService,
    \Magento\Framework\DB\Transaction $transaction,
    \Zaver\Payment\Model\Shipments $shipments,
    \Magento\Sales\Model\Order\Payment\Transaction\Builder $tranBuilder
  ) {
    parent::__construct($context, $logger);
    $this->_orderFactory = $orderFactory;
    $this->_helper = $helper;
    $this->_invoiceService = $invoiceService;
    $this->_dbTransaction = $transaction;
    $this->_shipments = $shipments;
    $this->_tranBuilder = $tranBuilder;
  }

  /**
   * Get an Instance of the Magento Order Factory
   * It can be used to instantiate an order
   * @return \Magento\Sales\Model\OrderFactory
   */
  protected function getOrderFactory() {
    return $this->_orderFactory;
  }

  /**
   * Get an Instance of helper data
   * It can be used to instantiate an order
   * @return \Zaver\Payment\Helper\Data
   */
  protected function getHelper() {
    return $this->_helper;
  }

  /**
   * Get an Instance of shipments model
   * It can be used to instantiate an order
   * @return \Zaver\Payment\Model\Shipments
   */
  protected function getShipments() {
    return $this->_shipments;
  }
}