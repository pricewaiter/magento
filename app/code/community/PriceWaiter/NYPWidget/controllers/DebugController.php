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

class PriceWaiter_NYPWidget_DebugController extends PriceWaiter_NYPWidget_Controller_Endpoint
{
    /**
     * @var Array
     */
    protected $supportedVersions = array(
        '2016-03-01',
    );

    public function processRequest(PriceWaiter_NYPWidget_Controller_Endpoint_Request $request)
    {
        $resp = array(
            'store' => $this->summarizeStore(),
            'total_models' => $this->summarizeTotalModels(),
        );

        return new PriceWaiter_NYPWidget_Controller_Endpoint_Response(200, $resp);
    }

    /**
     * Returns a summary of the store config.
     * @return Array
     */
    protected function summarizeStore()
    {
        $store = Mage::app()->getStore();
        $helper = Mage::helper('nypwidget');

        return array(
            'name' => $store->getName(),
            'code' => $store->getCode(),
            'pricewaiter_api_key' => $helper->getPriceWaiterApiKey($store),
            'pricewaiter_secret_set' => !!trim($helper->getSecret($store)),
        );
    }

    /**
     * Returns a summary of the available total models and their configuration.
     * @return Array
     */
    protected function summarizeTotalModels()
    {
        $collector = Mage::getSingleton(
            'sales/quote_address_total_collector',
            array('store' => Mage::app()->getStore())
        );

        $result = array();
        foreach($collector->getCollectors() as $code => $collector) {
            $result[] = array(
                'code' => $code,
                'class' => get_class($collector),
            );
        }

        return $result;
    }
}
