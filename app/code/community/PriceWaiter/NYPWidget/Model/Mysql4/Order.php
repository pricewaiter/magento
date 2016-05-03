<?php

class PriceWaiter_NYPWidget_Model_Mysql4_Order extends Mage_Core_Model_Mysql4_Abstract
{
    public function _construct()
    {
        $this->_init('nypwidget/order', 'entity_id');
    }
}
