<?php

require_once(__DIR__ . '/Base.php');

/**
 * Tests around PriceWaiter order callback.
 */
class Integration_OrderCallback_Inventory
    extends Integration_OrderCallback_Base
{
    public function testInventoryDecremented()
    {
        $prevInventory = $this->getCurrentProductInventory();

        list($request, $order, $callback) = $this->doOrderCallback();

        $this->assertEquals(
            $prevInventory - $request['quantity'],
            $this->getCurrentProductInventory()
        );
    }

    /**
     * @expectedException PriceWaiter_NYPWidget_Exception_OutOfStock
     */
    public function testOutOfStockThrows()
    {
        $this->doOrderCallback(array(
            'quantity' => 1000,
        ));
    }
}
