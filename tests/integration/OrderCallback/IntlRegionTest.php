<?php

require_once(__DIR__ . '/Base.php');

/**
 * Tests for international orders that do not have a matching region in the DB.
 */
class Integration_OrderCallback_Intl
    extends Integration_OrderCallback_Base
{
    public $billingAddress = array(
        'name' => 'Ned Flanders',
        'first_name' => 'Ned',
        'last_name' => 'Flanders',
        'address' => '744 Evergreen Terrace',
        'address2' => 'Floor 1',
        'address3' => 'Apt A',
        'city' => 'Springfield',
        'state' => '',
        'zip' => '12345',
        'country' => 'JP',
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
        'state' => '',
        'zip' => '12345',
        'country' => 'JP',
        'phone' => '987-654-3210',
    );

    /**
     * Makes an order write request and provides the resulting order data
     * to subsequent tests.
     */
    public function testNormalOrderCallback()
    {
        return $this->doOrderCallback();
    }


}
