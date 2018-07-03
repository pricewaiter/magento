<?php

require_once(__DIR__ . '/Base.php');

/**
 * Tests around PriceWaiter order callback.
 */
class Integration_OrderCallback_ConfigurableRegularPrice
    extends Integration_OrderCallback_Base
{
    public $product = array(
        'type' => 'configurable',
        'sku' => 'msj006c-Khaki-M',
        'id' => '404',

        // Simple product to re-stock for inventory purposes
        'id_for_inventory' => 898,

        'name' => 'Plaid Cotton Shirt',
        'price' => '108.90',
        'weight' => '1.0000',
        'options' => array(
            'Color' => "Khaki",
            'Size' => "M",
        ),
    );

    public function setUp()
    {
        $connection = Mage::getSingleton('core/resource')->getConnection('core_write');

        $query = "DELETE FROM `catalog_product_super_attribute_pricing` WHERE `product_super_attribute_id` = 17";
        $connection->query($query);

        $query = "INSERT INTO `catalog_product_super_attribute_pricing` (`value_id`, `product_super_attribute_id`, `value_index`, `is_percent`, `pricing_value`, `website_id`) VALUES (NULL, '17', '25', '0', '10.00', '0')";
        $connection->query($query);

        $storeId = Mage::app()->getStore()->getId();
        $product = Mage::getModel('catalog/product')->load(404);
        $product->setSpecialPrice(99.00);
        Mage::app()->setCurrentStore(Mage_Core_Model_App::ADMIN_STORE_ID);
        $product->save();

        Mage::app()->setCurrentStore($storeId);
    }

    public function tearDown()
    {
        $storeId = Mage::app()->getStore()->getId();
        $product = Mage::getModel('catalog/product')->load(404);
        $product->setSpecialPrice(null);
        Mage::app()->setCurrentStore(Mage_Core_Model_App::ADMIN_STORE_ID);
        $product->save();
        Mage::app()->setCurrentStore($storeId);
    }

    public function testOrderCallback()
    {
        return $this->doOrderCallback(['quantity' => 1]);
    }

    /**
     * @depends testOrderCallback
     */
    public function testOptionsPresentOnOrderItem(Array $args)
    {
        list($request, $order) = $args;

        $item = $order->getItemsCollection()->getFirstItem();
        $this->assertNotEmpty($item, 'order has an item');

        $this->assertEquals(-9.01, $order->getBaseDiscountAmount());
        $this->assertEquals(-9.01, $order->getDiscountAmount());
        $this->assertEquals(124.49, $order->getBaseGrandTotal());
        $this->assertEquals(109.00, $order->getBaseSubtotal());
        $this->assertEquals(109.00, $order->getSubtotal());

        $this->assertEquals(109.00, $item->getOriginalPrice());
        $this->assertEquals(9.01, $item->getDiscountAmount());
    }
}
