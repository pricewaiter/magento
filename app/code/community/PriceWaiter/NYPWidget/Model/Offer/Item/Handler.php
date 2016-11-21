<?php

/**
 * Class responsible for actually touching Magento products to do things
 * for PriceWaiter offers. Here be dragons.
 */
class PriceWaiter_NYPWidget_Model_Offer_Item_Handler
{
    /**
     * Adds the given product to the given quote.
     * @param Mage_Sales_Model_Quote     $quote
     * @param Mage_Catalog_Model_Product $product
     * @param Varien_Object              $addToCartForm
     * @param Integer                    $qty
     * @return Mage_Sales_Model_Quote_Item
     */
    public function addProductToQuote(
        Mage_Sales_Model_Quote $quote,
        Mage_Catalog_Model_Product $product,
        Varien_Object $addToCartForm,
        $qty
    )
    {
        $addToCartForm = clone($addToCartForm);
        $addToCartForm->setQty($qty);

        return $quote->addProduct($product, $addToCartForm);
    }

    /**
     * @param  Mage_Catalog_Model_Product $product
     * @param  Varien_Object              $addToCartForm
     * @param  array                      $quoteItems
     * @return Mage_Sales_Model_Quote_Item|false
     */
    public function findQuoteItem(
        Mage_Catalog_Model_Product $product,
        Varien_Object $addToCartForm,
        array $quoteItems
    )
    {
        $products = $this->getConfiguredProducts($product, $addToCartForm);
        list($parent, $children) = $this->splitParentAndChildProducts($products);

        foreach ($quoteItems as $quoteItem) {
            $matches = $this->quoteItemMatches(
                $quoteItem,
                $parent,
                $children
            );

            if ($matches) {
                return $quoteItem;
            }
        }

        return false;
    }

    /**
     * Returns a structure describing the inventory tracking for this product.
     * @param  Mage_Catalog_Model_Product $product
     * @param  Varien_Object              $addToCartForm
     * @return PriceWaiter_NYPWidget_Model_Offer_Item_Inventory
     */
    public function getInventory(
        Mage_Catalog_Model_Product $product,
        Varien_Object $addToCartForm
    )
    {
        $products = $this->getConfiguredProducts($product, $addToCartForm);
        return Mage::getModel('nypwidget/offer_item_inventory', $products);
    }

    /**
     * @return PriceWaiter_NYPWidget_Model_Offer_Item_Pricing
     */
    public function getPricing(
        Mage_Catalog_Model_Product $product,
        Varien_Object $addToCartForm
    )
    {
        $products = $this->getConfiguredProducts($product, $addToCartForm);

        $pricing = Mage::getModel(
            'nypwidget/offer_item_pricing',
            $products
        );

        return $pricing;
    }

    /**
     * Returns a set of *configured* products for the given parent product / cart data combo.
     * @param  Mage_Catalog_Model_Product $product
     * @param  Varien_Object              $cart
     * @return array
     */
    public function getConfiguredProducts(Mage_Catalog_Model_Product $product, Varien_Object $addToCartForm)
    {
        $type = $product->getTypeInstance();
        $products = $type->prepareForCart($addToCartForm);

        if (is_string($products)) {
            // Magento communicates "add to cart" errors by returning a string here.
            // Most likely, $addToCartForm does not contain data for all required
            // product options.
            $id = $product->getId();

            throw new PriceWaiter_NYPWidget_Exception_Product_Invalid(
                "Error preparing product (id: {$id}): {$products}"
            );
        }

        return $products;
    }

    /**
     * @param  Mage_Catalog_Model_Product $product
     * @return Array a simple array of [custom option id => value] for $product
     */
    protected function buildCustomOptionArray(Mage_Catalog_Model_Product $product)
    {
        $result = array();

        /** @var Mage_Catalog_Model_Product_Option $opt */
        foreach($product->getOptions() as $opt) {
            $code = 'option_' . $opt->getId();
            $customOption = $product->getCustomOption($code);

            if (!$customOption) {
                continue;
            }

            $result[$opt->getId()] = $customOption->getValue();
        }

        return $result;
    }

    /**
     * Tests that the products referred to by $childQuoteItems exactly
     * matches the products in $childProducts.
     * Used for matching quote items for non-simple products that
     * exploit quote item hierarchy.
     * @param  array  $childQuoteItems
     * @param  array  $childProducts
     * @return boolean
     */
    protected function childQuoteItemsMatchChildProducts(
        array $childQuoteItems,
        array $childProducts
    )
    {
        if (count($childQuoteItems) !== count($childProducts)) {
            return false;
        }

        // Compare the product ids / quantities of the given quote items
        // with the product ids / quantities of the products.
        // This additional quantity check prevents matching quote items
        // for bundle products where the same products are used but
        // quantities differ.

        $productIds = array();
        foreach ($childProducts as $product) {
            $id = strval($product->getId());
            $productIds[$id] = strval($product->getCartQty());
        }

        $quoteItemProductIds = array();
        foreach ($childQuoteItems as $quoteItem) {
            $id = strval($quoteItem->getProductId());
            $quoteItemProductIds[$id] = strval($quoteItem->getQty());
        }

        $diff = array_diff_assoc($productIds, $quoteItemProductIds);

        return empty($diff);
    }

    protected function productCustomOptionsMatch(
        Mage_Catalog_Model_Product $productA,
        Mage_Catalog_Model_Product $productB
    )
    {
        $customOptionsA = $this->buildCustomOptionArray($productA);
        $customOptionsB = $this->buildCustomOptionArray($productB);

        if (count($customOptionsA) !== count($customOptionsB)) {
            return false;
        }

        $diff = array_diff_assoc($customOptionsA, $customOptionsB);
        return empty($diff);
    }

    /**
     * @param  Mage_Sales_Model_Quote_Item $quoteItem
     * @param  Mage_Catalog_Model_Product  $parent   Parent product
     * @param  array                       $children Child products
     * @return Boolean
     */
    protected function quoteItemMatches(
        Mage_Sales_Model_Quote_Item $quoteItem,
        Mage_Catalog_Model_Product $parent,
        array $children
    )
    {
        // We only match against parent quote items.
        // This prevents things like having PW deals apply to
        // products *inside* bundles.
        $isParent = !$quoteItem->getParentItemId();
        if (!$isParent) {
            return false;
        }

        $isSameProduct = $quoteItem->getProductId() == $parent->getId();
        if (!$isSameProduct) {
            return false;
        }

        $customOptionsMatch = $this->productCustomOptionsMatch(
            $parent,
            $quoteItem->getProduct()
        );

        if (!$customOptionsMatch) {
            return false;
        }

        // Ok, This *parent* quote item matches well enough.
        // But we also need to verify that any children of this quote item
        // match the incoming child products (this is for configurable +
        // bundle support).

        $childQuoteItems = $quoteItem->getChildren();
        return $this->childQuoteItemsMatchChildProducts($childQuoteItems, $children);
    }

    /**
     * Splits an array of products into a single parent product and 0 or more
     * child products.
     * @param  array  $products
     * @return array
     */
    protected function splitParentAndChildProducts(array $products)
    {
        $parent = null;
        $children = array();

        /** @var Mage_Catalog_Model_Product $product */
        foreach($products as $product) {
            if ($product->getParentProductId()) {
                $children[] = $product;
                continue;
            }
            $parent = $product;
        }

        return array($parent, $children);
    }
}
