<?php

// TODO: Figure out how to get Mage to autoload this.
require_once(__DIR__ . '/../../../app/code/community/PriceWaiter/NYPWidget/controllers/ProductinfoController.php');
require_once(__DIR__ . '/_http.php');

class Integration_Controllers_ProductinfoControllerTest
    extends Integration_AbstractProductTest
{
    public function testProductFoundRequest()
    {
        $product = $this->getSimpleProduct(100);

        $response = $this->makeRequest(array(
            'product_sku' => $product->getSku(),
            '_magento_product_configuration' => http_build_query(array(
                'product' => $product->getId(),
            )),
        ));

        $this->assertEquals(200, $response->getHttpResponseCode(), 'Got 200 response');

        $body = $response->getBody();
        $this->assertNotEmpty($body, 'Response has body');

        $json = json_decode($body, true);
        $this->assertEquals(
            array(
                'allow_pricewaiter' => true,
                'can_backorder' => false,
                'inventory' => 100,
                'retail_price' => '150',
                'retail_price_currency' => 'USD',
            ),
            $json
        );

        $signature = $this->getResponseHeader($response, 'X-PriceWaiter-Signature');
        $this->assertNotEmpty($signature, 'Response has signature');

        $expectedSignature = 'sha256=' . hash_hmac('sha256', $body, getenv('PRICEWAITER_SHARED_SECRET'));
        $this->assertEquals($expectedSignature, $signature, 'Response signature looks right');
    }

    public function testProductNotFoundRequest()
    {
        $product = $this->getSimpleProduct();

        $response = $this->makeRequest(array(
            'product_sku' => $product->getSku(),
            '_magento_product_configuration' => http_build_query(array(
                'product' => $product->getId() . '9383938_reallyfake_9393',
            )),
        ));

        $this->assertEquals(404, $response->getHttpResponseCode());
    }

    public function testInvalidSignatureRequest()
    {
        $product = $this->getSimpleProduct();
        $response = $this->makeRequest(array(
            'product_sku' => $product->getSku(),
            '_magento_product_configuration' => http_build_query(array(
                'product' => $product->getId(),
            )),
        ), 'obviously_wrong_signature');

        $this->assertEquals(400, $response->getHttpResponseCode());
    }

    public function makeRequest(array $postFields, $signature = null)
    {
        $rawBody = http_build_query($postFields);

        $request = new TestableHttpRequest();
        $request->setRawBody($rawBody);
        $request->setPost($postFields);

        if ($signature === null) {
            $signature = 'sha256=' . hash_hmac('sha256', $rawBody, getenv('PRICEWAITER_SHARED_SECRET'));
        }

        $request->setMockHeader('X-PriceWaiter-Signature', $signature);

        $response = new TestableHttpResponse();

        $controller = Mage::getControllerInstance(
            'PriceWaiter_NYPWidget_ProductinfoController',
            $request,
            $response
        );

        $controller->indexAction();

        return $response;
    }

    public function getResponseHeader(TestableHttpResponse $response, $header)
    {
        foreach($response->getHeaders() as $h) {
            if (strcasecmp($h['name'], $header) === 0) {
                return $h['value'];
            }
        }

        $this->assertFail("Header '$header' not found on response.");
    }
}
