<?php

require_once(__DIR__ . '/Base.php');

class Integration_OrderCallback_TestOrders
    extends Integration_OrderCallback_Base
{
    /**
     * Makes a *test* order callback and provides the resulting order data to
     * subsequent tests.
     */
    public function testOrderCallback()
    {
        list($request, $order, $callback) = $this->doOrderCallback(array(
            'test' => '1'
        ));

        return array($request, $order, $callback, $prevInventory);
    }

    public function testInventoryNotDecremented()
    {
        $prevInventory = $this->getCurrentProductInventory();

        list($request, $order, $callback) = $this->testOrderCallback();

        $this->assertEquals($prevInventory, $this->getCurrentProductInventory());
    }

    /**
     * @depends testOrderCallback
     */
    public function testOrderCanceled(Array $args)
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
     * @depends testOrderCallback
     */
    public function testOrderNoInvoiceCaptured(Array $args)
    {
        list($request, $order) = $args;

        $invoiceIds = Mage::getModel('sales/order_invoice')
            ->getCollection()
            ->addAttributeToFilter('order_id', $order->getId())
            ->getAllIds();

        $this->assertCount(0, $invoiceIds);
    }
}
