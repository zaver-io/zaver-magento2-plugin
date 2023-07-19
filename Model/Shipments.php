<?php

namespace Zaver\Payment\Model;

use Magento\Framework\Model\AbstractModel;

class Shipments extends AbstractModel
{
  protected $_logger;

  /**
   * Define resource model
   */
  public function __construct(
    \Magento\Framework\App\ResourceConnection $resourceConnection,
    \Magento\Backend\Model\Auth\Session $authSession,
    \Psr\Log\LoggerInterface $logger
  ) {
    $this->_resourceConnection = $resourceConnection;
    $this->_authSession = $authSession;
    $this->_logger = $logger;
  }

  /**
   * Initialize resource model
   *
   * @return void
   */
  protected function _construct() {
    $this->_init(\Zaver\Payment\Model\ResourceModel\Shipments::class);
  }

  /**
   * Get
   *
   * @return array
   */
  public function getProductsShipped($orderId) {
    try {
      $aData = [];

      $connection = $this->_resourceConnection->getConnection();
      $tableName = $this->_resourceConnection->getTableName('zaver_shipments');
      $query = "SELECT order_item_id, qty, price, zaver_id, zaver_transaction_id, captured ";
      $query .= "FROM " . $tableName . " WHERE order_id=$orderId ORDER BY order_item_id;";
      $result = $connection->fetchAll($query);

      /**
       * Output the results
       */
      foreach ($result as $aVar) {
        if (array_key_exists($aVar["order_item_id"], $aData)) {
          $qty = $aData["order_item_id"]["qty"] + $aVar["qty"];
        }
        else {
          $qty = $aVar["qty"];
        }

        $aData[$aVar["order_item_id"]] = ["qty" => $qty,
          "price" => $aVar["price"],
          "zaver_id" => $aVar["zaver_id"],
          "zaver_transaction_id" => $aVar["zaver_transaction_id"],
          "captured" => $aVar["captured"],];
      }
    }
    catch (\Exception $ex) {
      $this->_logger->log(\Psr\Log\LogLevel::INFO, " ERROR in getProductsShipped():" . $ex->getMessage());
    }

    return $aData;
  }

  public function addShippedProducts($orderid, $shipid, $order_item_id, $qty, $price, $zaver_id) {
    try {
      $connection = $this->_resourceConnection->getConnection();

      /**
       * Execute the query
       */
      $query = "INSERT INTO `zaver_shipments` (`order_id`, `ship_id`, `order_item_id`,`qty`,`price`,`zaver_id`,`created_at`)
                VALUES ($orderid, $shipid, '$order_item_id', $qty, $price, '$zaver_id', now())";

      $connection->query($query);
    }
    catch (Exception $ex) {
      $this->_logger->log(\Psr\Log\LogLevel::INFO, " ERROR in addShippedProducts():" . $ex->getMessage());
    }
  }

  public function setProductsCapture($paymentid, $orderid, $shipid) {
    try {
      $connection = $this->_resourceConnection->getConnection();

      /**
       * Execute the query
       */
      $query = "UPDATE `zaver_shipments` SET `zaver_transaction_id`='$paymentid',`captured`=1 WHERE `order_id`=$orderid AND `ship_id`=$shipid";

      $connection->query($query);
    }
    catch (Exception $ex) {
      $this->_logger->log(\Psr\Log\LogLevel::INFO, " ERROR in addShippedProducts():" . $ex->getMessage());
    }
  }

  /**
   * Get the amount and capture from shipped items
   *
   * @return array
   */
  public function getInfoShipped($orderId, $entityId) {
    try {
      $aItems = [];

      $connection = $this->_resourceConnection->getConnection();

      $tableName1 = $this->_resourceConnection->getTableName('zaver_shipments');

      /**
       * Output the results
       */
      $query = "SELECT order_item_id, qty, price, zaver_id, zaver_transaction_id, captured ";
      $query .= "FROM " . $tableName1 . " WHERE order_id=$orderId AND ship_id=$entityId;";

      $result = $connection->fetchAll($query);

      $this->_logger->log(\Psr\Log\LogLevel::INFO, "query:$query");

      /**
       * Output the results
       */
      foreach ($result as $aVar) {
        $aData["qty"] = $aVar["qty"];
        $aData["price"] = $aVar["price"];
        $aData["zaver_id"] = $aVar["zaver_id"];
        $aData["zaver_transaction_id"] = $aVar["zaver_transaction_id"];
        $aData["captured"] = $aVar["captured"];
        $aItems[] = $aData;
      }
    }
    catch (\Exception $ex) {
      $this->_logger->log(\Psr\Log\LogLevel::INFO, " ERROR in getProductsShipped():" . $ex->getMessage());
    }

    return $aItems;
  }

  /**
   * Get the next number for transaction
   *
   * @return int
   */
  public function getNextTransaction($orderId, $paymentid, $txntype) {
    try {
      $strNextTransaction = $paymentid;

      $connection = $this->_resourceConnection->getConnection();

      $tableName1 = $this->_resourceConnection->getTableName('sales_payment_transaction');

      /**
       * Output the results
       */
      $query = "SELECT COUNT(txn_id)  AS cnt FROM " . $tableName1 . " WHERE ";
      $query .= "order_id=$orderId AND txn_id LIKE '$paymentid%' AND txn_type='$txntype'";

      $result = $connection->fetchAll($query);

      $this->_logger->log(\Psr\Log\LogLevel::INFO, "query:$query");

      /**
       * Output the results
       */
      foreach ($result as $aVar) {
        if ($aVar["cnt"] > 0) {
          $strNextTransaction = $paymentid . "-" . $aVar["cnt"];
        }
      }
    }
    catch (\Exception $ex) {
      $this->_logger->log(\Psr\Log\LogLevel::INFO, " ERROR in getNextTransaction():" . $ex->getMessage());
    }
    $this->_logger->log(\Psr\Log\LogLevel::INFO, "getNextTransaction():$strNextTransaction");
    return $strNextTransaction;
  }
}
