<?php

/**
 * Class responsible for tracking the user's currently applied deals.
 */
class PriceWaiter_NYPWidget_Model_Session
    extends Mage_Core_Model_Session_Abstract
{
    private $_activeDeals = null;

    private $_now = null;

    public function __construct()
    {
        $this->init('pricewaiter');
    }

    /**
     * @return String The unique PriceWaiter ID of the current buyer.s
     */
    public function getBuyerId()
    {
        return $this->getData('buyer_id');
    }

    /**
     * Sets the current buyer id.
     * @param $id
     * @return PriceWaiter_NYPWidget_Model_Session $this
     */
    public function setBuyerId($id)
    {
        $this->setData('buyer_id', $id);
        $this->_activeDeals = null;
        return $this;
    }

    /**
     * @return Integer UNIX timestamp representing "now".
     */
    public function getNow()
    {
        if ($this->_now === null) {
            return time();
        }

        return $this->_now;
    }

    /**
     * @internal For tests.
     * @param Integer $now
     */
    public function setNow($now)
    {
        if (is_string($now)) {
            $now = strtotime($now);
        }

        $this->_now = $now;

        return $this;
    }

    /**
     * @return Array All Deals for the buyer that are unrevoked and unexpired.
     */
    public function getActiveDeals()
    {
        if ($this->_activeDeals !== null) {
            return $this->_activeDeals;
        }

        $buyerId = $this->getBuyerId();

        if (!$buyerId) {
            return array();
        }

        $collection = Mage::getModel('nypwidget/deal')
            ->getCollection()
            // Only get Deals for the current buyer...
            ->addFieldToFilter('pricewaiter_buyer_id', $buyerId)

            // ...that haven't been revoked...
            ->addFieldToFilter('revoked', 0)

            // ...and havent' already been used to check out...
            ->addFieldToFilter('order_id', array('null' => true))

            // ...and either don't have an expiry or have an expiry in the future
            ->addFieldToFilter(
                'expires_at',
                array(
                    array('gt' => date('Y-m-d H:i:s', $this->getNow())),
                    array('null' => true),
                )
            );

        $this->_activeDeals = $collection->getItems();

        return $this->_activeDeals;
    }

    /**
     * @return $this
     */
    public function reset()
    {
        return $this->setBuyerId(null);
    }
}
