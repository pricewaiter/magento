<?php

/**
 * Resolves data from a PriceWaiter Offer into one or more
 * PriceWaiter_NYPWidget_Model_Item instances.
 */
class PriceWaiter_NYPWidget_Helper_Products_Simple
    extends PriceWaiter_NYPWidget_Helper_Products_Abstract
{
    /**
     * {@inheritdoc}
     */
    public function resolveItems(PriceWaiter_NYPWidget_Model_OfferItem $offerItem)
    {
        $product = $this->findProduct($offerItem);

        if (!$product) {
            return false;
        }

        if (!$this->isSimpleProduct($product)) {
            return false;
        }

        // NOTE: Simple products have a 1-1 mapping with OfferItems.

        return array(
            new PriceWaiter_NYPWidget_Model_ResolvedItem(
                $offerItem,
                $product
            )
        );
    }

    /**
     * @param  PriceWaiter_NYPWidget_Model_OfferItem $offerItem
     * @return Mage_Catalog_Model_Product|false
     */
    public function findProduct(PriceWaiter_NYPWidget_Model_OfferItem $offerItem)
    {
        $productFoundBySku = null;
        $productFoundById = null;

        $sku = trim($offerItem->getProductSku());
        $sku = $sku === '' ? false : $sku;

        if ($sku !== false) {
            $productFoundBySku = Mage::getModel('catalog/product');
            $productFoundBySku = $productFoundBySku->loadByAttribute('sku', $sku);
        }

        $id = $offerItem->getAddToCartForm('product');
        $id = is_numeric($id) ? $id : false;

        if ($id !== false) {
            $productFoundById = Mage::getModel('catalog/product');
            $productFoundById = $productFoundById->load($id);
            if (!$productFoundById->getId()) {
                $productFoundById = false;
            }
        }

        if ($productFoundById && $productFoundBySku) {

            return $this->resolveByIdAndSku($id, $sku, $productFoundById, $productFoundBySku);

        } else if ($productFoundById) {

            if ($sku !== false) {
                $this->_throwInconsistencyError(
                    'SKU did not match a product, but ID did',
                    $id, $sku,
                    $productFoundById, $productFoundBySku
                );
            }

            return $productFoundById;

        } else if ($productFoundBySku) {

            if ($id !== false) {
                $this->_throwInconsistencyError(
                    'ID did not match a product, but SKU did',
                    $id, $sku,
                    $productFoundById, $productFoundBySku
                );
            }

            return $productFoundBySku;
        }
    }

    /**
     * @param  Mage_Catalog_Model_Product $productFoundBySku
     * @param  Mage_Catalog_Model_Product $productFoundById
     */
    protected function resolveByIdAndSku(
        $id,
        $sku,
        Mage_Catalog_Model_Product $productFoundById,
        Mage_Catalog_Model_Product $productFoundBySku
    )
    {
        $areSameProduct = (
            $productFoundBySku->getId() === $productFoundById->getId()
        );

        if ($areSameProduct) {
            return $productFoundById;
        }

        $idIsSimple = $productFoundById->getTypeId() === Mage_Catalog_Model_Product_Type::TYPE_SIMPLE;
        $skuIsSimple = $productFoundBySku->getTypeId() === Mage_Catalog_Model_Product_Type::TYPE_SIMPLE;

        if ($idIsSimple && !$skuIsSimple) {
            // What *probably* happened here is that our SKU was not updated when
            // selecting product options. We consider the "by id" product more
            // authoritative in this case.
            return $productFoundById;
        }

        $this->_throwInconsistencyError(
            'Different product found by id and SKU',
            $id, $sku,
            $productFoundById,
            $productFoundBySku
        );
    }

    private function _throwInconsistencyError($message, $id, $sku, $productFoundById, $productFoundBySku)
    {
        $ex = new PriceWaiter_NYPWidget_Exception_Product_InconsistentData($message);
        $ex->id = $id;
        $ex->sku = $sku;
        $ex->productFoundById = $productFoundById;
        $ex->productFoundBySku = $productFoundBySku;

        throw $ex;
    }

}
