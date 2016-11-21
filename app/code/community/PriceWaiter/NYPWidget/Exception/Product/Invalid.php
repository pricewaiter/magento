<?php

/**
 * Exception thrown when an attempt is made to resolve an invalid product.
 */
class PriceWaiter_NYPWidget_Exception_Product_Invalid
    extends PriceWaiter_NYPWidget_Exception_Product_Abstract
{
    const DEFAULT_MESSAGE = 'Invalid product data received.';

    public function __construct($message = self::DEFAULT_MESSAGE)
    {
        parent::__construct($message);
    }
}
