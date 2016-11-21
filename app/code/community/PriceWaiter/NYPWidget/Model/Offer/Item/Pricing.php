<?php

/**
 * Class that adapts how Magento thinks about prices to how PriceWaiter does.
 */
class PriceWaiter_NYPWidget_Model_Offer_Item_Pricing
{
    protected $_products;

    public function __construct(array $products)
    {
        $this->_products = $products;
    }

    public function getCost()
    {
        return $this->calculatePrice(
            $this->getProductsForOtherPriceFields(),
            'getCostForProduct'
        );
    }

    /**
     * @return Mage_Directory_Model_Currency
     */
    public function getCurrency()
    {
        foreach($this->_products as $product) {
            $store = Mage::app()->getStore($product->getStoreId());
            return $store->getDefaultCurrency();
        }

        throw new RuntimeException("Cannot determine currency.");
    }

    /**
     * @return string 3-character currency code.
     */
    public function getCurrencyCode()
    {
        $currency = $this->getCurrency();
        return $currency->getCode();
    }

    /**
     * @return double|false Manufacturer's suggested retail price.
     */
    public function getMsrp()
    {
        return $this->calculatePrice(
            $this->getProductsForOtherPriceFields(),
            'getMsrpForProduct'
        );
    }

    /**
     * @return  double|false The regular or "compare at" price, if known.
     */
    public function getRegularPrice()
    {
        $regular = $this->calculatePrice(
            $this->getProductsForOtherPriceFields(),
            'getRegularPriceForProduct'
        );

        if ($regular === false) {
            return false;
        }

        // ONly return regular price if it is greater than than retail
        $retail = $this->getRetailPrice();
        if ($retail === false) {
            // No retail = can't figure out regular price
            return false;
        }

        // Only return "regular price" if it 0.01 or greater than retail
        $diff = $regular - $retail;
        if ($diff > 0.01) {
            return $regular;
        }

        return false;
    }

    /**
     * @return double|false The current full retail price of the product(s).
     */
    public function getRetailPrice()
    {
        return $this->calculatePrice(
            $this->getProductsForRetailPrice(),
            'getRetailPriceForProduct'
        );
    }

    /**
     * @internal  Aggregates the value returned by $getter for all products considered for pricing.
     * @param  array $products
     * @param  string $getter
     * @return double|false
     */
    protected function calculatePrice(array $products, $getter)
    {
        $result = false;

        foreach($products as $product) {
            $valueForProduct = $this->$getter($product);
            if ($valueForProduct === false) {
                // False for any 1 product = false for all.
                return false;
            }

            $qty = $this->getEffectiveQtyForProduct($product);
            $valueForProduct *= $qty;

            if ($result === false) {
                $result = $valueForProduct;
            } else {
                $result += $valueForProduct;
            }
        }

        return $result === false ? $result : doubleval($result);
    }

    /**
     * @param  Mage_Catalog_Model_Product $product
     * @return double|false Retailer's cost for the given product.
     */
    protected function getCostForProduct(Mage_Catalog_Model_Product $product)
    {
        $cost = $product->getCost();
        return is_numeric($cost) ? doubleval($cost) : false;
    }

    /**
     * @param  Mage_Catalog_Model_Product $product
     * @return integer The number of $product being bought.
     */
    protected function getEffectiveQtyForProduct(Mage_Catalog_Model_Product $product)
    {
        $isChild = !!$product->getParentProductId();
        if (!$isChild) {
            // Don't consider quantity for parent products
            return 1;
        }

        // For child products (i.e., items in a bundle, use cart qty as multiplier to determine price

        $qty = $product->getCartQty();

        if ($qty > 0) {
            return intval($qty);
        }

        return 1;
    }

    /**
     * @param  Mage_Catalog_Model_Product $product
     * @return double|false Manufacturer's suggested retail price (if known).
     */
    protected function getMsrpForProduct(Mage_Catalog_Model_Product $product)
    {
        $msrp = $product->getMsrp();

        if (is_numeric($msrp)) {
            return doubleval($msrp);
        }

        return false;
    }

    /**
     * @param  Mage_Catalog_Model_Product $product
     * @return double|false "Compare at" price for the product (if known).
     */
    protected function getRegularPriceForProduct(Mage_Catalog_Model_Product $product)
    {
        $candidates = array();

        $nonSpecialPrice = $product->getPrice();
        if ($nonSpecialPrice > 0) {
            $candidates[] = doubleval($nonSpecialPrice);
        }

        // Let MSRP factor in
        $msrp = $this->getMsrpForProduct($product);
        if ($msrp > 0) {
            $candidates[] = doubleval($msrp);
        }

        if (empty($candidates)) {
            return false;
        }

        sort($candidates);
        return $candidates[0];
    }

    /**
     * @param  Mage_Catalog_Model_Product $product
     * @return double|false The current full retail price for the given product.
     */
    protected function getRetailPriceForProduct(Mage_Catalog_Model_Product $product)
    {
        $retail = $product->getFinalPrice();

        if (is_numeric($retail)) {
            return doubleval($retail);
        }

        return false;
    }

    /**
     * @return array The set of products to use when summing up the final retail price.
     */
    protected function getProductsForRetailPrice()
    {
        // Any non-child product *should* have a good retail price attached to it.

        $result = array();

        foreach($this->_products as $product) {
            $isChild = !!$product->getParentProductId();
            if (!$isChild) {
                $result[] = $product;
            }
        }

        return $result;
    }

    /**
     * @return array The set of products to use when summing up fields *other* than final retail price.
     */
    protected function getProductsForOtherPriceFields()
    {
        // *If* we have child products here, return *only* those.
        // Otherwise return all products.

        $haveChildProducts = false;
        foreach($this->_products as $product) {
            $isChild = !!$product->getParentProductId();
            if ($isChild) {
                $haveChildProducts = true;
                break;
            }
        }

        if (!$haveChildProducts) {
            return $this->_products;
        }

        // Include only the child products.
        $result = array();
        foreach($this->_products as $product) {
            $isChild = !!$product->getParentProductId();
            if ($isChild) {
                $result[] = $product;
            }
        }

        return $result;
    }

}
