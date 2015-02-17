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
        $signature = 'sha256=febf35a286c9278efc025f5c158eaedd6d28e212e95fcb8e8035efa6286fab13';

        $fields = array(
            'product_sku' => 'ABC 456-Black-10',
            'product_configuration' => 'form_key%3DtpeLQCbr4TZMOFB7%26product%3D337%26related_product%3D%26qty%3D1'
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

        $response = $this->postMessage($fields, $signature);
        $this->assertEquals($response, $expectedResponse);
    }

    public function testConfigurableProduct()
    {
        $signature = 'sha256=37e94176514232f240466122f1e1ea18d91af8707037634b76ef0e1dd1009ad3';

        $fields = array(
            'product_sku' => 'wbk002c',
            'product_configuration' => 'form_key%3DtpeLQCbr4TZMOFB7%26product%3D877%26related_product%3D%26super_attribute%255B92%255D%3D20%26super_attribute%255B180%255D%3D79%26qty%3D2'
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

        $response = $this->postMessage($fields, $signature);
        $this->assertEquals($response, $expectedResponse);
    }

    public function testBundleProduct()
    {
        $signature = 'sha256=c7595a61378831d53439ed84c0b6fad3741846ad63eb03f5a5961cc9467ac724';

        $fields = array(
            'product_sku' => 'hdb010',
            'product_configuration' => 'form_key%3DtpeLQCbr4TZMOFB7%26product%3D447%26related_product%3D%26bundle_option%255B24%255D%3D91%26bundle_option_qty%255B24%255D%3D1%26bundle_option%255B23%255D%3D89%26bundle_option_qty%255B23%255D%3D1%26qty%3D1'
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

        $response = $this->postMessage($fields, $signature);
        $this->assertEquals($response, $expectedResponse);
    }

    public function testGroupedProduct()
    {
        $signature = 'sha256=d97d57b2e70157e138369c75200eb8f2eb1cbe88868fa99b0989ad3f477a0a94';

        $fields = array(
            'product_sku' => 'hdb010',
            'product_configuration' => 'form_key%3DsZgOGkXoxDHEu4CJ%26product%3D439%26related_product%3D%26super_group%255B376%255D%3D1%26super_group%255B377%255D%3D1%26super_group%255B541%255D%3D3'
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

        $response = $this->postMessage($fields, $signature);
        $this->assertEquals($response, $expectedResponse);
    }
}
