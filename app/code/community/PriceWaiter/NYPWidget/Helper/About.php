<?php

/**
 * Helper that provides metadata about the operating environment.
 */
class PriceWaiter_NYPWidget_Helper_About extends Mage_Core_Helper_Abstract
{
    /**
     * HTTP header on response that tells PriceWaiter the platform version.
     */
    const EXTENSION_VERSION_HEADER = 'X-PriceWaiter-Extension-Version';

    /**
     * HTTP header on response that tells PriceWaiter the platform name.
     */
    const PLATFORM_HEADER = 'X-PriceWaiter-Platform';

    /**
     * HTTP header on response that tells PriceWaiter the platform version.
     */
    const PLATFORM_VERSION_HEADER = 'X-PriceWaiter-Platform-Version';

    /**
     * @return String PriceWaiter extension version.
     */
    public function getExtensionVersion()
    {
        try
        {
            return (string)Mage::getConfig()->getNode()->modules->PriceWaiter_NYPWidget->version;
        }
        catch (Exception $ex)
        {
            return 'unknown';
        }
    }

    /**
     * @return String Platform identification string.
     */
    public function getPlatform()
    {
        if (method_exists('Mage', 'getEdition')) {
            return 'Magento ' . Mage::getEdition();
        } else {
            return 'Magento Pre-1.7';
        }
    }

    /**
     * @return String Magento version.
     */
    public function getPlatformVersion()
    {
        return Mage::getVersion();
    }

    /**
     * @internal Adds "about" response headers.
     * @param Zend_Controller_Response_Http $httpResponse
     */
    public function setResponseHeaders(Zend_Controller_Response_Http $httpResponse)
    {
        $httpResponse->setHeader(self::PLATFORM_HEADER, $this->getPlatform(), true);
        $httpResponse->setHeader(self::PLATFORM_VERSION_HEADER, $this->getPlatformVersion(), true);
        $httpResponse->setHeader(self::EXTENSION_VERSION_HEADER, $this->getExtensionVersion(), true);
    }

}
