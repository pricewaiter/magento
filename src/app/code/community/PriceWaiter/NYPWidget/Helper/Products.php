<?php

/**
 * Helper for working with product data in a PriceWaiter-specific way.
 */
class PriceWaiter_NYPWidget_Helper_Products extends Mage_Core_Helper_Abstract
{
    /**
     * @var array
     */
    protected $supportedProductTypeIds = array(
        Mage_Catalog_Model_Product_Type::TYPE_SIMPLE => true,
    );

    /**
     * Array of strategies that are used to resolve OfferItems into ResolvedItems.
     * @var array
     */
    protected $resolutionStrategies = array(
        'PriceWaiter_NYPWidget_Helper_Products_Simple',
        'PriceWaiter_NYPWidget_Helper_Products_Configurable',
    );

    /**
     * @internal
     * @var Array
     */
    private $resolutionStrategyInstances = null;

    /**
     * @param  Mage_Catalog_Model_Product $product
     * @return boolean Whether PriceWaiter is supported for the given product.
     */
    public function isProductSupported(Mage_Catalog_Model_Product $product)
    {
        if (!$this->isProductTypeSupported($product->getTypeId())) {
            return false;
        }

        $hasCustomOptions = $product->getProductOptionsCollection()->getSize() > 0;
        if ($hasCustomOptions) {
            return false;
        }

        return true;
    }

    /**
     * @param  String  $typeId A product type id, e.g. "simple".
     * @return boolean Whether PriceWaiter is supported for the given product type.
     */
    public function isProductTypeSupported($typeId)
    {
        return !empty($this->supportedProductTypeIds[$typeId]);
    }

    /**
     * Given an array of OfferItem models, attempts to resolve them into a corresponding
     * array of ResolvedItem models tied to actual Magento products.
     * @param  Array  $offerItems An Array of PriceWaiter_NYPWidget_Model_OfferItem instances.
     * @return Array An array of PriceWaiter_NYPWidget_Model_ResolvedItem instances.
     * @throws PriceWaiter_NYPWidget_Exception_Product_NotFound If a product can't be found.
     * @throws RuntimeException If $offerItems contains something that is not an OfferItem.
     */
    public function resolveItems(Array $offerItems)
    {
        $result = array();

        foreach ($offerItems as $offerItem) {

            if (!($offerItem instanceof PriceWaiter_NYPWidget_Model_OfferItem)) {
                throw new RuntimeException('Invalid item passed to ' . __METHOD__);
            }

            $resolvedItems = false;

            foreach($this->getResolutionStrategies() as $strategy) {

                $resolvedItems = $strategy->resolveItems($offerItem);

                // `false` from resolveItems() means the strategy could not
                // handle this item. But the next strategy may be able to!

                if ($resolvedItems !== false) {
                    break;
                }
            }

            if ($resolvedItems === false) {

                // No strategy worked to figure out this item.
                $this->handleResolveFailure($offerItem);

            } else {

                foreach($resolvedItems as $resolvedItem) {
                    $result[] = $resolvedItem;
                }
            }
        }

        return $result;
    }

    /**
     * @return Array Array of strategies for product resolution.
     */
    protected function getResolutionStrategies()
    {
        if (is_null($this->resolutionStrategyInstances)) {

            $this->resolutionStrategyInstances = array_map(function($class) {
                return new $class();
            }, $this->resolutionStrategies);

        }

        return $this->resolutionStrategyInstances;
    }

    /**
     * @internal
     */
    protected function handleResolveFailure(PriceWaiter_NYPWidget_Model_OfferItem $offerItem)
    {
        throw new PriceWaiter_NYPWidget_Exception_Product_NotFound();
    }
}
