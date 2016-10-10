<?php

/**
 * Exception thrown when an incoming request is of a version that we
 * don't support.
 */
class PriceWaiter_NYPWidget_Exception_Version
    extends PriceWaiter_NYPWidget_Exception_Abstract
{
    public $errorCode = 'invalid_version';

    /**
     * Versions that we *do* support.
     * @var Array
     */
    public $supportedVersions;

    public function __construct(Array $supportedVersions)
    {
        $this->supportedVersions = $supportedVersions;

        parent::__construct("Invalid version.");
    }

    public function jsonSerialize()
    {
        $json = parent::jsonSerialize();
        $json['data'] = array(
            'supportedVersions' => $this->supportedVersions,
        );

        return $json;
    }
}
