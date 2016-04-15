<?php

/**
 * Base for implementing an OfferItem resolution strategy.
 */
abstract class PriceWaiter_NYPWidget_Helper_Products_Abstract
{
    /**
     * @param  PriceWaiter_NYPWidget_Model_OfferItem $offerItem An item from a PriceWaiter Offer.
     * @return Array|false The resolved items or false this strategy cannot handle.
     */
    abstract public function resolveItems(PriceWaiter_NYPWidget_Model_OfferItem $offerItem);

    /**
     *
     * @param  Number $id
     * @return Mage_Catalog_Model_Product|false
     */
    protected function findProductById($id)
    {
        $product = Mage::getModel('catalog/product')->load($id);
        return $product && $product->getId() ? $product : false;
    }

    /**
     * @param  Mage_Catalog_Model_Product $product
     * @return boolean
     */
    protected function isConfigurableProduct(Mage_Catalog_Model_Product $product)
    {
        return $product->getTypeId() === Mage_Catalog_Model_Product_Type::TYPE_CONFIGURABLE;
    }

    /**
     * @param  Mage_Catalog_Model_Product $product
     * @return boolean
     */
    protected function isSimpleProduct(Mage_Catalog_Model_Product $product)
    {
        return $product->getTypeId() === Mage_Catalog_Model_Product_Type::TYPE_SIMPLE;
    }
}
