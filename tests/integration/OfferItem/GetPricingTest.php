<?php

class Integration_OfferItem_GetPricingTest
    extends Integration_AbstractProductTest
{
    public function provideAddToCartVariants()
    {
        return array(
            array('default', array()),
            array('qty > 1', array('qty' => 3)),
        );
    }

    /**
     * @dataProvider provideAddToCartVariants
     */
    public function testSimpleProduct($desc, $addToCartOverrides)
    {
        $product = $this->getSimpleProduct();
        $pricing = $this->getPricing($product, $addToCartOverrides);

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

    public function testSimpleProductWithQuantityDiscounts()
    {
        // This product has different pricing based on quantity.
        // We currently don't send offer qty to productinfo endpoint, so
        // we *have* to base our idea of "retail price" on qty = 1.

        $id = '381'; // Titian Raw Silk Pillow
        $product = $this->getProduct($id, 'simple');

        $pricing = $this->getPricing($product, array(
            'qty' => 3
        ));

        $tests = array(
            'getCurrencyCode' => 'USD',
            'getRetailPrice' => '125',
            'getCost' => false,
            'getMsrp' => false,
            'getRegularPrice' => false,
        );

        foreach($tests as $getter => $expected) {
            $this->assertEquals($expected, $pricing->$getter(), "$getter() returns correct value");
        }
    }

    /**
     * @dataProvider provideAddToCartVariants
     */
    public function testBundleProduct($desc, $addToCartOverrides)
    {
        // 1. get 1 of each, check price
        list($product, $addToCartForm, $childProducts) = $this->getBundleProduct(100, 1);
        $pricing = $this->getPricing($product, $addToCartForm, $addToCartOverrides);

        $tests = array(
            'getCurrencyCode' => 'USD',
            'getRetailPrice' => '245',
            'getCost' => false,
            'getMsrp' => false,
            'getRegularPrice' => '365',
        );

        foreach($tests as $getter => $expected) {
            $this->assertEquals($expected, $pricing->$getter(), "$getter() returns correct value when buying bundles of 1");
        }


        // 2. get 3 of each, check price
        list($product, $addToCartForm, $childProducts) = $this->getBundleProduct(100, 3);
        $pricing = $this->getPricing($product, $addToCartForm, $addToCartOverrides);

        $tests = array(
            'getCurrencyCode' => 'USD',
            'getRetailPrice' => '660',
            'getCost' => false,
            'getMsrp' => false,
            'getRegularPrice' => '1095',
        );

        foreach($tests as $getter => $expected) {
            $this->assertEquals($expected, $pricing->$getter(), "$getter() returns correct value for bundles of 3");
        }
    }

    /**
     * @dataProvider provideAddToCartVariants
     */
    public function testConfigurableProduct($desc, $addToCartOverrides)
    {
        list($product, $addToCartForm, $childProduct) = $this->getConfigurableProduct();
        $pricing = $this->getPricing($product, $addToCartForm, $addToCartOverrides);

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

    public function getPricing(Mage_Catalog_Model_Product $product, $addToCartForm = array(), $addToCartOverrides = array())
    {
        $addToCartForm = array_merge($addToCartForm, $addToCartOverrides);
        $addToCartForm['product'] = $product->getId();

        $offerItem = Mage::getModel('nypwidget/offer_item', array())
            ->withAddToCartForm($addToCartForm);

        return $offerItem->getPricing();
    }
}
