<?php

class Integration_OfferItem_HandlerTest extends Integration_AbstractProductTest
{
    /**
     * @expectedException PriceWaiter_NYPWidget_Exception_Product_Invalid
     */
    public function testConfigurableProductMissingOptions()
    {
        list($product, $addToCartForm) = $this->getConfigurableProduct(100);

        // Tweak add to cart form such that it is *INVALID*1!!!!
        unset($addToCartForm['super_attribute']);

        $handler = Mage::getSingleton('nypwidget/offer_item_handler');
        $handler->getConfiguredProducts(
            $product,
            new Varien_Object($addToCartForm)
        );
    }
}
