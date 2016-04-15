<?php

/**
 * Integration tests around parsing PriceWaiter offer items that reference
 * configurable products.
 */
class Integration_OfferItemResolution_ConfigurableProductTest
    extends PHPUnit_Framework_TestCase
{
    public $configurableProduct = array(
        'id' => 404,
        'sku' => 'msj006c',
        'options' => array(
            array(
                'option' => array(
                    'id' => 92,
                    'label' => 'Color',
                ),
                'value' => array(
                    'id' => 17,
                    'label' => 'Charcoal',
                ),
            ),
            array(
                'option' => array(
                    'id' => 180,
                    'label' => 'Size',
                ),
                'value' => array(
                    'id' => 79,
                    'label' => 'M',
                ),
            ),
        ),
        // Simple product based on selected options
        'simple_product' => array(
            'id' => 238,
            'sku' => 'msj007',
        ),
    );

    public function testResolveUsingConfigurableProductSkuAndProductOptions()
    {
        $this->markTestIncomplete();
    }

    public function testResolveUsingSimpleProductSkuAndProductOptions()
    {
        $this->markTestIncomplete();
    }

    public function testResolveUsingMetadata()
    {
        $addToCartForm = array(
            'form_key' => 'xXMpsryvI88PjkwD',
            'product' => $this->configurableProduct['id'],
            'related_product' => '',
            'qty' => '1',
        );

        // Add super_attribute[] options
        foreach($this->configurableProduct['options'] as $o) {
            $id = $o['option']['id'];
            $value = $o['value']['id'];
            $addToCartForm["super_attribute[{$id}]"] = $value;
        }

        $offerItem = new PriceWaiter_NYPWidget_Model_OfferItem(array(
            'metadata' => array(
                '_magento_product_configuration' => http_build_query($addToCartForm, '', '&'),
            ),
        ));

        $helper = Mage::helper('nypwidget/products');
        $resolvedItems = $helper->resolveItems(array($offerItem));

        $this->assertCount(1, $resolvedItems, 'Resolved to 1 item');

        $i = $resolvedItems[0];
        $this->assertInstanceOf('PriceWaiter_NYPWidget_Model_ResolvedItem', $i);

        $product = $i->getProduct();
        $this->assertEquals($this->configurableProduct['simple_product']['id'], $product->getId(), 'correct simple product located');
    }

    public function testMetadataFavoredOverSkuAndProductOptions()
    {
        $this->markTestIncomplete();
    }

}
