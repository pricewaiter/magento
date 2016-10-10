<?php

class Integration_Models_QuoteTotalTest extends Integration_AbstractProductTest
{
    public function testTotalModelShowsUp()
    {
        // Test that, when running quote total collection, ours is:
        // 1. In the mix
        // 2. Considered *after* discount (we don't apply if discount did anything)

        $product = $this->getSimpleProduct();
        $quote = $this->createQuote($product);

        $addr = $quote->getShippingAddress();
        $models = $addr->getTotalModels();

        $found = false;
        $discountFound = false;
        foreach($models as $model) {
            if ($model instanceof PriceWaiter_NYPWidget_Model_Total_Quote) {
                $found = true;
            } else if ($model->getCode() === 'discount') {
                $discountFound = true;
                $this->assertFalse($found, 'Discount found *after* our total model');
            }
        }

        $this->assertTrue($found, 'Our total model found in list');
    }

    public function testSimpleProductCollect()
    {
        $simpleProduct = $this->getSimpleProduct(100);
        $otherSimpleProduct = $this->getAlternateSimpleProduct(100);

        // Create a deal (on the first simple product)
        $deal = $this->createDeal($simpleProduct, '$5 off 1 - 4');

        // Create a quote and add some stuff to it.
        $quote = $this->createQuote(
            $simpleProduct,
            $otherSimpleProduct
        );

        $this->collectQuote($quote, $deal);

        $addr = $quote->getShippingAddress();
        list($dealItem, $nonDealItem) = $addr->getAllItems();

        $this->assertEquals(5, $dealItem->getDiscountAmount());
        $this->assertEmpty($nonDealItem->getDiscountAmount(), 'non deal item has no discount applied');

        $this->assertEquals(-5, $addr->getDiscountAmount());
        $this->assertEquals(-5, $addr->getBaseDiscountAmount());

        // We should find deal_usage records for this
        $res = Mage::getResourceModel('nypwidget/deal_usage');
        $usages = $res->getQuoteIdsUsingDeal($deal);

        $this->assertEquals(
            array($quote->getId()),
            $usages,
            'Deal usage on quote recorded'
        );

        return array(
            $deal,
            $quote,
        );
    }

    /**
     * @depends testSimpleProductCollect
     */
    public function testSimpleProductFetch($args)
    {
        list(
            $deal,
            $quote,
        ) = $args;

        PriceWaiter_NYPWidget_Model_Total_Quote::hackilySetDealsForTesting($deal);
        $shippingAddr = $quote->getShippingAddress();
        $totals = $shippingAddr->getTotals();
        PriceWaiter_NYPWidget_Model_Total_Quote::hackilySetDealsForTesting(null);

        $pwTotal = null;

        foreach($totals as $t) {
            if ($t->getCode() === 'pricewaiter') {
                $this->assertNull($pwTotal, 'only 1 pricewaiter total line present');
                $pwTotal = $t;
            }
        }

        $this->assertNotNull($pwTotal, 'PriceWaiter total line found');
        $this->assertEquals(-5, $pwTotal->getValue(), 'value correct on pw total');
    }


    public function testSimpleProductDoesNotApplyWhenNotFound()
    {
        $simpleProduct = $this->getSimpleProduct(100);
        $otherSimpleProduct = $this->getAlternateSimpleProduct(100);

        // Create a quote *not* including our deal product
        $quote = $this->createQuote($otherSimpleProduct);
        $deal = $this->createDeal($simpleProduct, '$5 off 1 - 4');

        $this->collectQuote($quote, $deal);

        // Check that deal is not applied to quote
        $addr = $quote->getShippingAddress();
        list($quoteItem) = $addr->getAllItems();;

        $this->assertEmpty($quoteItem->getDiscountAmount(), 'non deal item has no discount applied');
        $this->assertEmpty($addr->getDiscountAmount(), 'address has no discount');
        $this->assertEmpty($quote->getDiscountAmount(), 'quote has no discount');
    }


    public function testConfigurableProductCollect()
    {
        list($configurableProduct, $addToCartForm, $simpleProduct) = $this->getConfigurableProduct(100);

        $deal = $this->createDeal($configurableProduct, $addToCartForm, '$5 off 1 - 4');

        // Create a quote w/ product on it
        $quote = $this->createQuote(
            $configurableProduct, new Varien_Object($addToCartForm)
        );

        $this->collectQuote($quote, $deal);

        $addr = $quote->getShippingAddress();
        list($parentQuoteItem, $childQuoteItem) = $addr->getAllItems();


        $this->assertEquals(5, $parentQuoteItem->getDiscountAmount(), 'parent quote item has discount applied');
        $this->assertEmpty($childQuoteItem->getDiscountAmount(), 'child quote item has no discount applied');
    }

    public function testBundleProductCollect()
    {
        list($bundleProduct, $addToCartForm, $childProducts) = $this->getBundleProduct(100, 2);

        $deal = $this->createDeal($bundleProduct, $addToCartForm, '$5 off 1 - 4');

        $bundleProductForAdd = Mage::getModel('catalog/product')
            ->load($bundleProduct->getId());

        $quote = $this->createQuote(
            $bundleProductForAdd, new Varien_Object($addToCartForm)
        );

        $this->collectQuote($quote, $deal);

        $addr = $quote->getShippingAddress();
        $quoteItems = $addr->getAllItems();
        $parentQuoteItem = array_shift($quoteItems);

        $this->assertEmpty($parentQuoteItem->getParentId(), 'Quote item we think is parent actually is');
        $this->assertCount(count($childProducts), $quoteItems, 'Quote has expected # of items');

        // Discount should be applied to the *parent* quote item
        $this->assertEquals(5, $parentQuoteItem->getDiscountAmount());

        // And all child items should have no discount
        foreach($quoteItems as $childQuoteItem) {
            $this->assertEmpty($childQuoteItem->getDiscountAmount());
        }
    }

    public function testDoesNotApplyWithCouponCode()
    {
        $product = $this->getSimpleProduct();
        $deal = $this->createDeal($product, '$5 off 1');

        $quote = $this->createQuote($product);
        $quote->setCouponCode('SOME_COUPON_CODE')->save();

        $this->collectQuote($quote, $deal);

        $addr = $quote->getShippingAddress();
        list($quoteItem) = $addr->getAllItems();

        $this->assertEmpty($quoteItem->getDiscountAmount(), 'No discount applied to item');
        $this->assertEmpty($addr->getDiscountAmount(), 'no discount applied to address');
        $this->assertEmpty($quote->getDiscountAmount(), 'no discount applied to quote');
    }

    public function testOnlyAppliesMostRecentDeal()
    {
        // This situation *shouldn't* arise in the wild, but is not technically impossible.
        // When the customer has 2 active deals referring to the same product,
        // we expect that the *most recent* deal will be preferred.

        $simpleProduct = $this->getSimpleProduct();

        $deal1 = $this->createDeal($simpleProduct, '$5 off 1 - 4');
        $deal1->setCreatedAt('2016-09-21 12:12:12');
        $deal1->save();

        $deal2 = $this->createDeal($simpleProduct, '$10 off 1 - 4');
        $deal2->setCreatedAt('2016-09-21 13:13:13');
        $deal2->save();

        $quote = $this->createQuote($simpleProduct, 3);

        $this->collectQuote($quote, $deal1, $deal2);
        list($quoteItem) = $quote->getAllItems();

        $this->assertEquals(30, $quoteItem->getDiscountAmount(), 'Correct discount on quote item');
    }

    public function testDoesNotCollectWhenQuoteHasSalesRulesApplied()
    {
        $product = $this->getSimpleProduct();
        $deal = $this->createDeal($product, '$5 off 1');

        $quote = $this->createQuote($product);
        $quote->setAppliedRuleIds('1,2,3,4')->save();
        $quote->collectTotals();

        $addr = $quote->getShippingAddress();

        $totals = Mage::getModel('nypwidget/total_quote')
            ->setPriceWaiterDeals(array($deal))
            ->collect($addr);

        $this->assertEquals(0, $addr->getDiscountAmount());
    }

    public function testStillAppliesOtherDealWhenFirstOneIsNoGood()
    {
        $product = $this->getSimpleProduct();
        $badDeal = $this->createDeal($product, '-$7 off 1');

        $otherProduct = $this->getAlternateSimpleProduct();
        $goodDeal = $this->createDeal($otherProduct, '$5 off 1');

        $quote = $this->createQuote($product, $otherProduct);

        $this->collectQuote(
            $quote,
            $badDeal,
            $goodDeal
        );

        $addr = $quote->getShippingAddress();
        $this->assertEquals(-5, $addr->getDiscountAmount(), 'second deal still applied');
    }

    /**
     * Runs collectTotals() with certain PW deals applied.
     * @param  Mage_Sales_Model_Quote $quote
     */
    public function collectQuote(
        Mage_Sales_Model_Quote $quote
        /* ... $deals **/
    )
    {
        $deals = func_get_args();
        array_shift($deals);

        PriceWaiter_NYPWidget_Model_Total_Quote::hackilySetDealsForTesting($deals);
        $quote->collectTotals();
        PriceWaiter_NYPWidget_Model_Total_Quote::hackilySetDealsForTesting(null);
    }
}
