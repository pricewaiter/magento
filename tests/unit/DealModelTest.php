<?php

class TestableDeal extends PriceWaiter_NYPWidget_Model_Deal
{
    public $apiKeysToStoreId = array(
        'APIKEY' => 42,
    );

    protected function getStoreIdForApiKey($apiKey)
    {
        return isset($this->apiKeysToStoreId[$apiKey]) ?
            $this->apiKeysToStoreId[$apiKey] :
            false;
    }
}

class Unit_DealModelTest extends PHPUnit_Framework_TestCase
{
    public $defaultCreateDealBody;

    public function __construct()
    {
        $this->defaultCreateDealBody = json_decode('{
    "id": "1295a488-d2bf-4629-aa3d-6c8462ca444d",
    "test": false,
    "expires_at": "2016-07-15T22:40:53.243Z",
    "currency": "USD",
    "items": [
        {
            "product": {
                "sku": "TSHIRT-RD-MD",
                "name": "T-Shirt",
                "options": [
                    { "name" : "Size", "value": "Medium" },
                    { "name" : "Color", "value": "Red" }
                ]
            },
            "quantity": {
                "min": 3,
                "max": 3
            },
            "amount_per_item": {
                "cents": 1999,
                "value": "19.99"
            },
            "metadata": {
                "key": "value",
                "key2": "value2"
            }
        }
    ],
    "buyer": {
        "id": "2249db07-954f-40f5-86a9-40cb0538d51d",
        "email": "user@example.org",
        "marketing_opt_in": false,
        "location": {
            "postal_code": "98225",
            "country": "US"
        }
    },
    "coupon_code_prefix": "PW"
}');

    }

    public function testCreateDeal($body = null, $apiKey = 'APIKEY')
    {
        if ($body === null) {
            $body = $this->defaultCreateDealBody;
        }

        if (!is_string($body)) {
            $body = json_encode($body);
        }

        $request = new PriceWaiter_NYPWidget_Controller_Endpoint_Request(
            '8c3825f4-fd14-480d-93c3-daf52ab95ba9',
            $apiKey,
            '2016-03-01',
            $body
        );

        $deal = new TestableDeal();
        $deal->initFromCreateRequest($request);

        return $deal;
    }

    /**
     * @depends testCreateDeal
     */
    public function testCreateDealBuyerId(TestableDeal $deal)
    {
        $this->assertEquals('2249db07-954f-40f5-86a9-40cb0538d51d', $deal->getPricewaiterBuyerId());
    }

    /**
     * @depends testCreateDeal
     */
    public function testCreateDealWithExpiresAt(TestableDeal $deal)
    {
        $this->assertEquals('2016-07-15 22:40:53', $deal->getExpiresAt());
    }

    /**
     * @depends testCreateDeal
     */
    public function testIsExpiredWithExpiresAt(TestableDeal $deal)
    {
        $this->assertTrue($deal->isExpired(), 'Is currently expired');
        $this->assertFalse($deal->isExpired(strtotime('2015-08-15 00:00:00')), 'not expired in the past');
    }

    public function testCreateDealWithoutExpiresAt()
    {
        $body = $this->defaultCreateDealBody;
        unset($body->expires_at);

        $deal = $this->testCreateDeal($body);
        $this->assertNull($deal->getExpiresAt());

        return $deal;
    }

    /**
     * @depends testCreateDealWithoutExpiresAt
     */
    public function testIsExpiredWithoutExpiresAt(TestableDeal $deal)
    {
        $this->assertFalse($deal->isExpired(), 'Not currently expired');
        $this->assertFalse($deal->isExpired(strtotime('2015-08-15 00:00:00')), 'not expired in the past');
        $this->assertFalse($deal->isExpired(strtotime('2030-08-15 00:00:00')), 'not expired in the mysterious future');
    }

    /**
     * @expectedException PriceWaiter_NYPWidget_Exception_InvalidStore
     */
    public function testInvalidStoreThrows()
    {
        $this->testCreateDeal(null, 'SOME_WEIRD_API_KEY');
    }
}
