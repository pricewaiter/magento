<?php

class PriceWaiter_NYPWidget_Block_Widget extends Mage_Core_Block_Template
{
    /**
     * @return PriceWaiter_NYPWidget_Model_Embed
     */
    public function getEmbed()
    {
        // Figure out where + who we are...
        $product = Mage::registry('current_product');
        $store = Mage::app()->getStore();
        $category = Mage::registry('current_category');

        $session = Mage::getSingleton('customer/session');
        $customer = $session->getCustomer();
        $customerGroupId = $session->getCustomerGroupId();

        // ..and wire up an appropriate embed.
        return Mage::getModel('nypwidget/embed')
            ->setProduct($product)
            ->setStore($store)
            ->setCategory($category)
            ->setCustomer($customer->getId() ? $customer : false)
            ->setCustomerGroupId($customerGroupId);
    }
}
