<?php

class PriceWaiter_NYPWidget_Block_Adminhtml_Widget extends Mage_Adminhtml_Block_Abstract
{
    public function _getHelper()
    {
        $helper = Mage::helper('nypwidget');
        return $helper;
    }
}
