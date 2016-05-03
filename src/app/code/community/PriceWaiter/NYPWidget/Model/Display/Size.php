<?php

class PriceWaiter_NYPWidget_Model_Display_Size
{
    public function toOptionArray()
    {
        return array(
            array('value' => 0, 'label' => Mage::helper('nypwidget/data')->__('Large')),
            array('value' => 1, 'label' => Mage::helper('nypwidget/data')->__('Small')),
        );
    }
}
