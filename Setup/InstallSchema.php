<?php

namespace Zaver\Payment\Setup;

use Magento\Framework\DB\Ddl\Table;
use Magento\Framework\Setup\InstallSchemaInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;

/**
 * Class InstallSchema
 *
 * @package Zaver\Payment\Setup
 */
class InstallSchema implements InstallSchemaInterface
{
  /**
   * @param SchemaSetupInterface $setup
   * @param ModuleContextInterface $context
   * @throws \Zend_Db_Exception
   * @SuppressWarnings(PMD.UnusedFormalParameter)
   */
  public function install(SchemaSetupInterface $setup, ModuleContextInterface $context) {
    $installer = $setup;

    $installer->startSetup();

    $connection = $installer->getConnection();

    // Order table
    $connection->addColumn(
      $installer->getTable('sales_order'),
      'zaver_status',
      [
        'type' => Table::TYPE_SMALLINT,
        'comment' => 'Order status'
      ]
    );

    $connection->addColumn(
      $installer->getTable('sales_order'),
      'zaver_transaction_id',
      [
        'type' => Table::TYPE_TEXT,
        'length' => 32,
        'comment' => 'Payment transaction id in Shop'
      ]
    );

    $connection->addColumn(
      $installer->getTable('sales_order'),
      'zaver_payment_id',
      [
        'type' => Table::TYPE_TEXT,
        'length' => 100,
        'comment' => 'Payment transaction id in Zaver'
      ]
    );

    $connection->addColumn(
      $installer->getTable('sales_order'),
      'zaver_payment_status',
      [
        'type' => Table::TYPE_TEXT,
        'length' => 50,
        'comment' => 'Payment status in Zaver'
      ]
    );

    $connection->addColumn(
      $installer->getTable('sales_order'),
      'zaver_order_items_id',
      [
        'type' => Table::TYPE_TEXT,
        'length' => 10000,
        'comment' => 'Items id in Zaver. The key-value pairs itemid:zaveritemid separated by semicolons.'
      ]
    );

    $connection->addColumn(
      $installer->getTable('sales_order'),
      'zaver_refund_id',
      [
        'type' => Table::TYPE_TEXT,
        'length' => 100,
        'comment' => 'Payment refund id in Zaver'
      ]
    );

    if (!$installer->tableExists('zaver_shipments')) {
      /**
       * Create table 'zaver_shipments'
       */
      $table = $installer->getConnection()
        ->newTable($installer->getTable('zaver_shipments'))
        ->addColumn(
          'id',
          Table::TYPE_INTEGER,
          null,
          [
            'identity' => true,
            'unsigned' => true,
            'nullable' => false,
            'primary' => true,
            'auto_increment' => true,
          ],
          'Entity Id'
        )
        ->addColumn(
          'order_id',
          Table::TYPE_INTEGER,
          null,
          [
            'unsigned' => true,
            'nullable' => false,
            'primary' => true
          ],
          'Order Id'
        )
        ->addColumn(
          'ship_id',
          Table::TYPE_INTEGER,
          null,
          [
            'unsigned' => true,
            'nullable' => false,
            'primary' => true
          ],
          'Ship Id'
        )
        ->addColumn(
          'order_item_id',
          Table::TYPE_TEXT,
          100,
          [],
          'Order item id'
        )
        ->addColumn(
          'qty',
          Table::TYPE_DECIMAL,
          '12,4',
          [],
          'Product quantity'
        )
        ->addColumn(
          'price',
          Table::TYPE_DECIMAL,
          '20,4',
          [],
          'Product price'
        )
        ->addColumn(
          'zaver_id',
          Table::TYPE_TEXT,
          100,
          [],
          'Zaver order item id'
        )
        ->addColumn(
          'zaver_transaction_id',
          Table::TYPE_TEXT,
          100,
          [],
          'Zaver transaction id'
        )
        ->addColumn(
          'created_at',
          Table::TYPE_TIMESTAMP,
          null,
          [],
          'Date transaction date/time'
        )
        ->addColumn(
          'captured',
          Table::TYPE_SMALLINT,
          null,
          [
            'nullable' => false,
            'default' => '0',
          ],
          'Captured in zaver'
        );

      $installer->getConnection()->createTable($table);
    }

    if (!$installer->tableExists('zaver_creditmemo')) {
      /**
       * Create table 'zaver_shipments'
       */
      $table = $installer->getConnection()
        ->newTable($installer->getTable('zaver_creditmemo'))
        ->addColumn(
          'id',
          Table::TYPE_INTEGER,
          null,
          [
            'identity' => true,
            'unsigned' => true,
            'nullable' => false,
            'primary' => true,
            'auto_increment' => true,
          ],
          'Entity Id'
        )
        ->addColumn(
          'order_id',
          Table::TYPE_INTEGER,
          null,
          [
            'unsigned' => true,
            'nullable' => false,
            'primary' => true
          ],
          'Order Id'
        )
        ->addColumn(
          'creditmemo_id',
          Table::TYPE_INTEGER,
          null,
          [
            'unsigned' => true,
            'nullable' => false,
            'primary' => true
          ],
          'Creditmemo Id'
        )
        ->addColumn(
          'order_item_id',
          Table::TYPE_TEXT,
          100,
          [],
          'Order item id'
        )
        ->addColumn(
          'qty',
          Table::TYPE_DECIMAL,
          '12,4',
          [],
          'Product quantity'
        )
        ->addColumn(
          'price',
          Table::TYPE_DECIMAL,
          '20,4',
          [],
          'Product price'
        )
        ->addColumn(
          'zaver_id',
          Table::TYPE_TEXT,
          100,
          [],
          'Zaver order item id'
        )
        ->addColumn(
          'zaver_transaction_id',
          Table::TYPE_TEXT,
          100,
          [],
          'Zaver transaction id'
        )
        ->addColumn(
          'created_at',
          Table::TYPE_TIMESTAMP,
          null,
          [],
          'Date transaction date/time'
        )
        ->addColumn(
          'refunded',
          Table::TYPE_SMALLINT,
          null,
          [
            'nullable' => false,
            'default' => '0',
          ],
          'Refunded in zaver'
        );

      $installer->getConnection()->createTable($table);
    }

    $installer->endSetup();
  }
}
