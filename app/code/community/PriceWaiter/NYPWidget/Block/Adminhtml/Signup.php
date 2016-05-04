<?php

class PriceWaiter_NYPWidget_Block_Adminhtml_Signup extends Mage_Adminhtml_Block_System_Config_Form_Field
{
    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('pricewaiter/signup.phtml');
    }

    protected function _getElementHtml(Varien_Data_Form_Element_Abstract $el)
    {
        return $this->_toHtml();
    }

    public function getButtonHtml()
    {
        // If they have already set their API key, don't show the button.
        if (Mage::getStoreConfig('pricewaiter/configuration/api_key')) {
            return;
        }

        $button = $this->getLayout()->createBlock('adminhtml/widget_button')->setData(
            array(
                'id' => 'nypwidget_signup',
                'label' => $this->helper('adminhtml')->__('Sign Up for PriceWaiter'),
                'disabled' => true
            )
        );

        return $button->toHtml();
    }

    public function getTokenUrl()
    {
        return Mage::helper('adminhtml')->getUrl('adminhtml/adminhtml_pricewaiter/token');
    }

    public function getSecretUrl()
    {
        return Mage::helper('adminhtml')->getUrl('adminhtml/adminhtml_pricewaiter/secret');
    }
}
