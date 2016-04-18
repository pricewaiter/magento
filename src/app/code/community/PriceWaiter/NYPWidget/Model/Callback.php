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

class PriceWaiter_NYPWidget_Model_Callback
{
    /**
     * @var PriceWaiter_NYPWidget_Helper_Orders
     */
    private $_orderHelper = null;

    /**
     * @var PriceWaiter_NYPWidget_Helper_Data
     */
    private $_helper = null;

    /**
     * @internal Probably redundant, but don't want to tie our field names to internal Magento constants.
     * @var array
     */
    protected static $magentoAddressTypes = array(
        'billing' => Mage_Sales_Model_Quote_Address::TYPE_BILLING,
        'shipping' => Mage_Sales_Model_Quote_Address::TYPE_SHIPPING
    );

    /**
     * @internal
     * @var string
     */
    protected $passwordCharacters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';

    /**
     * @internal Constructs address models based on request data.
     * @param  String                       $type     'billing' or 'shipping'.
     * @param  Array                        $request
     * @param  Mage_Customer_Model_Customer $customer
     * @param  Mage_Core_Model_Store        $store
     * @return Mage_Sales_Model_Order_Address
     * @throws PriceWaiter_NYPWidget_Exception_InvalidRegion
     */
    public function buildAddress($type, Array $request, Mage_Customer_Model_Customer $customer, Mage_Core_Model_Store $store)
    {
        $state = $request["buyer_{$type}_state"];
        $country = $request["buyer_{$type}_country"];

        // Resolve state + country into a Mage_Directory_Model_Region
        $regionModel = Mage::getModel('directory/region')
            ->loadByCode($state, $country);

        if (!$regionModel->getId()) {
            throw new PriceWaiter_NYPWidget_Exception_InvalidRegion($state, $country);
        }

        return Mage::getModel('sales/order_address')
            // System data
            ->setStoreId($store->getId())
            ->setAddressType(self::$magentoAddressTypes[$type])

            // Customer + name
            ->setCustomerId($customer->getId())
            ->setPrefix('')
            ->setFirstname($request["buyer_{$type}_first_name"])
            ->setMiddlename('')
            ->setLastname($request["buyer_{$type}_last_name"])
            ->setSuffix('')
            ->setCompany('')

            // Actual address
            ->setStreet(array_filter(array(
                $request["buyer_{$type}_address"],
                $request["buyer_{$type}_address2"],
                $request["buyer_{$type}_address3"],
            )))
            ->setCity($request["buyer_{$type}_city"])
            //->setRegion($state)
            ->setRegion_id($regionModel->getId())
            ->setPostcode($request["buyer_{$type}_zip"])
            ->setCountry_id($country)

            // Phone numbers
            ->setTelephone($request["buyer_{$type}_phone"])
            ->setFax('');
    }

    /**
     * @internal Creates a new customer, saves it, and returns it.
     * @param  Array                 $request
     * @param  Mage_Core_Model_Store $store
     * @return Mage_Customer_Model_Customer
     */
    public function createCustomer(Array $request, Mage_Core_Model_Store $store)
    {
        $customer = Mage::getModel('customer/customer')
            // System
            ->setWebsiteId($store->getWebsiteId())
            ->setPassword($this->generatePassword(10))
            ->setConfirmation(null)

            // Person
            ->setEmail($request['buyer_email'])
            ->setFirstname($request['buyer_first_name'])
            ->setLastname($request['buyer_last_name']);

        $customer->save();

        $this->sendNewAccountEmail($customer, $store);

        return $customer;
    }

    /**
     * @internal Either returns an existing customer (by email) or creates a new one.
     * @param  Array                 $request
     * @param  Mage_Core_Model_Store $store
     * @return Mage_Customer_Model_Customer
     */
    public function findOrCreateCustomer(Array $request, Mage_Core_Model_Store $store)
    {
        $customer = Mage::getModel('customer/customer')
            ->setWebsiteId($store->getWebsiteId())
            ->loadByEmail($request['buyer_email']);

        if ($customer->getId()) {
            // This an existing customer for this Magento store.
            return $customer;
        }

        return $this->createCustomer($request, $store);
    }

    /**
     * Looks for an existing order that matches the given order callback request.
     * @param  Array $request
     * @return PriceWaiter_NYPWidget_Model_Order|false
     */
    public function getExistingOrder(Array $request)
    {
        $existingOrder = Mage::getModel('nypwidget/order');
        $existingOrder->loadByPriceWaiterId($request['pricewaiter_id']);

        if ($existingOrder->getId()) {
            return $existingOrder;
        }

        return false;
    }

    /**
     * @param  Array  $request
     * @return Mage_Core_Model_Store
     * @throws PriceWaiter_NYPWidget_Exception_ApiKey If no store configured for API key.
     */
    public function getStore(Array $request)
    {
        $apiKey = isset($request['api_key']) ? $request['api_key'] : null;
        $store = false;

        if ($apiKey) {
            $store = $this->getHelper()->getStoreByPriceWaiterApiKey($apiKey);
        }

        if (!$store) {
            throw new PriceWaiter_NYPWidget_Exception_ApiKey();
        }

        return $store;
    }

    /**
     * @return boolean
     */
    public function isTestOrder(Array $request)
    {
        return !empty($request['test']);
    }

    public function logIncomingOrder(Array $request)
    {
        $message = "The Name Your Price Widget has received a valid order notification.";

        if ($this->isTestOrder($request)) {
            $message .= ' This is a TEST order that will be created and canceled.';
        }

        $this->getHelper()->log($message);
    }

    /**
     * @param  Array  $request
     * @return Mage_Sales_Model_Order
     */
    public function processRequest(Array $request)
    {
        $orderHelper = $this->getOrderHelper();
        $orderHelper->verifyPriceWaiterOrderData($request);

        $store = $this->getStore($request);

        $existingOrder = $this->getExistingOrder($request);
        if ($existingOrder) {
            throw new PriceWaiter_NYPWidget_Exception_DuplicateOrder($existingOrder);
        }

        try {

            $customer = $this->findOrCreateCustomer($request, $store);

            $transaction = Mage::getModel('core/resource_transaction');
            $reservedOrderId = Mage::getSingleton('eav/config')->getEntityType('order')->fetchNewIncrementId($store->getId());

            // Grab the currency code from the request, if one is set.
            // Otherwise, use the store's default currency code.
            if ($request['currency']) {
                $currencyCode = $request['currency'];
            } else {
                $currencyCode = $store->getDefaultCurrencyCode();
            }

            $order = Mage::getModel('sales/order')
                ->setIncrementId($reservedOrderId)
                ->setStoreId($store->getId())
                ->setQuoteId(0)
                ->setGlobal_currency_code($currencyCode)
                ->setBase_currency_code($currencyCode)
                ->setStore_currency_code($currencyCode)
                ->setOrder_currency_code($currencyCode);

            // set Customer data
            $order->setCustomer_email($customer->getEmail())
                ->setCustomerFirstname($customer->getFirstname())
                ->setCustomerLastname($customer->getLastname())
                ->setCustomerGroupId($customer->getGroupId())
                ->setCustomer_is_guest(0)
                ->setCustomer($customer);

            // set Billing Address
            $order->setBillingAddress($this->buildAddress(
                'billing',
                $request,
                $customer,
                $store
            ));

            $order->setShippingAddress($this->buildAddress(
                'shipping',
                $request,
                $customer,
                $store
            ));

            // Add PriceWaiter shipping method
            $order
                ->setShipping_method('nypwidget_nypwidget')
                ->setShipping_amount($request['shipping'])
                ->setShippingDescription('PriceWaiter');

            // Add PriceWaiter payment method
            $orderPayment = Mage::getModel('sales/order_payment')
                ->setStoreId($store->getId())
                ->setCustomerPaymentId(0)
                ->setMethod('nypwidget');
            $order->setPayment($orderPayment);

            // Find the Product from the request
            $requestOptions = array();

            for ($i = $request['product_option_count']; $i > 0; $i--) {
                $requestOptions[$request['product_option_name' . $i]] = $request['product_option_value' . $i];
            }

            $this->_product = Mage::helper('nypwidget')->getProductWithOptions($request['product_sku'], $requestOptions);

            // Build the pricing information of the product
            $subTotal = 0;
            $rowTotal = ($request['unit_price'] * $request['quantity']) + $request['tax'];
            $itemDiscount = ($this->_product->getPrice() - $request['unit_price']);

            $orderItem = Mage::getModel('sales/order_item')
                ->setStoreId($store->getId())
                ->setQuoteItemId(0)
                ->setQuoteParentItemId(NULL)
                ->setProductId($this->_product->getId())
                ->setProductType($this->_product->getTypeId())
                ->setQtyBackordered(NULL)
                ->setTotalQtyOrdered($request['quantity'])
                ->setQtyOrdered($request['quantity'])
                ->setName($this->_product->getName())
                ->setSku($this->_product->getSku())
                ->setPrice($request['unit_price'])
                ->setBasePrice($request['unit_price'])
                ->setOriginalPrice($this->_product->getPrice())
                // ->setDiscountAmount($itemDiscount)
                ->setTaxAmount($request['tax'])
                ->setRowTotal($rowTotal)
                ->setBaseRowTotal($rowTotal);

            // Do we have a simple product with custom options, a bundle product, or a grouped product?
            if (($this->_product->getTypeId() == 'simple'
                    || $this->_product->getTypeId() == 'bundle'
                    || $this->_product->getTypeId() == 'grouped')
                && $request['product_option_count'] > 0
            ) {
                // Grab the options from the request, build $additionalOptions array
                $additionalOptions = array();
                for ($i = $request['product_option_count']; $i > 0; $i--) {
                    $addressitionalOptions[] = array(
                        'label' => $request['product_option_name' . $i],
                        'value' => $request['product_option_value' . $i]
                    );
                }

                // Apply the $additionalOptions array to the simple product
                $orderItem->setProductOptions(array('additional_options' => $additionalOptions));
            }

            // Build and apply the order totals
            $subTotal += $rowTotal;
            $order->addItem($orderItem);

            $order->setSubtotal($subTotal)
                ->setBaseSubtotal($subTotal)
                ->setGrandTotal($subTotal + $request['shipping'])
                ->setBaseGrandTotal($subTotal + $request['shipping']);

            $order->addStatusHistoryComment("This order has been programmatically created by the PriceWaiter Name Your Price Widget.");

            // Ok, done with the order.
            $transaction->addObject($order);
            $transaction->addCommitCallback(array($order, 'place'));
            $transaction->addCommitCallback(array($order, 'save'));
            $transaction->save();

            // Add this order to the list of received callback orders
            $pricewaiterOrder = Mage::getModel('nypwidget/order');
            $pricewaiterOrder->setData(array(
                'store_id' => $order->getStoreId(),
                'pricewaiter_id' => $request['pricewaiter_id'],
                'order_id' => $order->getId()
            ));
            $pricewaiterOrder->save();

            if (Mage::getStoreConfig('pricewaiter/customer_interaction/send_new_order_email')) {
                $order->sendNewOrderEmail();
            }


            // If this is a test order, cancel it to prevent it from any further processing.
            if ($this->_test) {
                $order->cancel();
                $order->save();
                return;
            }

            // Capture the invoice
            $invoiceId = Mage::getModel('sales/order_invoice_api')
                ->create($order->getIncrementId(), array());
            $invoice = Mage::getModel('sales/order_invoice')
                ->loadByIncrementId($invoiceId);
            $invoice->capture()->save();


            Mage::log("The Name Your Price Widget has created order #"
                . $order->getIncrementId() . " with order ID " . $order->getId());
            $this->_log("The Name Your Price Widget has created order #"
                . $order->getIncrementId() . " with order ID " . $order->getId());

            return $order;

        } catch (Exception $e) {
            // TODO: Move this to controller
            // Mage::app()->getResponse()->setHeader('HTTP/1.0 500 Internal Server Error', 500, true);
            // Mage::app()->getResponse()->setHeader('X-Platform-Error', $e->getMessage(), true);
            // $this->_log("PriceWaiter Name Your Price Widget was unable to create order. Check log for details.");
            // $this->_log($e->getMessage());
            throw $e;
        }
    }

    /**
     *
     * @param  Mage_Customer_Model_Customer $customer
     * @return Boolean Whether email was sent.
     */
    public function sendNewAccountEmail(Mage_Customer_Model_Customer $customer, Mage_Core_Model_Store $store)
    {
        if (!$store->getConfig('pricewaiter/customer_interaction/send_welcome_email')) {
            return false;
        }

        $customer->sendNewAccountEmail('registered', '', $store->getId());
        return true;
    }

    /**
     * @internal
     * @return PriceWaiter_NYPWidget_Helper_Data
     */
    public function getHelper()
    {
        if ($this->_helper === null) {
            $this->_helper = Mage::helper('nypwidget');
        }
        return $this->_helper;
    }

    /**
     * @internal
     * @param PriceWaiter_NYPWidget_Helper_Data $helper
     * @return PriceWaiter_NYPWidget_Model_Callback $this
     */
    public function setHelper(PriceWaiter_NYPWidget_Helper_Data $helper)
    {
        $this->_helper = $helper;
        return $this;
    }

    /**
     * @internal
     * @return PriceWaiter_NYPWidget_Helper_Orders
     */
    public function getOrderHelper()
    {
        if ($this->_orderHelper === null) {
            $this->_orderHelper = Mage::getHelper('nypwidget/orders');
        }
        return $this->_orderHelper;
    }

    /**
     * @internal
     * @param PriceWaiter_NYPWidget_Helper_Orders $helper
     * @return PriceWaiter_NYPWidget_Model_Callback $this
     */
    public function setOrderHelper(PriceWaiter_NYPWidget_Helper_Orders $helper)
    {
        $this->_orderHelper = $helper;
        return $this;
    }

    /**
     * @internal
     * @param  Integer $length
     * @return String
     */
    protected function generatePassword($length)
    {
        $password = [];

        $numberOfChars = strlen($this->passwordCharacters);

        for ($i = 0; $i < $length; $i++) {
            $password[] = $this->passwordCharacters[mt_rand(0, $numberOfChars)];
        }

        return implode('', $password);
    }

    private function _log($message)
    {
        if (Mage::getStoreConfig('pricewaiter/configuration/log')) {
            Mage::log($message, null, "PriceWaiter_Callback.log");
        }
    }
}
