<?php

require_once(__DIR__ . '/Base.php');

/**
 * Tests around PriceWaiter order callback.
 */
class Integration_OrderCallback_SpecialPrice
    extends Integration_OrderCallback_Base
{
    public $product = array(
        'type' => 'simple',
        'sku' => 'hdb008',
        'id' => '384',
        'name' => 'Park Row Throw',
    );

    public function testRespectsSpecialPrice()
    {
        list($request, $order, $callback) = $this->doOrderCallback();

        $item = $order->getItemsCollection()->getFirstItem();
        $product = $item->getProduct();

        // getSource()->getDiscountAmount()
        // $source->getDiscountDescription()

        $this->assertEquals(-40.02, $order->getBaseDiscountAmount());
        $this->assertEquals(-40.02, $order->getDiscountAmount());
        $this->assertEquals(224.48, $order->getBaseGrandTotal());
        $this->assertEquals(240.00, $order->getBaseSubtotal());
        $this->assertEquals(240.00, $order->getSubtotal());
        $this->assertEquals(240.00, $product->getPrice());
        $this->assertEquals(120.00, $product->getSpecialPrice());
    }
}
