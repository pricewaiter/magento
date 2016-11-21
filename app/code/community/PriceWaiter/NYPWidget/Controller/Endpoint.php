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
 * Base class for impelmenting PriceWaiter-specific endpoints.
 * Our endpoints all use HTTP POST, with signed JSON request/response payloads.
 * This base class handles the signing and request versioning.
  */
abstract class PriceWaiter_NYPWidget_Controller_Endpoint extends Mage_Core_Controller_Front_Action
{
    /**
     * Set to the set of version numbers that this endpoint can process.
     * @var array
     */
    protected $supportedVersions = array();

    /**
     * Content type in which responses are sent.
     */
    const RESPONSE_CONTENT_TYPE = 'application/json; charset=UTF-8';

    /**
     * HTTP header containing the API key of the PriceWaiter store.
     */
    const API_KEY_HEADER = 'X-PriceWaiter-Api-Key';

    /**
     * HTTP header containing the unique PW request id.
     */
    const REQUEST_ID_HEADER = 'X-PriceWaiter-Request-Id';

    /**
     * HTTP header in which request/response signature is stored.
     */
    const SIGNATURE_HEADER = 'X-PriceWaiter-Signature';

    /**
     * Header in which version is specified on requests.
     */
    const VERSION_HEADER = 'X-PriceWaiter-Version';


    public function indexAction()
    {
        $httpRequest = Mage::app()->getRequest();
        $httpResponse = Mage::app()->getResponse();

        // We support POST only. For non-POST requests, we serve the traditional
        // Magento "not found" page.
        if (!$httpRequest->isPost()) {
            return $this->silentNotFound($httpResponse);
        }

        $response = null;

        try
        {
            $rawBody = $httpRequest->getRawBody();
            $id = $httpRequest->getHeader(self::REQUEST_ID_HEADER);
            $apiKey = $httpRequest->getHeader(self::API_KEY_HEADER);
            $version = $httpRequest->getHeader(self::VERSION_HEADER);

            $request = new PriceWaiter_NYPWidget_Controller_Endpoint_Request(
                $id,
                $apiKey,
                $version,
                $rawBody
            );

            $signature = $httpRequest->getHeader(self::SIGNATURE_HEADER);
            $this->checkSignature($request, $signature);

            $this->checkVersion($request);

            $response = $this->processRequest($request);
        }
        catch (PriceWaiter_NYPWidget_Exception_Abstract $ex)
        {
            // This is an exception generated within our code, meant to be passed back to the client.
            // It's reporting something like "signature verification failed" or "product could
            // not be found" or "wtf is that currency".

            $response = new PriceWaiter_NYPWidget_Controller_Endpoint_Response(
                $ex->httpStatusCode,
                array(
                    'error' => $ex->jsonSerialize(),
                )
            );
        }
        catch (Exception $ex)
        {
            Mage::logException($ex);

            $response = new PriceWaiter_NYPWidget_Controller_Endpoint_Response(
                500,
                array(
                    'error' => array(
                        'code' => get_class($ex),
                        'message' => $ex->getMessage(),
                    ),
                )
            );
        }

        $this->sendResponse($response, $httpResponse);
    }

    /**
     * Override to handle a request to this endpoint.
     * @param  PriceWaiter_NYPWidget_Controller_Endpoint_Request $request
     * @return Array Array in the format [ $httpStatusCode, $responseBody ]
     */
    abstract public function processRequest(PriceWaiter_NYPWidget_Controller_Endpoint_Request $request);

    /**
     * Checks the X-PriceWaiter-Signature on the incoming request.
     * @param  Mage_Core_Controller_Request_Http $request
     * @return Boolean
     * @throws PriceWaiter_NYPWidget_Exception_Signature
     */
    protected function checkSignature(PriceWaiter_NYPWidget_Controller_Endpoint_Request $request, $signature)
    {
        $secret = $this->getSharedSecret();

        if ($request->isSignatureValid($signature, $secret)) {
            return true;
        }

        throw new PriceWaiter_NYPWidget_Exception_Signature();
    }

    /**
     * Checks the X-PriceWaiter-Version header on the incoming request.
     * @param  Mage_Core_Controller_Request_Http $request
     * @return Boolean
     * @throws PriceWaiter_NYPWidget_Exception_Version
     */
    protected function checkVersion(PriceWaiter_NYPWidget_Controller_Endpoint_Request $request)
    {
        if (count($this->supportedVersions) === 0) {
            $class = function_exists('get_called_class') ? get_called_class() : get_class();
            throw new RuntimeException("$class does not specify $supportedVersions");
        }

        if (in_array($request->getVersion(), $this->supportedVersions)) {
            return true;
        }

        throw new PriceWaiter_NYPWidget_Exception_Version($this->supportedVersions);
    }

    /**
     * @return String The shared secret used for HMAC signing and verification.
     */
    protected function getSharedSecret()
    {
        $helper = Mage::helper('nypwidget');
        return $helper->getSecret();
    }
    /**
     * Writes a PriceWaiter Endpoint response object out using a standard Magento response.
     * @param  PriceWaiter_NYPWidget_Controller_Endpoint_Response $response
     * @param  Mage_Core_Controller_Response_Http                 $httpResponse
     */
    protected function sendResponse(PriceWaiter_NYPWidget_Controller_Endpoint_Response $response, Mage_Core_Controller_Response_Http $httpResponse)
    {
        $secret = $this->getSharedSecret();
        $signature = $response->sign($secret);

        $json = $response->getBodyJson();

        $httpResponse->setHttpResponseCode($response->getStatusCode());
        $httpResponse->setHeader('Content-type', self::RESPONSE_CONTENT_TYPE, true);
        $httpResponse->setHeader(self::SIGNATURE_HEADER, $signature, true);

        $about = Mage::helper('nypwidget/about');
        $about->setResponseHeaders($httpResponse);

        $httpResponse->setBody($json);
    }

    /**
     * Returns a "not found" page as though this route did not even exist...
     * @param  Mage_Core_Controller_Response_Http $response
     */
    protected function silentNotFound(Mage_Core_Controller_Response_Http $httpResponse)
    {
        $httpResponse->setHttpResponseCode(404);
        $this->norouteAction();
    }
}
