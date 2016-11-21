<?php

require_once(__DIR__ . '/Base.php');

class Integration_OrderCallback_CustomOptions
    extends Integration_OrderCallback_Base
{
    public $product = array(
        'type' => 'configurable',
        'sku' => 'mtk004c',
        'id' => '410',

        // Simple product to re-stock for inventory purposes
        'id_for_inventory' => 238,

        'name' => 'Chelsea Tee',
        'price' => '154.00',
        'options' => array(
            'Color' => "Blue",
            'Size' => "S",
            'monogram' => 'mono gram me!!',
            'Test Custom Options' => 'model 1',
        ),
    );

    public function testOrderCallback()
    {
        return $this->doOrderCallback();
    }

    /**
     * @depends testOrderCallback
     */
    public function testOrderItemPriceSet(Array $args)
    {
        list($request, $order) = $args;

        $item = $order->getItemsCollection()->getFirstItem();
        $this->assertNotEmpty($item, 'order has an item');

        $this->assertEquals(
            '154.00',
            $item->getPrice()
        );
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
