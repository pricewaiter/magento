<?php

/**
 * Tests around inventory tracking.
 */
class Integration_OfferItem_GetInventoryTest
    extends Integration_AbstractProductTest
{
    public function testSimpleProductNotManagingStock()
    {
        $product = $this->getSimpleProduct();

        $product->getStockItem()
            ->setManageStock(0)
            ->setUseConfigManageStock(0)
            ->save();

        try
        {
            $item = $this->getOfferItemForProduct($product);
            $inventory = $item->getInventory();

            $this->assertFalse($inventory->getStock(), 'Stock reported as false');

            $product->getStockItem()
                ->setManageStock(0)
                ->setUseConfigManageStock(1)
                ->save();
        }
        catch (Exception $ex)
        {
            $product->getStockItem()
                ->setManageStock(0)
                ->setUseConfigManageStock(1)
                ->save();

            throw $ex;
        }
    }

    public function testSimpleProductWithBackorders()
    {
        $product = $this->getSimpleProduct();

        $product->getStockItem()
            ->setBackorders(Mage_CatalogInventory_Model_Stock::BACKORDERS_YES_NONOTIFY)
            ->setUseConfigBackorders(0)
            ->save();

        try
        {
            $item = $this->getOfferItemForProduct($product);
            $inventory = $item->getInventory();

            $this->assertTrue($inventory->canBackorder(), 'canBackorder returns true');

            $product->getStockItem()
                ->setBackorders(Mage_CatalogInventory_Model_Stock::BACKORDERS_NO)
                ->setUseConfigBackorders(1)
                ->save();
        }
        catch (Exception $ex)
        {
            $product->getStockItem()
                ->setBackorders(Mage_CatalogInventory_Model_Stock::BACKORDERS_NO)
                ->setUseConfigBackorders(1)
                ->save();

            throw $ex;
        }
    }

    public function testSimpleProductOutOfStock()
    {
        $product = $this->getSimpleProduct(0);
        $item = $this->getOfferItemForProduct($product);
        $inventory = $item->getInventory();

        $this->assertEquals(0, $inventory->getStock(), 'Stock is correct');
    }

    public function testSimpleProductInStock()
    {
        $product = $this->getSimpleProduct(100);
        $item = $this->getOfferItemForProduct($product);
        $inventory = $item->getInventory();

        $this->assertFalse($inventory->canBackorder(), 'Backorder disabled');
        $this->assertEquals(100, $inventory->getStock(), 'Stock is correct');
    }

    public function testConfigurableProduct()
    {
        list($product, $addToCartForm, $simpleProduct) = $this->getConfigurableProduct(42);
        $item = $this->getOfferItemForProduct($product, $addToCartForm);
        $inventory = $item->getInventory();

        $this->assertFalse($inventory->canBackorder(), 'Backorder disabled');
        $this->assertEquals(42, $inventory->getStock(), 'Stock is correct');
    }

    public function testBundleProduct()
    {
        list($product, $addToCartForm, $bundleProducts) = $this->getBundleProduct(10, 6);
        $item = $this->getOfferItemForProduct($product, $addToCartForm);
        $inventory = $item->getInventory();

        $this->assertFalse($inventory->canBackorder(), 'Backorder disabled');
        $this->assertEquals(1, $inventory->getStock(), 'Stock is correct');
    }
}
