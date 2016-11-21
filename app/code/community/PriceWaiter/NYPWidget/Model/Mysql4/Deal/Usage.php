<?php

class PriceWaiter_NYPWidget_Model_Mysql4_Deal_Usage extends Mage_Core_Model_Mysql4_Abstract
{
    public function _construct()
    {
        $this->_init('nypwidget/deal_usage', '');
    }

    /**
     * @param  Mage_Sales_Model_Quote $quote
     * @return Array An Array of Deal models.
     */
    public function getDealsUsedByQuote(
        Mage_Sales_Model_Quote $quote
    )
    {
        $adapter = $this->_getReadAdapter();
        $select = $adapter->select();

        $select
            ->from($this->getMainTable(), array('deal_id'))
            ->where(
                'quote_id = ?',
                $quote->getId()
            );

        $ids = $adapter->fetchCol($select);

        $collection = Mage::getModel('nypwidget/deal')
            ->getCollection()
            ->addFieldToFilter(
                'deal_id',
                array('in' => $ids)
            );

        return $collection->getItems();
    }

    /**
     * @param  PriceWaiter_NYPWidget_Model_Deal|string $deal
     * @return Array IDs of quotes that have used $deal.
     */
    public function getQuoteIdsUsingDeal(
        $deal
    )
    {
        $adapter = $this->_getReadAdapter();
        $select = $adapter->select();

        $select
            ->from($this->getMainTable(), array('quote_id'))
            ->where(
                'deal_id = ?',
                is_object($deal) ? $deal->getId() : $deal
            );

        $result = $adapter->fetchCol($select);
        return $result;
    }

    /**
     * Looks up the (unique) ids of orders that use any of the given deals.
     * @param  array  $dealIds
     * @return array  Array of order ids
     */
    public function getOrderIdsForDealIds(array $dealIds)
    {
        $dealIds = array_unique($dealIds);

        if (empty($dealIds)) {
            return array();
        }

        $adapter = $this->_getReadAdapter();
        $select = $adapter->select();

        $select
            ->distinct()
            ->from(
                array(
                    'deal' => $this->getTable('nypwidget/deal'),
                ),
                array('order_id')
            )
            ->join(
                array('order' => $this->getTable('sales/order')),
                'order.entity_id = deal.order_id',
                array()
            )
            // We're *really* not concerned with orders in certain states.
            ->where('order.state NOT IN (?)', array(
                Mage_Sales_Model_Order::STATE_CANCELED,
            ))
            ->where('deal.deal_id IN (?)', $dealIds);

        return $adapter->fetchCol($select);
    }

    /**
     * Given an array of order entity_ids, returns an array
     * array(
     *   'order_id_1' => array('deal_id_1', 'deal_id_2')
     * )
     * Describing the deals applied to the orders.
     * Any orders without deals will be excluded from the results.
     * @param  array  $orderIds
     * @return array
     */
    public function getDealUsageForOrderIds(array $orderIds)
    {
        $orderIds = array_unique($orderIds);

        $adapter = $this->_getReadAdapter();
        $select = $adapter->select();

        $select
            ->from($this->getTable('nypwidget/deal'), array('deal_id', 'order_id'))
            ->where('order_id in (?)', $orderIds);

        $rows = $adapter->fetchAll($select);
        $result = array();

        foreach($rows as $row) {
            $orderId = $row['order_id'];
            $dealId = $row['deal_id'];

            if (!isset($result[$orderId])) {
                $result[$orderId] = array();
            }
            $result[$orderId][] = $dealId;
        }

        return $result;
    }

    /**
     * @param  array  $dealIds
     * @return array An array of arrays, each with 'order' and 'dealIds' keys.
     */
    public function getOrdersAndDealUsageForDealIds(array $dealIds)
    {
        // This method does all the work needed to turn an abitrary set of deal
        // ids into order + deal usage information in a minimal number of DB queries:

        // 1. Translate $dealIds into an array of order ids
        $orderIds = $this->getOrderIdsForDealIds($dealIds);

        // 2. Look up all deal usage for those order ids (including those not in $dealIds)
        $dealUsage = $this->getDealUsageForOrderIds($orderIds);

        // 3. Query for order models by id
        $orders = Mage::getModel('sales/order')
            ->getCollection()
            ->addFieldToFilter('entity_id', array('in' => $orderIds))
            ->getItems();

        // Finally, stitch together order models with arrays of deals
        $result = array();

        /** @var Mage_Sales_Model_Order $order */
        foreach($orders as $order) {
            $result[] = array(
                'order' => $order,
                'dealIds' => $dealUsage[$order->getId()],
            );
        }

        return $result;
    }

    /**
     * Records a set of zero or more Deals used on a quote.
     * Previous links between Deal <-> Quote are overwritten.
     *
     * @param  Mage_Sales_Model_Quote $quote
     * @param  array $deals Array of Deal models or Deal ids.
     */
    public function recordDealUsageForQuote(
        Mage_Sales_Model_Quote $quote,
        array $deals
    )
    {
        $quoteId = $quote->getId();

        // In normal circumstances, $quote will have an ID.
        // There are *some* times when it is technically possible for it
        // not to (like if someone is fiddling with the cart in code).
        if (!$quoteId) {
            // We can't record usage without a quote id.
            return;
        }

        $rowsToInsert = array();
        foreach ($deals as $deal) {
            $rowsToInsert[] = array(
                'deal_id' => is_object($deal) ? $deal->getId() : $deal,
                'quote_id' => $quoteId,
            );
        }

        $adapter = $this->_getWriteAdapter();
        $adapter->beginTransaction();
        try
        {
            $adapter->delete(
                $this->getMainTable(),
                array(
                    'quote_id = ?' => $quote->getId(),
                )
            );

            if (!empty($rowsToInsert)) {
                $adapter->insertArray(
                    $this->getMainTable(),
                    array('deal_id', 'quote_id'),
                    $rowsToInsert
                );
            }
        } catch (Exception $ex)
        {
            $adapter->rollBack();
            throw $ex;
        }

        $adapter->commit();
    }
}
