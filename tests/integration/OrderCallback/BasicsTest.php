<?php

class TestableCallbackModel extends PriceWaiter_NYPWidget_Model_Callback
{
    public $newOrderEmailsSent = 0;
    public $welcomeEmailsSent = 0;
    public $shouldVerify = true;

    protected function sendWelcomeEmail(Mage_Customer_Model_Customer $customer, Mage_Core_Model_Store $store)
    {
        $sent = parent::sendWelcomeEmail($customer, $store);
        if ($sent) {
            $this->welcomeEmailsSent++;
        }
        return $sent;
    }

    protected function sendNewOrderEmail(Mage_Sales_Model_Order $order, Mage_Core_Model_Store $store)
    {
        $sent = parent::sendNewOrderEmail($order, $store);
        if ($sent) {
            $this->newOrderEmailsSent++;
        }
        return $sent;
    }

    public function verifyRequest(Array $request)
    {
        return $this->shouldVerify;
    }

}

/**
 * Tests around PriceWaiter order callback.
 */
class Integration_OrderCallback_Basics
    extends PHPUnit_Framework_TestCase
{
    public $apiKey = 'MAGENTO';

    public $simpleProduct = array(
        'type' => 'simple',
        'sku' => 'hde012',
        'id' => '399',
        'name' => 'Madison 8GB Digital Media Player',
        'price' => '150.00',
    );

    public $buyer = array(
        'first_name' => 'Ned',
        'last_name' => 'Flanders',
        'name' => 'Ned Flanders',
    );

    public $billing = array(
        'name' => 'Ned Flanders',
        'first_name' => 'Ned',
        'last_name' => 'Flanders',
        'address' => '744 Evergreen Terrace',
        'address2' => 'Floor 1',
        'address3' => 'Apt A',
        'city' => 'Springfield',
        'state' => 'OR',
        'zip' => '12345',
        'phone' => '123-456-7890',
        'country' => 'US',
    );

    public $shipping = array(
        'name' => 'Homer Simpson',
        'first_name' => 'Homer',
        'last_name' => 'Simpson',
        'address' => '742 Evergreen Terrace',
        'address2' => '',
        'address3' => '',
        'city' => 'Springfield',
        'state' => 'OR',
        'zip' => '12345',
        'phone' => '987-654-3210',
        'country' => 'US',
    );

    public $payment = array(
        'method' => 'authorize_net',
        'method_nice' => 'Authorize.net',
        'cc_type' => 'Visa',
        'magento_cc_type' => 'VI',
        'cc_last4' => '4242',
    );

    /**
     * @return Array
     */
    public function buildOrderCallbackRequest()
    {
        $product = $this->simpleProduct;
        $payment = $this->payment;
        $apiKey = $this->apiKey;

        $request = [
            // Default to new customer per-order.
            'buyer_email' => uniqid() . '@example.org',
            'pricewaiter_id' => uniqid(),
            'order_completed_timestamp' => '2015-01-03T14:19:37-07:00',
            'api_key' => $apiKey,
            'payment_method' => $payment['method'],
            'payment_method_nice' => $payment['method_nice'],
            'transaction_id' => uniqid(),
            'currency' => 'USD',
            'quantity' => 2,
            'unit_price' => '99.99',
            'shipping_method' => 'UPS Ground',
            'shipping' => '10.50',
            'tax' => '14.00',
            'cc_type' => $payment['cc_type'],
            'cc_last4' => $payment['cc_last4'],
        ];

        // maths
        $request['total'] = (
            ($request['unit_price'] * $request['quantity']) +
            $request['shipping'] +
            $request['tax']
        );

        foreach($this->buyer as $key => $value) {
            $request["buyer_{$key}"] = $value;
        }

        foreach($this->billing as $key => $value) {
            $request["buyer_billing_{$key}"] = $value;
        }

        foreach($this->shipping as $key => $value) {
            $request["buyer_shipping_{$key}"] = $value;
        }

        $request['product_option_count'] = 0;
        $request['product_sku'] = $product['sku'];

        return $request;
    }

    /**
     * @expectedException PriceWaiter_NYPWidget_Exception_ApiKey
     */
    public function testInvalidApiKeyThrows()
    {
        $callback = new TestableCallbackModel();

        $request = [
            'pricewaiter_id' => '1234',
            'api_key' => 'NOT A REAL API KEY',
        ];

        $callback->processRequest($request);
    }

    /**
     * Makes an order write request and provides the resulting order data
     * to subsequent tests.
     */
    public function testNormalOrderCallback()
    {
        $callback = new TestableCallbackModel();

        $request = $this->buildOrderCallbackRequest();
        $order = $callback->processRequest($request);

        $this->assertInstanceOf(Mage_Sales_Model_Order, $order);

        // Pass valid order on to dependent tests.
        return array($request, $order, $callback);
    }

    /**
     * Makes a *test* order callback and provides the resulting order data to
     * subsequent tests.
     */
    public function testTestOrderCallback()
    {
        $callback = new TestableCallbackModel();

        $request = $this->buildOrderCallbackRequest();
        $request['test'] = '1';

        $order = $callback->processRequest($request);

        $this->assertInstanceOf(Mage_Sales_Model_Order, $order);

        // Pass valid order on to dependent tests.
        return array($request, $order, $callback);
    }

    /**
     * @depends testNormalOrderCallback
     * @expectedException PriceWaiter_NYPWidget_Exception_DuplicateOrder
     */
    public function testDuplicateOrderThrows(Array $args)
    {
        list($request, $order) = $args;

        $pwOrder = Mage::getModel('nypwidget/order')->loadByMagentoOrderId($order->getEntityId());
        $this->assertTrue(!!$pwOrder->getId(), 'PriceWaiter_NYPWidget_Model_Order found');

        $callback = new TestableCallbackModel();

        // Run second order callback with same pricewaiter_id
        $request = $this->buildOrderCallbackRequest();
        $request['pricewaiter_id'] = $pwOrder->getPricewaiterId();

        $callback->processRequest($request);
    }

    /**
     * @depends testNormalOrderCallback
     */
    public function testNewCustomerCreated(Array $args)
    {
        list($request, $order) = $args;

        $customerId = $order->getCustomerId();
        $this->assertNotEmpty($customerId, 'customer_id is set on order');

        $customer = Mage::getModel('customer/customer')
            ->load($customerId);

        $this->assertNotEmpty($customer->getId(), 'customer found');

        // Store -> Website relationship in Magento:
        // Main Website
        //   - Madison Island (Store)
        //     - English (Store View)
        //     - French  (Store View)
        //     - German  (Store View)
        //
        //  Customers are tied to a *website*, orders are made to a *store*

        $this->assertNotNull($customer->getWebsiteId(), 'customer has a website_id');
        $store = $order->getStore();
        $this->assertEquals($store->getWebsiteId(), $customer->getWebsiteId(), 'customer created for correct website');

        $map = [
            'buyer_email' => 'getEmail',
            'buyer_name' => 'getName',
            'buyer_first_name' => 'getFirstname',
            'buyer_last_name' => 'getLastname',
        ];
        foreach($map as $key => $getter) {
            $this->assertEquals($request[$key], $customer->$getter(), "$key = $getter()");
        }
    }

    public function testCustomerCreatedSendsWelcomeEmailIfEnabled()
    {
        $store = Mage::helper('nypwidget')->getStoreByPriceWaiterApiKey($this->apiKey);
        $store->setConfig('pricewaiter/customer_interaction/send_welcome_email', 1);

        list($request, $order, $callback) = $this->testNormalOrderCallback();
        $this->assertEquals(1, $callback->welcomeEmailsSent, 'welcome email sent to new customer');
    }

    public function testCustomerCreatedDoesntSendWelcomeEmailIfNotEnabled()
    {
        $store = Mage::helper('nypwidget')->getStoreByPriceWaiterApiKey($this->apiKey);
        $store->setConfig('pricewaiter/customer_interaction/send_welcome_email', 0);

        list($request, $order, $callback) = $this->testNormalOrderCallback();
        $this->assertEquals(0, $callback->welcomeEmailsSent, 'no welcome email sent');
    }

    public function testExistingCustomerOnWebsiteReused()
    {
        $store = Mage::helper('nypwidget')->getStoreByPriceWaiterApiKey($this->apiKey);

        $customer = Mage::getModel('customer/customer')
            ->setEmail(uniqid() . '@example.org')
            ->setStore($store)
            ->save();

        $this->assertTrue(!!$customer->getId(), 'customer saved');

        $request = $this->buildOrderCallbackRequest();
        $request['buyer_email'] = $customer->getEmail();

        $callback = new TestableCallbackModel();
        $order = $callback->processRequest($request);

        $this->assertEquals($customer->getId(), $order->getCustomerId(), 'customer account reused');
    }

    public function testCustomerFromOtherWebsiteNotReused()
    {
        $weirdWebsite = Mage::getModel('core/website')
            ->save();

        $customer = Mage::getModel('customer/customer')
            ->setEmail(uniqid() . '@example.org')
            ->setWebsiteId($weirdWebsite->getId())
            ->save();

        $this->assertTrue(!!$customer->getId(), 'customer saved');

        $request = $this->buildOrderCallbackRequest();
        $request['buyer_email'] = $customer->getEmail();

        $callback = new TestableCallbackModel();
        $order = $callback->processRequest($request);

        $this->assertNotEquals($customer->getId(), $order->getCustomerId(), 'customer account reused');
    }

    /**
     * @depends testNormalOrderCallback
     */
    public function testInvoiceCaptured(Array $args)
    {
        list($request, $order) = $args;

        $invoiceIds = Mage::getModel('sales/order_invoice')
            ->getCollection()
            ->addAttributeToFilter('order_id', $order->getId())
            ->getAllIds();

        $this->assertCount(1, $invoiceIds);

        // TODO: Anything else interesting it is useful to track for invoices?
    }

    /**
     * @depends testNormalOrderCallback
     */
    public function testShippingAddress(Array $args)
    {
        list($request, $order) = $args;
        $this->doAddressTest('shipping', $request, $order);
    }

    /**
     * @depends testNormalOrderCallback
     */
    public function testBillingAddress(Array $args)
    {
        list($request, $order) = $args;
        $this->doAddressTest('billing', $request, $order);
    }

    public function doAddressTest($type, Array $request, Mage_Sales_Model_Order $order)
    {
        // TODO: This should be a unit test

        $addressGetter = 'get' . ucfirst($type) . 'Address';
        $addr = $order->$addressGetter();
        $this->assertInstanceOf('Mage_Sales_Model_Order_Address', $addr, "$addressGetter returned something");

        $expectedStreet = array_filter([
            $request["buyer_{$type}_address"],
            $request["buyer_{$type}_address2"],
            $request["buyer_{$type}_address3"],
        ]);
        $this->assertGreaterThan(0, count($expectedStreet), 'test data includes street');
        $this->assertEquals($expectedStreet, $addr->getStreet());

        $expectedValues = [
            'getCity' => $request["buyer_{$type}_city"],
            'getCountryId' => $request["buyer_{$type}_country"],
            'getFax' => '',
            'getFirstname' => $request["buyer_{$type}_first_name"],
            'getLastname' => $request["buyer_{$type}_last_name"],
            'getMiddlename' => '',
            'getPostcode' => $request["buyer_{$type}_zip"],
            'getRegionCode' => $request["buyer_{$type}_state"],
            'getSuffix' => '',
            'getTelephone' => $request["buyer_{$type}_phone"],
        ];

        foreach($expectedValues as $getter => $expectedValue) {
            $this->assertNotNull($expectedValue); // make sure we actually have a value to compare
            $this->assertEquals($expectedValue, $addr->$getter());
        }
    }

    /**
     * @depends testNormalOrderCallback
     */
    public function testItems(Array $args)
    {
        list($request, $order) = $args;

        $items = $order->getItemsCollection();
        $this->assertCount(1, $items);

        $item = $items->getFirstItem();

        $expectedValues = [
            'getProductId' => $this->simpleProduct['id'],
            'getProductType' => $this->simpleProduct['type'],
            'getSku' => $this->simpleProduct['sku'],
            'getStoreId' => $order->getStoreId(),
            'getOrderId' => $order->getId(),
            'getName' => $this->simpleProduct['name'],

        ];

        foreach($expectedValues as $getter => $expectedValue) {
            $this->assertNotNull($expectedValue, "no expected value to test against $getter");
            $this->assertEquals($expectedValue, $item->$getter());
        }
    }

    public function testSendNewOrderEmailIfEnabled()
    {
        $store = Mage::helper('nypwidget')->getStoreByPriceWaiterApiKey($this->apiKey);
        $store->setConfig('pricewaiter/customer_interaction/send_new_order_email', 1);

        list($request, $order, $callback) = $this->testNormalOrderCallback();
        $this->assertEquals(1, $callback->newOrderEmailsSent);
    }

    public function testDoesntSendNewOrderEmailIfNotEnabled()
    {
        $store = Mage::helper('nypwidget')->getStoreByPriceWaiterApiKey($this->apiKey);
        $store->setConfig('pricewaiter/customer_interaction/send_new_order_email', 0);

        list($request, $order, $callback) = $this->testNormalOrderCallback();
        $this->assertEquals(0, $callback->newOrderEmailsSent, 'no new order email sent');
    }

    /**
     * @depends testTestOrderCallback
     */
    public function testTestOrderCanceled(Array $args)
    {
        list($request, $order) = $args;

        $order = Mage::getModel('sales/order')->load($order->getId());
        $this->assertTrue(!!$order->getId(), 'order persisted to db');

        $this->assertEquals(
            Mage_Sales_Model_Order::STATE_CANCELED,
            $order->getStatus(),
            'order is canceled'
        );
    }

    /**
     * @depends testTestOrderCallback
     */
    public function testTestOrderNoInvoiceCaptured(Array $args)
    {
        list($request, $order) = $args;

        $invoiceIds = Mage::getModel('sales/order_invoice')
            ->getCollection()
            ->addAttributeToFilter('order_id', $order->getId())
            ->getAllIds();

        $this->assertCount(0, $invoiceIds);
    }


    /**
     * @depends testNormalOrderCallback
     */
    public function testOrderPayment(Array $args)
    {
        $p = $this->payment;

        list($request, $order) = $args;

        $payment = $order->getPayment();
        $this->assertTrue(!!$payment, "payment exists");

        $this->assertEquals($request['transaction_id'], $payment->getTransactionId());
        $this->assertEquals($p['magento_cc_type'], $payment->getCcType());
        $this->assertEquals($p['cc_last4'], $payment->getCcLast4());

        // Check that PW payment method is stashed on payment
        $this->assertNotNull($payment->getAdditionalData(), 'payment has additional data');
        $data = unserialize($payment->getAdditionalData());
        $this->assertTrue(is_array($data), 'getAdditionalData() contains serialized array');
        $this->assertEquals($p['method'], $data['pricewaiter_payment_method']);
    }

    /**
     * @depends testNormalOrderCallback
     */
    public function testOrderPaymentTransaction(Array $args)
    {
        list($request, $order) = $args;
        $payment = $order->getPayment();

        $txn = Mage::getModel('sales/order_payment_transaction')
            ->setOrderPaymentObject($payment)
            ->loadByTxnId($payment->getTransactionId());

        $this->assertTrue(!!$txn->getId(), 'transaction found');

        $this->assertEquals('capture', $txn->getTxnType());
        $this->assertTrue(!!$txn->getIsClosed(), 'transaction is closed');
    }

    /**
     * Checks that for auth-only PW payments, the resulting transaction is AUTH type.
     */
    public function testOrderPaymentTransactionForAuth()
    {
        $this->markTestIncomplete();
    }

    public function testOrderWithBlankShippingMethod()
    {
        $callback = new TestableCallbackModel();

        $request = $this->buildOrderCallbackRequest();
        $request['shipping_method'] = '';
        $order = $callback->processRequest($request);

        $this->assertInstanceOf(Mage_Sales_Model_Order, $order);

        $this->assertNotEmpty($order->getShippingDescription(), 'there is *something* in the shipping description field');

        // Pass valid order on to dependent tests.
        return array($request, $order, $callback);
    }

}
