<?php

class PriceWaiter_NYPWidget_Block_Widget extends Mage_Core_Block_Template
{
    /**
     * @return array Bits of information used to make up the cache key.
     */
    public function getCacheKeyInfo()
    {
        $items = parent::getCacheKeyInfo();

        if (!is_array($items)) {
            $items = array();
        }

        $items[] = 'PriceWaiter';


        // 1. Cache per-customer
        $customer = $this->getCustomer();
        $items[] = $customer ? $customer->getId() : 0;

        // 2. Cache per-customer group
        $items[] = $this->getCustomerGroupId();

        // 3. Cache per-product
        $product = $this->getProduct();
        $items[] = $product ? $product->getId() : 0;

        // 4. Cache per-category
        $category = $this->getCategory();
        $items[] = $category ? $category->getId() : 0;

        return $items;
    }

    /**
     * @return Integer This block's cache lifetime (in seconds).
     */
    public function getCacheLifetime()
    {
        return 7200;
    }

    public function getCacheTags()
    {
        $tags = parent::getCacheTags();
        if (!is_array($tags)) {
            $tags = array();
        }

        $tags[] = Mage_Catalog_Model_Product::CACHE_TAG;
        $tags[] = Mage_Catalog_Model_Category::CACHE_TAG;
        $tags[] = PriceWaiter_NYPWidget_Helper_Data::CACHE_TAG;

        return $tags;
    }

    /**
     * @return Mage_Catalog_Model_Category
     */
    public function getCategory()
    {
        $category = Mage::registry('current_category');
        return $category && $category->getId() ? $category : false;
    }

    /**
     * @return Mage_Customer_Model_Customer|false
     */
    public function getCustomer()
    {
        $session = $this->getCustomerSession();
        $customer = $session->getCustomer();

        if ($customer && $customer->getId()) {
            return $customer;
        }

        return false;
    }

    /**
     * @return Number
     */
    public function getCustomerGroupId()
    {
        $session = $this->getCustomerSession();
        return $session->getCustomerGroupId();
    }

    /**
     * @return Mage_Customer_Model_Session
     */
    public function getCustomerSession()
    {
        return Mage::getSingleton('customer/session');
    }

    /**
     * @return Mage_Catalog_Model_Product|false
     */
    public function getProduct()
    {
        $product = Mage::registry('current_product');
        return $product && $product->getId() ? $product : false;
    }

    /**
     * @return Mage_Core_Model_Store
     */
    public function getStore()
    {
        return Mage::app()->getStore();
    }

    /**
     * @return PriceWaiter_NYPWidget_Model_Embed
     */
    public function getEmbed()
    {
        return Mage::getModel('nypwidget/embed')
            ->setProduct($this->getProduct())
            ->setStore($this->getStore())
            ->setCategory($this->getCategory())
            ->setCustomer($this->getCustomer())
            ->setCustomerGroupId($this->getCustomerGroupId());
    }
}
