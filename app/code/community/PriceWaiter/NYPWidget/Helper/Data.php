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

    public function isEnabled()
    {
        if (Mage::getStoreConfig('pricewaiter/configuration/enabled')) {

            // Is the pricewaiter widget enabled for this product
            $product = $this->_getProduct();
            if (!is_object($product) or ($product->getId() and $product->getData('nypwidget_disabled'))) {
                return false;
            }

            // Is the PriceWaiter widget enabled for this category
            $category = Mage::registry('current_category');
            if (is_object($category)) {
                $nypcategory = Mage::getModel('nypwidget/category')->loadByCategory($category);
                if (!$nypcategory->isActive()) {
                    return false;
                }
            } else {
                // We end up here if we are visiting the product page without being
                // "in a category". Basically, we arrived via a search page.
                // The logic here checks to see if there are any categories that this
                // product belongs to that enable the PriceWaiter widget. If not, return false.
                $categories = $product->getCategoryIds();
                $categoryActive = false;
                foreach ($categories as $categoryId) {
                    unset($currentCategory);
                    unset($nypcategory);
                    $currentCategory = Mage::getModel('catalog/category')->load($categoryId);
                    $nypcategory = Mage::getModel('nypwidget/category')->loadByCategory($currentCategory);
                    if ($nypcategory->isActive()) {
                        $categoryActive = true;
                        break;
                    }
                }
                if (!$categoryActive) {
                    return false;
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
                    return false;
                }
            }
        } else {
            // We end up here if PriceWaiter is disabled for this store
            return false;
        }

        return true;
    }

    public function getProductOptions($admin = false)
    {
        if ($admin) {
            return "
            PriceWaiterOptions.product = {
                sku: 'TEST-SKU',
                name: 'Test Name',
                price: 19.99,
                image: 'http://placekitten.com/220/220'
            };
            var PriceWaiterProductType = 'simple';
            var PriceWaiterRegularPrice = 19.99
            ";
        }

        $product = $this->_getProduct();

        if ($product->getId()) {

            switch ($product->getTypeId()) {
                case "simple":
                    return $this->_pwBoilerPlate($product) . "
                    var PriceWaiterProductType = 'simple';
                ";
                    break;
                case "configurable":
                    return $this->_pwBoilerPlate($product) . "
                    var PriceWaiterProductType = 'configurable';
                ";
                    break;
                case "grouped":
                    return $this->_pwBoilerPlate($product) . "
                    var PriceWaiterProductType = 'grouped';\n"
                    . $this->_getGroupedProductInfo() . "\n";
                    break;
                case "virtual":
                    // Virtual products are not yet supported
                    return false;
                    break;
                case "bundle":
                    return $this->_pwBoilerPlate($product) . "
                    var PriceWaiterProductType = 'bundle';
                ";
                    break;
                case "downloadable":
                    // Downloadable products are not yet supported
                    return false;
                    break;
                default:
                    return false;
                    break;
            }
        } else {
            return false;
        }
    }

    public function getWidgetUrl()
    {
        if ($this->_testing) {
            return "https://widget-staging.pricewaiter.com/nyp/script/widget.js";
        } elseif ($this->isEnabledForStore()) {
            return "https://widget.pricewaiter.com/script/"
            . Mage::getStoreConfig('pricewaiter/configuration/api_key')
            . ".js";
        }

        return "https://widget.pricewaiter.com/nyp/script/widget.js";
    }

    public function getApiUrl()
    {
        if ($this->_testing) {
            return "https://api-staging.pricewaiter.com/1/order/verify?"
            . "api_key="
            . Mage::getStoreConfig('pricewaiter/configuration/api_key');
        } else {
            return "https://api.pricewaiter.com/1/order/verify?api_key="
            . Mage::getStoreConfig('pricewaiter/configuration/api_key');
        }
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
