<?php

require_once(__DIR__ . '/Base.php');

/**
 * Tests around PriceWaiter order callback.
 */
class Integration_ProductInfo_Simple
    extends Integration_ProductInfo_Base
{
    public $retail = '150';
    public $regular = '150.0000';

    public function testProductWithDecentInventory()
    {
        $this->setProductInStock(true);
        $info = $this->doProductInfoRequest();

        $this->assertEquals(array(
            'inventory' => 100,
            'allow_pricewaiter' => true,
            'can_backorder' => false,
            'retail_price' => $this->retail,
            'retail_price_currency' => 'USD',
            'regular_price' => $this->regular,
            'regular_price_currency' => 'USD',
        ), $info);
    }

    public function testProductExceedingInventory()
    {
        $this->setProductInStock(true, 100);
        $info = $this->doProductInfoRequest(1337);

        $this->assertEquals(array(
            'inventory' => 100,
            'allow_pricewaiter' => true,
            'can_backorder' => false,
            'retail_price' => $this->retail,
            'retail_price_currency' => 'USD',
            'regular_price' => $this->regular,
            'regular_price_currency' => 'USD',
        ), $info);
    }

    public function testProductOutOfStock()
    {
        $this->setProductInStock(false);
        $info = $this->doProductInfoRequest(10);

        $this->assertEquals(array(
            'inventory' => 0,
            'allow_pricewaiter' => true,
            'can_backorder' => false,
        ), $info);
    }
}
