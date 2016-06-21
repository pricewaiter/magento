<?php

/**
 * Base for implementing OC-related tests.
 */
abstract class Integration_ProductInfo_Base
    extends PHPUnit_Framework_TestCase
{
    public $apiKey = 'MAGENTO';

    public $product = array(
        'type' => 'simple',
        'sku' => 'hde012',
        'id' => '399',
        'name' => 'Madison 8GB Digital Media Player',
        'price' => '150.00',
        'weight' => '1.0000',
    );

    /**
     * @return Array
     */
    protected function buildProductInfoRequest($quantity = 1)
    {
        $product = $this->product;

        $request = array(
            "form_key" => uniqid(true),
            "product" => 399,
            "related_product" => '',
            "qty" => $quantity,
        );

        return $request;
    }

    protected function doProductInfoRequest()
    {
        $productHelper = Mage::helper('nypwidget/product');
        return $productHelper->lookupData($this->buildProductInfoRequest());
    }

    protected function setProductInStock($inStock = true, $minRequiredQty = 100)
    {
        $id = isset($this->product['id_for_inventory']) ?
            $this->product['id_for_inventory'] :
            $this->product['id'];

        $product = Mage::getModel('catalog/product')
            ->load($id);

        $stock = $product->getStockItem();
        $stock
            ->setQty($minRequiredQty)
            ->setIsInStock($inStock)
            ->save();
    }

    protected function getCurrentProductInventory()
    {
        $product = Mage::getModel('catalog/product')
            ->load($this->product['id']);

        $stock = $product->getStockItem();

        // Necessary to get around stale data in stock item.
        $stock->load($stock->getId());

        return $stock->getQty();
    }

    protected function getStore()
    {
        return Mage::helper('nypwidget')->getStoreByPriceWaiterApiKey($this->apiKey);
    }
}
