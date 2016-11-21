<?php

class PriceWaiter_NYPWidget_ProductinfoController
    extends Mage_Core_Controller_Front_Action
{
    public function indexAction()
    {
        $httpRequest = $this->getRequest();
        $httpResponse = $this->getResponse();

        Mage::helper('nypwidget/about')->setResponseHeaders($httpResponse);

        // Ensure that we have received POST data
        $requestBody = $httpRequest->getRawBody();
        $postFields = $httpRequest->getPost();

        // Validate the request
        // - return 400 if signature cannot be verified
        $signature = $httpRequest->getHeader('X-PriceWaiter-Signature');
        if (!$this->isPriceWaiterRequestValid($signature, $requestBody)) {
            $httpResponse->setHttpResponseCode(400);
            return false;
        }

        try
        {
            $store = Mage::app()->getStore();

            // Turn an array of POST data into a thing that can actually
            // tell us something about the product(s).
            $offerItem = $this->getOfferItem($httpRequest->getPost(), $store);

            // Format the result we're going to return.
            $result = $this->buildResponse($offerItem, $store);

            // And finally, return it.
            $json = json_encode($result);

            $httpResponse->setHeader('X-PriceWaiter-Signature', $this->getResponseSignature($json));
            $httpResponse->setHeader('Content-Type', 'application/json');
            $httpResponse->setBody($json);
        }
        catch (Exception $ex)
        {
            Mage::logException($ex);

            $httpResponse->setHttpResponseCode(404);

            // Extra: Include an error code if we have one.
            if ($ex instanceof PriceWaiter_NYPWidget_Exception_Abstract) {
                $httpResponse->setHeader('X-PriceWaiter-Error', $ex->errorCode);
            }
        }
    }

    public function buildResponse(
        PriceWaiter_NYPWidget_Model_Offer_Item $item,
        Mage_Core_Model_Store $store
    )
    {
        $result = array(
            'allow_pricewaiter' => true, // See pricewaiter/magento-dev#115
        );

        // 1. Add pricing information
        $pricing = $item->getPricing();

        $retail = $pricing->getRetailPrice();
        if ($retail !== false) {
            $result['retail_price'] = strval($retail);
            $result['retail_price_currency'] = $pricing->getCurrencyCode();
        }

        $cost = $pricing->getCost();
        if ($cost !== false) {
            $result['cost'] = strval($cost);
            $result['cost_currency'] = $pricing->getCurrencyCode();
        }

        $regular = $pricing->getRegularPrice();
        if ($regular !== false) {
            $result['regular_price'] = strval($regular);
            $result['regular_price_currency'] = $pricing->getCurrencyCode();
        }

        // 2. Add inventory
        $inventory = $item->getInventory();
        $stock = $inventory->getStock();
        if ($stock !== false) {
            $result['inventory'] = $stock;
            $result['can_backorder'] = $inventory->canBackorder();
        }

        return $result;
    }

    /**
     * @return PriceWaiter_NYPWidget_Model_Offer_Item
     */
    public function getOfferItem(array $post, Mage_Core_Model_Store $store)
    {
        $data = array(
            'product' => array(),
            'metadata' => array(),
        );

        // Per spec, $post will contain product_sku, and any other field
        // should be interpreted as metadata.
        foreach($post as $key => $value) {
            if ($key === 'product_sku') {
                $data['product']['sku'] = $value;
            } else {
                $data['metadata'][$key] = $value;
            }
        }

        return Mage::getModel('nypwidget/offer_item', $data, $store);
    }

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
