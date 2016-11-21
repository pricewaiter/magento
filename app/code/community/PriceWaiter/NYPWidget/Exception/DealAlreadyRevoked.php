<?php

/**
 * Exception thrown when a Deal cannot be revoked because it already was.
 */
class PriceWaiter_NYPWidget_Exception_DealAlreadyRevoked
    extends PriceWaiter_NYPWidget_Exception_Abstract
{
    public $errorCode = 'deal_already_revoked';
}
