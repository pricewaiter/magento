<?php

class Unit_DiscounterTest extends PHPUnit_Framework_TestCase
{
    public $currencies = array();

    public $tests = array(
        'USD offer, USD product, qty = 1' => array(
            'offer' => array(
                'currency' => 'USD',
                'amountPerItem' => '12.50',
                'minQty' => 1,
                'maxQty' => 1,
            ),
            'quoteItem' => array(
                'qty' => 1,
                'price' => '20.00',
                'currency' => 'USD',
                'baseCurrency' => 'USD',
            ),
            'expected' => array(
                'discount' => '7.50',
                'baseDiscount' => '7.50',
                'originalDiscount' => '7.50',
                'baseOriginalDiscount' => '7.50',
            ),
        ),
        'USD offer, USD product, qty = 3, offerQty = 2' => array(
            'offer' => array(
                'currency' => 'USD',
                'amountPerItem' => '12.50',
                'minQty' => 2,
                'maxQty' => 2,
            ),
            'quoteItem' => array(
                'qty' => 3,
                'price' => '20.00',
                'currency' => 'USD',
                'baseCurrency' => 'USD',
            ),
            'expected' => array(
                'discount' => '15.00',
                'baseDiscount' => '15.00',
                'originalDiscount' => '15.00',
                'baseOriginalDiscount' => '15.00',
            ),
        ),
        'GBP offer, USD product, qty = 3, offerQty = 2' => array(
            'offer' => array(
                'currency' => 'GBP',
                'amountPerItem' => '6.25',
                'minQty' => 2,
                'maxQty' => 2,
            ),
            'quoteItem' => array(
                'qty' => 3,
                'price' => '20.00',
                'currency' => 'USD',
                'baseCurrency' => 'USD',
            ),
            'expected' => array(
                'discount' => '15.00',
                'baseDiscount' => '15.00',
                'originalDiscount' => '15.00',
                'baseOriginalDiscount' => '15.00',
            ),
        ),
        'GBP offer, USD product, GBP base, qty = 3, offerQty = 2' => array(
            'offer' => array(
                'currency' => 'GBP',
                'amountPerItem' => '6.25',
                'minQty' => 2,
                'maxQty' => 2,
            ),
            'quoteItem' => array(
                'qty' => 3,
                'price' => '20.00',
                'originalPrice' => '25.00',
                'currency' => 'USD',
                'baseCurrency' => 'GBP',
            ),
            'expected' => array(
                'discount' => '15.00',
                'baseDiscount' => '7.50',
                'originalDiscount' => '25.00',
                'baseOriginalDiscount' => '12.50'
            ),
        ),
    );

    /**
     * @dataProvider provideCalculationTests
     */
    public function testCalculation($name)
    {
        $params = $this->tests[$name];

        $calc = new PriceWaiter_NYPWidget_Model_Discounter();
        $calc
            ->setOfferCurrency($this->currencies[$params['offer']['currency']])
            ->setOfferAmountPerItem($params['offer']['amountPerItem'])
            ->setOfferMinQty($params['offer']['minQty'])
            ->setOfferMaxQty($params['offer']['maxQty'])

            ->setQuoteCurrency($this->currencies[$params['quoteItem']['currency']])
            ->setQuoteBaseCurrency($this->currencies[$params['quoteItem']['baseCurrency']])
            ->setQuoteItemQty($params['quoteItem']['qty'])
            ->setProductPrice($params['quoteItem']['price'])
            ->setProductOriginalPrice(
                isset($params['quoteItem']['originalPrice']) ?
                    $params['quoteItem']['originalPrice'] :
                    null
            )
            ;

        $this->assertEquals($params['expected']['discount'], $calc->getDiscount(), 'Discount is correct');
        $this->assertEquals($params['expected']['baseDiscount'], $calc->getBaseDiscount(), 'Base discount is correct');
        $this->assertEquals($params['expected']['originalDiscount'], $calc->getOriginalDiscount(), 'Original Price Discount is correct');
        $this->assertEquals($params['expected']['baseOriginalDiscount'], $calc->getBaseOriginalDiscount(), 'Base original price discount is correct');
    }

    public function provideCalculationTests()
    {
        $tests = array();
        foreach ($this->tests as $name => $params) {
            $tests[] = array($name);
        }
        return $tests;
    }

    public function setUp()
    {
        // NOTE: We are pretending 1 GBP = 2 USD to keep things simple.

        $usd = new Mage_Directory_Model_Currency();
        $usd->setCurrencyCode('USD');
        $usd->setRates(array(
            'GBP' => 0.5,
        ));

        $gbp = new Mage_Directory_Model_Currency();
        $gbp->setCurrencyCode('GBP');
        $gbp->setRates(array(
            'USD' => 2,
        ));

        $this->currencies = array(
            'USD' => $usd,
            'GBP' => $gbp,
        );
    }
}
