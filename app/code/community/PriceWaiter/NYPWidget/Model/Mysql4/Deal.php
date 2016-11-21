<?php

class PriceWaiter_NYPWidget_Model_Mysql4_Deal extends Mage_Core_Model_Mysql4_Abstract
{
    /**
     * @internal
     * deal_id is provided externally and used as the PK.
     * @var boolean
     */
    protected $_isPkAutoIncrement = false;

    public function _construct()
    {
        $this->_init('nypwidget/deal', 'deal_id');
    }
}
