<?php

require_once(__DIR__ . '/Base.php');

/**
 * Tests around customer account creation + use.
 */
class Integration_OrderCallback_CustomerTest
    extends Integration_OrderCallback_Base
{
    /**
     * @depends doOrderCallback
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

    public function testExistingCustomerOnWebsiteReused()
    {
        $store = $this->getStore();

        $customer = Mage::getModel('customer/customer')
            ->setEmail(uniqid() . '@example.org')
            ->setStore($store)
            ->save();

        $this->assertNotEmpty($customer->getId(), 'customer saved');

        list($request, $order, $callback) = $this->doOrderCallback(array(
            'buyer_email' => $customer->getEmail(),
        ));

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

        $this->assertNotEquals($customer->getId(), $order->getCustomerId(), 'customer account not reused');
    }

    public function testNewCustomerAddress()
    {
        $email = uniqid() . '@example.org';
        list($request, $order, $callback) = $this->doOrderCallback(array(
            'buyer_email' => $email,
        ));

        $customerId = $order->getCustomerId();
        $this->assertNotEmpty($customerId, 'customer_id is set on order');
        $customer = Mage::getModel('customer/customer')
            ->load($customerId);
        $this->assertNotEmpty($customer->getId(), 'customer found');

        $billing = $customer->getDefaultBillingAddress();
        $this->assertNotEmpty($billing, 'Billing address found');

        $this->assertEquals($billing->firstname, 'Ned');
        $this->assertEquals($billing->lastname, 'Flanders');
        $this->assertEquals($billing->city, 'Springfield');
        $this->assertEquals($billing->region_id, '49');
        $this->assertEquals($billing->country_id, 'US');
        $this->assertEquals($billing->postcode, '12345');
        $this->assertEquals($billing->street, "744 Evergreen Terrace\nFloor 1\nApt A");
        $this->assertEquals($billing->telephone, '123-456-7890');

        $shipping = $customer->getDefaultShippingAddress();
        $this->assertNotEmpty($shipping, 'Shipping address found');

        $this->assertEquals($shipping->firstname, 'Ned');
        $this->assertEquals($shipping->lastname, 'Flanders');
        $this->assertEquals($shipping->city, 'Springfield');
        $this->assertEquals($shipping->region_id, '49');
        $this->assertEquals($shipping->country_id, 'US');
        $this->assertEquals($shipping->postcode, '12345');
        $this->assertEquals($shipping->street, '742 Evergreen Terrace');
        $this->assertEquals($shipping->telephone, '987-654-3210');
    }
}
