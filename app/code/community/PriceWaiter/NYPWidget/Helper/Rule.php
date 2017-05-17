<?php

class PriceWaiter_NYPWidget_Helper_Rule extends Mage_Core_Helper_Abstract
{
    /**
     * Determines whether a sales rule applies to more than just shipping.
     *
     * @var Mage_SalesRule_Model_Rule $rule
     * @return bool
     */
    public function ruleAppliesToShippingOnly($rule)
    {
        // Apply to Shipping Amount
        $applyToShipping = (int)$rule->getApplyToShipping();
        if ($applyToShipping === 1) {
            return true;
        }

        // Free Shipping
        $simpleFreeShipping = (int)$rule->getSimpleFreeShipping();
        if ($simpleFreeShipping === Mage_SalesRule_Model_Rule::FREE_SHIPPING_ITEM || // For matching items only
            $simpleFreeShipping === Mage_SalesRule_Model_Rule::FREE_SHIPPING_ADDRESS // For shipment with matching items
        ) {
            return true;
        }

        return false;
    }
}
