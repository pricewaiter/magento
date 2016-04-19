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
     * @internal Adds items to the given order and calculates final total.
     * @param Mage_Sales_Model_Order       $order    [description]
     * @param Array                        $request  [description]
     * @param Mage_Core_Model_Store        $store    [description]
     * @param Mage_Customer_Model_Customer $customer [description]
     */
    public function addItemsToOrder(
        Mage_Sales_Model_Order $order,
        Array $request,
        Mage_Core_Model_Store $store,
        Mage_Customer_Model_Customer $customer
    )
    {
        $product = $this->findProduct($request);

        if (!$product) {
            throw new PriceWaiter_NYPWidget_Exception_Product_NotFound();
        }

        // Build the pricing information of the product
        $subTotal = 0;
        $rowTotal = ($request['unit_price'] * $request['quantity']) + $request['tax'];

        $orderItem = Mage::getModel('sales/order_item')
            ->setStoreId($store->getId())
            ->setQuoteItemId(0)
            ->setQuoteParentItemId(null)
            ->setProductId($product->getId())
            ->setProductType($product->getTypeId())
            ->setQtyBackordered(null)
            ->setTotalQtyOrdered($request['quantity'])
            ->setQtyOrdered($request['quantity'])
            ->setName($product->getName())
            ->setSku($product->getSku())
            ->setPrice($request['unit_price'])
            ->setBasePrice($request['unit_price'])
            ->setOriginalPrice($product->getPrice())
            ->setTaxAmount($request['tax'])
            ->setRowTotal($rowTotal)
            ->setBaseRowTotal($request['unit_price'] * $request['quantity']);

        $additionalOptions = array();
        foreach($this->buildProductOptionsArray($request) as $option => $value) {
            $additionalOptions[] = array(
                'label' => $request['product_option_name' . $i],
                'value' => $request['product_option_value' . $i]
            );
        }

        $orderItem->setProductOptions(array('additional_options' => $additionalOptions));

        // Build and apply the order totals
        $subTotal += $rowTotal;
        $order->addItem($orderItem);

        $order->setSubtotal($subTotal)
            ->setBaseSubtotal($subTotal)
            ->setGrandTotal($subTotal + $request['shipping'])
            ->setBaseGrandTotal($subTotal + $request['shipping']);
    }

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
     * @internal  Assembles a Mage_Sales_Model_Order out of all incoming request data.
     * @param  Array                        $request
     * @param  Mage_Core_Model_Store        $store
     * @param  Mage_Customer_Model_Customer $customer
     * @return Mage_Sales_Model_Order
     */
    public function buildMagentoOrder(Array $request, Mage_Core_Model_Store $store, Mage_Customer_Model_Customer $customer)
    {
        $reservedOrderId = Mage::getSingleton('eav/config')
            ->getEntityType('order')
            ->fetchNewIncrementId($store->getId());

        $currencyCode = $request['currency'];

        $order = Mage::getModel('sales/order')
            // System fields
            ->setIncrementId($reservedOrderId)
            ->setStoreId($store->getId())
            ->setQuoteId(0)
            ->setGlobalCurrencyCode($currencyCode)
            ->setBaseCurrencyCode($currencyCode)
            ->setStoreCurrencyCode($currencyCode)
            ->setOrderCurrencyCode($currencyCode)

            // Customer
            ->setCustomerEmail($customer->getEmail())
            ->setCustomerFirstname($customer->getFirstname())
            ->setCustomerLastname($customer->getLastname())
            ->setCustomerGroupId($customer->getGroupId())
            ->setCustomerIsGuest(0)
            ->setCustomer($customer)

            // Addresses
            ->setBillingAddress($this->buildAddress(
                'billing',
                $request,
                $customer,
                $store
            ))
            ->setShippingAddress($this->buildAddress(
                'shipping',
                $request,
                $customer,
                $store
            ));

        $this->setOrderShippingMethod($order, $request, $store, $customer);

        $this->setOrderPaymentMethod($order, $request, $store, $customer);

        $this->addItemsToOrder($order, $request, $store, $customer);

        $order->addStatusHistoryComment("This order has been programmatically created by the PriceWaiter Name Your Price Widget.");

        return $order;
    }

    /**
     * @internal
     * Assembles an array of product options in the format array("name" => "value", "name" => "value")
     * @param  Array  $request
     */
    public function buildProductOptionsArray(Array $request)
    {
        $options = array();

        // Decode product_option_name<N> and product_option_value<N>-style incoming data.
        for ($i = $request['product_option_count']; $i > 0; $i--) {
            $nameKey = "product_option_name{$i}";
            $name = $request[$nameKey];

            $valueKey = "product_option_value{$i}";
            $value = $request[$valueKey];

            $requestOptions[$name] = $value;
        }

        return $options;
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
     * @internal Resolves the product for an incoming request.
     * @param  Array  $request
     * @return Mage_Catalog_Model_Product|false
     */
    public function findProduct(Array $request)
    {
        $productSku = $request['product_sku'];
        $productOptions = $this->buildProductOptionsArray($request);

        $product = $this->getHelper()->getProductWithOptions($productSku, $productOptions);

        return $product->getId() ? $product : false;
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

        $customer = $this->findOrCreateCustomer($request, $store);

        $order = $this->buildMagentoOrder($request, $store, $customer);

        // Ok, done with the order.
        $this->saveAndPlaceOrder($order);

        // --- After this point, we have a *live* order in the system. ---

        $this->recordOrderCreation($request, $order);

        $this->sendNewOrderEmail($order, $store);

        if ($this->isTestOrder($request)) {
            $this->cancelOrder($order);
        } else {
            $this->captureInvoice($order);
        }

        return $order;
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
     * @param  Mage_Sales_Model_Order $order
     * @param  Mage_Core_Model_Store  $store
     * @return Boolean Whether email was sent.
     */
    public function sendNewOrderEmail(Mage_Sales_Model_Order $order, Mage_Core_Model_Store $store)
    {
        if (!$store->getConfig('pricewaiter/customer_interaction/send_new_order_email')) {
            return false;
        }

        $order->sendNewOrderEmail();
        return true;
    }

    /**
     * @internal Assigns details around order payment.
     * @param Mage_Sales_Model_Order       $order
     * @param Array                        $request
     * @param Mage_Core_Model_Store        $store
     * @param Mage_Customer_Model_Customer $customer
     */
    public function setOrderPaymentMethod(
        Mage_Sales_Model_Order $order,
        Array $request,
        Mage_Core_Model_Store $store,
        Mage_Customer_Model_Customer $customer
    )
    {
        // Add PriceWaiter payment method
        $orderPayment = Mage::getModel('sales/order_payment')
            ->setStoreId($store->getId())
            ->setCustomerPaymentId(0)
            ->setMethod('nypwidget');

        $order->setPayment($orderPayment);
    }

    /**
     * @internal  Assigns details around shipping method to $order.
     * @param Mage_Sales_Model_Order       $order
     * @param Array                        $request
     * @param Mage_Core_Model_Store        $store
     * @param Mage_Customer_Model_Customer $customer
     */
    public function setOrderShippingMethod(
        Mage_Sales_Model_Order $order,
        Array $request,
        Mage_Core_Model_Store $store,
        Mage_Customer_Model_Customer $customer
    )
    {
        // Use PriceWaiter shipping method
        $order->setShippingMethod('nypwidget_nypwidget')
            ->setShippingAmount($request['shipping'])
            ->setShippingDescription('PriceWaiter');
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
     * @param  Mage_Sales_Model_Order $order
     */
    protected function cancelOrder(Mage_Sales_Model_Order $order)
    {
        $order->cancel();
        $order->save();
    }

    /**
     * @internal
     * @param  Mage_Sales_Model_Order $order
     */
    protected function captureInvoice(Mage_Sales_Model_Order $order)
    {
        $invoiceId = Mage::getModel('sales/order_invoice_api')
            ->create($order->getIncrementId(), array());

        $invoice = Mage::getModel('sales/order_invoice')
            ->loadByIncrementId($invoiceId);

        $invoice->capture()->save();
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

    /**
     * Stores a record in the db indicating we've processed $request into $order. This is used for
     * duplicate order detection.
     * @param  Array                  $request
     * @param  Mage_Sales_Model_Order $order
     */
    protected function recordOrderCreation(Array $request, Mage_Sales_Model_Order $order)
    {
        Mage::getModel('nypwidget/order')
            ->setStoreId($order->getStoreId())
            ->setPricewaiterId($request['pricewaiter_id'])
            ->setOrderId($order->getId())
            ->save();
    }

    /**
     * @param  Mage_Sales_Model_Order $order
     */
    protected function saveAndPlaceOrder(Mage_Sales_Model_Order $order)
    {
        $transaction = Mage::getModel('core/resource_transaction');
        $transaction->addObject($order);
        $transaction->addCommitCallback(array($order, 'place'));
        $transaction->addCommitCallback(array($order, 'save'));
        $transaction->save();
    }

    private function _log($message)
    {
        if (Mage::getStoreConfig('pricewaiter/configuration/log')) {
            Mage::log($message, null, "PriceWaiter_Callback.log");
        }
    }
}
