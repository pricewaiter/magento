<?php

class ProductInfo extends PHPUnit_Framework_TestCase
{
    private $_ch = null;

    public function __construct()
    {
        $protocol = getenv('PROTOCOL');
        $hostname = getenv('VIRTUALHOST_NAME');
        $this->_ch = curl_init();
        curl_setopt($this->_ch, CURLOPT_POST, 1);
        curl_setopt($this->_ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($this->_ch, CURLOPT_URL,
            "{$protocol}://{$hostname}/index.php/pricewaiter/productinfo");
    }

    public function postMessage($fields, $signature)
    {
        $postString = '';

        foreach ($fields as $k => $v) {
            $postString .= urlencode($k) . '=' . urlencode($v) . '&';
        }

        curl_setopt($this->_ch, CURLOPT_HTTPHEADER, array(
            "X-PriceWaiter-Signature: $signature"
        ));
        curl_setopt($this->_ch, CURLOPT_POSTFIELDS, rtrim($postString, '&'));

        return curl_exec($this->_ch);
    }

    public function testSimpleProduct()
    {
        $signature = 'sha256=1ad5813456f31d6a97172f6f3aaa68ccd3675f12764e29eab0353db41cca1f21';

        $fields = array(
            'product_sku' => 'ABC 456-Black-10',
            '_magento_product_configuration' => 'form_key%3DtpeLQCbr4TZMOFB7%26product%3D337%26related_product%3D%26qty%3D1'
        );

        $expectedResponse = <<<EOR
{
    "allow_pricewaiter": true,
    "inventory": 7,
    "can_backorder": false,
    "retail_price": "295",
    "retail_price_currency": "EUR",
    "regular_price": "295.0000",
    "regular_price_currency": "EUR"
}
EOR;

        $expectedResponse = preg_replace('/\s+/', '', trim($expectedResponse));
        $response = $this->postMessage($fields, $signature);
        $this->assertEquals($expectedResponse, $response);
    }

    public function testConfigurableProduct()
    {
        $signature = 'sha256=ddb5282a97d8a71dee207e6488ce9ff29140e3bdd5b0ecbedad23336a97b4918';

        $fields = array(
            'product_sku' => 'wbk002c',
            '_magento_product_configuration' => 'form_key%3DtpeLQCbr4TZMOFB7%26product%3D877%26related_product%3D%26super_attribute%255B92%255D%3D20%26super_attribute%255B180%255D%3D79%26qty%3D2'
        );

        $expectedResponse = <<<EOR
{
    "allow_pricewaiter": true,
    "inventory": 25,
    "can_backorder": false,
    "retail_price": "120.0000",
    "retail_price_currency": "EUR",
    "regular_price": "150.0000",
    "regular_price_currency": "EUR"
}
EOR;

        $expectedResponse = preg_replace('/\s+/', '', trim($expectedResponse));
        $response = $this->postMessage($fields, $signature);
        $this->assertEquals($expectedResponse, $response);
    }

    public function testBundleProduct()
    {
        $signature = 'sha256=38b0881f31498061484d2d4189c426eb6fa18dddbab9e9881f079f867755a233';

        $fields = array(
            'product_sku' => 'hdb010',
            '_magento_product_configuration' => 'form_key%3DtpeLQCbr4TZMOFB7%26product%3D447%26related_product%3D%26bundle_option%255B24%255D%3D91%26bundle_option_qty%255B24%255D%3D1%26bundle_option%255B23%255D%3D89%26bundle_option_qty%255B23%255D%3D1%26qty%3D1'
        );

        $expectedResponse = <<<EOR
{
    "allow_pricewaiter": true,
    "inventory": 0,
    "can_backorder": true,
    "retail_price": "245",
    "retail_price_currency": "EUR"
}
EOR;

        $expectedResponse = preg_replace('/\s+/', '', trim($expectedResponse));
        $response = $this->postMessage($fields, $signature);
        $this->assertEquals($expectedResponse, $response);
    }

    public function testGroupedProduct()
    {
        $signature = 'sha256=3bd2b05dfd657bb44993c5340365d5d50642507542080369e16e6e52ee14eb99';

        $fields = array(
            'product_sku' => 'hdb010',
            '_magento_product_configuration' => 'form_key%3DsZgOGkXoxDHEu4CJ%26product%3D439%26related_product%3D%26super_group%255B376%255D%3D1%26super_group%255B377%255D%3D1%26super_group%255B541%255D%3D3'
        );

        $expectedResponse = <<<EOR
{
    "allow_pricewaiter": true,
    "inventory": 7,
    "can_backorder": false,
    "retail_price": "3200",
    "retail_price_currency": "EUR",
    "regular_price": "3200",
    "regular_price_currency": "EUR"
}
EOR;

        $expectedResponse = preg_replace('/\s+/', '', trim($expectedResponse));
        $response = $this->postMessage($fields, $signature);
        $this->assertEquals($expectedResponse, $response);
    }
}
