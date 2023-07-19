<?php

namespace Zaver\Payment\Ui\Component\Listing\Column;

use \Magento\Sales\Api\OrderRepositoryInterface;
use \Magento\Framework\View\Element\UiComponent\ContextInterface;
use \Magento\Framework\View\Element\UiComponentFactory;
use \Magento\Ui\Component\Listing\Columns\Column;
use \Magento\Framework\Api\SearchCriteriaBuilder;

class ShipGridColumn extends \Magento\Ui\Component\Listing\Columns\Column
{
  protected $_orderRepository;
  protected $_searchCriteria;
  protected $_shipments;
  protected $_logger;
  protected $_priceHelper;

  public function __construct(
    ContextInterface $context,
    UiComponentFactory $uiComponentFactory,
    OrderRepositoryInterface $orderRepository,
    SearchCriteriaBuilder $criteria,
    \Zaver\Payment\Model\Shipments $shipments,
    \Psr\Log\LoggerInterface $logger,
    \Magento\Framework\Pricing\PriceCurrencyInterface $priceHelper,
    array $components = [], array $data = []) {
    $this->_orderRepository = $orderRepository;
    $this->_searchCriteria = $criteria;
    $this->_shipments = $shipments;
    $this->_logger = $logger;
    $this->_priceHelper = $priceHelper;

    parent::__construct($context, $uiComponentFactory, $components, $data);
  }

  /**
   *
   * @param array $dataSource
   * @return array
   */
  public function prepareDataSource(array $dataSource) {
    if (isset($dataSource['data']['items'])) {
      foreach ($dataSource['data']['items'] as & $item) {
        $orderid = $item['order_id'];
        $entity = $item['entity_id'];

        $aDataRes = $this->_shipments->getInfoShipped($orderid, $entity);
        $amount = 0;
        $item['capture'] = __('No');

        foreach ($aDataRes as $aData) {
          if (isset($aData["price"])) {
            $amount += $aData["price"] * $aData["qty"];

            if ($aData["captured"] == 1) {
              $item['capture'] = __('Yes');
            }
          }
        }

        $item['amount'] = $this->_priceHelper->format($amount, false, 2, null, "EUR");
      }
    }

    return $dataSource;
  }
}
