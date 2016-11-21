<?php

class Integration_Model_SessionTest extends PHPUnit_Framework_TestCase
{
    private $fixtures = array(
        array(
            'id' => 'deal-revoked',
            'pricewaiter_buyer_id' => 'cool-buyer',
            'revoked' => 1,
        ),
        array(
            'id' => 'deal-wrong-buyer-2',
            'pricewaiter_buyer_id' => 'uncool-buyer',
        ),
        array(
            'id' => 'deal-good-1',
            'pricewaiter_buyer_id' => 'cool-buyer',
            'expires_at' => '2016-09-15 15:00:00',
        ),
        array(
            'id' => 'deal-wrong-buyer-2',
            'pricewaiter_buyer_id' => 'uncool-buyer',
            'revoked' => 1,
        ),
        array(
            'id' => 'deal-good-2',
            'pricewaiter_buyer_id' => 'cool-buyer',
        ),
        array(
            'id' => 'deal-expired-1',
            'pricewaiter_buyer_id' => 'cool-buyer',
            'expires_at' => '2016-09-15 14:13:12',
        ),
        array(
            'id' => 'deal-expired-2',
            'pricewaiter_buyer_id' => 'cool-buyer',
            'expires_at' => '2015-09-15 14:13:12',
        ),
        array(
            'id' => 'deal-unrevoked-but-already-used-1',
            'pricewaiter_buyer_id' => 'cool-buyer',
            'order_id' => 1234,
        ),
    );

    private $now = '2016-09-15 14:13:12';

    public function setUp()
    {
        parent::setUp();

        $baseDeal = array(
            'revoked' => 0,
        );

        foreach($this->fixtures as $deal) {
            $record = array_merge($baseDeal, $deal);

            $deal = Mage::getModel('nypwidget/deal')
                ->setId($record['id']);

            unset($record['id']);
            foreach($record as $key => $value) {
                $deal->setData($key, $value);
            }

            $deal->save();
        }
    }

    public function tearDown()
    {
        foreach($this->fixtures as $record) {
            $deal = Mage::getModel('nypwidget/deal')
                ->load($record['id']);
            if ($deal) {
                $deal->delete();
            }
        }
    }

    public function testGetAndSetBuyerId()
    {
        $session = Mage::getModel('nypwidget/session');
        $session->setBuyerId('abcd');
        $this->assertEquals('abcd', $session->getBuyerId());
    }

    public function testSetBuyerIdResetsDeals()
    {
        $session = Mage::getModel('nypwidget/session')
            ->setBuyerId('cool-buyer');
        $session->getActiveDeals();

        $session->setBuyerId('somone-else');
        $this->assertCount(0, $session->getActiveDeals());
    }

    public function testReset()
    {
        $session = Mage::getModel('nypwidget/session')
            ->setBuyerId('cool-buyer');
        $session->getActiveDeals();

        $session->reset();
        $this->assertNull($session->getBuyerId(), 'No buyer id after reset');
        $this->assertCount(0, $session->getActiveDeals(), 'no active deals after reset');
    }

    public function testGetActiveDeals()
    {
        $session = Mage::getModel('nypwidget/session')
            ->setBuyerId('cool-buyer')
            ->setNow($this->now);

        $deals = $session->getActiveDeals();

        $ids = array();
        foreach($deals as $deal) {
            $ids[] = $deal->getId();
        }
        sort($ids);

        $this->assertEquals(
            array(
                'deal-good-1',
                'deal-good-2',
            ),
            $ids
        );
    }
}
