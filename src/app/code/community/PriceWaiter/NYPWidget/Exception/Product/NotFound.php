<?php

/**
 * Exception thrown when a product cannot be found.
 */
class PriceWaiter_NYPWidget_Exception_Product_NotFound
    extends PriceWaiter_NYPWidget_Exception_Product_Abstract
{
    public $errorCode = 'product_not_found';
}
