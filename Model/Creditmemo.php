<?php

namespace Zaver\Payment\Model;

use Magento\Framework\Model\AbstractModel;

class Creditmemo extends AbstractModel
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
    $this->_init(\Zaver\Payment\Model\ResourceModel\Creditmemo::class);
  }

  /**
   * Get
   *
   * @return array
   */
  public function getProductsCreditMemo($orderId) {
    try {
      $aData = [];

      $connection = $this->_resourceConnection->getConnection();
      $tableName = $this->_resourceConnection->getTableName('zaver_creditmemo');
      $query = "SELECT order_item_id, qty, price, zaver_id, zaver_transaction_id, refunded ";
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
          "refunded" => $aVar["refunded"],];
      }
    }
    catch (\Exception $ex) {
    }

    return $aData;
  }

  public function addCreditMemoProducts($orderid, $creditmemoid, $order_item_id, $qty, $price, $zaver_id) {
    try {
      $connection = $this->_resourceConnection->getConnection();

      /**
       * Execute the query
       */
      $query = "INSERT INTO `zaver_creditmemo` (`order_id`, `creditmemo_id`, `order_item_id`,`qty`,`price`,`zaver_id`,`created_at`)
                VALUES ($orderid, $creditmemoid, '$order_item_id', $qty, $price, '$zaver_id', now())";

      $connection->query($query);
    }
    catch (Exception $ex) {
    }
  }

  public function setProductsCapture($paymentid, $orderid) {
    try {
      $connection = $this->_resourceConnection->getConnection();

      /**
       * Execute the query
       */
      $query = "UPDATE `zaver_creditmemo` SET `zaver_transaction_id`='$paymentid',`refunded`=1 WHERE `order_id`=$orderid";

      $connection->query($query);
    }
    catch (Exception $ex) {
    }
  }

  /**
   * Get the amount and capture from creditmemo items
   *
   * @return array
   */
  public function getInfoCreditMemo($orderId, $entityId) {
    try {
      $aItems = [];

      $connection = $this->_resourceConnection->getConnection();

      $tableName1 = $this->_resourceConnection->getTableName('zaver_creditmemo');

      /**
       * Output the results
       */
      $query = "SELECT order_item_id, qty, price, zaver_id, zaver_transaction_id, refunded ";
      $query .= "FROM " . $tableName1 . " WHERE order_id=$orderId AND creditmemo_id=$entityId;";

      $result = $connection->fetchAll($query);

      /**
       * Output the results
       */
      foreach ($result as $aVar) {
        $aData["qty"] = $aVar["qty"];
        $aData["price"] = $aVar["price"];
        $aData["zaver_id"] = $aVar["zaver_id"];
        $aData["zaver_transaction_id"] = $aVar["zaver_transaction_id"];
        $aData["refunded"] = $aVar["refunded"];
        $aItems[] = $aData;
      }
    }
    catch (\Exception $ex) {
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
    }

    return $strNextTransaction;
  }
}
