<?php

/**
 * Tests around quote -> order conversions.
 */
class Integration_QuoteToOrderTest extends Integration_AbstractProductTest
{
    public $modelsToDelete = array();

    public function testDealsUpdatedWithOrderIdWhenUsed()
    {
        $deals = $this->getDeals(2);

        $quote = Mage::getModel('sales/quote')
            ->setStoreId(1) // This doesn't really matter, but must be set
            ->save();
        $this->assertGreaterThan(0, $quote->getId(), 'Quote saved');
        $this->modelsToDelete[] = $quote;


        // Mark our fake deals as used by our fake quote
        Mage::getResourceModel('nypwidget/deal_usage')
            ->recordDealUsageForQuote($quote, $deals);

        // Actually saving order models to db is complicated and we don't
        // actually need it here.
        $order = Mage::getModel('sales/order')
            ->setId(42);

        $event = new Varien_Event(compact('quote', 'order'));
        $ob = new Varien_Event_Observer();
        $ob->setEvent($event);

        $observer = Mage::getModel('nypwidget/observer');
        $observer->tieOrderToPriceWaiterDeals($ob);

        // This should've linked the deals back to the order
        foreach($deals as $deal) {
            $id = $deal->getId();
            $deal = Mage::getModel('nypwidget/deal')
                ->load($deal->getId());

            $this->assertEquals($order->getId(), $deal->getOrderId(), "deal $id is linked back to order");
        }
    }

    public function tearDown()
    {
        parent::tearDown();

        foreach($this->modelsToDelete as $model) {
            $model->delete();
        }

        $this->modelsToDelete = array();
    }

    protected function getDeals($count, $idBase = null)
    {
        if ($idBase === null) {
            $idBase = uniqid();
        }

        $result = array();

        for($i = 1; $i <= $count; $i++) {
            $deal = Mage::getModel('nypwidget/deal')
                ->setId("deal-$idBase-$i")
                ->save();

            $this->modelsToDelete[] = $deal;

            $result[] = $deal;
        }

        return $result;
    }
}
