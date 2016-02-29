<?php

class ProductInfo extends PHPUnit_Framework_TestCase
{
    private $_ch = null;
    private $existingSecret = null;
    private $secret = '7964fdf170df8cbe12c75487c24699d5564f1a53d3c2b6fe';

    public function __construct()
    {
        $protocol = getenv('PROTOCOL');
        $hostname = getenv('VIRTUALHOST_NAME');
        $port = getenv('PORT');
        $this->_ch = curl_init();
        curl_setopt($this->_ch, CURLOPT_POST, 1);
        curl_setopt($this->_ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($this->_ch, CURLOPT_URL,
            "{$protocol}://{$hostname}:{$port}/index.php/pricewaiter/productinfo");
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

        // Stash the existing secret key and set one for testing.
        $config = Mage::getModel('core/config');
        $this->existingSecret = Mage::getStoreConfig('pricewaiter/configuration/api_secret');
        $config->saveConfig('pricewaiter/configuration/api_secret', $this->secret);

        $status = curl_exec($this->_ch);

        // Reset the secret key.
        $config->saveConfig('pricewaiter/configuration/api_secret', $this->existingSecret);

        return $status;
    }

    public function testSimpleProduct()
    {
        $signature = 'sha256=936813aecdbad097751ff49fdf6adb660589ac691fbc8ed84bcbf64461baa9ab';

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
    "retail_price_currency": "USD",
    "regular_price": "295.0000",
    "regular_price_currency": "USD"
}
EOR;

        $expectedResponse = preg_replace('/\s+/', '', trim($expectedResponse));
        $response = $this->postMessage($fields, $signature);
        $this->assertEquals($expectedResponse, $response);
    }

    public function testConfigurableProduct()
    {
        $signature = 'sha256=762d247fe45890905c388c4881a2130f9f24923d5c6e26320be956097b2fa9ce';

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
    "retail_price_currency": "USD",
    "regular_price": "150.0000",
    "regular_price_currency": "USD"
}
EOR;

        $expectedResponse = preg_replace('/\s+/', '', trim($expectedResponse));
        $response = $this->postMessage($fields, $signature);
        $this->assertEquals($expectedResponse, $response);
    }

    public function testBundleProduct()
    {
        $signature = 'sha256=075b44b01fa014db62f0344d06e6a7b41a7f14bb6a06abc4ef961cbfac74edcf';

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
    "retail_price_currency": "USD"
}
EOR;

        $expectedResponse = preg_replace('/\s+/', '', trim($expectedResponse));
        $response = $this->postMessage($fields, $signature);
        $this->assertEquals($expectedResponse, $response);
    }

    public function testGroupedProduct()
    {
        $signature = 'sha256=cac0b0a0226dcf39029f0873e5b72736a550aa7b8f893e9cd6630625a916b6d1';

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
    "retail_price_currency": "USD",
    "regular_price": "3200",
    "regular_price_currency": "USD"
}
EOR;

        $expectedResponse = preg_replace('/\s+/', '', trim($expectedResponse));
        $response = $this->postMessage($fields, $signature);
        $this->assertEquals($expectedResponse, $response);
    }
}
