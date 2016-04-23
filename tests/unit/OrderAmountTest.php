<?php

class Unit_OrderAmount extends PHPUnit_Framework_TestCase
{
    public function rounder($value)
    {
        return number_format($value, 2, '.', '');
    }

    /**
     * @dataProvider provideOrderAmountTests
     */
    public function testOrderAmounts($amountPerItem, $quantity, $shipping, $tax, $expectedValues)
    {
        $callback = new PriceWaiter_NYPWidget_Model_Callback();
        $request = array(
            'unit_price' => $amountPerItem,
            'quantity' => $quantity,
            'shipping' => $shipping,
            'tax' => $tax,
        );
        $actualValues = $callback->calculateOrderAmounts($request, array($this, 'rounder'));
        $this->assertEquals($expectedValues, $actualValues);
    }

    public function provideOrderAmountTests()
    {
        return array(
            array(
                'amountPerItem' => '88.87',
                'quantity' => '4',
                'shipping' => '12.34',
                'tax' => '9.37',
                'expectedValues' => array(
                    'discount_amount' => 0,
                    'grand_total' => '377.19',
                    'shipping_amount' => '12.34',
                    'shipping_tax_amount' => 0,
                    'subtotal' => '364.85',
                    // NOTE: subtotal includes tax; tax details are on the order item.
                    'tax_amount' => '0',
                    'shipping_discount_amount' => 0,
                    'subtotal_incl_tax' => '364.85',
                    'shipping_incl_tax' => '12.34',
                ),
            ),
        );
    }

    /**
     * @dataProvider provideOrderItemAmountTests
     */
    public function testOrderItemAmounts($listPrice, $amountPerItem, $quantity, $shipping, $tax, $expectedValues)
    {
        $callback = new PriceWaiter_NYPWidget_Model_Callback();
        $request = array(
            'unit_price' => $amountPerItem,
            'quantity' => $quantity,
            'shipping' => $shipping,
            'tax' => $tax,
        );
        $actualValues = $callback->calculateOrderItemAmounts($request, $listPrice, array($this, 'rounder'));
        $this->assertEquals($expectedValues, $actualValues);
    }

    public function provideOrderItemAmountTests()
    {
        return array(
            array(
                'listPrice' => '150.0000',
                'amountPerItem' => '88.87',
                'quantity' => '4',
                'shipping' => '12.34',
                'tax' => '9.37',
                'expectedValues' => array(
                    'discount_amount' => 0,
                    'discount_percent' => 0,
                    'original_price' => '150.0000',
                    'price' => '88.87',
                    'price_incl_tax' => '91.21',
                    'row_total' => '355.48',
                    'row_total_incl_tax' => '364.85',
                    'tax_amount' => '9.37',
                    'tax_before_discount' => '9.37',
                    'tax_percent' => '2.64',
                ),
            ),
        );
    }
}
