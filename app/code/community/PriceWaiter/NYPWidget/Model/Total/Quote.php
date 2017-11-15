<?php

/**
 * Total model used to apply PriceWaiter discounts at the quote level.
 */
class PriceWaiter_NYPWidget_Model_Total_Quote
    extends Mage_Sales_Model_Quote_Address_Total_Abstract
{
    private $_deals = null;
    private $_session = null;

    /**
     * @internal
     */
    private static $_dealsForTesting = null;

    /**
     * @internal Supports fetch() hack.
     * @var array
     */
    private static $_fetchingAddresses = array();

    /**
     * Calculates PriceWaiter discounts and applies them to the quote and
     * relevant quote items.
     *
     * @param   Mage_Sales_Model_Quote_Address $address
     * @return  PriceWaiter_NYPWidget_Model_Total_Quote $this
     */
    public function collect(Mage_Sales_Model_Quote_Address $address)
    {
        parent::collect($address);

        $deals = $this->getPriceWaiterDeals();

        // shouldCollectAddress adds session notices about discounts
        // if we are not eligible due to coupon codes or card discounts
        // avoid showing those notices if there are no deals
        if (!count($deals)) {
            return $this;
        }

        if (!$this->shouldCollectAddress($address)) {
            return $this;
        }

        // In case of collision, favor more recent deals over less recent.
        usort($deals, array(__CLASS__, 'sortDealsRecentFirst'));

        $appliedDeals = array();
        $appliedDealIds = array();

        $discountedQuoteItems = array();
        $pwDiscount = 0;

        /** @var PriceWaiter_NYPWidget_Model_Deal $deal */
        foreach ($deals as $deal) {
            $applied = $this->collectDeal($deal, $address, $discountedQuoteItems);

            if (!$applied) {
                continue;
            }

            $pwDiscount += $applied;

            $appliedDeals[] = $deal;
            $appliedDealIds[] = $deal->getId();
        }

        // Note that we have used one or more deals for this quote.
        // This connection will get picked up later when quote is
        // converted into an order.
        // TODO: Link Deal -> Order on order creation without requiring an
        //       intermediate link btw. Deal -> Quote.

        $res = Mage::getResourceModel('nypwidget/deal_usage');
        $res->recordDealUsageForQuote(
            $address->getQuote(),
            $appliedDeals
        );

        // Track deal usage and discount amounts. fetch(), below, will pick them up.
        // Note that these fields are *not* perisisted directly to the DB.
        $address->setPriceWaiterDiscount($pwDiscount);
        $address->setPriceWaiterDealIds($appliedDealIds);

        return $this;
    }

    /**
     * Adds the "PriceWaiter Savings" totals row for the address.
     *
     * @param   Mage_Sales_Model_Quote_Address $address
     * @return  PriceWaiter_NYPWidget_Model_Total_Quote $this
     */
    public function fetch(Mage_Sales_Model_Quote_Address $address)
    {
        $discount = $address->getPriceWaiterDiscount();
        $deals = $address->getPriceWaiterDealIds();

        if (!$deals || !$discount) {
            return $this;
        }

        if ($this->fetchOverridingDiscount($address)) {
            // We sneakily overrode the existing discount total
            return $this;
        }

        // If Discount not loaded, then we can't replace it so we just add ours.
        $address->addTotal($this->buildPriceWaiterTotal(-$discount));

        return $this;
    }

    protected function buildPriceWaiterTotal($discount)
    {
        $helper = Mage::helper('nypwidget');

        return array(
            'code' => 'pricewaiter',
            'title' => $helper->__('PriceWaiter Savings'),
            'value' => $discount,
        );
    }

    protected function fetchOverridingDiscount(Mage_Sales_Model_Quote_Address $address)
    {
        $addrId = $address->getId() ? $address->getId() : spl_object_hash($address);
        $alreadyFetching = !empty(self::$_fetchingAddresses[$addrId]);

        if ($alreadyFetching) {
            // Avoid stack overflow.
            return true;
        }

        // Note that we are fetching for this address so that when we call
        // $addr->getTotals() below we don't end up recursing infinitely.
        self::$_fetchingAddresses[$addrId] = true;

        try
        {
            $discountTotal = null;
            foreach ($address->getTotals() as $total) {
                if ($total->getCode() === 'discount') {
                    $discountTotal = $total;
                    break;
                }
            }

            unset(self::$_fetchingAddresses[$addrId]);

            if ($discountTotal) {
                $this->hackilyOverwriteDiscountTotal($discountTotal);
                return true;
            }

            return false; // "We did not overwrite discount"
        }
        catch (Exception $ex)
        {
            unset(self::$_fetchingAddresses[$addrId]);
            throw $ex;
        }
    }

    protected function hackilyOverwriteDiscountTotal(Mage_Sales_Model_Quote_Address_Total $total)
    {
        $pwTotal = $this->buildPriceWaiterTotal($total->getValue());
        $total->setCode($pwTotal['code']);
        $total->setTitle($pwTotal['title']);
    }

    /**
     * @return array Deal models to be applied for discounting.
     */
    public function getPriceWaiterDeals()
    {
        // HACK: Allow injecting deals for tests, since we don't control
        //       instantiation of this class during Quote::collectTotals().
        if (self::$_dealsForTesting !== null) {
            return self::$_dealsForTesting;
        }

        if ($this->_deals === null) {
            $this->initDealsFromSession();
        }

        return $this->_deals;
    }

    /**
     * @internal Setter for deals array.
     * @param Array $deals Deals
     * @return  PriceWaiter_NYPWidget_Model_Total_Quote $this
     */
    public function setPriceWaiterDeals(Array $deals)
    {
        $this->_deals = $deals;
        return $this;
    }

    /**
     * @return PriceWaiter_NYPWidget_Model_Session
     */
    public function getSession()
    {
        return $this->_session ?
            $this->_session :
            Mage::getSingleton('nypwidget/session');
    }

    /**
     * Sets the session instance to use (instead of the default one).
     * @param PriceWaiter_NYPWidget_Model_Session $session
     */
    public function setSession(PriceWaiter_NYPWidget_Model_Session $session)
    {
        $this->_session = $session;
        return $this;
    }

    /**
     * @internal Sets the deals to be returnd by getPriceWaiterDeals().
     * @param array $deals
     */
    public static function hackilySetDealsForTesting($deals)
    {
        self::$_dealsForTesting = $deals;
    }

    /**
     * @internal
     * @param  PriceWaiter_NYPWidget_Model_Deal $a
     * @param  PriceWaiter_NYPWidget_Model_Deal $b
     * @return Integer
     */
    public static function sortDealsRecentFirst(
        PriceWaiter_NYPWidget_Model_Deal $a,
        PriceWaiter_NYPWidget_Model_Deal $b
    )
    {
        $aCreated = @strtotime($a->getCreatedAt());
        $bCreated = @strtotime($b->getCreatedAt());

        // If a is more recent than b, sort it before
        return $bCreated - $aCreated;
    }

    /**
     * Attempts to apply a Deal to the given Quote_Address.
     * @param  PriceWaiter_NYPWidget_Model_Deal $deal
     * @param  Mage_Sales_Model_Quote_Address   $address
     * @param  array $discountedQuoteItems Tracks quote items that receive discounts.
     * @return array|false
     */
    protected function collectDeal(
        PriceWaiter_NYPWidget_Model_Deal $deal,
        Mage_Sales_Model_Quote_Address $address,
        array &$discountedQuoteItems
    )
    {
        $offerItems = $deal->getOfferItems();

        $quote = $address->getQuote();
        $quoteItems = $address->getAllItems();

        $quoteItemsForOfferItems = array();

        try
        {
            // Check that we can apply *all* offer items.
            foreach($offerItems as $offerItem) {
                $quoteItem = $offerItem->findQuoteItem($quoteItems);

                if (!$quoteItem) {
                    // No candidate quote item found -- cannot apply this deal.
                    return false;
                }

                if (in_array($quoteItem, $discountedQuoteItems)) {
                    // This quote item has already had a PriceWaiter deal applied.
                    // PW Deals *should not* stack on top of each other.
                    return false;
                }

                $quoteItemsForOfferItems[] = $quoteItem;
            }
        }
        catch (Exception $ex)
        {
            // Prevent malformed / invalid deals from killing the cart.
            Mage::logException($ex);
            return false;
        }

        // TODO: Existing discount conflict resolution

        // If we've gotten this far, it means all offer items are good to apply.

        $pwDiscount = 0;

        foreach($offerItems as $index => $offerItem) {

            $quoteItem = $quoteItemsForOfferItems[$index];

            // Things we need to do here:
            //   1. Get the *price* of the product in question.
            //   2. Calculate how much of a discount to apply
            //   3. Apply the discount to the appropriate quote item
            //   4. Apply the discount to the address.

            $discounter = $this->createDiscountCalculator(
                $quote,
                $quoteItem,
                $offerItem
            );

            list($discount, $baseDiscount, $originalDiscount, $baseOriginalDiscount) = array(
                $discounter->getDiscount(),
                $discounter->getBaseDiscount(),
                $discounter->getOriginalDiscount(),
                $discounter->getBaseOriginalDiscount(),
            );

            if ($discount <= 0) {
                continue;
            }

            // Note that we're altering this quote item so we don't
            // try to alter it again.
            $discountedQuoteItems[] = $quoteItem;

            $quoteItem->setDiscountAmount($quoteItem->getDiscountAmount() + $discount);
            $quoteItem->setBaseDiscountAmount($quoteItem->getBaseDiscountAmount() + $baseDiscount);

            // These fields are not persisted, but may be used in tax calculation
            $quoteItem->setOriginalDiscountAmount($quoteItem->getOriginalDiscount + $originalDiscount);
            $quoteItem->setBaseOriginalDiscountAmount($quoteItem->getBaseOriginalDiscountAmount() + $baseOriginalDiscount);

            $this->_addAmount(-$discount);
            $this->_addBaseAmount(-$baseDiscount);

            // NOTE: Setting Discount Amount on the *address* here will make the
            //       totals row for the built-in discounter show up.
            //       We should probably be storing our discount in different places.

            $address->setDiscountAmount(
                $address->getDiscountAmount() - $discount
            );

            $address->setBaseDiscountAmount(
                $address->getBaseDiscountAmount() - $baseDiscount
            );


            $pwDiscount += $discount;
        }

        return $pwDiscount;
    }

    /**
     * Configures a discounter to actually calculate how big a discount to apply.
     */
    protected function createDiscountCalculator(
        Mage_Sales_Model_Quote $quote,
        Mage_Sales_Model_Quote_Item $quoteItem,
        PriceWaiter_NYPWidget_Model_Offer_Item $offerItem
    )
    {

        return Mage::getModel('nypwidget/discounter')
            ->setProductPrice($this->getItemPrice($quoteItem))
            ->setProductOriginalPrice($this->getItemOriginalPrice($quoteItem))

            ->setQuoteBaseCurrency($this->getCurrencyByCode($quote->getBaseCurrency()))
            ->setQuoteCurrency($this->getCurrencyByCode($quote->getCurrency()))
            ->setQuoteItemQty($quoteItem->getQty())

            ->setOfferCurrency($this->getCurrencyByCode($offerItem->getCurrencyCode()))
            ->setOfferAmountPerItem($offerItem->getAmountPerItem())
            ->setOfferMinQty($offerItem->getMinimumQuantity())
            ->setOfferMaxQty($offerItem->getMaximumQuantity())
            ;
    }

    protected function getCurrencyByCode($code)
    {
        return Mage::getModel('directory/currency')->load($code);
    }

   /**
    * @internal Cribbed from SalesRule/Model/Validator.php
    */
    protected function getItemPrice(Mage_Sales_Model_Quote_Item_Abstract $item)
    {
        $price = $item->getDiscountCalculationPrice();
        $calcPrice = $item->getCalculationPrice();
        return ($price !== null) ? $price : $calcPrice;
    }

   /**
    * @internal Cribbed from SalesRule/Model/Validator.php
    */
    protected function getItemOriginalPrice(Mage_Sales_Model_Quote_Item_Abstract $item)
    {
        return Mage::helper('tax')->getPrice($item, $item->getOriginalPrice(), true);
    }

    /**
     * Examines the current user's session and loads their current PriceWaiter deals.
     * @return void
     */
    protected function initDealsFromSession()
    {
        $session = $this->getSession();
        $this->setPriceWaiterDeals($session->getActiveDeals());
    }

    /**
     * Hook to prevent collect() and fetch() on a given address.
     * @param  Mage_Sales_Model_Quote_Address $addr
     * @return Boolean
     */
    protected function shouldCollectAddress(Mage_Sales_Model_Quote_Address $addr)
    {
        // HACK: We only support collecting for items on the shipping address for now.
        //       To properly support all address types we need a minor db migration +
        //       reworking of recordDealUsageForQuote() to accept address id as well as
        //       quote id.
        if ($addr->getAddressType() !== Mage_Sales_Model_Quote_Address::TYPE_SHIPPING) {
            return false;
        }

        // Things that stop us from performing collection:
        // 1. Quote has coupon code applied
        // 2. Quote has salesrules applied which affect more than just shipping

        $quote = $addr->getQuote();

        // (1) Don't allow when there is a coupon code
        $couponCode = $quote->getCouponCode();
        $hasCouponCode = !empty($couponCode);
        if ($hasCouponCode) {
            Mage::getSingleton('core/session')->addNotice(
                'Your offer discount could not be applied because there is a coupon code in use.'
            );
            return false;
        }

        // (2) Don't allow if the sales rule applies to _more than shipping only_
        $ruleIds = $quote->getAppliedRuleIds();
        if (!empty($ruleIds)) {
            $helper = Mage::helper('nypwidget/rule');
            $ruleCollection = Mage::getModel('salesrule/rule')
                ->getCollection()
                ->addFieldToFilter('rule_id', array('in' => $ruleIds));

            foreach ($ruleCollection as $rule) {
                if (!$helper->ruleAppliesToShippingOnly($rule)) {
                    Mage::getSingleton('core/session')->addNotice(
                        'Your offer discount could not be applied because of an existing promotion.'
                    );
                    return false;
                }
            }
        }

        return true;
    }

}
