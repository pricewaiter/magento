<?php

/**
 * Exception thrown when a Deal cannot be found by ID.
 */
class PriceWaiter_NYPWidget_Exception_DealNotFound
    extends PriceWaiter_NYPWidget_Exception_Abstract
{
    public $errorCode = 'deal_not_found';
}
