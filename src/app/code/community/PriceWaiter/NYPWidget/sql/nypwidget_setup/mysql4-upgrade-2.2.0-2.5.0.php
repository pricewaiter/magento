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

// Remove "Processing - PriceWaiter" order status if not in use.
$processingOrders = Mage::getModel('sales/order')->getCollection()
    ->addFieldToFilter('status', 'pricewaiter_processing')
    ->addAttributeToSelect('created_at');

$isPriceWaiterProcessingUsed = $processingOrders->count() > 0;

if ($processingOrders->count() === 0) {

    $status = Mage::getModel('sales/order_status')
        ->getCollection()
        ->addFieldToFilter('status', 'pricewaiter_processing')
        ->getFirstItem();

    if ($status) {
        $status->delete();
    }
}

// Set default order status property (globally)
$defaultStatus = Mage::getConfig()->getNode('pricewaiter/orders/default_status');
if (!$defaultStatus) {

    // We need to assign one.

    // Keep using "Processing - PriceWaiter" if we have been.
    if ($isPriceWaiterProcessingUsed) {
        $defaultStatus = 'pricewaiter_processing';
    } else {
        // Otherwise, just use the default "processing" status
        $status = Mage::getModel('sales/order_status')
            ->loadDefaultByState(Mage_Sales_Model_Order::STATE_PROCESSING);
        $defaultStatus = $status->getStatus();
    }
}

if ($defaultStatus) {
    Mage::getConfig()->setNode('default/pricewaiter/orders/default_status', $defaultStatus);
}

