<?php
/*
 * Copyright 2012 PriceWaiter, LLC
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *      http://www.apache.org/licenses/LICENSE-2.0
 *
 *  Unless required by applicable law or agreed to in writing, software
 *  distributed under the License is distributed on an "AS IS" BASIS,
 *  WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 *  See the License for the specific language governing permissions and
 *  limitations under the License.
 *
 */
class PriceWaiter_NYPWidget_Model_Callback
{

    private $_storeId = '1';
    private $_groupId = '1';
    private $_sendConfirmation = '0';

    private $orderData = array();
    private $_product;

    private $_sourceCustomer;
    private $_sourceOrder;

    public function processRequest($request)
    {
        // If the PriceWaiter extension is in testing mode, skip request validation
    	if (!Mage::helper('nypwidget')->isTesting()) {
	        // Build URL to check validity of order notification.
    		$url = Mage::helper('nypwidget')->getApiUrl();

    		$ch = curl_init($url);
    		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    		curl_setopt($ch, CURLOPT_POST, true);
    		curl_setopt($ch, CURLOPT_POSTFIELDS, $request);

	        // If PriceWaiter returns an invalid response
    		if (curl_exec($ch) == "1") {
    			$message = "The Name Your Price Widget has received a valid order notification.";
    			Mage::log($message);
    			$this->_log($message);
    		} else {
    			$message = "An invalid PriceWaiter order notification has been received.";
    			Mage::log($message);
    			$this->_log($message);
    			return;
    		}
    	}

        // Notification has been verified. Create an order from request data.

        // Is this an existing customer?
        $customer = Mage::getModel('customer/customer');
        $customer->setWebsiteId(Mage::app()->getStore()->getWebsiteId());
        $customer->loadByEmail($request['buyer_email']);
        preg_match('#^(\w+\.)?\s*([\'\’\w]+)\s+([\'\’\w]+)\s*(\w+\.?)?$#',$request['buyer_name'] , $name);
        $request['buyer_first_name'] = $name[2];
        $request['buyer_last_name'] = $name[3];

        if (!$customer->getId()) {
            // Create a new customer with this email
            $customer->reset();
            $passwordLength = 10;
            $passwordCharacters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
            $password = '';
            for ($p = 0; $p < $passwordLength; $p++) {
                $password .= $passwordCharacters[mt_rand(0, strlen($passwordCharacters))];
            }

            $customer->setEmail($request['buyer_email']);
            $customer->setFirstname($name[2]);
            $customer->setLastname($name[3]);
            $customer->setPassword($password);
            $customer->setConfirmation(null);
            $customer->save();
            $customer->load($customer->getId());
        }

        $order = $this->setOrderInfo($request, $customer);
        $order = $this->create();

        Mage::log("The Name Your Price Widget has created order #"
            . $order->getIncrementId() . " with order ID " . $order->getId());
        $this->_log("The Name Your Price Widget has created order #"
            . $order->getIncrementId() . " with order ID " . $order->getId());
    }

    private function _log($message)
    {
        if (Mage::getStoreConfig('pricewaiter/configuration/log')) {
            Mage::log($message, null, "PriceWaiter_Callback.log");
        }
    }

    public function setOrderInfo($request, Mage_Customer_Model_Customer $sourceCustomer)
    {
        $this->_sourceOrder = $request;
        $this->_sourceCustomer = $sourceCustomer;

        //You can extract/refactor this if you have more than one product, etc.
        $this->_product = Mage::getModel('catalog/product')->getCollection()
            ->addAttributeToFilter('sku', $request['product_sku'])
            ->addAttributeToSelect('*')
            ->getFirstItem();

        //Get a phone number, or make a dummy one
        if ($request['buyer_shipping_phone']) {
            $telephone = $request['buyer_shipping_phone'];
        } else {
            $telephone = "000-000-0000";
        }

        //Load full product data to product object
        $this->_product->load($this->_product->getId());

        // If this is a configurable product, find the corresponding simple
        if ($this->_product->getTypeId() == 'configurable') {
            // Do configurable product specific stuff
            $attrs  = $this->_product->getTypeInstance(true)->getConfigurableAttributesAsArray($this->_product);
            $requestOptions = array();

            // Split out all of the product options from the request
            for ($i = 1; $i < 100; $i++) {
                if (array_key_exists("product_option_name" . $i, $request)) {
                    $requestOptions[$request['product_option_name' . $i]] = $request['product_option_value' . $i];
                } else {
                    break;
                }
            }

            foreach ($attrs as $attr) {
                if (array_key_exists($attr['label'], $requestOptions)) {
                    //$value = $requestOptions[$attr['label']];
                    foreach ($attr['values'] as $value) {
                        if ($value['label'] == $requestOptions[$attr['label']]) {
                            $valueIndex = $value['value_index'];
                            break;
                        }
                    }
                    unset($requestOptions[$attr['label']]);
                    $requestOptions[$attr['attribute_id']] = $valueIndex;
                    //$requestOptions[$attr['attribute_id']] = $value;
                }
            }

            $parentProduct = $this->_product;
            $this->_product = $this->_product->getTypeInstance()->getProductByAttributes($requestOptions, $this->_product);
            $this->_product->load($this->_product->getId());
        }

        $itemDiscount = ($this->_product->getPrice() - $request['unit_price']);

        $this->orderData = array(
            'session'       => array(
                'customer_id'   => $this->_sourceCustomer->getId(),
                'store_id'      => $this->_storeId,
            ),
            'payment'       => array(
                'method'    => 'nypwidget',
            ),
            'add_products'  =>array(
                $this->_product->getId() => array('qty' => $request['quantity']),
            ),
            'item_tax' => $request['tax'],
            'item_discount' => ($itemDiscount * $request['quantity']),
            'order' => array(
                'currency' => 'USD',
                'account' => array(
                    'group_id' => $this->_groupId,
                    'email' => $this->_sourceCustomer->getEmail()
                ),
                'billing_address' => array(
                    'customer_address_id' => $this->_sourceCustomer->getCustomerAddressId(),
                    'prefix' => '',
                    'firstname' => $request['buyer_first_name'],
                    'middlename' => '',
                    'lastname' => $request['buyer_last_name'],
                    'suffix' => '',
                    'company' => '',
                    'street' => array($request['buyer_shipping_address'],$request['buyer_shipping_address2']),
                    'city' => $request['buyer_shipping_city'],
                    'country_id' => $request['buyer_shipping_country'],
                    'region' => '',
                    'region_id' => $request['buyer_shipping_state'],
                    'postcode' => $request['buyer_shipping_zip'],
                    'telephone' => $telephone,
                    'fax' => '',
                ),
                'shipping_address' => array(
                    'customer_address_id' => $this->_sourceCustomer->getCustomerAddressId(),
                    'prefix' => '',
                    'firstname' => $request['buyer_first_name'],
                    'middlename' => '',
                    'lastname' => $request['buyer_last_name'],
                    'suffix' => '',
                    'company' => '',
                    'street' => array($request['buyer_shipping_address'],$request['buyer_shipping_address2']),
                    'city' => $request['buyer_shipping_city'],
                    'country_id' => $request['buyer_shipping_country'],
                    'region' => '',
                    'region_id' => $request['buyer_shipping_state'],
                    'postcode' => $request['buyer_shipping_zip'],
                    'telephone' => $telephone,
                    'fax' => '',
                    'shipping_amount' => $request['shipping'],
                ),
                'shipping_method' => 'nypwidget_nypwidget',
                'comment' => array(
                    'customer_note' => 'This order has been programmatically created by the PriceWaiter Name Your Price Widget.',
                ),
                'send_confirmation' => $this->_sendConfirmation
            ),
        );
    }

    /**
    * Retrieve order create model
    *
    * @return  Mage_Adminhtml_Model_Sales_Order_Create
    */
    protected function _getOrderCreateModel()
    {
        return Mage::getSingleton('adminhtml/sales_order_create');
    }

    /**
    * Retrieve session object
    *
    * @return Mage_Adminhtml_Model_Session_Quote
    */
    protected function _getSession()
    {
        return Mage::getSingleton('adminhtml/session_quote');
    }

    /**
    * Initialize order creation session data
    *
    * @param array $data
    * @return Mage_Adminhtml_Sales_Order_CreateController
    */
    protected function _initSession($data)
    {
        /* Get/identify customer */
        if (!empty($data['customer_id'])) {
            $this->_getSession()->setCustomerId((int) $data['customer_id']);
        }

        /* Get/identify store */
        if (!empty($data['store_id'])) {
            $this->_getSession()->setStoreId((int) $data['store_id']);
        }

        return $this;
    }

    /**
    * Creates order
    */
    public function create()
    {
        $orderData = $this->orderData;

        if (!empty($orderData)) {

            $this->_initSession($orderData['session']);

            try {
                $this->_processQuote($orderData);
                if (!empty($orderData['payment'])) {
                    $this->_getOrderCreateModel()->setPaymentData($orderData['payment']);
                    $this->_getOrderCreateModel()->getQuote()->getPayment()->addData($orderData['payment']);
                }

                $item = $this->_getOrderCreateModel()->getQuote()->getItemByProduct($this->_product);
                $item->setTaxAmount($orderData['item_tax']);
                $item->setDiscountAmount($orderData['item_discount']);
                $item->setBaseTaxAmount($orderData['item_tax']);
                $item->setBaseDiscountAmount($orderData['item_discount']);

                Mage::app()->getStore()->setConfig(Mage_Sales_Model_Order::XML_PATH_EMAIL_ENABLED, "0");

                $orderTotal = $orderData['order']['shipping_address']['shipping_amount']
                    + $this->_getOrderCreateModel()->getQuote()->getGrandTotal()
                    + $orderData['item_tax'] - $orderData['item_discount'];

                $_order = $this->_getOrderCreateModel()
                    ->importPostData($orderData['order'])
                    ->createOrder();

                $_order = $_order
                    ->setSubtotal($_order->getSubtotal() + $orderData['item_tax'])
                    ->setBaseSubtotal($_order->getBaseSubtotal() + $orderData['item_tax'])
                    ->setGrandTotal($orderTotal)
                    ->setBaseGrandTotal($orderTotal)
                    ->save();

                $invoiceId = Mage::getModel('sales/order_invoice_api')
                    ->create($_order->getIncrementId(), array());
                $invoice = Mage::getModel('sales/order_invoice')
                    ->loadByIncrementId($invoiceId);
                $invoice->capture()->save();

                $this->_getSession()->clear();
                Mage::unregister('rule_data');

                return $_order;
            }
            catch (Exception $e){
                $this->_log("PriceWaiter Name Your Price Widget was unable to create order. Check log for details.");
                $this->_log($e->getMessage());
            }
        }

        return null;
    }

    protected function _processQuote($data = array())
    {
        /* Saving order data */
        if (!empty($data['order'])) {
            $this->_getOrderCreateModel()->importPostData($data['order']);
        }

        $this->_getOrderCreateModel()->getBillingAddress();
        $this->_getOrderCreateModel()->setShippingAsBilling(true);

        /* Just like adding products from Magento admin grid */
        if (!empty($data['add_products'])) {
            $this->_getOrderCreateModel()->addProducts($data['add_products']);
        }

        /* Collect shipping rates */
        $this->_getOrderCreateModel()->collectShippingRates();

        /* Add payment data */
        if (!empty($data['payment'])) {
            $this->_getOrderCreateModel()->getQuote()->getPayment()->addData($data['payment']);
        }

        $this->_getOrderCreateModel()
            ->initRuleData()
            ->saveQuote();

        if (!empty($data['payment'])) {
            $this->_getOrderCreateModel()->getQuote()->getPayment()->addData($data['payment']);
        }

        return $this;
    }
}
