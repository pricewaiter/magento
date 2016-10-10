<?php

/**
 * Exception thrown when a Deal cannot be created because it already was.
 */
class PriceWaiter_NYPWidget_Exception_DealAlreadyCreated
    extends PriceWaiter_NYPWidget_Exception_Abstract
{
    public $errorCode = 'deal_already_exists';
}
