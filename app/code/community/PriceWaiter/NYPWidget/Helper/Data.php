<?php

/*
 * Copyright 2013-2014 Price Waiter, LLC
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *    http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 *
 */

class PriceWaiter_NYPWidget_Helper_Data extends Mage_Core_Helper_Abstract
{
    private $_product = false;
    private $_testing = false;
    private $_buttonEnabled = null;
    private $_conversionToolsEnabled = null;

    public function isTesting()
    {
        return $this->_testing;
    }

    public function isEnabledForStore()
    {
        // Is the pricewaiter widget enabled for this store and an API Key has been set.
        if (Mage::getStoreConfig('pricewaiter/configuration/enabled')
            && Mage::getStoreConfig('pricewaiter/configuration/api_key')
        ) {
            return true;
        }

        return false;
    }

    // Set the values of $_buttonEnabled and $_conversionToolsEnabled
    private function _setEnabledStatus()
    {
        if ($this->_buttonEnabled != null && $this->_conversionToolsEnabled != null) {
            return true;
        }

        if (Mage::getStoreConfig('pricewaiter/configuration/enabled')) {
            $this->_buttonEnabled = true;
        }

        if (Mage::getStoreConfig('pricewaiter/conversion_tools/enabled')) {
            $this->_conversionToolsEnabled = true;
        }

        // Is the pricewaiter widget enabled for this product
        $product = $this->_getProduct();
        if (!is_object($product) or ($product->getId() and $product->getData('nypwidget_disabled'))) {
            $this->_buttonEnabled = false;
        }

        if (!is_object($product) or ($product->getId() and $product->getData('nypwidget_ct_disabled'))) {
            $this->_conversionToolsEnabled = false;
        }

        // Is the PriceWaiter widget enabled for this category
        $category = Mage::registry('current_category');
        if (is_object($category)) {
            $nypcategory = Mage::getModel('nypwidget/category')->loadByCategory($category);
            if (!$nypcategory->isActive()) {
                $this->_buttonEnabled = false;
            }
            if (!$nypcategory->isConversionToolsEnabled()) {
                $this->_conversionToolsEnabled = false;
            }
        } else {
            // We end up here if we are visiting the product page without being
            // "in a category". Basically, we arrived via a search page.
            // The logic here checks to see if there are any categories that this
            // product belongs to that enable the PriceWaiter widget. If not, return false.
            $categories = $product->getCategoryIds();
            $categoryActive = false;
            $categoryCTActive = false;
            foreach ($categories as $categoryId) {
                unset($currentCategory);
                unset($nypcategory);
                $currentCategory = Mage::getModel('catalog/category')->load($categoryId);
                $nypcategory = Mage::getModel('nypwidget/category')->loadByCategory($currentCategory);
                if ($nypcategory->isActive()) {
                    if ($nypcategory->isConversionToolsEnabled()) {
                        $categoryCTActive = true;
                    }
                    $categoryActive = true;
                    break;
                }
            }
            if (!$categoryActive) {
                $this->_buttonEnabled = false;
            }

            if (!$categoryCTActive) {
                $this->_conversionToolsEnabled = false;
            }

        }

        // Is PriceWaiter enabled for this Customer Group
        $disable = Mage::getStoreConfig('pricewaiter/customer_groups/disable');
        if ($disable) {
            // An admin has chosen to disable the PriceWaiter widget by customer group.
            $customerGroupId = Mage::getSingleton('customer/session')->getCustomerGroupId();
            $customerGroups = Mage::getStoreConfig('pricewaiter/customer_groups/group_select');
            $customerGroups = preg_split('/,/', $customerGroups);

            if (in_array($customerGroupId, $customerGroups)) {
                $this->_buttonEnabled = false;
            }
        }

        // Are Conversion Tools  enabled for this Customer Group
        $disableCT = Mage::getStoreConfig('pricewaiter/conversion_tools/customer_group_disable');
        if ($disableCT) {
            // An admin has chosen to disable the Conversion Tools by customer group.
            $customerGroupId = Mage::getSingleton('customer/session')->getCustomerGroupId();
            $customerGroups = Mage::getStoreConfig('pricewaiter/conversion_tools/group_select');
            $customerGroups = preg_split('/,/', $customerGroups);

            if (in_array($customerGroupId, $customerGroups)) {
                $this->_conversionToolsEnabled = false;
            }
        }
    }

    public function isConversionToolsEnabled()
    {
        $this->_setEnabledStatus();

        return $this->_conversionToolsEnabled;
    }

    public function isButtonEnabled()
    {
        $this->_setEnabledStatus();

        return $this->_buttonEnabled;
    }

    public function getWidgetUrl()
    {
        if ($this->isEnabledForStore()) {
            return "https://widget.pricewaiter.com/script/"
                . Mage::getStoreConfig('pricewaiter/configuration/api_key')
                . ".js";
        }

        return "https://widget.pricewaiter.com/nyp/script/widget.js";
    }

    public function getApiUrl()
    {
        return "https://api.pricewaiter.com/1/order/verify?api_key="
            . Mage::getStoreConfig('pricewaiter/configuration/api_key');
    }

    public function getProductPrice($product)
    {
        $productPrice = 0;

        if ($product->getId()) {
            if ($product->getTypeId() != 'grouped') {
                $productPrice = $product->getFinalPrice();
            }
        }

        return $productPrice;
    }

    private function _getProduct()
    {
        if (!$this->_product) {
            $this->_product = Mage::registry('current_product');
        }

        return $this->_product;
    }

    private function _getGroupedProductInfo()
    {
        $product = $this->_getProduct();
        $javascript = "var PriceWaiterGroupedProductInfo =  new Array();\n";

        $associatedProducts = $product->getTypeInstance(true)->getAssociatedProducts($product);
        foreach ($associatedProducts as $simpleProduct) {
            $javascript .= "PriceWaiterGroupedProductInfo[" . $simpleProduct->getId() . "] = ";
            $javascript .= "new Array('" . htmlentities($simpleProduct->getName()) . "', '"
                . number_format($simpleProduct->getPrice(), 2) . "')\n";
        }

        return $javascript;
    }

    public function getStoreByApiKey($apiKey)
    {
        $stores = Mage::app()->getStores();

        // Find the store with the matching API key by checking the key for each store
        // in Magento
        foreach ($stores as $store) {
            if ($apiKey == Mage::getStoreConfig('pricewaiter/configuration/api_key', $store->getId())) {
                return $store;
            }
        }

        return Mage::app()->getStore();
    }
}
