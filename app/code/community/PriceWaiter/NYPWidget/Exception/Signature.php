<?php

/**
 * Exception thrown when an incoming request's signature does not match what
 * was expected.
 */
class PriceWaiter_NYPWidget_Exception_Signature
    extends PriceWaiter_NYPWidget_Exception_Abstract
{
    public $errorCode = 'invalid_signature';
}
