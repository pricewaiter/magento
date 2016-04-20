<?php
/*
 * Copyright 2013-2015 Price Waiter, LLC
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *    http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 *
 */

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
