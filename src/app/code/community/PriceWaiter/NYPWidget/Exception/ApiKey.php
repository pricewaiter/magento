<?php

/**
 * Exception thrown when an incoming PriceWaiter API key does not match any
 * store in the system.
 */
class PriceWaiter_NYPWidget_Exception_ApiKey
    extends PriceWaiter_NYPWidget_Exception_Abstract
{
    public $errorCode = 'invalid_api_key';

    public function __construct()
    {
        parent::__construct('An invalid PriceWaiter API key was detected.');
    }
}
