<?php
/*
 * Copyright 2013-2016 Price Waiter, LLC
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *    http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 *
 */

/**
 * Simplified HTTP request abstraction for PriceWaiter endpoints.
 */
class PriceWaiter_NYPWidget_Controller_Endpoint_Request
{
    private $_apiKey;
    private $_id;
    private $_rawBody;
    private $_version;
    private $_timestamp;

    public function __construct($id, $apiKey, $version, $rawBody, $timestamp = null)
    {
        $this->_id = $id;
        $this->_apiKey = $apiKey;
        $this->_version = $version;
        $this->_rawBody = $rawBody;
        $this->_timestamp = $timestamp === null ? time() : $timestamp;
    }

    /**
     * @return String The public PriceWaiter API key for this request.
     */
    public function getApiKey()
    {
        return $this->_apiKey;
    }

    /**
     * @return Object The request body.
     */
    public function getBody()
    {
        return json_decode($this->_rawBody);
    }

    /**
     * @return String The unique PriceWaiter request id.
     */
    public function getId()
    {
        return $this->_id;
    }

    /**
     * @return Integer UNIX timestamp representing request date/time.
     */
    public function getTimestamp()
    {
        return $this->_timestamp;
    }

    /**
     * @return String Version of request data.
     */
    public function getVersion()
    {
        return $this->_version;
    }

    /**
     * Checks an HMAC signature for this request.
     * @param  String $signature
     * @param  String $secret
     * @return boolean
     */
    public function isSignatureValid($signature, $secret)
    {
        // NOTE: Never let an empty secret be used.
        if (trim($secret) === '') {
            return false;
        }

        $headers = array(
            PriceWaiter_NYPWidget_Controller_Endpoint::API_KEY_HEADER => $this->getApiKey(),
            PriceWaiter_NYPWidget_Controller_Endpoint::REQUEST_ID_HEADER => $this->getId(),
            PriceWaiter_NYPWidget_Controller_Endpoint::VERSION_HEADER => $this->getVersion(),
        );

        $content = array();

        foreach ($headers as $key => $value) {
            $content[] = "${key}: $value";
        }
        $content[] = $this->_rawBody;
        $content = implode("\n", $content);

        $detected = 'sha256=' . hash_hmac('sha256', $content, $secret, false);

        if (function_exists('hash_equals')) {
            // Favor PHP's secure hash comparison function in 5.6 and up.
            // For a robust drop-in compatibility shim, see: https://github.com/realityking/hash_equals
            return hash_equals($detected, $signature);
        }

        return $detected === $signature;
    }
}
