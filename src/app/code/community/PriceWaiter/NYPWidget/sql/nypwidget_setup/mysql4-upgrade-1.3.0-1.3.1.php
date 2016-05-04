<?php

// Product types supported by the Name Your Price Widget
$supportTypeIds = array('simple', 'configurable', 'grouped', 'bundle');

$installer = $this;
$installer->startSetup();

// Add a new attribute to all products to toggle the Conversion Tools on/off
// 2016-02-29 MJE disabled in future upgrade
// $installer->addAttribute('catalog_product', 'nypwidget_ct_disabled',
//     array(
//         'group' => 'General',
//         'label' => 'Disable PriceWaiter Conversion Tools? (such as Exit Intent)',
//         'type' => 'int',
//         'input' => 'boolean',
//         'default' => '0',
//         'class' => '',
//         'backend' => '',
//         'frontend' => '',
//         'source' => '',
//         'global' => Mage_Catalog_Model_Resource_Eav_Attribute::SCOPE_STORE,
//         'visible' => true,
//         'required' => true,
//         'user_defined' => false,
//         'searchable' => true,
//         'filterable' => true,
//         'comparable' => true,
//         'visible_on_front' => true,
//         'visible_in_advanced_search' => false,
//         'unique' => false,
//         'apply_to' => $supportTypeIds,
//     )
// );

// Add an attribute to our nypwidget_category table to hold conversion tools information
$installer->run("
ALTER TABLE {$this->getTable('nypwidget_category')} ADD COLUMN `nypwidget_ct_enabled` tinyint(1) NOT NULL default '1' AFTER nypwidget_enabled;
");
