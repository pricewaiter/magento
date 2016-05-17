<?php

$installer = $this;
$installer->startSetup();

// 1. Figure out if any orders used the old "Processing - PriceWaiter" setting
$processingOrders = Mage::getModel('sales/order')->getCollection()
    ->addFieldToFilter('status', 'pricewaiter_processing')
    ->addAttributeToSelect('created_at');

$isPriceWaiterProcessingUsed = $processingOrders->count() > 0;

// 2. If not, delete it.
if (!$isPriceWaiterProcessingUsed) {

    $status = Mage::getModel('sales/order_status')
        ->getCollection()
        ->addFieldToFilter('status', 'pricewaiter_processing')
        ->getFirstItem();

    if ($status) {
        $status->delete();
    }
}

// 3. Figure out what the default status should be for new orders
$defaultOrderStatus = null;

if ($isPriceWaiterProcessingUsed) {
    // Keep using "Processing - PriceWaiter" if we have been.
    $defaultOrderStatus = 'pricewaiter_processing';
} else {
    // Otherwise, just use the default status for the "processing" state
    $status = Mage::getModel('sales/order_status')
        ->loadDefaultByState(Mage_Sales_Model_Order::STATE_PROCESSING);
    $defaultOrderStatus = $status->getStatus();
}

// 4. Assign value for default order status setting for each store
if ($defaultOrderStatus) {
    $this->setConfigData('pricewaiter/orders/default_status', $defaultOrderStatus);
}
