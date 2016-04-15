<?php

/**
 * An item in a PriceWaiter Offer that has been resolved to a specific
 * Magento simple product, quantity range, and price.
 */
class PriceWaiter_NYPWidget_Model_ResolvedItem
{
    private $_offerItem;
    private $_product;

    /**
     * @param PriceWaiter_NYPWidget_Model_OfferItem $offerItem
     * @param Mage_Catalog_Model_Product            $product
     * @throws PriceWaiter_NYPWidget_Exception_Product_InvalidType If $product is not simple.
     */
    public function __construct(
        PriceWaiter_NYPWidget_Model_OfferItem $offerItem,
        Mage_Catalog_Model_Product $product
    )
    {
        if ($product->getTypeId() !== Mage_Catalog_Model_Product_Type::TYPE_SIMPLE) {
            throw new PriceWaiter_NYPWidget_Exception_Product_InvalidType(
                $product,
                'Must be a simple product.'
            );
        }

        $this->_offerItem = $offerItem;
        $this->_product = $product;
    }

    /**
     * Generates conditions necessary to match this item in a SalesRule.
     * @return Array
     */
    public function buildSalesRuleConditions()
    {
        $product = $this->getProduct();
        $sku = $product->getSku();

        if (!$sku) {

            // We currently rely on SKU to do our limiting via salesrules.
            // If the product does not have a SKU, we can't do our thing.
            // TODO: Establish whether we can use product ID rather than SKU here.

            throw new PriceWaiter_NYPWidget_Exception_Product_SkuRequired(
                'Limit via SalesRule',
                $product
            );
        }


        // NOTE: This weird format is how Magento represents this data in the
        //       the DB. There's actually a hierarchy here, indicated by '--'
        //       separators. It is meant to eventually be fed into
        //       Mage_Salesrule_Model_Rule::loadPost().

        $conditions = array(
            '1' => array(
                'type' => 'salesrule/rule_condition_product_found',
                'value' => '1',
                'aggregator' => 'all',
            ),
            '1--1' => array(
                'type' => 'salesrule/rule_condition_product',
                'attribute' => 'sku',
                'operator' => '==',
                'value' => $sku,
            ),
            '1--2' => array(
                'type' => 'salesrule/rule_condition_product',
                'attribute' => 'quote_item_qty',
                'operator' => '>=',
                'value' => (string)$this->getMinimumQuantity(),
            ),
            // NOTE: Max quantity is currently handled via the discount_qty field on the salesrule
        );

        return $conditions;
    }

    /**
     * @return Number Agreed price per item.
     */
    public function getAmountPerItem()
    {
        return $this->getOfferItem()->getAmountPerItem();
    }

    /**
     * The maximum number of this item that can be purchased.
     * @return Integer
     */
    public function getMaximumQuantity()
    {
        return $this->getOfferItem()->getMaximumQuantity();
    }

    /**
     * The minimum number of this item that must be purchased.
     * @return Integer
     */
    public function getMinimumQuantity()
    {
        return $this->getOfferItem()->getMinimumQuantity();
    }

    /**
     * @return PriceWaiter_NYPWidget_Model_OfferItem The OfferItem that was resolved into this item.
     */
    public function getOfferItem()
    {
        return $this->_offerItem;
    }

    /**
     * Returns the Magento simple product this item references.
     * @return Mage_Catalog_Model_Product
     */
    public function getProduct()
    {
        return $this->_product;
    }

    /**
     * @return Number The per-item price of the product as configured.
     */
    public function getProductPrice()
    {
        return $this->getProduct()->getFinalPrice();
    }
}
