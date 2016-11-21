<?php

/**
 * Tests around adding things from your PriceWaiter offer to your Magento cart.
 */
class Integration_OfferItem_EnsureInQuoteTest extends Integration_AbstractProductTest
{
    public function testSimpleProductNotInQuote()
    {
        $product = $this->getSimpleProduct(100);

        $offerItem = Mage::getModel('nypwidget/offer_item', array(
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

        $quote = Mage::getModel('sales/quote')
            ->setStoreId($offerItem->getStoreId())
            ->save();
        $this->assertGreaterThan(0, $quote->getId(), 'Quote was saved');
        $this->assertCount(0, $quote->getAllItems(), 'Nothing in quote initially');

        $offerItem->ensurePresentInQuote($quote);
        $quoteItems = $quote->getAllItems();
        $this->assertCount(1, $quoteItems, 'Item now present in quote');

        $this->assertEquals($product->getId(), $quoteItems[0]->getProductId(), 'Correct product referenced in quote');
        $this->assertEquals(2, $quoteItems[0]->getQty(), 'Max quantity added by default');
    }

    public function testSimpleProductAlreadyInQuote()
    {
        $product = $this->getSimpleProduct(100);

        $offerItem = Mage::getModel('nypwidget/offer_item', array(
            'quantity' => array(
                'min' => 8,
                'max' => 12,
            ),
            'metadata' => array(
                '_magento_product_configuration' => http_build_query(array(
                    'product' => strval($product->getId()),
                    'qty' => 10, // Ignored.
                )),
            ),
        ));

        $quote = Mage::getModel('sales/quote')
            ->setStoreId($offerItem->getStoreId())
            ->save();
        $this->assertGreaterThan(0, $quote->getId(), 'Quote was saved');
        $this->assertCount(0, $quote->getAllItems(), 'Nothing in quote initially');

        $existingItem = $quote->addProduct($product, new Varien_Object(array(
            'qty' => 1,
        )));
        $this->assertEquals(1, $existingItem->getQty(), 'Existing quantity is what we expect');

        $allItems = $quote->getAllItems();
        $this->assertCount(1, $allItems, 'Expected # of items in quote initially');

        $offerItem->ensurePresentInQuote($quote);

        $this->assertEquals(8, $existingItem->getQty(), 'Existing item quantity modified');

        $allItems = $quote->getAllItems();
        $this->assertCount(1, $allItems, 'Still only 1 item in quote');
    }

    /**
     * @expectedException PriceWaiter_NYPWidget_Exception_Product_OutOfStock
     */
    public function testSimpleProductOutOfStock()
    {
        $product = $this->getSimpleProduct(0);

        $offerItem = Mage::getModel('nypwidget/offer_item', array(
            'quantity' => array(
                'min' => 1,
                'max' => 1,
            ),
            'metadata' => array(
                '_magento_product_configuration' => http_build_query(array(
                    'product' => strval($product->getId()),
                    'qty' => 10, // Ignored.
                )),
            ),
        ));

        $quote = Mage::getModel('sales/quote')
            ->setStoreId($offerItem->getStoreId())
            ->save();
        $this->assertGreaterThan(0, $quote->getId(), 'Quote was saved');
        $this->assertCount(0, $quote->getAllItems(), 'Nothing in quote initially');

        $offerItem->ensurePresentInQuote($quote);
    }
}
