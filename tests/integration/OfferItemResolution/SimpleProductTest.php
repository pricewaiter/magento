<?php

/**
 * Tests around translating incoming PriceWaiter data into actual native Magento product data.
 */
class Integration_OfferItemResolution_SimpleProductTest
    extends PHPUnit_Framework_TestCase
{
    public $simpleProduct = array(
        'sku' => 'hde012',
        'id' => '399',
    );

    public $otherSimpleProduct = array(
        'sku' => 'hde013',
        'id' => '400',
    );

    /**
     * @expectedException PriceWaiter_NYPWidget_Exception_Product_NotFound
     */
    public function testProductNotFoundBySku()
    {
        $this->resolveSimpleProductItem(null, 'FAKE-SKU');
    }

    public function testFindSimpleProductBySKU()
    {
        $items = $this->resolveSimpleProductItem(null, $this->simpleProduct['sku']);
        $this->assertCount(1, $items);
        $item = $items[0];

        $this->assertInstanceOf('PriceWaiter_NYPWidget_Model_ResolvedItem', $item);

        $product = $item->getProduct();
        $this->assertEquals($this->simpleProduct['id'], $product->getId());
        $this->assertEquals($this->simpleProduct['sku'], $product->sku);
    }

    public function testFindSimpleProductByIdOnly()
    {
        $items = $this->resolveSimpleProductItem($this->simpleProduct['id'], null);
        $this->assertCount(1, $items);
        $item = $items[0];

        $this->assertInstanceOf('PriceWaiter_NYPWidget_Model_ResolvedItem', $item);

        $product = $item->getProduct();
        $this->assertEquals($this->simpleProduct['id'], $product->getId());
        $this->assertEquals($this->simpleProduct['sku'], $product->sku);
    }

    public function testFindSimpleProductBySkuAndId()
    {
        $sku = $this->simpleProduct['sku'];
        $id = $this->simpleProduct['id'];

        $items = $this->resolveSimpleProductItem($id, $sku);
        $this->assertCount(1, $items);
        $item = $items[0];

        $this->assertInstanceOf('PriceWaiter_NYPWidget_Model_ResolvedItem', $item);

        $product = $item->getProduct();
        $this->assertEquals($id, $product->getId());
        $this->assertEquals($sku, $product->sku);
    }

    public function testDetectWhenSimpleProductSKUAndIdDontMatch()
    {
        try
        {
            $items = $this->resolveSimpleProductItem(
                $this->simpleProduct['id'],
                $this->otherSimpleProduct['sku']
            );
            $this->assertTrue(false, 'Lookup with conflicting id/sku should not have succeeded');
        }
        catch (PriceWaiter_NYPWidget_Exception_Product_InconsistentData $ex)
        {
            $this->assertInstanceOf('Mage_Catalog_Model_Product', $ex->productFoundById);
            $this->assertEquals($this->simpleProduct['id'], $ex->productFoundById->getId());

            $this->assertInstanceOf('Mage_Catalog_Model_Product', $ex->productFoundBySku);
            $this->assertEquals($this->otherSimpleProduct['id'], $ex->productFoundBySku->getId());
        }
    }

    /**
     * @expectedException PriceWaiter_NYPWidget_Exception_Product_InconsistentData
     */
    public function testDetectWhenSkuFoundButIdNot()
    {
        $this->resolveSimpleProductItem(123456789, $this->simpleProduct['sku']);
    }

    /**
     * @expectedException PriceWaiter_NYPWidget_Exception_Product_InconsistentData
     */
    public function testDetectWhenIdFoundButSkuNot()
    {
        $this->resolveSimpleProductItem($this->simpleProduct['id'], 'FAKE-SKU');
    }

    /**
     * Given an optional product id + sku, constructs an OfferItem and attempts
     * to resolve it into a ResolvedItem.
     */
    public function resolveSimpleProductItem($id = null, $sku = null)
    {
        $data = array();

        if ($id !== null) {
            // Put a product id in the add to cart metadata
            $data['metadata'] = array(
                '_magento_product_configuration' => "form_key%3DTZOErf9coZs1VcFU%26product%3D{$id}%26related_product%3D%26qty%3D1",
            );
        }

        if ($sku !== null) {
            // Put the explicit product sku in
            $data['product'] = compact('sku');
        }

        $offerItem = new PriceWaiter_NYPWidget_Model_OfferItem($data);
        $helper = Mage::helper('nypwidget/products');

        return $helper->resolveItems(array($offerItem));
    }
}
