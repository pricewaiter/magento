<?php

require_once(__DIR__ . '/../AbstractSimpleProductTest.php');

/**
 * Tests around revoking a deal containing a single simple product.
 */
class RevokeDealSimpleProductTest extends AbstractSimpleProductTest
{
    public function testRevokeDealUpdatesDeal()
    {
        $deal = $this->createDeal();

        $revokeRequest = new PriceWaiter_NYPWidget_Controller_Endpoint_Request(
            'some-revoke-request',
            getenv('PRICEWAITER_API_KEY'),
            '2016-03-01',
            json_encode(array(
                'id' => $deal->getDealId(),
            )),
            strtotime('2016-03-15 12:13:14')
        );

        $this->assertSame($deal, $deal->processRevokeRequest($revokeRequest), 'processRevokeRequest() returns $this');

        $this->assertTrue(!!$deal->revoked, 'deal is revoked after processRevokeRequest');
        $this->assertEquals('some-revoke-request', $deal->revoke_request_id, 'revoke_request_id set on revoke');
        $this->assertEquals('2016-03-15 12:13:14', $deal->revoked_at, 'revoked_at timestamp is set');

        $id = $deal->getDealId();
        $foundDeal = Mage::getModel('nypwidget/deal')->load($id);
        $this->assertNotEmpty($foundDeal, 'deal still exists in db after revoke');
        $this->assertTrue(!!$deal->revoked, 'deal in db marked as revoked');
    }
}
