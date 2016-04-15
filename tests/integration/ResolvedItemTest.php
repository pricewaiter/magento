<?php

/**
 * Integration tests around the ResolvedItem model.
 */
class Integration_ResolvedItemTest extends PHPUnit_Framework_Testcase
{
    public $simpleProduct = array(
        'sku' => 'hde012',
        'id' => '399',
        'price' => '150.00',
    );

    /**
     * @dataProvider provideProductsWithPrices
     * @param  Mage_Catalog_Model_Product $product
     */
    public function testPrice(Mage_Catalog_Model_Product $product, $expectedPrice)
    {
        $offerItem = new PriceWaiter_NYPWidget_Model_OfferItem(array(
            'currency' => 'USD',
        ));

        $item = new PriceWaiter_NYPWidget_Model_ResolvedItem($offerItem, $product);
        $this->assertSame(doubleval($expectedPrice), $item->getProductPrice());
    }

    public function provideProductsWithPrices()
    {
        return array_map(
            function($p) {

                $product = Mage::getModel('catalog/product')->load($p['id']);
                if (!$product) {
                    $this->assertFail("Product with id '{$p['id']}' not found.");
                }

                return array($product, $p['price']);
            },
            array($this->simpleProduct)
        );
    }
}
