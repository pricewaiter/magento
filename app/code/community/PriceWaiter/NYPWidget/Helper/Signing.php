<?php

class PriceWaiter_NYPWidget_Helper_Signing extends Mage_Core_Helper_Abstract
{
    /**
     * Returns a signature that can be added to the head of a PriceWaiter API response.
     * @param {String} $responseBody The full body of the request to sign.
     * @return {String} Signature that should be set as the X-PriceWaiter-Signature header.
     */
    public function getResponseSignature($responseBody)
    {
        $secret = Mage::helper('nypwidget')->getSecret();
        $signature = 'sha256=' . hash_hmac('sha256', $responseBody, $secret, false);
        return $signature;
    }

    /**
     * Validates that the current request came from PriceWaiter.
     * @param {String} $signatureHeader Full value of the X-PriceWaiter-Signature header.
     * @param {String} $requestBody Complete body of incoming request.
     * @return {Boolean} Wehther the request actually came from PriceWaiter.
     */
    public function isPriceWaiterRequestValid($signatureHeader = null, $requestBody = null)
    {
        if ($signatureHeader === null || $requestBody === null) {
            return false;
        }

        $secret = Mage::helper('nypwidget')->getSecret();

        if (trim($secret) === '') {
            // Don't allow a blank secret to validate.
            return false;
        }

        $detected = 'sha256=' . hash_hmac('sha256', $requestBody, $secret, false);

        if (function_exists('hash_equals')) {
            // Favor PHP's secure hash comparison function in 5.6 and up.
            // For a robust drop-in compatibility shim, see: https://github.com/indigophp/hash-compat
            return hash_equals($detected, $signatureHeader);
        }

        return $detected === $signatureHeader;
    }
}
