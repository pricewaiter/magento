<?php

/**
 * Helper for order-related functionality.
 */
class PriceWaiter_NYPWidget_Helper_Orders extends Mage_Core_Helper_Abstract
{
    /**
     * @internal Logfile we use.
     */
    const LOGFILE = 'PriceWaiter_Callback.log';

    /**
     * @return String URL of the PriceWaiter API server.
     */
    public function getPriceWaiterApiUrl()
    {
        $apiUrl = getenv('PRICEWAITER_API_URL');

        if ($apiUrl) {
            return $apiUrl;
        }

        return 'https://api.pricewaiter.com';
    }

    /**
     * @return String URL to which to POST order data for verification.
     */
    public function getOrderVerificationUrl()
    {
        // Build verification URL off base API url.
        $url = $this->getApiUrl();
        $url = rtrim($url, '/');
        $url .= '/1/order/verify';

        return $url;
    }

    /**
     * @return Boolean
     */
    public function isLogEnabled()
    {
        return !!Mage::getStoreConfig('pricewaiter/configuration/log');
    }

    /**
     * @param  String $message
     * @return PriceWaiter_NYPWidget_Helper_Orders $this
     */
    public function log($message)
    {
        if ($this->isLogEnabled()) {
            Mage::log($message, null, self::LOGFILE);
        }

        return $this;
    }

    /**
     * Attempts to validate incoming PriceWaiter order data by POSTing it back
     * to the PriceWaiter Order Verification endpoint.
     * @param  Array  $data
     * @throws PriceWaiter_NYPWidget_Exception_InvalidOrderData
     * @return PriceWaiter_NYPWidget_Helper_Orders $this
     */
    public function verifyPriceWaiterOrderData(Array $data)
    {
        $valid = false;
        $ch = curl_init($this->getOrderVerificationUrl());

        var_dump($data);

        if ($ch) {
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);

            $response = curl_exec($ch);
            $valid = ($response === '1');
        }

        if (!$valid) {
            throw new PriceWaiter_NYPWidget_Exception_InvalidOrderData();
        }

        return $this;
    }
}
