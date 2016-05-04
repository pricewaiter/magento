<?php

/**
 * Exception thrown when an incoming order callback request cannot be validated.
 */
class PriceWaiter_NYPWidget_Exception_InvalidOrderData
    extends PriceWaiter_NYPWidget_Exception_Abstract
{
    public $errorCode = 'invalid_order_data';

    public function __construct()
    {
        parent::__construct(
            'An invalid PriceWaiter order notification has been received.'
        );
    }
}
