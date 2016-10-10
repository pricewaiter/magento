<?php

// TODO: Figure out how to get Mage to autoload this.
require_once(__DIR__ . '/../../../app/code/community/PriceWaiter/NYPWidget/controllers/CheckoutController.php');
require_once(__DIR__ . '/_http.php');

class Integration_Controller_CheckoutControllerTest
    extends Integration_AbstractProductTest
{
    public function testRedirectForActiveDealWithInStockProduct()
    {
        $product = $this->getSimpleProduct();
        $deal = $this->createDeal($product, '$5 off 1 - 3');

        // Clear the cart
        $cart = Mage::getSingleton('checkout/cart')->truncate();

        $r = $this->makeCheckoutRequest($deal->getId());

        $this->assertEquals(302, $r->statusCode, 'Returned 302 status code');
        $this->assertEmpty($r->errorCode, 'No error code');
        $this->assertEquals(Mage::getUrl('checkout/cart'), $r->location, 'Redirects to cart');
        $this->assertInCart($product);
    }

    public function testRedirectForRevokedDeal()
    {
        // If the buyer has clicked a link for a revoked deal,
        // *assume* there is an unrevoked deal waiting for them somewhere--
        // add the item to their cart and count on our total model discovering
        // the correct deal and showing their discount.

        $this->clearCart();

        $product = $this->getSimpleProduct();
        $deal = $this->createDeal($product, '$5 off 1 - 3');
        $deal->setRevoked(true);
        $deal->save();

        $r = $this->makeCheckoutRequest($deal->getId());

        $this->assertEquals(302, $r->statusCode, 'Returned 302 status code');
        $this->assertEquals(Mage::getUrl('checkout/cart'), $r->location, 'Redirects to cart page');
        $this->assertEquals('deal_revoked', $r->errorCode, 'Response includes helpful error code in header.');
    }

    public function testRedirectForExpiredDeal()
    {
        // Similar thinking here as for revoked--add product to cart
        // and hope for the best.

        $this->clearCart();

        $product = $this->getSimpleProduct();
        $deal = $this->createDeal($product, '$5 off 1 - 3');
        $deal->setExpiresAt(date('Y-m-d H:i:s', strtotime('1 day ago')));
        $deal->save();

        $r = $this->makeCheckoutRequest($deal->getId());

        $this->assertEquals(302, $r->statusCode, 'Returned 302 status code');
        $this->assertEquals(Mage::getUrl('checkout/cart'), $r->location, 'Redirects to cart page');
        $this->assertEquals('deal_expired', $r->errorCode, 'Response includes helpful error code.');
    }

    public function testRedirectForOutOfStockDeal()
    {
        // When buyers land on our checkout_url page, we attempt to ensure the
        // product(s) in their deal have been added to their cart.
        // If the product is out of stock, we redirect to the product page and
        // show an error message.

        $product = $this->getSimpleProduct(0);
        $deal = $this->createDeal($product, '$5 off 1 - 3');

        $this->clearCart();

        $r = $this->makeCheckoutRequest($deal->getId());

        $this->assertEquals(302, $r->statusCode, 'Returned 302 status code');
        $this->assertEquals($product->getProductUrl(), $r->location, 'Redirects to product page');
        $this->assertInCart($product);

        // We expect there's an error waiting for us in session
        $session = Mage::getSingleton('checkout/cart')->getCheckoutSession();
        $messages = $session->getMessages()->getItems();
        $this->assertCount(1, $messages, 'Error message sitting in session');
    }

    public function testRedirectForInvalidProduct()
    {
        // If a deal's product data is invalid in some way, we
        // should just send the user back to the homepage and include
        // some debugging info in headers.

        $product = $this->getSimpleProduct(0);
        $deal = $this->createDeal($product, '$5 off 1 - 3');

        // Remove metadata, making it "impossible" to look up product data
        $json = json_decode($deal->getCreateRequestBodyJson(), true);
        $json['items'][0]['metadata'] = new StdClass();
        $deal->setCreateRequestBodyJson($json);
        $deal->save();

        $r = $this->makeCheckoutRequest($deal->getId());

        $this->assertEquals(302, $r->statusCode, 'Returned 302 status code');
        $this->assertNotEmpty($r->errorCode, 'Response includes an error code');
        $this->assertEquals(Mage::getUrl('/'), $r->location, 'Redirects to homepage');
    }

    protected function assertInCart(Mage_Catalog_Model_Product $product)
    {
        $cart = Mage::getSingleton('checkout/cart');

        $quote = $cart->getQuote();
        foreach($quote->getAllItems() as $item) {
            if ($item->getProductId() == $product->getId()) {
                return;
            }
        }

        $this->fail('Product not found in cart');
    }

    protected function clearCart()
    {
        $cart = Mage::getSingleton('checkout/cart');
        $cart->truncate();
    }

    protected function makeCheckoutRequest($dealId)
    {
        $request = new Zend_Controller_Request_Http();
        $request->setQuery('d', $dealId);

        $response = new TestableHttpResponse();

        $controller = Mage::getControllerInstance(
            'PriceWaiter_NYPWidget_CheckoutController',
            $request,
            $response
        );

        $controller->indexAction();

        $statusCode = $response->getHttpResponseCode();
        $location = null;
        $errorCode = null;

        foreach($response->getHeaders() as $h) {
            if ($h['name'] === 'Location') {
                $location = $h['value'];
            } else if ($h['name'] === 'X-Pricewaiter-Error') {
                $errorCode = $h['value'];
            }
        }

        return (object)compact('statusCode', 'location', 'errorCode');
    }
}
