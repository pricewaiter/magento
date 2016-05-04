<?php

$installer = $this;
$installer->startSetup();

// Remove old toggle attributes on products
$installer->removeAttribute('catalog_product', 'nypwidget_disabled');
$installer->removeAttribute('catalog_product', 'nypwidget_ct_disabled');
