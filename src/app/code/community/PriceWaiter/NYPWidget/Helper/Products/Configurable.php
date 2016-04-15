<?php

/**
 * Attempts to resolve an OfferItem
 */
class PriceWaiter_NYPWidget_Helper_Products_Configurable
    extends PriceWaiter_NYPWidget_Helper_Products_Abstract
{
    /**
     * {@inheritdoc}
     */
    public function resolveItems(PriceWaiter_NYPWidget_Model_OfferItem $offerItem)
    {
        $id = $offerItem->getAddToCartForm('product');
        $superAttributes = $offerItem->getAddToCartForm('super_attribute');

        if (!$id || !is_array($superAttributes)) {
            // This probably isn't actually a configurable product. Let another
            // strategy attempt to handle it.
            return false;
        }

        $product = $this->findProductById($id);

        if (!$product || !$this->isConfigurableProduct($product)) {
            // Let another strategy handle this.
            return false;
        }

        // Take the configurable product + options and resolve the actual simple product
        $type = $product->getTypeInstance();

        $cartCandidates = $type->prepareForCartAdvanced(
            new Varien_Object(array(
                'product' => $id,
                'super_attribute' => $superAttributes,
            )),
            $product,
            null
        );

        // Filter out the configurable product ...
        $cartCandidates = array_filter($cartCandidates, function($p) use($product) {
            return $p->getId() !== $product->getId();
        });

        // ...and we should be left with the resolved simple product
        if (count($cartCandidates) !== 1) {
            return false;
        }

        $simpleProduct = array_shift($cartCandidates);

        if (!$this->isSimpleProduct($simpleProduct)) {
            // This is not what we were expecting.
            return false;
        }

        return array(
            new PriceWaiter_NYPWidget_Model_ResolvedItem(
                $offerItem,
                $simpleProduct
            ),
        );
    }
}
