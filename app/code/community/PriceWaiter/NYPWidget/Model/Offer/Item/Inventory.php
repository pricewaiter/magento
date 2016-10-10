<?php

/**
 * Layer that adapts the way Magento thinks about inventory to the way
 * PriceWaiter thinks about inventory.
 */
class PriceWaiter_NYPWidget_Model_Offer_Item_Inventory
{
    protected $_products;
    protected $_productsWithStockItems = null;

    public function __construct(array $products)
    {
        $this->_products = $products;
    }

    /**
     * @return Boolean Whether backorders are allowed for this item.
     */
    public function canBackorder()
    {
        // NOTE: Any *one* item not being backorderable means we can't consider
        //       the group backorderable.

        foreach($this->getProductsWithStockItems() as $p) {
            list($product, $stockItem) = $p;

            // Technically getBackorders() is a flag with multiple states but
            // BACKORDERS_NO = 0 so this works.
            if (!$stockItem->getBackorders()) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return Integer|false The current # in stock (or false if unknown).
     */
    public function getStock()
    {
        $result = false;

        foreach($this->getProductsWithStockItems() as $p) {
            list($product, $stockItem) = $p;

            if (!$stockItem->getManageStock()) {
                // Not tracking stock for this item, so ignore.
                continue;
            }

            // Since we are considering the products as a group, return the
            // *minimum* quantity available.
            $qty = $this->getQty($product, $stockItem);

            if ($result === false || $qty < $result) {
                $result = $qty;
            }
        }

        return $result === false ? $result : intval($result);
    }

    /**
     * @param  Mage_Catalog_Model_Product             $product
     * @param  Mage_CatalogInventory_Model_Stock_Item $stockItem
     * @return Integer the # of the product available.
     */
    protected function getQty(
        Mage_Catalog_Model_Product $product,
        Mage_CatalogInventory_Model_Stock_Item $stockItem
    )
    {
        // For products that are part of a bundle, we have to
        // return how many are available in that increment.
        //
        // So if bundle contains 2 x Shirt, and Shirt has 100 left,
        // that means the effective quantity for the Shirt in the bundle
        // is 50 (100 / 2).
        $increment = $product->getCartQty();

        if ($increment > 1) {
            return floor($stockItem->getQty() / $increment);
        }

        // Ordinarily, though, we just use the stock item's quantity.
        return $stockItem->getQty();
    }

    /**
     * @return array The set of stock items for the products being considered.
     */
    protected function getProductsWithStockItems()
    {
        if ($this->_productsWithStockItems !== null) {
            return $this->_productsWithStockItems;
        }

        $this->_productsWithStockItems = array();

        // If we have any child products, only return stock items for those.
        // Otherwise (for e.g. grouped products), return stock items for *all* products.
        $haveAnyChildren = false;

        foreach($this->_products as $product) {
            $isChild = !!$product->getParentProductId();
            if ($isChild) {
                $haveAnyChildren = true;
                break;
            }
        }

        foreach($this->_products as $product) {
            $isChild = !!$product->getParentProductId();

            if ($haveAnyChildren && !$isChild) {
                // Ignore parents for inventory purposes
                continue;
            }

            $this->_productsWithStockItems[] = array($product, $product->getStockItem());
        }

        return $this->_productsWithStockItems;
    }
}
