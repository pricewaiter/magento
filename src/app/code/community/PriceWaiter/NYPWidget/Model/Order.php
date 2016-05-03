<?php

class PriceWaiter_NYPWidget_Model_Order extends Mage_Core_Model_Abstract
{
    public function _construct()
    {
        $this->_init('nypwidget/order', 'entity_id');
    }

    /**
     * @param  String $orderId
     * @return PriceWaiter_NYPWidget_Model_Order $this
     */
    public function loadByMagentoOrderId($orderId)
    {
        $collection = Mage::getModel('nypwidget/order')
            ->getCollection()
            ->addFieldToFilter('order_id', $orderId);

        if (count($collection)) {
            $this->load($collection->getFirstItem()->getEntityId());
        }

        return $this;
    }

    /**
     * @param  String $pricewaiterId
     * @return PriceWaiter_NYPWidget_Model_Order $this
     */
    public function loadByPriceWaiterId($pricewaiterId)
    {
        if (is_null($pricewaiterId)) {
            return false;
        }

        $collection = Mage::getModel('nypwidget/order')
            ->getCollection()
            ->addFieldToFilter('pricewaiter_id', $pricewaiterId);

        if (count($collection)) {
            $this->load($collection->getFirstItem()->getEntityId());
        }

        return $this;
    }
}
