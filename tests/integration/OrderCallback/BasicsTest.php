<?php

require_once(__DIR__ . '/Base.php');

/**
 * Tests around PriceWaiter order callback.
 */
class Integration_OrderCallback_Basics
    extends Integration_OrderCallback_Base
{
    /**
     * @expectedException PriceWaiter_NYPWidget_Exception_ApiKey
     */
    public function testInvalidApiKeyThrows()
    {
        $this->doOrderCallback(array(
            'api_key' => 'NOT A REAL API KEY',
        ));
    }

    /**
     * Makes an order write request and provides the resulting order data
     * to subsequent tests.
     */
    public function testNormalOrderCallback()
    {
        return $this->doOrderCallback();
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

        // Run second order callback with same pricewaiter_id
        $this->doOrderCallback(array(
            'pricewaiter_id' => $pwOrder->getPricewaiterId(),
        ));
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

    public function testOrderStatusSettingRespected()
    {
        $helper = Mage::helper('nypwidget');

        $store = $helper->getStoreByPriceWaiterApiKey($this->apiKey);
        $oldStatus = $helper->getDefaultOrderStatus($store);

        $store->setConfig(
            PriceWaiter_NYPWidget_Helper_Data::XML_PATH_DEFAULT_ORDER_STATUS,
            'payment_review'
        );
        $this->assertEquals(
            'payment_review',
            $helper->getDefaultOrderStatus(),
            'correctly overwrote default order status setting'
        );

        try
        {
            list($request, $order) = $this->testNormalOrderCallback();
            $this->assertEquals('payment_review', $order->getStatus());
        }
        catch (Exception $ex)
        {
            $store->setConfig(
                PriceWaiter_NYPWidget_Helper_Data::XML_PATH_DEFAULT_ORDER_STATUS,
                $oldStatus
            );
            throw $ex;
        }

        $store->setConfig(
            PriceWaiter_NYPWidget_Helper_Data::XML_PATH_DEFAULT_ORDER_STATUS,
            $oldStatus
        );
    }

}
