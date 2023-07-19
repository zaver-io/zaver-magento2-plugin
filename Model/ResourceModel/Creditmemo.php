<?php

namespace Zaver\Payment\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class Creditmemo extends AbstractDb
{
    /**
     * Define main table
     */

    protected function _construct()
    {
        $this->_init('zaver_creditmemo', 'id');
    }

}
