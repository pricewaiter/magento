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

/**
 * PriceWaiter Payment method.
 */
class PriceWaiter_NYPWidget_Model_PaymentMethod extends Mage_Payment_Model_Method_Abstract
{
    protected $_code = 'nypwidget';

    // Custom info block to display payment details on order in admin.
    protected $_infoBlockType = 'nypwidget/payment_info_pricewaiter';

    protected $_isGateway = false;
    protected $_canOrder = true;
    protected $_canAuthorize = true;
    protected $_canCapture = true;
    protected $_canCapturePartial = false;
    protected $_canRefund = false;
    protected $_canVoid = true;
    protected $_canUseInternal = true;
    protected $_canUseCheckout = false;
    protected $_canUseForMultishipping = false;

    private static $_currentOrderCallbackRequest = [];

    public function authorize(Varien_Object $payment, $amount)
    {
        return $this;
    }

    public function capture(Varien_Object $payment, $amount)
    {
        return $this;
    }

    public function void(Varien_Object $payment)
    {
        return $this;
    }

    public function isAvailable($quote = null)
    {
        return false;
    }

    public function getConfigPaymentAction()
    {
        $request = $this->getCurrentOrderCallbackRequest();
        $isTest = !empty($request['test']);

        // For test orders (which will be immediately canceled) we don't
        // want to "capture" payment, since that removes our ability to cancel.
        if ($isTest) {
            return Mage_Payment_Model_Method_Abstract::ACTION_AUTHORIZE;
        }

        return Mage_Payment_Model_Method_Abstract::ACTION_AUTHORIZE_CAPTURE;
    }

    /**
     * @internal
     */
    public static function resetCurrentOrderCallbackRequest()
    {
        self::$_currentOrderCallbackRequest = array();
    }

    /**
     * @internal Hack to allow payment method access to incoming order data.
     * @param Array $request
     */
    public static function setCurrentOrderCallbackRequest(Array $request)
    {
        self::$_currentOrderCallbackRequest = $request;
    }

    /**
     * @internal
     * @return Array
     */
    protected function getCurrentOrderCallbackRequest()
    {
        return self::$_currentOrderCallbackRequest;
    }
}
