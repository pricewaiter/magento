<?php

/**
 * Class responsible for calculating PriceWaiter discounts.
 */
class PriceWaiter_NYPWidget_Model_Discounter
{
    protected $offerAmountPerItem = null;
    protected $offerMinQty = 1;
    protected $offerMaxQty = 1;

    /**
     * @var Mage_Directory_Model_Currency
     */
    protected $offerCurrency = null;

    /**
     * @var double
     */
    protected $productPrice = 0;

    /**
     * @var Mage_Directory_Model_Currency
     */
    protected $quoteBaseCurrency = null;

    /**
     * @var Mage_Directory_Model_Currency
     */
    protected $quoteCurrency = null;

    /**
     * @var integer
     */
    protected $quoteItemQty = 1;


    /**
     * @param Mage_Directory_Model_Currency $currency
     */
    public function setQuoteBaseCurrency(Mage_Directory_Model_Currency $currency)
    {
        $this->quoteBaseCurrency = $currency;
        return $this;
    }

    /**
     * @param Mage_Directory_Model_Currency $currency
     */
    public function setQuoteCurrency(Mage_Directory_Model_Currency $currency)
    {
        $this->quoteCurrency = $currency;
        return $this;
    }

    /**
     * @param double|string $amount
     */
    public function setOfferAmountPerItem($amount)
    {
        $this->offerAmountPerItem = $amount;
        return $this;
    }

    /**
     * @param Mage_Directory_Model_Currency $currency
     */
    public function setOfferCurrency(Mage_Directory_Model_Currency $currency)
    {
        $this->offerCurrency = $currency;
        return $this;
    }

    /**
     * @param integer $qty
     */
    public function setOfferMinQty($qty)
    {
        $this->offerMinQty = $qty;
        return $this;
    }

    /**
     * @param integer $qty
     */
    public function setOfferMaxQty($qty)
    {
        $this->offerMaxQty = $qty;
        return $this;
    }

    /**
     * Product's price, expressed in the quote's currency.
     * @param double|string $price
     */
    public function setProductPrice($price)
    {
        $this->productPrice = $price;
        return $this;
    }

    /**
     * Product's "original price", expressed in the quote's currency.
     * @param double|string $price
     */
    public function setProductOriginalPrice($price)
    {
        $this->productOriginalPrice = $price;
        return $this;
    }

    /**
     * @param Integer $qty Quantity being ordered.
     */
    public function setQuoteItemQty($qty)
    {
        $this->quoteItemQty = $qty;
        return $this;
    }

    /**
     * @return double The discount amount to apply to the quote item (as a positive number), expressed in the quote's currency.
     */
    public function getDiscount()
    {
        return $this->calculateDiscount(
            $this->productPrice,
            $this->quoteCurrency
        );
    }

    /**
     * @return double The discount amount to apply to the quote item, expressed in the quote's base currency.
     */
    public function getBaseDiscount()
    {
        return $this->calculateDiscount(
            $this->productPrice,
            $this->quoteBaseCurrency
        );
    }

    /**
     * @return double The discount amount over the original price (in quote currency)
     */
    public function getOriginalDiscount()
    {
        $price = $this->productOriginalPrice ?
            $this->productOriginalPrice :
            $this->productPrice;

        return $this->calculateDiscount($price, $this->quoteCurrency);
    }

    /**
     * @return double The discount amount over the product's base price (in quote base currency)
     */
    public function getBaseOriginalDiscount()
    {
        $price = $this->productOriginalPrice ?
            $this->productOriginalPrice :
            $this->productPrice;

        return $this->calculateDiscount($price, $this->quoteBaseCurrency);
    }

    /**
     * Calculates a discount in the given currency.
     * @param  double|string $price Product price
     */
    protected function calculateDiscount($priceInQuoteCurrency, Mage_Directory_Model_Currency $currency)
    {
        $effectiveQty = min($this->quoteItemQty, $this->offerMaxQty);

        if ($effectiveQty < $this->offerMinQty) {
            // Not enough of item on quote to qualify.
            return 0;
        }

        // Standardize offer into quote currency
        $amountPerItemInQuoteCurrency = $this->offerCurrency->convert(
            $this->offerAmountPerItem,
            $this->quoteCurrency
        );

        if ($amountPerItemInQuoteCurrency >= $priceInQuoteCurrency) {
            // Offer is for more than product price. No discount applied.
            return 0;
        }

        $discountInQuoteCurrency = ($priceInQuoteCurrency - $amountPerItemInQuoteCurrency) * $effectiveQty;
        return $this->quoteCurrency->convert($discountInQuoteCurrency, $currency);
    }

}
