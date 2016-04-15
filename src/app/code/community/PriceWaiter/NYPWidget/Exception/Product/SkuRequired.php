<?php

/**
 * Exception thrown when we attempt something that requires that a product have a sku but it doesn't.
 */
class PriceWaiter_NYPWidget_Exception_Product_SkuRequired extends PriceWaiter_NYPWidget_Exception_Product_Abstract
{
    public function __construct($reason, Mage_Catalog_Model_Product $product)
    {
        $id = $product->getId();
        $message = "SKU required for $reason, but product $id does not have one.";

        parent::__construct($message);
    }
}
