<?php

/*
 * Copyright 2013-2015 Price Waiter, LLC
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

class PriceWaiter_NYPWidget_CallbackController extends Mage_Core_Controller_Front_Action
{

    public function indexAction()
    {
        if (!$this->getRequest()->isPost()) {
            $this->_log("HTTP Request is invalid.");
            return;
        }

        Mage::helper('nypwidget')->setHeaders();

        try {
            $request = $this->getRequest()->getPost();
            if (!array_key_exists('pricewaiter_id', $request)) {
                $this->_log($request);
                $this->_log("PriceWaiter Notification is missing required fields.");
                return;
            }
            $this->_log("Incoming PriceWaiter order notification.");
            $this->_log($request);

            Mage::getModel('nypwidget/callback')->processRequest($request);

            // TODO: Add order Increment ID to response
            // Mage::app()->getResponse()->setHeader('X-Platform-Order-Id', $order->getIncrementId());

            // TODO: Logging
            //
            // Mage::log("The Name Your Price Widget has created order #"
            //     . $order->getIncrementId() . " with order ID " . $order->getId());
            // $this->_log("The Name Your Price Widget has created order #"
            //     . $order->getIncrementId() . " with order ID " . $order->getId());

        } catch (Exception $e) {

            // TODO: This kind of stuff
            // Mage::app()->getResponse()->setHeader('HTTP/1.0 500 Internal Server Error', 500, true);
            // Mage::app()->getResponse()->setHeader('X-Platform-Error', $e->getMessage(), true);
            // $this->_log("PriceWaiter Name Your Price Widget was unable to create order. Check log for details.");
            // $this->_log($e->getMessage());

            Mage::logException($e);
            $this->_log($e);
        }
    }

    private function _log($message)
    {
        if (Mage::getStoreConfig('pricewaiter/configuration/log')) {
            Mage::log($message, null, "PriceWaiter_Callback.log");
        }
    }

}
