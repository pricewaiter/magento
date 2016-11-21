<?php

/**
 * Exception thrown when there's not enough inventory of an product.
 */
class PriceWaiter_NYPWidget_Exception_Product_OutOfStock
    extends PriceWaiter_NYPWidget_Exception_Product_Abstract
{
    public $errorCode = 'out_of_stock';
}
