<?php

require_once(__DIR__ . '/../AbstractSimpleProductTest.php');

class CreateDealSimpleProductTest extends AbstractSimpleProductTest
{
    // NOTE: These tests currently use the 1.9.2 sample dataset rather than
    //       custom fixtures. A future enhancement would be to generate fixture
    //       data specifically for the tests.

    public function testSimpleProductDealBasics()
    {
        $deal = $this->createDeal();

        $this->assertEquals('SOMECREATEREQUEST', $deal->getCreateRequestId());
        $this->assertEquals(date('Y-m-d H:i:s', strtotime($this->now)), $deal->getCreatedAt());
        $this->assertNotEmpty($deal->getStoreId());
        $this->assertNull($deal->getExpiresAt());

        $id = $deal->getDealId();
        $foundDeal = Mage::getModel('nypwidget/deal')->load($id);
        $this->assertEquals('SOMECREATEREQUEST', $foundDeal->getCreateRequestId());
    }

    public function testSimpleProductAddToCartUrl()
    {
        $deal = $this->createDeal();

        $baseUrl = getenv('MAGENTO_BASE_URL');

        $this->assertEquals(
            "{$baseUrl}/checkout/cart/add/?form_key=FORMKEY&product=399&qty=1",
            $deal->getAddToCartUrl('FORMKEY')
        );
    }

}
