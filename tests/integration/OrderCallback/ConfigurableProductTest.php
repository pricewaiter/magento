<?php

require_once(__DIR__ . '/Base.php');

/**
 * Tests around PriceWaiter order callback.
 */
class Integration_OrderCallback_ConfigurableProduct
    extends Integration_OrderCallback_Base
{
    public $product = array(
        'type' => 'configurable',
        'sku' => 'msj007',
        'id' => '404',

        // Simple product to re-stock for inventory purposes
        'id_for_inventory' => 238,

        'name' => 'Plaid Cotton Shirt',
        'price' => '160.00',
        'weight' => '1.0000',
        'options' => array(
            'Color' => "Red",
            'Size' => "M",
        ),
    );

    public function testOrderCallback()
    {
        return $this->doOrderCallback();
    }

    /**
     * @depends testOrderCallback
     */
    public function testOptionsPresentOnOrderItem(Array $args)
    {
        list($request, $order) = $args;

        $item = $order->getItemsCollection()->getFirstItem();
        $this->assertNotEmpty($item, 'order has an item');

        $expectedProductOptions = array(
            'additional_options' => array(),
        );

        foreach($this->product['options'] as $name => $value) {
            $expectedProductOptions['additional_options'][] = array(
                'label' => $name,
                'value' => $value,
            );
        }

        $this->assertEquals($expectedProductOptions, $item->getProductOptions());
    }
}
