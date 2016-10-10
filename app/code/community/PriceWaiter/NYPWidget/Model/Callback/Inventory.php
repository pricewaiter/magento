<?php

/**
 * Manages decrementing inventory for orders written via PriceWaiter order callback.
 */
class PriceWaiter_NYPWidget_Model_Callback_Inventory
{
    protected $_order = null;

    public function __construct(Mage_Sales_Model_Order $order)
    {
        $this->_order = $order;
    }

    /**
     * @return Array An array in the format [ "product_id" => [ "qty" => quantity_ordered ] ].
     */
    public function getProductsAndQuantities()
    {
        $items = $this->_order->getAllItems();
        $result = array();

        foreach($items as $item) {
            $productId = $item->getProductId();
            $result[$productId] = array('qty' => $item->getQtyOrdered());
        }

        return $result;
    }

    /**
     * Decrements inventory for items sold via PriceWaiter.
     * @throws Mage_Core_Exception If there's not enough inventory to fill the order.
     */
    public function registerPriceWaiterSale()
    {
        // This is adapted from code in Mage_CatalogInventory_Observer.
        // The issue is that Magento's inventory code is quote-based, but we (currently) create orders directly.

        // 1. Tell Mage_CatalogInventory_Model_Stock to decrement inventory.
        //    This will throw if there's not enough inventory to fill the order.
        $stock = Mage::getSingleton('cataloginventory/stock');
        $productQtys = $this->getProductsAndQuantities();

        try
        {
            $stockItemsNeedingSave = $stock->registerProductsSale($productQtys);
        }
        catch (Mage_Core_Exception $ex)
        {
            $translatedEx = PriceWaiter_NYPWidget_Exception_Abstract::translateMagentoException($ex);
            throw $translatedEx;
        }

        // 2. If that resulted in items going out of stock, they need to be saved + reindexed.
        //    Again, this is adapted from Mage_CatalogInventory_Observer

        $productIdsToIndex = array();

        foreach ($stockItemsNeedingSave as $stockItem) {
            $stockItem->save();
            $productIds[] = $stockItem->getProductId();
        }

        if ($productIdsToIndex) {
            Mage::getResourceSingleton('catalog/product_indexer_price')->reindexProductIds($productIdsToIndex);
        }
    }
}
