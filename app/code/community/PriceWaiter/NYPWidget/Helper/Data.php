<?php
/*
 * Copyright 2012 PriceWaiter, LLC
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

    public function isEnabled()
    {
        // Is the pricewaiter widget enabled for this store
        if (Mage::getStoreConfig('pricewaiter/configuration/enabled')) {

            // Is the pricewaiter widget enabled for this product
            $product = $this->_getProduct();
            if (!is_object($product) or ($product->getId() and !$product->getData('nypwidget_enabled'))) {
                return false;
            }

            // Is the pricewaiter widget enabled for this category
            $category = Mage::registry('current_category');
            if (is_object($category)) {
                $nypcategory = Mage::getModel('nypwidget/category')->loadByCategory($category);
                if (!$nypcategory->isActive()) {
                    return false;
                }
            }

        } else {
            return false;
        }

        return true;
    }

    public function getPriceWaiterOptions()
    {
        $apiKey = Mage::getStoreConfig('pricewaiter/configuration/api_key');

        $displayPhrase     = Mage::getStoreConfig('pricewaiter/appearance/display_phrase') ? 'button_mo' : 'button_nyp';
        $displaySize       = Mage::getStoreConfig('pricewaiter/appearance/display_size') ? 'sm' : 'lg';
        $displayColor      = Mage::getStoreConfig('pricewaiter/appearance/display_color');
        $displayHoverColor = Mage::getStoreConfig('pricewaiter/appearance/display_hover_color');

        $pwOptions = "
            var PriceWaiterOptions = {
                apiKey: '" . $apiKey . "',
                button: {
                    type: " . json_encode($displayPhrase) . ",
                    size: " . json_encode($displaySize) . ",";

        if ($displayColor) {
            $pwOptions .= "
                    color: " . json_encode($displayColor) . ",";
        }

        if ($displayHoverColor) {
            $pwOptions .= "
                    hoverColor: " . json_encode($displayHoverColor) . ",";
        }

        $pwOptions .= "
                },
            };\n";

       return $pwOptions;
    }

    public function getProductOptions($admin = false)
    {
        if ($admin) {
            return "PriceWaiterOptions.product = {
                sku: 'TEST-SKU',
                name: 'Test Name',
                price: 19.99,
                image: 'http://placekitten.com/220/220'
            };";
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
					 	var PriceWaiterProductType = 'grouped';
					";
                    return false;
                    break;
                case "virtual":
                    // Virtual products are not yet supported
                    return false;
                    break;
                case "bundle":
                	// Bundle products support is still in beta
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
            return "https://testing.pricewaiter.com/nyp/script/widget.js";
        } else {
            return "https://widget.pricewaiter.com/nyp/script/widget.js";
        }
    }

    public function getApiUrl()
    {
        if ($this->_testing) {
            return "https://api-testing.pricewaiter.com/1/order/verify?"
                . "api_key="
                . Mage::getStoreConfig('pricewaiter/configuration/api_key');
        } else {
            return "https://api.pricewaiter.com/1/order/verify?api_key="
                . Mage::getStoreConfig('pricewaiter/configuration/api_key');
        }
    }

    private function _pwBoilerPlate($product)
    {
    	return "
    	PriceWaiterOptions.product = {
            sku: " . json_encode($product->getSku()) . ",
            name: " . json_encode($product->getName()) . ",
            price: " . json_encode($product->getFinalPrice()) . ",
            image: " . json_encode($product->getImageUrl()) . "
        };
        var PriceWaiterRegularPrice = " . $product->getPrice() . ";
        ";
    }

    private function _getProduct()
    {
        if (!$this->_product) {
            $this->_product = Mage::registry('current_product');
        }

        return $this->_product;
    }

}
