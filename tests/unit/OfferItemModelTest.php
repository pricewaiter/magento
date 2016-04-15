<?php

/**
 * Unit tests around the OfferItem model, whose responsibility is providing a
 * standardized view into offer data being handed off to Magento from PriceWaiter.
 */
class Unit_OfferItemModelTest extends PHPUnit_Framework_TestCase
{
    public function testAddToCartFormValue()
    {
        $item = new PriceWaiter_NYPWidget_Model_OfferItem(array(
            'metadata' => array(
                '_magento_product_configuration' => "form_key=SiW6bjM7iCqcrQAw&product=403&related_product=&super_attribute%5B92%5D=27&super_attribute%5B180%5D=77&qty=1",
            ),
        ));

        $this->assertSame('403', $item->getAddToCartForm('product'));
        $this->assertEquals(
            array(
                '92' => '27',
                '180' => '77',
            ),
            $item->getAddToCartForm('super_attribute')
        );
    }

    public function testAddToCartFormFull()
    {
        $item = new PriceWaiter_NYPWidget_Model_OfferItem(array(
            'metadata' => array(
                '_magento_product_configuration' => "form_key=SiW6bjM7iCqcrQAw&product=403&related_product=&super_attribute%5B92%5D=27&super_attribute%5B180%5D=77&qty=1",
            ),
        ));

        $this->assertEquals(
            array(
                'product' => '403',
                'super_attribute' => array(
                    '92' => '27',
                    '180' => '77',
                ),
                'form_key' => 'SiW6bjM7iCqcrQAw',
                'related_product' => '',
                'qty' => '1',
            ),
            $item->getAddToCartForm()
        );

    }

    public function testAddToCartFormValueDefault()
    {
        $item = new PriceWaiter_NYPWidget_Model_OfferItem(array());
        $this->assertSame('403', $item->getAddToCartForm('product', '403'));
    }

    public function testAddToCartFormValueDoubleEncoded()
    {
        $item = new PriceWaiter_NYPWidget_Model_OfferItem(array(
            'metadata' => array(
                '_magento_product_configuration' => rawurlencode("form_key=SiW6bjM7iCqcrQAw&product=403&related_product=&super_attribute%5B92%5D=27&super_attribute%5B180%5D=77&qty=1"),
            ),
        ));

        $this->assertSame('403', $item->getAddToCartForm('product'));
        $this->assertEquals(
            array(
                '92' => '27',
                '180' => '77',
            ),
            $item->getAddToCartForm('super_attribute')
        );
    }

    public function testAmountPerItemInCents()
    {
        $item = new PriceWaiter_NYPWidget_Model_OfferItem(array(
            'amount_per_item' => array(
                'cents' => 1337,
                'value' => '13.37',
            ),
        ));

        $this->assertEquals(1337, $item->getAmountPerItemInCents());
    }

    public function testMetadataGetAll()
    {
        $md = array(
            'foo' => 'bar',
            'baz' => 'bat',
        );
        $item = new PriceWaiter_NYPWidget_Model_OfferItem(array('metadata' => $md));
        $this->assertEquals($md, $item->getMetadata());
    }

    public function testMetadataGetItem()
    {
        $metadata = array(
            'foo' => 'bar',
            'baz' => 'bat',
        );

        $item = new PriceWaiter_NYPWidget_Model_OfferItem(compact('metadata'));
        $this->assertEquals('bar', $item->getMetadata('foo'));
    }

    public function testMetadataGetItemNullDefault()
    {
        $metadata = array(
            'foo' => 'bar',
            'baz' => 'bat',
        );
        $item = new PriceWaiter_NYPWidget_Model_OfferItem(compact('metadata'));
        $this->assertNull($item->getMetadata('non_existent_key'));
    }

    public function testMetadataGetItemWithDefault()
    {
        $md = array(
            'foo' => 'bar',
            'baz' => 'bat',
        );
        $item = new PriceWaiter_NYPWidget_Model_OfferItem(array('metadata' => $md));
        $this->assertEquals('woo', $item->getMetadata('non_existent_key', 'woo'));
    }

    public function testQuantitySingleNumber()
    {
        $item = new PriceWaiter_NYPWidget_Model_OfferItem(array(
            'quantity' => 3,
        ));
        $this->assertEquals(3, $item->getMinimumQuantity(), "getMinimumQuantity");
        $this->assertEquals(3, $item->getMaximumQuantity(), "getMaximumQuantity");
    }

    public function testQuantityMinAndMax()
    {
        $item = new PriceWaiter_NYPWidget_Model_OfferItem(array(
            'quantity' => array(
                'min' => 3,
                'max' => 5,
            ),
        ));

        $this->assertEquals(3, $item->getMinimumQuantity(), "getMinimumQuantity");
        $this->assertEquals(5, $item->getMaximumQuantity(), "getMaximumQuantity");
    }

    public function testProductName()
    {
        $item = new PriceWaiter_NYPWidget_Model_OfferItem(array(
            'product' => array(
                'name' => 'Foo Product',
            ),
        ));
        $this->assertEquals('Foo Product', $item->getProductName());
    }

    public function testProductOptions()
    {
        $item = new PriceWaiter_NYPWidget_Model_OfferItem(array(
            'product' => array(
                'options' => array(
                    array('name' => 'Color', 'value' => 'Blue'),
                    array('name' => 'Size', 'value' => 'Medium'),
                ),
            ),
        ));

        $this->assertEquals(
            array(
                'Color' => 'Blue',
                'Size' => 'Medium',
            ),
            $item->getProductOptions()
        );
    }

    public function testProductSku()
    {
        $item = new PriceWaiter_NYPWidget_Model_OfferItem(array(
            'product' => array(
                'sku' => 'ABCD1234',
            ),
        ));
        $this->assertEquals('ABCD1234', $item->getProductSku());
    }
}
