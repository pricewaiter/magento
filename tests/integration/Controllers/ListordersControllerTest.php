<?php

// TODO: Figure out how to get Mage to autoload this.
require_once(__DIR__ . '/../../../app/code/community/PriceWaiter/NYPWidget/controllers/ListordersController.php');

require_once(__DIR__ . '/_http.php');

class Integration_Controllers_ListordersControllerTest
    extends Integration_AbstractProductTest
{
    public function testStateTranslation()
    {
        $tests = array(
            Mage_Sales_Model_Order::STATE_NEW => 'paid',
            Mage_Sales_Model_Order::STATE_PENDING_PAYMENT => 'pending',
            Mage_Sales_Model_Order::STATE_PROCESSING => 'paid',
            Mage_Sales_Model_Order::STATE_COMPLETE => 'paid',
            Mage_Sales_Model_Order::STATE_CLOSED => 'paid',
            Mage_Sales_Model_Order::STATE_CANCELED => false,
            Mage_Sales_Model_Order::STATE_HOLDED => 'paid',
            Mage_Sales_Model_Order::STATE_PAYMENT_REVIEW => 'pending',
        );

        foreach($tests as $input => $expected) {
            $actual = PriceWaiter_NYPWidget_ListordersController::translateMagentoOrderState($input);
            $this->assertSame($expected, $actual, "Translates $input");
        }
    }

    public function testRequest()
    {
        $product = $this->getSimpleProduct();

        $deals = array_map(function($amount) use ($product) {
            return $this->createDeal($product, "\$$amount off 1");
        }, range(1, 5));

        $dealIds = array_map(function (PriceWaiter_NYPWidget_Model_Deal $deal) {
            return $deal->getId();
        }, $deals);

        $order = $this->createOrder($product);
        $deals[3]->setOrderId($order->getId())->save();

        $request = new PriceWaiter_NYPWidget_Controller_Endpoint_Request(
            'request-id',
            getenv('PRICEWAITER_API_KEY'),
            '2016-03-01',
            json_encode(array(
                'pricewaiter_deals' => $dealIds,
            ))
        );

        $controller = $this->getControllerInstance();

        $response = $controller->processRequest($request);

        $expectedJson = json_encode(
            array(
                array(
                    'id' => $order->getIncrementId(),
                    'state' => 'paid',
                    'currency' => 'USD',
                    'subtotal' => array(
                        'value' => '150',
                    ),
                    'pricewaiter_deals' => array(
                        $deals[3]->getId(),
                    ),
                ),
            ),
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
        );

        $this->assertEquals($expectedJson, $response->getBodyJson());
    }

    public function testFormatOrder()
    {
        $product = $this->getSimpleProduct();
        $order = $this->createOrder($product);
        $dealIds = array(
            'fake-deal-1',
            'fake-deal-2',
        );

        $controller = $this->getControllerInstance();
        $formatted = $controller->formatOrder($order, $dealIds);

        $expected = array(
            'id' => $order->getIncrementId(),
            'currency' => 'USD',
            'pricewaiter_deals' => $dealIds,
        );

        foreach($expected as $prop => $value) {
            $this->assertEquals($value, $formatted->$prop);
        }

        $this->assertEquals($order->getIncrementId(), $formatted->id);
    }

    public function testFormatOrderWithActualOrder()
    {
        $id = '41';
        $order = Mage::getModel('sales/order')
            ->load($id);
        $this->assertNotEmpty($order->getId(), 'fixture data found');

        $controller = $this->getControllerInstance();
        $formatted = $controller->formatOrder($order, array());

        $this->assertEquals(
            (object)array(
                'id' => '100000049',
                'state' => 'paid',
                'currency' => 'USD',
                'subtotal' => (object)array(
                    'value' => '750',
                ),
                'pricewaiter_deals' => array(),
            ),
            $formatted
        );
    }

    public function getControllerInstance($body = null)
    {
        $request = new Zend_Controller_Request_Http();
        $response = new Zend_Controller_Response_Http();

        $controller = Mage::getControllerInstance(
            'PriceWaiter_NYPWidget_ListordersController',
            $request,
            $response
        );

        return $controller;
    }
}
