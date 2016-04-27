<?php

require_once(__DIR__ . '/Base.php');

class Integration_OrderCallback_Payment
    extends Integration_OrderCallback_Base
{

    public function testNormalOrderCallback()
    {
        return $this->doOrderCallback(array(
            'avs_result' => 'Y'
        ));
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

        $this->assertModelLooksLike($payment, array(
            'getTransactionId' => $request['transaction_id'],
            'getCcType' => $this->payment['magento_cc_type'],
            'getCcLast4' => $this->payment['cc_last4'],
            'getAmountAuthorized' => $order->getGrandTotal(),
            'getAmountOrdered' => $order->getGrandTotal(),
            'getAmountPaid' => $order->getGrandTotal(),
            'getBaseAmountAuthorized' => $order->getGrandTotal(),
            'getBaseAmountOrdered' => $order->getGrandTotal(),
            'getBaseAmountPaid' => $order->getGrandTotal(),
            'getBaseAmountPaidOnline' => $order->getGrandTotal(),
            'getBaseShippingAmount' => $request['shipping'],
            'getBaseShippingCaptured' => $request['shipping'],
            'getShippingAmount' => $request['shipping'],
            'getShippingCaptured' => $request['shipping'],
        ));

        // Check that PW payment method is stashed on payment
        $this->assertNotNull($payment->getAdditionalData(), 'payment has additional data');
        $data = unserialize($payment->getAdditionalData());
        $this->assertTrue(is_array($data), 'getAdditionalData() contains serialized array');
        $this->assertEquals($p['method'], $data['pricewaiter_payment_method']);
    }

    /**
     * @depends testNormalOrderCallback
     */
    public function testOrderPaymentAVS(Array $args)
    {
        list($request, $order) = $args;

        $payment = $order->getPayment();
        $this->assertEquals('Y', $payment->getCcAvsStatus());
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
    public function testAuthOnlyOrderCallback()
    {
        return $this->doOrderCallback(array(
            'transaction_type' => 'auth',
        ));
    }

    /**
     * @depends testAuthOnlyOrderCallback
     */
    public function testAuthOnlyPayment(Array $args)
    {
        list($request, $order) = $args;

        $payment = $order->getPayment();
        $this->assertNotEmpty($payment, 'payment found for order');

        $this->assertModelLooksLike($payment, array(
            'getTransactionId' => $request['transaction_id'],
            'getCcType' => $this->payment['magento_cc_type'],
            'getCcLast4' => $this->payment['cc_last4'],
            'getAmountAuthorized' => $order->getGrandTotal(),
            'getAmountOrdered' => $order->getGrandTotal(),
            'getAmountPaid' => null,
            'getBaseAmountAuthorized' => $order->getGrandTotal(),
            'getBaseAmountOrdered' => $order->getGrandTotal(),
            'getBaseAmountPaid' => null,
            'getBaseAmountPaidOnline' => null,
            'getBaseShippingAmount' => $request['shipping'],
            'getBaseShippingCaptured' => 0,
            'getShippingAmount' => $request['shipping'],
            'getShippingCaptured' => 0,
        ));
    }

    /**
     * @depends testAuthOnlyOrderCallback
     */
    public function testAuthOnlyTransaction(Array $args)
    {
        list($request, $order) = $args;
        $payment = $order->getPayment();

        $txn = Mage::getModel('sales/order_payment_transaction')
            ->setOrderPaymentObject($payment)
            ->loadByTxnId($payment->getTransactionId());

        $this->assertTrue(!!$txn->getId(), 'transaction found');

        $this->assertEquals('authorization', $txn->getTxnType());
        $this->assertTrue(!$txn->getIsClosed(), 'transaction is not closed');
    }

    /**
     * @depends testAuthOnlyOrderCallback
     */
    public function testAuthOnlyNoInvoice(Array $args)
    {
        list($request, $order) = $args;

        $invoices = $order->getInvoiceCollection()->getAllIds();
        $this->assertCount(0, $invoices, 'order has no invoices');
    }
}
