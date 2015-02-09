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

class PriceWaiter_NYPWidget_ProductinfoController extends Mage_Core_Controller_Front_Action
{
    public function indexAction()
    {
        // Ensure that we have received POST data
        $requestBody = Mage::app()->getRequest()->getRawBody();
        $postFields = Mage::app()->getRequest()->getPost();

        if (count($postFields) == 0) {
            $this->norouteAction();
            return;
        }

        // Validate the request
        // - return 400 if signature cannot be verified
        $signature = Mage::app()->getRequest()->getHeader('X-PriceWaiter-Signature');
        if (Mage::helper('nypwidget')->isPriceWaiterRequestValid($signature, $requestBody) == false) {
            Mage::app()->getResponse()->setHeader('HTTP/1.0 400 Bad Request Error', 400, true);
            return false;
        }

        // Process the request
        // - return 404 if the product does not exist (or PriceWaiter is not enabled)
        $product = Mage::getModel('catalog/product')
            ->loadByAttribute('sku', $postFields['product_sku']);

        if (is_object($product) && $product->getId()) {
            $productInformation = array();

            if (Mage::helper('nypwidget')->isEnabledForStore() &&
                $product->getData('nypwidget_disabled') == 0) {
                $productInformation['allow_pricewaiter'] = true;
            } else {
                $productInformation['allow_pricewaiter'] = false;
            }

            // TODO: Handle production options before pulling remaining product information.
            $stockItem = Mage::getMOdel('cataloginventory/stock_item')
                ->loadByProduct($product);
            $qty = $stockItem->getQty();

            // Check for backorders set for the site
            $backorder = false;
            if ($stockItem->getUseConfigBackorders() &&
                Mage::getStoreConfig('cataloginventory/item_options/backorders')) {
                $backorder = true;
            } else if ($stockItem->getBackorders()) {
                $backorder = true;
            }

            if ($qty != '') {
                $productInformation['inventory'] = (int) $qty;
                $productInformation['can_backorder'] = $backorder;
            }

            $productInformation['retail_price'] = (string) $product->getPrice();
            $productInformation['retail_price_currency'] = Mage::app()->getStore()->getCurrentCurrencyCode();

            $cost = $product->getData('cost');
            if ($cost) {
                $productInformation['cost'] = (string) $cost;
                $productInformation['cost_currency'] = (string) $productInformation['retail_price_currency'];
            }

            // Sign response and send.
            $json = json_encode($productInformation, JSON_PRETTY_PRINT);
            $signature = Mage::helper('nypwidget')->getResponseSignature($json);

            Mage::app()->getResponse()->setHeader('X-PriceWaiter-Signature', $signature);
            Mage::app()->getResponse()->setBody($json);
        } else {
            Mage::app()->getResponse()->setHeader('HTTP/1.0 404 Not Found', 404, true);
            return;
        }
    }
}
