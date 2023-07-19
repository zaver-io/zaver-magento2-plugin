<?php

namespace Zaver\Payment\Model\ResourceModel\Shipments;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Zaver\Payment\Model\Shipments as Model;
use Zaver\Payment\Model\ResourceModel\Shipments as ResourceModel;

class Collection extends AbstractCollection
{
    /**
     * Define model & resource model
     */
    protected function _construct()
    {
        $this->_init(Model::class, ResourceModel::class);
    }
}
