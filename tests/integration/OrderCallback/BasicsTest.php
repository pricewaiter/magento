<?php

class TestableOrderHelper extends PriceWaiter_NYPWidget_Helper_Orders
{
    public $shouldVerify = true;

    public function verifyPriceWaiterOrderData(Array $data)
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
    public $simpleProduct = array(
        'sku' => 'hde012',
        'id' => '399',
        'price' => '150.00'
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

    /**
     * @return Array
     */
    public function buildOrderCallbackRequest()
    {
        $product = $this->simpleProduct;
        $apiKey = 'MAGENTO';

        $request = [
            // Default to new customer per-order.
            'buyer_email' => uniqid() . '@example.org',
            'pricewaiter_id' => uniqid(),
            'order_completed_timestamp' => '2015-01-03T14:19:37-07:00',
            'api_key' => $apiKey,
            'payment_method' => 'authorize_net',
            'transaction_id' => uniqid(),
            'currency' => 'USD',
            'quantity' => 2,
            'unit_price' => '99.99',
            'shipping_method' => 'UPS Ground',
            'shipping' => '10.50',
            'tax' => '14.00',
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

        return $request;
    }

    /**
     * @expectedException PriceWaiter_NYPWidget_Exception_ApiKey
     */
    public function testInvalidApiKeyThrows()
    {
        $callback = Mage::getModel('nypwidget/callback');
        $callback->setOrderHelper(new TestableOrderHelper());

        $data = [
            'pricewaiter_id' => '1234',
            'api_key' => 'NOT A REAL API KEY',
            'should_verify' => '1',
        ];

        $callback->processRequest($data);
    }

    /**
     * Makes an order write request and provides the resulting order data
     * to subsequent tests.
     */
    public function testSuccessfulOrderWrite()
    {
        $callback = Mage::getModel('nypwidget/callback');
        $callback->setOrderHelper(new TestableOrderHelper());

        $request = $this->buildOrderCallbackRequest();
        $order = $callback->processRequest($request);

        $this->assertInstanceOf(Mage_Sales_Model_Order, $order);

        // Pass valid order on to dependent tests.
        return array($request, $order);
    }

    /**
     * @depends testSuccessfulOrderWrite
     * @expectedException PriceWaiter_NYPWidget_Exception_DuplicateOrder
     */
    public function testDuplicateOrderThrows(Array $args)
    {
        list($request, $order) = $args;

        $pwOrder = Mage::getModel('nypwidget/order')->loadByMagentoOrderId($order->getEntityId());
        $this->assertTrue(!!$pwOrder->getId(), 'PriceWaiter_NYPWidget_Model_Order found');

        $callback = Mage::getModel('nypwidget/callback');
        $callback->setOrderHelper(new TestableOrderHelper());

        // Run second order callback with same pricewaiter_id
        $request = $this->buildOrderCallbackRequest();
        $request['pricewaiter_id'] = $pwOrder->getPricewaiterId();

        $callback->processRequest($request);
    }

    /**
     * @depends testSuccessfulOrderWrite
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
        $this->markTestIncomplete();
    }

    public function testCustomerCreatedDoesntSendWelcomeEmailIfNotEnabled()
    {
        $this->markTestIncomplete();
    }

    public function testCustomerReused()
    {
        $this->markTestIncomplete();
    }

    public function testCustomerFromOtherStoreNotReused()
    {
        $this->markTestIncomplete();
    }

    /**
     * @depends testSuccessfulOrderWrite
     */
    public function testShippingAddress(Array $args)
    {
        $this->markTestIncomplete();
    }

    /**
     * @depends testSuccessfulOrderWrite
     */
    public function testBillingAddress(Array $args)
    {
        $this->markTestIncomplete();
    }

    public function testSendNewOrderEmailIfEnabled()
    {
        $this->markTestIncomplete();
    }

    public function testDoesntSendNewOrderEmailIfNotEnabled()
    {
        $this->markTestIncomplete();
    }

    public function testTestOrderCanceled()
    {
        $this->markTestIncomplete();
    }

    public function testOrderPaymentIncludesGoodStuff()
    {
        $this->markTestIncomplete();
    }

    public function testTransactionRowCreated()
    {
        $this->markTestIncomplete();
    }

}
