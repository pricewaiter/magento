<?php

/**
 * Exception thrown when there's not enough inventory of an item.
 */
class PriceWaiter_NYPWidget_Exception_OutOfStock
    extends PriceWaiter_NYPWidget_Exception_Abstract
{
    public $errorCode = 'out_of_stock';
}
