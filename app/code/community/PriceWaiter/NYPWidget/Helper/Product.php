<?php

class PriceWaiter_NYPWidget_Helper_Product extends Mage_Core_Helper_Abstract
{
    public function lookupData(Array $productConfiguration) {
        $productInformation = array();
        $productInformation['allow_pricewaiter'] = Mage::helper('nypwidget')->isEnabledForStore();

        $cart = Mage::getModel('checkout/cart');

        $product = Mage::getModel('catalog/product')
            ->setStoreId(Mage::app()->getStore()->getId())
            ->load($productConfiguration['product']);

        // adding out-of-stock items to cart will fail
        try {
            $cart->addProduct($product, $productConfiguration);
            $cart->save();
        } catch (Mage_Core_Exception $e) {
            $productInformation['inventory'] = 0;
            $productInformation['can_backorder'] = false;
            return $productInformation;
        }

        $cartItem = $cart->getQuote()->getAllItems();
        if ($product->getTypeId() == Mage_Catalog_Model_Product_Type::TYPE_SIMPLE
            || $product->getTypeId() == Mage_Catalog_Model_Product_Type::TYPE_BUNDLE
            || $product->getTypeId() == Mage_Catalog_Model_Product_Type::TYPE_GROUPED
        ) {
            $cartItem = $cartItem[0];
        } else {
            $cartItem = $cartItem[1];
        }

        if ($product->getTypeId() != Mage_Catalog_Model_Product_Type::TYPE_GROUPED) {
            $product = Mage::getModel('catalog/product')->load($cartItem->getProduct()->getId());
        }

        $productFound = is_object($product) && $product->getId();
        if (!$productFound) {
            return false;
        }

        // Pull the product information from the cart item.
        $productType = $product->getTypeId();
        if ($productType == Mage_Catalog_Model_Product_Type::TYPE_SIMPLE
            || $productType == Mage_Catalog_Model_Product_Type::TYPE_CONFIGURABLE
        ) {
            $qty = $product->getStockItem()->getQty();
            $productFinalPrice = $product->getFinalPrice();
            $productPrice = $product->getPrice();
            $msrp = $product->getData('msrp');
            $cost = $product->getData('cost');
        } elseif ($productType == Mage_Catalog_Model_Product_Type::TYPE_GROUPED) {
            $qty = Mage::helper('nypwidget')->getGroupedQuantity($productConfiguration);
            $productFinalPrice = Mage::helper('nypwidget')->getGroupedFinalPrice($productConfiguration);
            $productPrice = $productFinalPrice;
            $msrp = false;
            $cost = Mage::helper('nypwidget')->getGroupedCost($productConfiguration);
        } else {
            $qty = $cartItem->getProduct()->getStockItem()->getQty();
            $productFinalPrice = $cartItem->getPrice();
            $productPrice = $cartItem->getFinalPrice();
            $msrp = $cartItem->getData('msrp');
            $cost = $cartItem->getData('cost');
        }

        // Check for backorders set for the site
        $backorder = false;
        if ($product->getStockItem()->getUseConfigBackorders() &&
            Mage::getStoreConfig('cataloginventory/item_options/backorders')
        ) {
            $backorder = true;
        } else if ($product->getStockItem()->getBackorders()) {
            $backorder = true;
        }

        // If the product is returning a '0' quantity, but is "In Stock", set the "backorder" flag to true.
        if ($product->getStockItem()->getIsInStock() == 1 && $qty == 0) {
            $backorder = true;
        }

        $productInformation['inventory'] = (int)$qty;
        $productInformation['can_backorder'] = $backorder;

        $currency = Mage::app()->getStore()->getCurrentCurrencyCode();

        if ($productFinalPrice != 0) {
            $productInformation['retail_price'] = (string)$productFinalPrice;
            $productInformation['retail_price_currency'] = $currency;
        }

        if ($msrp != '') {
            $productInformation['regular_price'] = (string)$msrp;
            $productInformation['regular_price_currency'] = $currency;
        } elseif ($productPrice != 0) {
            $productInformation['regular_price'] = (string)$productPrice;
            $productInformation['regular_price_currency'] = $currency;
        }

        if ($cost) {
            $productInformation['cost'] = (string)$cost;
            $productInformation['cost_currency'] = (string)$productInformation['retail_price_currency'];
        }

        return $productInformation;
    }
}
