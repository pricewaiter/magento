<?php

/**
 * Base for product data related exceptions.
 */
abstract class PriceWaiter_NYPWidget_Exception_Product_Abstract
    extends PriceWaiter_NYPWidget_Exception_Abstract
{
    public $errorCode = 'invalid_product';
}
