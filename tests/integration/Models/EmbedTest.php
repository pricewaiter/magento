<?php

class Integration_Models_EmbedTest
    extends Integration_AbstractProductTest
{
    // TODO: More coverage in here:
    //
    //   - Enable/disable by customer group
    //   - Enable/disable by category

    public function testIsButtonEnabled()
    {
        $product = $this->getSimpleProduct();

        $embed = Mage::getModel('nypwidget/embed')
            ->setStore(Mage::app()->getStore())
            ->setProduct($product);

        $this->assertTrue($embed->isButtonEnabled());

        $opts = $embed->buildPriceWaiterOptionsVar();
        $this->assertObjectNotHasAttribute('enableButton', $opts);
    }

    public function testButtonDisabledForStore()
    {
        $this->markTestIncomplete();
    }

    public function testIsConversionToolsEnabled()
    {
        $product = $this->getSimpleProduct();

        $embed = Mage::getModel('nypwidget/embed')
            ->setStore(Mage::app()->getStore())
            ->setProduct($this->getSimpleProduct());

        $this->assertTrue($embed->isConversionToolsEnabled());

        $opts = $embed->buildPriceWaiterOptionsVar();
        $this->assertObjectNotHasAttribute('enableConversionTools', $opts);
    }

    public function testConversionToolsDisabledForStore()
    {
        $this->markTestIncomplete();
    }

    public function testShouldRenderPlaceholder()
    {
        $embed = Mage::getModel('nypwidget/embed')
            ->setStore(Mage::app()->getStore())
            ->setProduct($this->getSimpleProduct());

        $this->assertTrue($embed->shouldRenderButtonPlaceholder());
    }

    public function testAnonymousRenderDoesntThrow()
    {
        // Not a very good test, but at least asserts that the whole
        // complex frontend render does not throw

        $embed = Mage::getModel('nypwidget/embed')
            ->setStore(Mage::app()->getStore())
            ->setProduct($this->getSimpleProduct());

        $tags = $embed->getScriptTags();
        $this->assertNotEmpty($tags);
    }

    public function testNoIdsToSkusForSimpleProduct()
    {
        $product = $this->getSimpleProduct();

        $embed = Mage::getModel('nypwidget/embed')
            ->setStore(Mage::app()->getStore())
            ->setProduct($product);

        $vars = $embed->getJavascriptVariables();
        $this->assertArrayNotHasKey('PriceWaiterIdToSkus', $vars);
    }

    public function testIdToSkusPresentForConfigurable()
    {
        list($product) = $this->getConfigurableProduct();

        $embed = Mage::getModel('nypwidget/embed')
            ->setStore(Mage::app()->getStore())
            ->setProduct($product);

        $vars = $embed->getJavascriptVariables();
        $this->assertArrayHasKey('PriceWaiterIdToSkus', $vars);
    }

    public function testBuildProductObjectWithSimpleProduct()
    {
        $product = $this->getSimpleProduct();
        $obj = Mage::getModel('nypwidget/embed')->buildProductObject($product);

        $this->assertNotEmpty($obj->image, 'image is present');
        unset($obj->image);

        $this->assertEquals(
            array(
                'name' => 'Madison 8GB Digital Media Player',
                'sku' => 'hde012',
                'price' => '150.0000',
            ),
            (array)$obj
        );
    }

    public function testProductVarUsesSpecialPrice()
    {
        $id = '384'; // Park Row Throw
        $product = $this->getProduct($id, 'simple');

        $obj = Mage::getModel('nypwidget/embed')->buildProductObject($product);
        $this->assertEquals(120, $obj->price, 'Price is correct');
    }

    public function testIncludeCustomerInfo()
    {
        $customer = Mage::getModel('customer/customer')
            ->setStore(Mage::app()->getStore())
            ->loadByEmail('mickey@example.com');
        $this->assertNotEmpty($customer->getId(), 'Customer found in db');

        $embed = Mage::getModel('nypwidget/embed')
            ->setStore(Mage::app()->getStore())
            ->setProduct($this->getSimpleProduct())
            ->setCustomer($customer);

        $opts = $embed->buildPriceWaiterOptionsVar();
        $this->assertObjectHasAttribute('user', $opts);
        $this->assertObjectHasAttribute('email', $opts->user);
        $this->assertEquals($customer->getEmail(), $opts->user->email, 'Email set on PriceWaiterOptions');

        $this->assertObjectHasAttribute('postal_code', $opts);
        $this->assertEquals('32801', $opts->postal_code);

        $this->assertObjectHasAttribute('country', $opts);
        $this->assertEquals('US', $opts->country);
    }

    public function testNoCustomerInfoWhenNotLoggedIn()
    {
        $embed = Mage::getModel('nypwidget/embed')
            ->setStore(Mage::app()->getStore())
            ->setProduct($this->getSimpleProduct());

        $opts = $embed->buildPriceWaiterOptionsVar();
        $this->assertObjectNotHasAttribute('user', $opts);
        $this->assertObjectNotHasAttribute('postal_code', $opts);
        $this->assertObjectNotHasAttribute('country', $opts);
    }

    public function testCustomOptionsVar()
    {
        $id = '410'; // Chelsea Tee w/ extra options
        $productWithCustomOptions = $this->getProduct($id, 'configurable');

        $embed = Mage::getModel('nypwidget/embed')
            ->setStore(Mage::app()->getStore())
            ->setProduct($productWithCustomOptions);

        $vars = $embed->getJavascriptVariables();
        $this->assertArrayHasKey('PriceWaiterCustomOptions', $vars);

        $this->assertEquals(
            array(
                array(
                    'id' => '3',
                    'type' => 'area',
                    'title' => 'monogram',
                    'required' => false,
                ),
                array(
                    'id' => '2',
                    'type' => 'drop_down',
                    'title' => 'Test Custom Options',
                    'required' => false,
                    'values' => array(
                        array(
                            'id' => '1',
                            'title' => 'model 1',
                        ),
                        array(
                            'id' => '2',
                            'title' => 'model 2',
                        ),
                    ),
                ),
            ),
            $vars['PriceWaiterCustomOptions']
        );
    }

    public function testCustomOptionsVarWithSkus()
    {
        // Add skus to options and ensure that it is included in the resulting var.
        $id = '410'; // Chelsea Tee w/ extra options
        $productWithCustomOptions = $this->getProduct($id, 'configurable');

        $productWithCustomOptions->getOptionById('3')->setSku('foo');
        $productWithCustomOptions->getOptionById('2')
            ->getValueById('1')
            ->setSku('model-one');

        $productWithCustomOptions->getOptionById('2')
            ->getValueById('2')
            ->setSku('model-two');

        $embed = Mage::getModel('nypwidget/embed')
            ->setStore(Mage::app()->getStore())
            ->setProduct($productWithCustomOptions);

        $vars = $embed->getJavascriptVariables();
        $this->assertArrayHasKey('PriceWaiterCustomOptions', $vars);

        $this->assertEquals(
            array(
                array(
                    'id' => '3',
                    'type' => 'area',
                    'title' => 'monogram',
                    'required' => false,
                    'sku' => 'foo',
                ),
                array(
                    'id' => '2',
                    'type' => 'drop_down',
                    'title' => 'Test Custom Options',
                    'required' => false,
                    'values' => array(
                        array(
                            'id' => '1',
                            'title' => 'model 1',
                            'sku' => 'model-one',
                        ),
                        array(
                            'id' => '2',
                            'title' => 'model 2',
                            'sku' => 'model-two',
                        ),
                    ),
                ),
            ),
            $vars['PriceWaiterCustomOptions']
        );

    }

    public function testNoCustomOptionsVarForProductWithNoCustomOptions()
    {
        $product = $this->getSimpleProduct();

        $embed = Mage::getModel('nypwidget/embed')
            ->setStore(Mage::app()->getStore())
            ->setProduct($product);

        $vars = $embed->getJavascriptVariables();
        $this->assertArrayNotHasKey('PriceWaiterCustomOptions', $vars);
    }
}
