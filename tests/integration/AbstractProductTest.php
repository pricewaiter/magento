<?php

/**
 * Base for implementing integration tests that use product data.
 *
 * Contains lots of hardcoded references to product IDs that appear
 * in the sample data set.
 */
abstract class Integration_AbstractProductTest extends PHPUnit_Framework_TestCase
{
    /**
     * Available order states (for reference).
     * @var array
     */
    public static $orderStates = array(
        Mage_Sales_Model_Order::STATE_NEW,
        Mage_Sales_Model_Order::STATE_PENDING_PAYMENT,
        Mage_Sales_Model_Order::STATE_PROCESSING,
        Mage_Sales_Model_Order::STATE_COMPLETE,
        Mage_Sales_Model_Order::STATE_CLOSED,
        Mage_Sales_Model_Order::STATE_CANCELED,
        Mage_Sales_Model_Order::STATE_HOLDED,
        Mage_Sales_Model_Order::STATE_PAYMENT_REVIEW,
    );

    protected $stockChangesToRevert = array();

    /**
     * @param  Mage_Catalog_Model_Product $product
     * @param  Array|Varien_Object        $addToCartForm
     * @param  string                     $spec
     * @return PriceWaiter_NYPWidget_Model_Deal
     */
    public function createDeal(Mage_Catalog_Model_Product $product, $addToCartForm, $spec = null)
    {
        // Allow createDeal($product, $spec)
        if (is_null($spec) && is_string($addToCartForm)) {
            $spec = $addToCartForm;
            $addToCartForm = array(
                'product' => $product->getId(),
            );
        }

        list($discount, $minQty, $maxQty) = $this->parseDealSpec($spec);
        $now = time();

        $amount = $product->getFinalPrice() - $discount;

        $request = new PriceWaiter_NYPWidget_Controller_Endpoint_Request(
            uniqid(),
            getenv('PRICEWAITER_API_KEY'),
            '2016-03-01',
            json_encode(
                array(
                    'id' => uniqid(),
                    'items' => array(
                        array(
                            'product' => array(
                                'sku' => $product->getSku(),
                            ),
                            'quantity' => array(
                                'min' => $minQty,
                                'max' => $maxQty,
                            ),
                            'amount_per_item' => array(
                                'cents' => floor($amount * 100),
                                'value' => number_format($amount, 2, '.', ''),
                            ),
                            'metadata' => array(
                                "_magento_product_configuration" => http_build_query($addToCartForm),
                            ),
                        )
                    ),
                    'coupon_code_prefix' => 'PW',
                    "buyer" => array(
                        "id" => uniqid(),
                        "email" => "user@example.org",
                        "marketing_opt_in" => false,
                        "location" => array(
                            "postal_code" => "98225",
                            "country" => "US",
                        ),
                    ),
                )
            ),
            $now
        );

        $deal = Mage::getModel('nypwidget/deal');
        $deal->processCreateRequest($request);

        return $deal;
    }

    /**
     * Takes a series of arguments and attempts to return a quote for you.
     *
     * Example usage:
     *
     *   // creates quote with 4 of $product:
     *   $this->createQuote($product, 4)
     *
     *   // Creates a quote with $product1 configured as in $addToCartForm1 and 1 of $product2:
     *   $this->createQuote($product1, $addToCartForm1, $product2);
     *
     * @return Mage_Sales_Model_Quote
     */
    public function createQuote(/* lots of args */)
    {
        $productsWithAddToCartForms = array();
        $storeId = Mage::app()->getStore()->getId();

        $product = null;

        foreach(func_get_args() as $arg) {

            if (is_numeric($arg)) {
                // interpret as a qty for prev product
                $arg = array('qty' => $arg);
            }

            if (is_array($arg)) {
                // Interpret as an add to cart form
                $arg = new Varien_Object($arg);
            }

            if (get_class($arg) === 'Varien_Object') {
                // Interpret as an add to cart form
                $productsWithAddToCartForms[] = array($product, $arg);
                $product = null;
                continue;
            }

            if ($product) {
                $productsWithAddToCartForms[] = array($product, null);
            }

            if ($arg instanceof Mage_Catalog_Model_Product) {
                // A product to add to our quote.
                // The next arg could be a quantity or an add to cart form,
                // so stash it for now.
                $product = $arg;
            }
        }

        if ($product) {
            $productsWithAddToCartForms[] = array($product, null);
            $product = null;
        }

        // Create our quote
        $quote = Mage::getModel('sales/quote')
            ->setStoreId($storeId);

        foreach($productsWithAddToCartForms as $p) {
            list($product, $addToCartForm) = $p;
            $quote->addProduct($product, $addToCartForm);
        }

        $quote->save();
        $this->assertGreaterThan(0, $quote->getId(), 'Quote was saved properly.');

        // HACK Calling collectTotals() won't work until the quote has addresses
        $quote->getBillingAddress();
        $quote->getShippingAddress();

        return $quote;
    }

    /**
     * Creates an order to work with.
     *
     * Arguments are the same as for createQuote(), with the addition that
     * you can specify an order state (e.g. Mage_Sales_Model_Order::STATE_PAYMENT_REVIEW)
     * and it will get picked up.
     *
     * @return Mage_Sales_Model_Order
     */
    public function createOrder(/* lots of potential args */)
    {
        $createQuoteArgs = array();
        $state = null;

        foreach(func_get_args() as $arg) {
            // HACK: Allow passing order state in
            if (in_array($arg, self::$orderStates)) {
                $state = $arg;
            } else {
                $createQuoteArgs[] = $arg;
            }
        }

        $quote = call_user_func_array(array($this, 'createQuote'), $createQuoteArgs);

        // Add shipping info
        $quote->getShippingAddress()
            ->setFirstname('Pat')
            ->setLastname('Customer')
            ->setStreet('1234 Fake St.')
            ->setCity('Bellingham')
            ->setRegionId('WA')
            ->setPostcode('98225')
            ->setTelephone('123 456 7890')
            ->setCountryId('US')
            ->setShippingMethod('freeshipping_free')
            ->addShippingRate(
                Mage::getModel('sales/quote_address_rate')
                ->setCode('freeshipping_free')
                ->setRate(0)
            )
            ;

        $quote->getBillingAddress()
            ->setFirstname('Pat')
            ->setLastname('Customer')
            ->setStreet('1234 Fake St.')
            ->setCity('Bellingham')
            ->setRegionId('WA')
            ->setPostcode('98225')
            ->setTelephone('123 456 7890')
            ->setCountryId('US')
            ;

        $payment = Mage::getModel('sales/quote_payment')
            ->setMethod('cashondelivery')
            ->setQuote($quote)
            ->save();

        $quote->setPayment($payment);
        $quote->collectTotals();
        $quote->save();

        $service = Mage::getModel('sales/service_quote', $quote);
        $order = $service->submitOrder();
        $this->assertNotEmpty($order, 'Order returned');
        $this->assertNotEmpty($order->getId(), 'Order saved');

        if ($state && $order->getState() !== $state) {
            $order->addStatusToHistory($state);
        }

        return $order;
    }

    /**
     * @return PriceWaiter_NYPWidget_Model_Deal Literally any deal.
     */
    public function getAnyDeal()
    {
        $product = $this->getSimpleProduct();
        return $this->createDeal($product, '$5 off 1');
    }

    /**
     * @return PriceWaiter_NYPWidget_Model_Offer_Item
     */
    public function getOfferItemForProduct(
        Mage_Catalog_Model_Product $product,
        $addToCartForm = array()
    )
    {
        $addToCartForm['product'] = $product->getId();

        return Mage::getModel('nypwidget/offer_item', array())
            ->withAddToCartForm($addToCartForm);
    }

    public function getBundleProduct($stock = 100, $qty = 1)
    {
        $id = '447'; // Pillow and Throw Set
        $addToCartForm = array(
            'product' => $id,
            'bundle_option' => array(
                '24' => '91', // Titian Raw Silk Pillow   +$125.00
                '23' => '89', // Park Row Throw   +$120.00
            ),
            'bundle_option_qty' => array(
                '24' => strval($qty),
                '23' => strval($qty),
            ),
        );

        $productForLookup = $this->getProduct($id, 'bundle');
        $type = $productForLookup->getTypeInstance();

        $children = $type->prepareForCart(new Varien_Object($addToCartForm));
        $bundleProduct = array_shift($children);

        $this->assertCount(2, $children, 'Expected # of child products found');
        $this->assertNotEmpty($bundleProduct, 'Bundle product found');

        if ($stock !== null) {
            foreach($children as $childProduct) {
                $this->setProductStock($childProduct, $stock);
            }
        }
        return array($bundleProduct, $addToCartForm, $children);
    }

    public function getConfigurableProduct($stock = 100)
    {
        $id = 404;
        $addToCartForm = array(
            'product' => "$id",
            'super_attribute' => array(
                '92' => '17', // Charcoal
                '180' => '80', // Small
            ),
        );
        $simpleProductId = 237;

        $product = $this->getProduct($id, 'configurable');
        $simpleProduct = $this->getProduct($simpleProductId, 'simple', $stock);

        return array($product, $addToCartForm, $simpleProduct);
    }

    public function getAlternateConfigurableProductVariant($stock = 100)
    {
        $id = 404;
        $addToCartForm = array(
            'product' => "$id",
            'super_attribute' => array(
                '92' => '28', // Red
                '180' => '78', // Large
            ),
        );
        $simpleProductId = 902;

        $product = $this->getProduct($id, 'configurable');
        $simpleProduct = $this->getProduct($simpleProductId, 'simple', $stock);

        return array($product, $addToCartForm, $simpleProduct);
    }

    /**
     * Returns a *different* configured configurable product.
     * @param  integer $stock
     * @return Mage_Catalog_Model_Product
     */
    public function getAlternateConfigurableProduct($stock = 100)
    {
        $id = 408;
        $addToCartForm = array(
            'product' => "$id",
            'super_attribute' => array(
                '92' => '22', // White
                '180' => '79', // Medium
            ),
        );
        $simpleProductId = 251;

        $product = $this->getProduct($id, 'configurable');
        $simpleProduct = $this->getProduct($simpleProductId, 'simple', $stock);

        return array($product, $addToCartForm, $simpleProduct);
    }

    public function getSimpleProduct($stock = 100)
    {
        return $this->getProduct(399, 'simple', $stock);
    }

    public function getAlternateSimpleProduct($stock = 100)
    {
        return $this->getProduct(395, 'simple', $stock);
    }

    /**
     * Returns one of the simple products that's included in
     * the bundle returned by getBundleProduct().
     * @param  integer $stock
     * @return Mage_Catalog_Model_Product
     */
    public function getSimpleProductThatIsPartOfBundle($stock = 100)
    {
        $id = 381; // Titian Raw Silk Pillow
        return $this->getProduct($id, 'simple', $stock);
    }

    public function getProduct($id, $expectedType, $stock = null)
    {
        $product = Mage::getModel('catalog/product');

        $product->load($id);

        $this->assertNotEmpty($product->getId(), "Product #{$id} found.");
        $this->assertEquals($expectedType, $product->getTypeId(), "Product #{$id} has expected type.");

        if ($stock !== null) {
            $this->setProductStock($product, $stock);
        }

        return $product;
    }

    /**
     * Takes a string like "$5 of 1 - 3" and parses out an amount,
     * min qty, and max qty.
     */
    protected function parseDealSpec($spec)
    {
        $pattern = '/^(?:(-|)\$(\d+)) off (\d+)(?:|\s*(?:-|to)\s*(\d+))$/i';
        if (!preg_match($pattern, $spec, $m)) {
            throw new Exception("Invalid deal spec: '$spec'");
        }

        $negative = !!$m[1];
        $discount = $m[2];
        $minQty = $m[3];
        $maxQty = empty($m[4]) ? $m[3] : $m[4];

        if ($negative) {
            $discount *= -1;
        }

        return array($discount, $minQty, $maxQty);
    }

    protected function setProductStock(
        Mage_Catalog_Model_Product $product,
        $qty
    )
    {
        $id = $product->getId();
        $stockItem = $product->getStockItem();

        // Record items' *current* stock so we can revert back on tearDown.
        if (empty($this->stockChangesToRevert[$id])) {
            $this->stockChangesToRevert[$id] = array(
                'qty' => $stockItem->getQty(),
                'is_in_stock' => $stockItem->getIsInStock(),
            );
        }

        $stockItem
            ->setQty($qty)
            ->setIsInStock($qty > 0)
            ->save();

    }

    public function tearDown()
    {
        foreach ($this->stockChangesToRevert as $productId => $og) {
            $product = Mage::getModel('catalog/product')
                ->load($productId);

            $product->getStockItem()
                ->setQty($og['qty'])
                ->setIsInStock($og['is_in_stock'])
                ->save();
        }
        $this->stockChangesToRevert = array();

        parent::tearDown();
    }
}
