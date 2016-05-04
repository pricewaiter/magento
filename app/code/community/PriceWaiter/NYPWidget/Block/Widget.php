<?php

class PriceWaiter_NYPWidget_Block_Widget extends Mage_Core_Block_Template
{
    public function _getHelper()
    {
        $helper = Mage::helper('nypwidget');
        return $helper;
    }

    public function getPriceWaiterOptions()
    {
        $product = Mage::registry('current_product');
    }
}
