<?php

class PriceWaiter_NYPWidget_Model_Observer
{
    // Adds the "PriceWaiter" tab to the "Manage Categories" page
    public function addTab(Varien_Event_Observer $observer)
    {
        $tabs = $observer->getEvent()->getTabs();
        $tabs->addTab('pricewaiter', array(
            'label' => Mage::helper('catalog')->__('PriceWaiter'),
            'content' => $tabs->getLayout()->createBlock(
                    'nypwidget/category')->toHtml(),
        ));
        return true;
    }

    // Saves "PriceWaiter" options from Category page
    public function saveCategory(Varien_Event_Observer $observer)
    {
        $category = $observer->getEvent()->getCategory();
        $postData = $observer->getEvent()->getRequest()->getPost();
        $enabled = $postData['pricewaiter']['enabled'];
        $ctEnabled = $postData['pricewaiter']['ct_enabled'];

        // Save the current setting, by category, and store
        $nypcategory = Mage::getModel('nypwidget/category')->loadByCategory($category, $category->getStore()->getId());
        $nypcategory->setData('nypwidget_enabled', $enabled);
        $nypcategory->setData('nypwidget_ct_enabled', $ctEnabled);
        $nypcategory->save();

        return true;
    }
}
