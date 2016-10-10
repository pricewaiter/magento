<?php
/**
 * Exception thrown when a request comes in for store that was not found.
 */
class PriceWaiter_NYPWidget_Exception_InvalidStore
    extends PriceWaiter_NYPWidget_Exception_Abstract
{
    public $errorCode = 'magento_invalid_store';
}
