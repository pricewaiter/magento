<?php

/**
 * Tests around locating the item in a quote that corresponds to an item in
 * a PriceWaiter offer.
 */
class Integration_OfferItem_FindQuoteItemTest extends Integration_AbstractProductTest
{
    public function testSimpleProductFindQuoteItem()
    {
        $product = $this->getSimpleProduct();
        $otherProduct = $this->getAlternateSimpleProduct();

        $offerItem = new PriceWaiter_NYPWidget_Model_Offer_Item(array(
            'quantity' => array(
                'min' => 1,
                'max' => 2,
            ),
            'metadata' => array(
                '_magento_product_configuration' => http_build_query(array(
                    'product' => strval($product->getId()),
                    // Red herring--we disregard Magento cart quantity in favor
                    // of the quantity requested during the Offer process.
                    'qty' => 10,
                )),
            ),
        ));

        $quote = Mage::getModel('sales/quote');
        $quote->addProduct($product);
        $quote->addProduct($otherProduct);

        $quoteItems = $quote->getAllItems();
        $quoteItem = $offerItem->findQuoteItem($quoteItems);

        $this->assertNotEmpty($quoteItem, 'Quote item found');
        $this->assertEquals(
            $product->getId(),
            $quoteItem->getProductId(),
            'Product id is correct'
        );
    }

    public function testConfigurableProductFindQuoteItem()
    {
        list($product, $addToCartForm, $simpleProduct) = $this->getConfigurableProduct();
        list($otherProduct, $otherAddToCartForm) = $this->getAlternateConfigurableProduct();

        $offerItem = new PriceWaiter_NYPWidget_Model_Offer_Item(array(
            'quantity' => array(
                'min' => 1,
                'max' => 2,
            ),
            'metadata' => array(
                '_magento_product_configuration' => http_build_query(array_merge(
                    $addToCartForm,
                    array(
                        'product' => strval($product->getId()),
                        // Red herring--we disregard Magento cart quantity in favor
                        // of the quantity requested during the Offer process.
                        'qty' => 10,
                    )
                )),
            ),
        ));

        $quote = Mage::getModel('sales/quote');
        $quote->addProduct($product, new Varien_Object(array_merge($addToCartForm, array(
            'qty' => 1,
        ))));
        $quote->addProduct($otherProduct, new Varien_Object(array_merge($addToCartForm, array(
            'qty' => 1,
        ))));
        $quoteItems = $quote->getAllItems();

        $quoteItem = $offerItem->findQuoteItem($quoteItems);

        $this->assertNotEmpty($quoteItem, 'Quote item found');
        $this->assertEquals(
            $product->getId(),
            $quoteItem->getProductId(),
            'Discount to be applied to parent quote item'
        );

        $children = $quoteItem->getChildren();
        $this->assertCount(1, $children, 'Child quote item present');
        $this->assertEquals($simpleProduct->getId(), $children[0]->getProductId());
    }

    /**
     * Tests that we don't find quote items for other variants of the same
     * configurable product.
     */
    public function testConfigurableProductFindQuoteItemForDifferentVariant()
    {
        list ($product, $addToCartForm) = $this->getConfigurableProduct();
        list ($altProduct, $altAddToCartForm) = $this->getAlternateConfigurableProductVariant();

        // Add one product to the quote...
        $quote = $this->createQuote($product, $addToCartForm);
        $quoteItems = $quote->getAllItems();
        $this->assertCount(2, $quoteItems, 'Quote has expected # of items');

        // ...and a different variant to the offer item
        $offerItem = $this->createOfferItem(
            $altProduct,
            $altAddToCartForm
        );

        $found = $offerItem->findQuoteItem($quoteItems);

        $this->assertFalse(!!$found, 'Should not have found a quote item.');
    }

    public function testBundleProductFindQuoteItem()
    {
        list($product, $addToCartForm, $childProducts) = $this->getBundleProduct(100, 2);
        $otherProduct = $this->getSimpleProduct();

        $offerItem = new PriceWaiter_NYPWidget_Model_Offer_Item(array(
            'quantity' => array(
                'min' => 1,
                'max' => 2,
            ),
            'metadata' => array(
                '_magento_product_configuration' => http_build_query(array_merge(
                    $addToCartForm,
                    array(
                        'product' => strval($product->getId()),
                    )
                )),
            ),
        ));

        // Create a quote with bundle product + something else in it
        $quote = Mage::getModel('sales/quote');
        $quote->addProduct($otherProduct);
        $quote->addProduct($product, new Varien_Object($addToCartForm));

        $quoteItems = $quote->getAllItems();
        $quoteItem = $offerItem->findQuoteItem($quoteItems);

        $this->assertNotEmpty($quoteItem, 'Quote item found');
        $this->assertEquals($product->getId(), $quoteItem->getProductId(), 'Quote item has bundle product id');

        $childQuoteItems = $quoteItem->getChildren();

        $this->assertGreaterThan(0, count($childQuoteItems), 'Quote item has *any* children');
        $this->assertCount(count($childProducts), $childQuoteItems, 'Quote item has correct # of child items');

        foreach($childQuoteItems as $childItem) {
            $this->assertEquals(2, $childItem->getQty(), 'Child item has correct quantity');
        }
    }

    public function testBundleProductFindQuoteItemRespectsChildQuantities()
    {
        list($product, $addToCartForm, $childProducts) = $this->getBundleProduct(100, 2);
        $otherProduct = $this->getSimpleProduct();

        $offerItem = new PriceWaiter_NYPWidget_Model_Offer_Item(array(
            'quantity' => array(
                'min' => 1,
                'max' => 2,
            ),
            'metadata' => array(
                '_magento_product_configuration' => http_build_query(array_merge(
                    $addToCartForm,
                    array(
                        'product' => strval($product->getId()),
                    )
                )),
            ),
        ));

        // Modify quantities of child products
        $addToCartFormWithDifferentQty = $addToCartForm;
        foreach($addToCartFormWithDifferentQty['bundle_option_qty'] as $id => $qty) {
            $addToCartFormWithDifferentQty['bundle_option_qty'][$id] = $qty + 1;
        }

        $quote = Mage::getModel('sales/quote');
        $quote->addProduct($otherProduct);
        $quote->addProduct($product, new Varien_Object($addToCartFormWithDifferentQty));

        $quoteItems = $quote->getAllItems();
        $quoteItem = $offerItem->findQuoteItem($quoteItems);

        $this->assertFalse($quoteItem, 'Quote item not found when quantities differ');
    }

    public function testFindQuoteItemIgnoresSimpleProductInBundle()
    {
        // If a cart looks like this:
        //
        //   - Bundle product
        //     - Child product A
        //     - Child product B
        //   - Child product A (as non-bundle)
        //
        //   And we have a deal for "Child product a", it should *not* apply to
        //   child product a that's part of a bundle.

        list($bundleProduct, $addToCartForm, $childProducts) = $this->getBundleProduct();
        $simpleProduct = $this->getSimpleProductThatIsPartOfBundle();

        $offerItem = new PriceWaiter_NYPWidget_Model_Offer_Item(array(
            'quantity' => array(
                'min' => 1,
                'max' => 2,
            ),
            'metadata' => array(
                '_magento_product_configuration' => http_build_query(array(
                    'product' => strval($simpleProduct->getId()),
                )),
            ),
        ));

        // First, try on a quote with *only* a bundle product in it
        $quote = $this->createQuote(
            $bundleProduct, $addToCartForm
        );
        $quoteItems = $quote->getAllItems();
        $quoteItem = $offerItem->findQuoteItem($quoteItems);

        $this->assertFalse(!!$quoteItem, 'No quote item found when quote only contains bundle product');

        // NOTE: Have to reload these because Magento
        list($bundleProduct, $addToCartForm, $childProducts) = $this->getBundleProduct();

        // // Next, try with a quote with a bundle *and* the simple product
        $quote = $this->createQuote(
            $bundleProduct, $addToCartForm,
            $simpleProduct
        );
        $quoteItems = $quote->getAllItems();
        $quoteItem = $offerItem->findQuoteItem($quoteItems);

        $this->assertTrue(!!$quoteItem, 'Quote item found when quote contains bundle product + simple product');
        $this->assertEmpty($quoteItem->getParentItemId(), 'Quote item found is not a child item');
    }

    protected function createOfferItem(Mage_Catalog_Model_Product $product, $addToCartForm = null, $qty = 1)
    {
        if (!$addToCartForm) {
            $addToCartForm = array();
        } else if ($addToCartForm instanceof Varien_Object) {
            $addToCartForm = $addToCartForm->toArray();
        }

        if (empty($addToCartForm['product'])) {
            $addToCartForm['product'] = $product->getId();
        }

        $offerItem = new PriceWaiter_NYPWidget_Model_Offer_Item(array(
            'quantity' => array(
                'min' => $qty,
                'max' => $qty,
            ),
            'metadata' => array(
                '_magento_product_configuration' => http_build_query($addToCartForm),
            ),
        ));

        return $offerItem;
    }
}
