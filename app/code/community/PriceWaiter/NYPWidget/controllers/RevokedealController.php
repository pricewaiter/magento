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
 * Controller that handles /pricewaiter/revokedeal
 */
class PriceWaiter_NYPWidget_RevokedealController extends PriceWaiter_NYPWidget_Controller_Endpoint
{
    /**
     * Versions of request data this controller supports.
     * @var Array
     */
    protected $supportedVersions = array(
        '2016-03-01',
    );

    public function processRequest(PriceWaiter_NYPWidget_Controller_Endpoint_Request $request)
    {
        $body = $request->getBody();
        $id = isset($body->id) ? $body->id : null;

        // Find the Deal
        $deal = Mage::getModel('nypwidget/deal')->load($id);

        if (!$deal->getId()) {
            throw new PriceWaiter_NYPWidget_Exception_DealNotFound();
        }

        $deal->processRevokeRequest($request);

        return PriceWaiter_NYPWidget_Controller_Endpoint_Response::ok();
    }

}
