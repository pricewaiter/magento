<?php
/*
 * Copyright 2012 PriceWaiter, LLC
 * 
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *      http://www.apache.org/licenses/LICENSE-2.0
 *
 *  Unless required by applicable law or agreed to in writing, software
 *  distributed under the License is distributed on an "AS IS" BASIS,
 *  WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 *  See the License for the specific language governing permissions and
 *  limitations under the License.
 *
 */

// Product types supported by the Name Your Price Widget
$supportTypeIds = array('simple', 'configurable');
$installer = $this;
$installer->startSetup();

// Add an attribute to all prodcuts to toggle the Widget on/off
$installer->addAttribute('catalog_product', 'nypwidget_enabled',
    array(
        'group'             => 'General',
        'label'             => 'PriceWaiter Widget Enabled',
        'type'              => 'int',
        'input'             => 'boolean',
        'default'           => '1',
        'class'             => '',
        'backend'           => '',
        'frontend'          => '',
        'source'            => '',
        'global'            => Mage_Catalog_Model_Resource_Eav_Attribute::SCOPE_STORE,
        'visible'           => true,
        'required'          => true,
        'user_defined'      => false,
        'searchable'        => true,
        'filterable'        => true,
        'comparable'        => true,
        'visible_on_front'  => true,
        'visible_in_advanced_search' => false,
        'unique'            => false,
        'apply_to'          => $supportTypeIds,
    )
);

// Add an attribute to all categories to toggle the Widget on/off
$installer->addAttribute('catalog_category', 'nypwidget_enabled',
    array(
        'group'             => 'General',
        'label'             => 'PriceWaiter Widget Enabled',
        'type'              => 'int',
        'input'             => 'select',
        'default'           => '1',
        'class'             => '',
        'backend'           => '',
        'frontend'          => '',
        'source'            => 'eav/entity_attribute_source_boolean',
        'global'            => Mage_Catalog_Model_Resource_Eav_Attribute::SCOPE_STORE,
        'visible'           => true,
        'required'          => true,
        'user_defined'      => false,
        'searchable'        => true,
        'filterable'        => true,
        'comparable'        => true,
        'visible_on_front'  => true,
        'visible_in_advanced_search' => false,
        'unique'            => false,
    )
);

$entityTypeId = $installer->getEntityTypeId('catalog_category');
$attributeSetId = $installer->getDefaultAttributeSetId($entityTypeId);
$attributeGroupId = $installer->getDefaultAttributeGroupId($entityTypeId, $attributeSetId);

$installer->addAttributeToGroup(
    $entityTypeId,
    $attributeSetId,
    $attributeGroupId,
    'nypwidget_enabled',
    '11'
);

$attributeId = $installer->getAttributeId($entityTypeId, 'nypwidget_enabled');
$installer->run("
    INSERT INTO `{$installer->getTable('catalog_category_entity_int')}` (
        `entity_type_id`, `attribute_id`, `entity_id`, `value`
    )
    SELECT '{$entityTypeId}', '{$attributeId}', `entity_id`, '1'
        FROM `{$installer->getTable('catalog_category_entity')}`;
");

// Apply to root and default category
Mage::getModel('catalog/category')
    ->load(1)
    ->setImportedCatId(0)
    ->setInitialSetupFlag(true)
    ->save();
Mage::getModel('catalog/category')
    ->load(2)
    ->setImportedCatId(0)
    ->setInitialSetupFlag(true)
    ->save();
$installer->endSetup();

// Create a new "Pending - PriceWaiter" status for orders
// that have been pulled from PriceWaiter back into Magento
$installer->run("
    INSERT INTO  `{$this->getTable('sales/order_status')}` (
        `status`, `label`
    ) VALUES (
        'pricewaiter_pending', 'Pending - PriceWaiter'
    );
    INSERT INTO  `{$this->getTable('sales/order_status_state')}` (
        `status`, `state`, `is_default`
    ) VALUES (
        'pricewaiter_pending', 'pending', '0'
    );
");

// The default value above only applies to new products.
// Build a collection of products, and set 'nypwidget_enabled' to the default value
// This part can take a bit of time.
Mage::app()->setUpdateMode(false);
Mage::app()->setCurrentStore(0);

$products = Mage::getModel('catalog/product')->getCollection()
    ->addAttributeToFilter('type_id', array('in' => $supportTypeIds));

foreach ($products as $product) {
    Mage::getSingleton('catalog/product_action')
        ->updateAttributes(array($product->getId()), array('nypwidget_enabled' => 1), 0);
}

?>
