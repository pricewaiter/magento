<?php

class PriceWaiter_NYPWidget_Block_Category extends Mage_Adminhtml_Block_Template
    implements Mage_Adminhtml_Block_Widget_Tab_Interface
{
    public function __construct()
    {
        $this->setTemplate('pricewaiter/categorytab.phtml');
    }

    private function _getCategory()
    {
        $category = Mage::registry('category');
        $nypcategory = Mage::getModel('nypwidget/category')->loadByCategory($category, $category->getStore()->getId());

        return $nypcategory;
    }

    public function getIsEnabled()
    {
        $category = $this->_getCategory();
        return $category->isActive(true);
    }

    public function getIsConversionToolsEnabled()
    {
        $category = $this->_getCategory();
        return $category->isConversionToolsEnabled(true);
    }

    public function getTabLabel()
    {
        return $this->__('PriceWaiter Widget');
    }

    public function getTabTitle()
    {
        return $this->__('PriceWaiter');
    }

    public function canShowTab()
    {
        return true;
    }

    public function isHidden()
    {
        return false;
    }

}
