<?php

/**
 * Tests around what products PriceWaiter is supported on.
 */
class Integration_SupportTest extends PHPUnit_Framework_TestCase
{
    public $supportedProductTypes = array(
        'simple' => true,
        'configurable' => false,
        'grouped' => false,
        'bundle' => false,
        'downloadable' => false,
        'virtual' => false,
        'some weird type we dont know about' => false,
    );

    public $supportedProducts = array(
        array(
            'description' => 'Simple Product',
            'product_id' => 399,
            'supported' => true,
        ),
        array(
            'description' => 'Simple Product w/ Custom Options',
            'product_id' => 370,
            'supported' => false,
        ),
        array(
            'description' => 'Configurable Product',
            'product_id' => 404,
            'supported' => false,
        ),
        array(
            'description' => 'Configurable Product w/ Custom Options',
            'product_id' => 410,
            'supported' => false,
        ),
    );

    /**
     * @dataProvider provideProductSupportData
     */
    public function testProductSupport($description, $productId, $shouldBeSupported)
    {
        if ($productId === null) {
            $this->markTestIncomplete();
            return;
        }

        $helper = Mage::helper('nypwidget/products');

        $product = Mage::getModel('catalog/product')->load($productId);
        $this->assertNotEmpty($product->getId(), "Product $productId found.");

        $name = $product->getName();
        $message = $shouldBeSupported ?
            "$description ($name) is supported" :
            "$description ($name) is not supported";

        $this->assertEquals($shouldBeSupported, $helper->isProductSupported($product), $message);
    }

    public function provideProductSupportData()
    {
        return array_map(
            function($entry) {
                return array(
                    $entry['description'],
                    isset($entry['product_id']) ? $entry['product_id'] : null,
                    $entry['supported']
                );
            },
            $this->supportedProducts
        );
    }

    /**
     * @dataProvider provideProductTypeSupportData
     */
    public function testProductTypeSupport($type, $shouldBeSupported)
    {
        $helper = Mage::helper('nypwidget/products');

        $message = $shouldBeSupported ?
            "$type is supported" :
            "$type is not supported";

        $this->assertEquals($shouldBeSupported, $helper->isProductTypeSupported($type), $message);
    }

    public function provideProductTypeSupportData()
    {
        $data = array();
        foreach ($this->supportedProductTypes as $typeId => $supported) {
            $data[] = array($typeId, $supported);
        }
        return $data;
    }
}
