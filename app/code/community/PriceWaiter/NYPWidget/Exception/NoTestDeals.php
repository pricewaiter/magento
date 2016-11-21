<?php

/**
 * Exception thrown on any attempt to create a "test" deal.
 */
class PriceWaiter_NYPWidget_Exception_NoTestDeals
    extends PriceWaiter_NYPWidget_Exception_Abstract
{
    public $errorCode = 'no_test_deals';
}
