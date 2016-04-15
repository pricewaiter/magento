<?php

/**
 * Exception thrown when we encounter a product of a type we don't support.
 */
class PriceWaiter_NYPWidget_Exception_Product_InvalidType extends PriceWaiter_NYPWidget_Exception_Product_Abstract
{
    public function __construct($product, $message = null)
    {
        if ($message === null) {
            $message = $this->_buildMessageForProduct($product);
        }

        parent::__construct($message);
    }

    protected function _buildMessageForProduct($product)
    {
        return "Invalid product type.";
    }
}
