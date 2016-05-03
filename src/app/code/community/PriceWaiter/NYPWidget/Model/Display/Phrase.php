<?php

class PriceWaiter_NYPWidget_Model_Display_Phrase
{
    public function toOptionArray()
    {
        return array(
            array('value' => 0, 'label' => Mage::helper('nypwidget/data')->__('Name Your Price')),
            array('value' => 1, 'label' => Mage::helper('nypwidget/data')->__('Make an Offer')),
        );
    }
}
