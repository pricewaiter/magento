<?php

/**
 * Exception thrown when an attempt is made to look up an order that
 * does not exist.
 */
class PriceWaiter_NYPWidget_Exception_OrderNotFound
    extends PriceWaiter_NYPWidget_Exception_Abstract
{
    public $errorCode = 'order_not_found';
}
