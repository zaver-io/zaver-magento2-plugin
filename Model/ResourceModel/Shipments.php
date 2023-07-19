<?php

namespace Zaver\Payment\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class Shipments extends AbstractDb
{
    /**
     * Define main table
     */

    protected function _construct()
    {
        $this->_init('zaver_shipments', 'id');
    }

}
