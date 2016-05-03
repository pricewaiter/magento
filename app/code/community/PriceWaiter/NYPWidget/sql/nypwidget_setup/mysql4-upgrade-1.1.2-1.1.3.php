<?php

// Product types supported by the Name Your Price Widget
$supportTypeIds = array('simple', 'configurable', 'grouped', 'bundle');
$installer = $this;
$installer->startSetup();

// Remove the old attribute
$installer->removeAttribute('catalog_product', 'nypwidget_enabled');

// Add a new attribute to all prodcuts to toggle the Widget on/off
// 2016-02-29 MJE disabled in future upgrade
// $installer->addAttribute('catalog_product', 'nypwidget_disabled',
//     array(
//         'group' => 'General',
//         'label' => 'Disable PriceWaiter Widget?',
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
