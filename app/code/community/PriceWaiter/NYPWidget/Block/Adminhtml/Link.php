<?php

class PriceWaiter_NYPWidget_Block_Adminhtml_Link extends Varien_Data_Form_Element_Link implements Varien_Data_Form_Element_Renderer_Interface
{
    public function render(Varien_Data_Form_Element_Abstract $element)
    {
        $helper = Mage::helper('nypwidget');
        $this->setData('href', $helper->getPriceWaiterSettingsUrl());
        $this->setData('target', '_blank');
        $this->setData('value', 'Edit other settings on PriceWaiter.com');
        return $this->toHtml();
    }
}
