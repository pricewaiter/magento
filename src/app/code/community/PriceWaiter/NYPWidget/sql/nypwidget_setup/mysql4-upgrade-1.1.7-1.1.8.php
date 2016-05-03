<?php

// Add table to keep track of order IDs associated with `pricewaiter_id`s
// Will prevent duplicate orders from the order callback API
$installer = $this;
$installer->startSetup();
$installer->run("
DROP TABLE IF EXISTS {$this->getTable('nypwidget_orders')};
CREATE TABLE {$this->getTable('nypwidget_orders')} (
	PRIMARY KEY (`entity_id`),
	`entity_id` int(11) unsigned NOT NULL auto_increment,
	`store_id` int(11) unsigned NOT NULL,
	`pricewaiter_id` varchar(100)  NOT NULL,
	`order_id` int(11) unsigned NOT NULL
);
");
