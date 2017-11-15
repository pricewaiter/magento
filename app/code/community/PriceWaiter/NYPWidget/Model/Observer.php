<?php

class PriceWaiter_NYPWidget_Model_Observer
{
    // Adds the "PriceWaiter" tab to the "Manage Categories" page
    public function addTab(Varien_Event_Observer $observer)
    {
        $tabs = $observer->getEvent()->getTabs();
        $tabs->addTab('pricewaiter', array(
            'label' => Mage::helper('catalog')->__('PriceWaiter'),
            'content' => $tabs->getLayout()->createBlock(
                    'nypwidget/category')->toHtml(),
        ));
        return true;
    }

    // Saves "PriceWaiter" options from Category page
    public function saveCategory(Varien_Event_Observer $observer)
    {
        $category = $observer->getEvent()->getCategory();
        $postData = $observer->getEvent()->getRequest()->getPost();
        $enabled = $postData['pricewaiter']['enabled'];
        $ctEnabled = $postData['pricewaiter']['ct_enabled'];

        // Save the current setting, by category, and store
        $nypcategory = Mage::getModel('nypwidget/category')->loadByCategory($category, $category->getStore()->getId());
        $nypcategory->setData('nypwidget_enabled', $enabled);
        $nypcategory->setData('nypwidget_ct_enabled', $ctEnabled);
        $nypcategory->save();

        return true;
    }

    /**
     * Called when the PriceWaiter configuration page is saved.
     */
    public function handleConfigurationSave()
    {
        Mage::helper('nypwidget')->clearCache();
    }

    /**
     * Called when the customer logs out.
     * @param  Varien_Event_Observer $observer
     */
    public function handleCustomerLogout(Varien_Event_Observer $observer)
    {
        // Reset the PriceWaiter Buyer ID in session
        $session = Mage::getSingleton('nypwidget/session');
        $session->reset();
    }

    /**
     * Called when a quote is converted into an order.
     * Used to tie the order to any PriceWaiter Deals used to establish the pricing.
     *
     * @param  Varien_Event_Observer $observer
     */
    public function tieOrderToPriceWaiterDeals(Varien_Event_Observer $observer)
    {
        $event = $observer->getEvent();
        $quote = $event->getQuote();
        $order = $event->getOrder();

        try
        {
            $res = Mage::getResourceModel('nypwidget/deal_usage');
            $deals = $res->getDealsUsedByQuote($quote);

            if (empty($deals)) {
                return;
            }

            $transaction = Mage::getModel('core/resource_transaction');

            foreach($deals as $deal) {
                $deal->setOrderId($order->getId());
                $transaction->addObject($deal);
            }

            $transaction->save();
        }
        catch (Exception $ex)
        {
            // Never let our code prevent an order from being processed.
            Mage::logException($ex);
        }
    }
}
