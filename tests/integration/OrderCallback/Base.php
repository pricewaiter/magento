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
 * Base for implementing OC-related tests.
 */
abstract class Integration_OrderCallback_Base
    extends PHPUnit_Framework_TestCase
{
    public $apiKey = 'MAGENTO';

    public $product = array(
        'type' => 'simple',
        'sku' => 'hde012',
        'id' => '399',
        'name' => 'Madison 8GB Digital Media Player',
        'price' => '150.00',
        'weight' => '1.0000',
    );

    public $buyer = array(
        'first_name' => 'Ned',
        'last_name' => 'Flanders',
        'name' => 'Ned Flanders',
    );

    public $billingAddress = array(
        'name' => 'Ned Flanders',
        'first_name' => 'Ned',
        'last_name' => 'Flanders',
        'address' => '744 Evergreen Terrace',
        'address2' => 'Floor 1',
        'address3' => 'Apt A',
        'city' => 'Springfield',
        'state' => 'OR',
        'zip' => '12345',
        'country' => 'US',
        'phone' => '123-456-7890',
    );

    public $shippingAddress = array(
        'name' => 'Homer Simpson',
        'first_name' => 'Homer',
        'last_name' => 'Simpson',
        'address' => '742 Evergreen Terrace',
        'address2' => '',
        'address3' => '',
        'city' => 'Springfield',
        'state' => 'OR',
        'zip' => '12345',
        'country' => 'US',
        'phone' => '987-654-3210',
    );

    public $payment = array(
        'method' => 'authorize_net',
        'method_nice' => 'Authorize.net',
        'cc_type' => 'Visa',
        'magento_cc_type' => 'VI',
        'cc_last4' => '4242',
    );

    public $storeCurrencyCode = 'USD';

    public $weirdCurrencyCode = 'GBP';

    public function setUp()
    {
        $this->resetConfiguration();
        $this->ensureProductInStock();
    }

    protected function assertModelLooksLike($model, Array $expectedValues)
    {
        $actualValues = array();
        foreach($expectedValues as $getter => $expected) {
            $actualValues[$getter] = $model->$getter();
        }

        $this->assertEquals($expectedValues, $actualValues);
    }

    /**
     * Submits an order callback request.
     */
    protected function doOrderCallback(Array $requestData = array())
    {
        $callback = new TestableCallbackModel();
        $request = $this->buildOrderCallbackRequest($requestData);

        $order = $callback->processRequest($request);
        $this->assertInstanceOf('Mage_Sales_Model_Order', $order);

        return array($request, $order, $callback);
    }

    /**
     * @return Array
     */
    protected function buildOrderCallbackRequest(Array $additionalData = array())
    {
        $product = $this->product;
        $payment = $this->payment;
        $apiKey = $this->apiKey;

        $request = array(
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
        );

        // maths
        $request['total'] = (
            ($request['unit_price'] * $request['quantity']) +
            $request['shipping'] +
            $request['tax']
        );

        foreach($this->buyer as $key => $value) {
            $request["buyer_{$key}"] = $value;
        }

        foreach($this->billingAddress as $key => $value) {
            $request["buyer_billing_{$key}"] = $value;
        }

        foreach($this->shippingAddress as $key => $value) {
            $request["buyer_shipping_{$key}"] = $value;
        }

        if (empty($this->product['options'])) {
            $request['product_option_count'] = 0;
        } else {
            $request['product_option_count'] = count($this->product['options']);
            $i = 0;
            foreach($this->product['options'] as $name => $value) {
                $i++;
                $request["product_option_name{$i}"] = $name;
                $request["product_option_value{$i}"] = $value;
            }
        }

        $request['product_sku'] = $product['sku'];

        return array_merge($request, $additionalData);
    }

    protected function ensureProductInStock()
    {
        // Put some more in stock if needed
        $minRequiredQty = 100;

        $id = isset($this->product['id_for_inventory']) ?
            $this->product['id_for_inventory'] :
            $this->product['id'];

        $product = Mage::getModel('catalog/product')
            ->load($id);

        $stock = $product->getStockItem();

        if ($stock->getQty() < $minRequiredQty) {
            $stock
                ->setQty($minRequiredQty)
                ->setIsInStock(1)
                ->save();
        } else if (!$stock->getIsInStock()) {
            $stock
                ->setIsInStock(1)
                ->save();
        }
    }

    protected function getCurrentProductInventory()
    {
        $product = Mage::getModel('catalog/product')
            ->load($this->product['id']);

        $stock = $product->getStockItem();

        // Necessary to get around stale data in stock item.
        $stock->load($stock->getId());

        return $stock->getQty();
    }

    protected function getStore()
    {
        return Mage::helper('nypwidget')->getStoreByPriceWaiterApiKey($this->apiKey);
    }

    protected function resetConfiguration()
    {
        $store = $this->getStore();
        $store->setConfig('pricewaiter/customer_interaction/send_welcome_email', 0);
        $store->setConfig('pricewaiter/customer_interaction/send_new_order_email', 0);
    }

}
