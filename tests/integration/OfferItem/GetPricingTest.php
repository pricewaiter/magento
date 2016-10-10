<?php

class Integration_OfferItem_GetPricingTest
    extends Integration_AbstractProductTest
{
    public function testSimpleProduct()
    {
        $product = $this->getSimpleProduct();
        $pricing = $this->getPricing($product);

        $tests = array(
            'getCurrencyCode' => 'USD',
            'getRetailPrice' => 150,
            'getCost' => false,
            'getMsrp' => false,
            'getRegularPrice' => false,
        );

        foreach($tests as $getter => $expected) {
            $this->assertEquals($expected, $pricing->$getter(), "$getter() returns correct value");
        }
    }

    public function testSimpleProductWithCostAttribute()
    {
        // TODO Dataset does not include anything w/ cost set.
        //      Need to set it ourselves.
        $this->markTestIncomplete();
    }

    public function testSimpleProductWithMsrpAttribute()
    {
        $id = '393'; // Madison RX3400
        $product = $this->getProduct($id, 'simple');

        $pricing = $this->getPricing($product);
        $this->assertEquals(815, $pricing->getMsrp(), 'MSRP found');
    }

    public function testSimpleProductWithSpecialPriceApplied()
    {
        $id = '338'; // Jackie O Round Sunglasses
        $product = $this->getProduct($id, 'simple');

        $pricing = $this->getPricing($product);

        $tests = array(
            'getCurrencyCode' => 'USD',
            'getRetailPrice' => 225,
            'getCost' => false,
            'getMsrp' => false,
            'getRegularPrice' => 295,
        );

        foreach($tests as $getter => $expected) {
            $this->assertEquals($expected, $pricing->$getter(), "$getter() returns correct value");
        }
    }

    public function testBundleProduct()
    {
        list($product, $addToCartForm, $childProducts) = $this->getBundleProduct(100, 3);
        $pricing = $this->getPricing($product, $addToCartForm);

        $tests = array(
            'getCurrencyCode' => 'USD',
            'getRetailPrice' => '660',
            'getCost' => false,
            'getMsrp' => false,
            'getRegularPrice' => '1095',
        );

        foreach($tests as $getter => $expected) {
            $this->assertEquals($expected, $pricing->$getter(), "$getter() returns correct value");
        }
    }

    public function testConfigurableProduct()
    {
        list($product, $addToCartForm, $childProduct) = $this->getConfigurableProduct();
        $pricing = $this->getPricing($product, $addToCartForm);

        $tests = array(
            'getCurrencyCode' => 'USD',
            'getRetailPrice' => 160,
            'getMsrp' => false,
            'getRegularPrice' => false,
        );

        foreach($tests as $getter => $expected) {
            $this->assertEquals($expected, $pricing->$getter(), "$getter() returns correct value");
        }
    }

    public function getPricing(Mage_Catalog_Model_Product $product, $addToCartForm = array())
    {
        $addToCartForm['product'] = $product->getId();

        $offerItem = Mage::getModel('nypwidget/offer_item', array())
            ->withAddToCartForm($addToCartForm);

        return $offerItem->getPricing();
    }
}
