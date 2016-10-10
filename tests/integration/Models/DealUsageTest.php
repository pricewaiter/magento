<?php

/**
 * Tests around the DealUsage resource model used to establish and integerrogate
 * the relationship between deals and quotes and orders.
 */
class Integration_Models_DealUsageTest
    extends Integration_AbstractProductTest
{
    public function testRecordDealUsageOnQuote()
    {
        $product = $this->getSimpleProduct();
        $quote = $this->createQuote($product);

        $dealsToRecord = array_map(function($amount) use($product) {
            return $this->createDeal($product, "\$$amount off 1");
        }, range(1, 3));

        $res = Mage::getResourceModel('nypwidget/deal_usage');

        // Do all this > 1 time to ensure we don't crash due to dupe records
        for ($i = 1; $i <= 2; $i++) {
            // 1. Record that we use a deal on a quote/order
            try
            {
                $res->recordDealUsageForQuote($quote, $dealsToRecord);
            }
            catch (Exception $ex)
            {
                $this->fail("recordDealUsageForQuote iteration {$i} failed: " . $ex->getMessage());
            }

            // 2. Verify usage was recorded
            foreach($dealsToRecord as $deal) {
                $this->assertEquals(
                    array($quote->getId()),
                    $res->getQuoteIdsUsingDeal($deal),
                    "getQuoteIdsUsingDeal iteration {$i} looks right"
                );
            }

            // 3. Verify we can fetch by quote id also
            $dealsTiedToQuote = $res->getDealsUsedByQuote($quote);
            $this->assertCount(count($dealsToRecord), $dealsTiedToQuote, 'Correct # of deals used by quote');

            $expectedDealIds = array_map(function($d) { return $d->getId(); }, $dealsToRecord);
            sort($expectedDealIds);

            $dealIdsTiedToQuote = array_map(function($d) { return $d->getId(); }, $dealsTiedToQuote);
            sort($dealIdsTiedToQuote);

            $this->assertEquals(
                $expectedDealIds,
                $dealIdsTiedToQuote,
                'Correct deals returned by getDealsUsedByQuote()'
            );
        }
    }

    public function testFindOrderIdsForDealIds()
    {
        $res = Mage::getResourceModel('nypwidget/deal_usage');

        $product = $this->getSimpleProduct();

        // Create 2 orders, one with deals and one without
        $orderWithDeal = $this->createOrder($product);
        $orderWithoutDeal = $this->createOrder($product);

        // Create 3 deals, 2 tied to order and 1 not
        $deals = array(
            $this->createDeal($product, '$1 off 1')
                ->setOrderId($orderWithDeal->getId())
                ->save(),
            $this->createDeal($product, '$2 off 1')
                ->setOrderId($orderWithDeal->getId())
                ->save(),
            $this->createDeal($product, '$3 off 1'),
        );

        $dealIds = array_map(function(PriceWaiter_NYPWidget_Model_Deal $deal) {
            return $deal->getId();
        }, $deals);

        $this->assertEquals(
            array($orderWithDeal->getId()),
            $res->getOrderIdsForDealIds($dealIds),
            'Returns single order when all 3 deal ids passed in'
        );

        array_shift($dealIds);
        $this->assertEquals(
            array($orderWithDeal->getId()),
            $res->getOrderIdsForDealIds($dealIds),
            'Returns single order when 2 deal ids passed in'
        );

        array_shift($dealIds);
        $this->assertEquals(
            array(),
            $res->getOrderIdsForDealIds($dealIds),
            'Returns empty array when deal w/o order passed in'
        );
    }

    public function testFindOrderIdsForDealIdsEmpty()
    {
        $found = Mage::getResourceModel('nypwidget/deal_usage')
            ->getOrderIdsForDealIds(array());

        $this->assertEquals(array(), $found);
    }

    public function provideOrderStateTests()
    {
        return array(
            array(Mage_Sales_Model_Order::STATE_NEW, true),
            array(Mage_Sales_Model_Order::STATE_PENDING_PAYMENT, true),
            array(Mage_Sales_Model_Order::STATE_PROCESSING, true),
            array(Mage_Sales_Model_Order::STATE_COMPLETE, true),
            array(Mage_Sales_Model_Order::STATE_CLOSED, true),
            array(Mage_Sales_Model_Order::STATE_CANCELED, false),
            array(Mage_Sales_Model_Order::STATE_HOLDED, true),
            array(Mage_Sales_Model_Order::STATE_PAYMENT_REVIEW, true),
        );
    }

    /**
     * @dataProvider provideOrderStateTests
     */
    public function testFindOrderIdsForDealIdsStateSupport($state, $shouldBeFound)
    {
        $res = Mage::getResourceModel('nypwidget/deal_usage');

        $product = $this->getSimpleProduct();
        $order = $this->createOrder($state, $product);
        $deal = $this->createDeal($product, '$1 off 1');
        $deal->setOrderId($order->getId())->save();

        $found = $res->getOrderIdsForDealIds(array($deal->getId()));

        if ($shouldBeFound) {
            $this->assertEquals(
                array($order->getId()),
                $found,
                'should find the order'
            );
        } else {
            $this->assertEmpty(array(), $found, 'should not find the order');
        }
    }

    public function testFindDealIdsForOrderIds()
    {
        $res = Mage::getResourceModel('nypwidget/deal_usage');

        $product = $this->getSimpleProduct();

        // Create 3 orders-- 1 with 2 deals, 1 with 1 deal, and 1 with 0 deals
        $orderWith1Deal = $this->createOrder($product);
        $orderWith2Deals = $this->createOrder($product);
        $orderWithoutDeal = $this->createOrder($product);

        // Create deals
        $deals = array(
            $this->createDeal($product, '$1 off 1')
                ->setOrderId($orderWith1Deal->getId())
                ->save(),
            $this->createDeal($product, '$2 off 1')
                ->setOrderId($orderWith2Deals->getId())
                ->save(),
            $this->createDeal($product, '$3 off 1')
                ->setOrderId($orderWith2Deals->getId())
                ->save(),
            $this->createDeal($product, '$4 off 1'),
        );


        $orderIds = array();
        $expected = array();

        $this->assertEquals($expected, $res->getDealUsageForOrderIds(array()), 'returns empty array for empty array of order ids');

        $orderIds[] = $orderWithoutDeal->getId();
        $this->assertEquals($expected, $res->getDealUsageForOrderIds($orderIds), 'returns empty array for order w/o deals');

        $orderIds[] = $orderWith2Deals->getId();
        $expected[$orderWith2Deals->getId()] = array($deals[1]->getId(), $deals[2]->getId());
        $this->assertEquals($expected, $res->getDealUsageForOrderIds($orderIds));

        $orderIds[] = $orderWith1Deal->getId();
        $expected[$orderWith1Deal->getId()] = array($deals[0]->getId());
        $this->assertEquals($expected, $res->getDealUsageForOrderIds($orderIds));
    }

    public function testGetOrdersAndDealUsageForDealIds()
    {
        $res = Mage::getResourceModel('nypwidget/deal_usage');

        $product = $this->getSimpleProduct();

        // Create 3 orders-- 1 with 2 deals, 1 with 1 deal, and 1 with 0 deals
        $orderWith1Deal = $this->createOrder($product);
        $orderWith2Deals = $this->createOrder($product);
        $orderWithoutDeal = $this->createOrder($product);

        // Create deals
        $deals = array(
            $this->createDeal($product, '$1 off 1')
                ->setOrderId($orderWith1Deal->getId())
                ->save(),
            $this->createDeal($product, '$2 off 1')
                ->setOrderId($orderWith2Deals->getId())
                ->save(),
            $this->createDeal($product, '$3 off 1')
                ->setOrderId($orderWith2Deals->getId())
                ->save(),
            $this->createDeal($product, '$4 off 1'),
        );

        $dealIds = array_map(function (PriceWaiter_NYPWidget_Model_Deal $deal) {
            return $deal->getId();
        }, $deals);

        $usage = $res->getOrdersAndDealUsageForDealIds($dealIds);

        $this->assertCount(2, $usage);

        usort($usage, function($x, $y) {
            return $x['order']->getId() - $y['order']->getId();
        });

        $this->assertEquals($orderWith1Deal->getId(), $usage[0]['order']->getId());
        $this->assertEquals(array($deals[0]->getId()), $usage[0]['dealIds']);

        $this->assertEquals($orderWith2Deals->getId(), $usage[1]['order']->getId());
        $this->assertEquals(array(
            $deals[1]->getId(),
            $deals[2]->getId()
        ), $usage[1]['dealIds']);
    }
}
