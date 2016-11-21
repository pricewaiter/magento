<?php

/**
 * Exception thrown to indicate this installation is only capable working with single-item deals.
 */
class PriceWaiter_NYPWidget_Exception_SingleItemOnly
    extends PriceWaiter_NYPWidget_Exception_Abstract
{
    public $errorCode = 'single_item_only';
}
