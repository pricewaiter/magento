<?php

/**
 * Exception thrown when an order comes in for a geographic region we don't know about.
 */
class PriceWaiter_NYPWidget_Exception_InvalidRegion
    extends PriceWaiter_NYPWidget_Exception_Abstract
{
    public $errorCode = 'magento_invalid_region';

    /**
     * @var String
     */
    public $regionCode = null;

    /**
     * @var String
     */
    public $countryCode = null;

    public function __construct($regionCode, $countryCode)
    {
        $this->regionCode = $regionCode;
        $this->countryCode = $countryCode;

        parent::__construct(
            "Invalid region: '$regionCode, $countryCode'"
        );
    }
}
