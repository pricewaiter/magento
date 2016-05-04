<?php

require_once(__DIR__ . '/Base.php');

class Integration_OrderCallback_Emails
    extends Integration_OrderCallback_Base
{
    public function testCustomerCreatedSendsWelcomeEmailIfEnabled()
    {
        $store = $this->getStore();
        $store->setConfig('pricewaiter/customer_interaction/send_welcome_email', 1);

        list($request, $order, $callback) = $this->doOrderCallback();
        $this->assertEquals(1, $callback->welcomeEmailsSent, 'welcome email sent to new customer');
    }

    /**
     * @depends doOrderCallback
     */
    public function testCustomerCreatedDoesntSendWelcomeEmailIfNotEnabled(Array $args)
    {
        $this->assertEquals(0, $callback->welcomeEmailsSent, 'no welcome email sent');
    }

    public function testSendNewOrderEmailIfEnabled()
    {
        $store = $this->getStore();
        $store->setConfig('pricewaiter/customer_interaction/send_new_order_email', 1);

        list($request, $order, $callback) = $this->doOrderCallback();
        $this->assertEquals(1, $callback->newOrderEmailsSent);
    }

    /**
     * @depends doOrderCallback
     */
    public function testDoesntSendNewOrderEmailIfNotEnabled(Array $args)
    {
        list($request, $order, $callback) = $args;
        $this->assertEquals(0, $callback->newOrderEmailsSent, 'no new order email sent');
    }


}
