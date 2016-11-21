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
 * A simplified abstract Response model for PriceWaiter HTTP endpoints.
 */
class PriceWaiter_NYPWidget_Controller_Endpoint_Response
{
    private $_body = null;
    private $_statusCode = 200;
    private $_bodyJson = null;

    public function __construct($statusCode = 200, $body = array())
    {
        $this->_statusCode = $statusCode;
        $this->_body = $body;
    }

    /**
     * @return String JSON-encoded body.
     */
    public function getBodyJson()
    {
        if ($this->_bodyJson !== null) {
            return $this->_bodyJson;
        }

        $options = 0;

        if (defined('JSON_UNESCAPED_SLASHES')) {
            $options |= JSON_UNESCAPED_SLASHES;
        }

        if (defined('JSON_PRETTY_PRINT')) {
            $options |= JSON_PRETTY_PRINT;
        }

        return ($this->_bodyJson = json_encode($this->_body, $options));
    }

    /**
     * @return Integer The HTTP status code to use.
     */
    public function getStatusCode()
    {
        return $this->_statusCode;
    }

    /**
     * Returns the HMAC signature for this response.
     * Meant for use in the X-PriceWaiter-Signature header.
     * @param  String $secret Shared secret.
     * @return String.
     */
    public function sign($secret)
    {
        $json = $this->getBodyJson();
        return 'sha256=' . hash_hmac('sha256', $this->getBodyJson(), $secret, false);
    }

    /**
     * @return PriceWaiter_NYPWidget_Controller_Endpoint_Response A generic 200 "ok" response.
     */
    public static function ok()
    {
        return new self();
    }
}
