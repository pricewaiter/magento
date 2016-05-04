<?php

$this->startSetup();

// Create a new "Processing - PriceWaiter" status for orders
// that have been pulled from PriceWaiter back into Magento.
// NOTE: This replaces "Pending - PriceWaiter"
$this->run("
    INSERT INTO  `{$this->getTable('sales/order_status')}` (
        `status`, `label`
    ) VALUES (
        'pricewaiter_processing', 'Processing - PriceWaiter'
    );
    INSERT INTO  `{$this->getTable('sales/order_status_state')}` (
        `status`, `state`, `is_default`
    ) VALUES (
        'pricewaiter_processing', 'processing', '0'
    );
");
