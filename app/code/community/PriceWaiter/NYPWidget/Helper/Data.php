<?php
/*
 * Copyright 2012 PriceWaiter, LLC
 * 
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *      http://www.apache.org/licenses/LICENSE-2.0
 *
 *  Unless required by applicable law or agreed to in writing, software
 *  distributed under the License is distributed on an "AS IS" BASIS,
 *  WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 *  See the License for the specific language governing permissions and
 *  limitations under the License.
 *
 */
class PriceWaiter_NYPWidget_Helper_Data extends Mage_Core_Helper_Abstract
{
    private $_product = false;
    private $_testing = false;
    
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
        
        $displayType       = !Mage::getStoreConfig('pricewaiter/appearance/display_type') ? 'button' : 'text';
        $displaySize       = !Mage::getStoreConfig('pricewaiter/appearance/display_size') ? 'lg' : 'sm';
        $displayColor      = Mage::getStoreConfig('pricewaiter/appearance/display_color');
        $displayHoverColor = Mage::getStoreConfig('pricewaiter/appearance/display_hover_color');
        
        $pwOptions = "
            var PriceWaiterOptions = {
                apiKey: '" . $apiKey . "',
                button: {
                    type: " . json_encode($displayType) . ",
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

    public function getPriceWaiterWidget()
    {
        return "(function() {
            var pw = document.createElement('script');
            pw.type = 'text/javascript';
            pw.src = \"" . $this->getWidgetUrl() . "\";
            pw.async = true;

            var s = document.getElementsByTagName('script')[0];
            s.parentNode.insertBefore(pw, s);

        })();";
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
                    return $this->_getSimpleProductOptions($product);
                    break;
                case "configurable":
                    return $this->_getConfigurableProductOptions($product);
                    break;
                case "grouped":
                    // Grouped products aren't allowed, PriceWaiter only works for single products.
                    return false;
                    break;
                case "virtual":
                    // Virtual products are not yet supported
                    return false;
                    break;
                case "bundle":
                    // Bundled products aren't allowed, PriceWaiter only works for single products.
                    return false;
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

    private function _getSimpleProductOptions($product)
    {
        return "PriceWaiterOptions.product = {
            sku: " . json_encode($product->getSku()) . ",
            name: " . json_encode($product->getName()) . ",
            price: " . json_encode($product->getPrice()) . ",
            image: " . json_encode($product->getImageUrl()) . "
        };";
    }

    private function _getConfigurableProductOptions($product)
    {
        return "PriceWaiterOptions.product = {
            sku: " . json_encode($product->getSku()) . ",
            name: " . json_encode($product->getName()) . ",
            price: " . json_encode($product->getPrice()) . ",
            image: " . json_encode($product->getImageUrl()) . "
        };

        PriceWaiterOptions.onload = 
            function(PriceWaiter) {
                // Bind to each configurable options 'change' event
                spConfig.settings.each(function(setting){
                    var attributeId = $(setting).id;
                    attributeId = attributeId.replace(/attribute/,'');
                    var optionName = spConfig.config.attributes[attributeId].label;
                    // If this option is required, tell the PriceWaiter widget about the requirement
                    if ($(setting).hasClassName('required-entry') && (typeof PriceWaiter.setProductOptionRequired == 'function')) {
                        PriceWaiter.setProductOptionRequired(optionName, true);
                    }
                    Event.observe(setting, 'change', function(event){
                        // Update PriceWaiter's price and options when changes are made
                        PriceWaiter.setPrice(Number(spConfig.config.basePrice) + Number(spConfig.reloadPrice()));
                        var optionValue = setting.value != \"\" ? setting.options[setting.selectedIndex].innerHTML : undefined;
                        // if the option value is undefined, clear the option. Otherwise, set the newly selected option.
                        if (optionValue == undefined) {
                            PriceWaiter.clearProductOption(optionName);
                        } else {
                            PriceWaiter.setProductOption(optionName, optionValue);
                        }
                    });
                });
            };";
    }
  
    private function _getProduct()
    {
        if (!$this->_product) {
            $this->_product = Mage::registry('current_product');
        }

        return $this->_product;
    }

}
