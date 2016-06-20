<?php

require_once(__DIR__ . '/SimpleTest.php');

class Integration_ProductInfo_Configurable
    extends Integration_ProductInfo_Simple
{
    public $retail = '140';
    public $regular = '140.0000';

    public $product = array(
        'type' => 'configurable',
        'id' => '414',
        'id_for_inventory' => '481',
    );

    protected function setUp() {
        $this->product['id_for_inventory'] = '414';
        $this->setProductInStock(true, 42);
        $this->product['id_for_inventory'] = '481';
        $cart = Mage::getModel('checkout/cart');
        $cart->truncate();
    }

    protected function buildProductInfoRequest($quantity = 1)
    {
        $request = array(
            "form_key" => uniqid(true),
            "product" => 414,
            "super_attribute" => array(
                92 => 25,
                180 => 59,
            ),
            "related_product" => '',
            "qty" => $quantity,
        );

        return $request;
    }

}
