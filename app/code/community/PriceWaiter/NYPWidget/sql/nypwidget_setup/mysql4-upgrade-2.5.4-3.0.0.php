<?php

$installer = $this;
$installer->startSetup();

$connection = $installer->getConnection();

$dealsTable = $connection
    ->newTable($installer->getTable('nypwidget/deal'))
    ->addColumn(
        'deal_id',
        Varien_Db_Ddl_Table::TYPE_VARCHAR,
        36,
        array(
            'nullable' => false,
            'primary' => true,
        ),
        'Unique Deal ID'
    )
    ->addColumn(
        'revoked',
        Varien_Db_Ddl_Table::TYPE_BOOLEAN,
        null,
        array(
            'default' => 0,
            'nullable' => false,
        ),
        'Whether this deal has been revoked'
    )
    ->addColumn(
        'store_id',
        Varien_Db_Ddl_Table::TYPE_INTEGER,
        10,
        array(
            'nullable' => false,
            'unsigned' => true,
        ),
        'Store this deal was for'
    )
    ->addColumn(
        'create_request_id',
        Varien_Db_Ddl_Table::TYPE_VARCHAR,
        36,
        array(
            'nullable' => false,
        ),
        'X-PriceWaiter-Request-Id header from create call'
    )
    ->addColumn(
        'created_at',
        Varien_Db_Ddl_Table::TYPE_DATETIME,
        null,
        array(
            'nullable' => false,
        ),
        'Create date (UTC)'
    )
    ->addColumn(
        'revoke_request_id',
        Varien_Db_Ddl_Table::TYPE_VARCHAR,
        36,
        array(
            'nullable' => true,
        ),
        'X-PriceWaiter-Request-Id header from revoke call'
    )
    ->addColumn(
        'revoked_at',
        Varien_Db_Ddl_Table::TYPE_DATETIME,
        null,
        array(
            'nullable' => true,
        ),
        'Revoke date (UTC)'
    )
    ->addColumn(
        'create_request_body_json',
        Varien_Db_Ddl_Table::TYPE_TEXT,
        null,
        array(
            'nullable' => false,
        ),
        'Full JSON for the create deal request.'
    )
    ->addColumn(
        'expires_at',
        Varien_Db_Ddl_Table::TYPE_DATETIME,
        null,
        array(
            'nullable' => true,
        ),
        'Expiry date (UTC)'
    )
    ->addColumn(
        'pricewaiter_buyer_id',
        Varien_Db_Ddl_Table::TYPE_VARCHAR,
        36,
        array(
            'nullable' => false,
        ),
        'PriceWaiter buyer id'
    )
    ->addColumn(
        'order_id',
        Varien_Db_Ddl_Table::TYPE_INTEGER,
        10,
        array(
            'nullable' => true,
            'unsigned' => true,
        ),
        'Magento order id deal was used on (if any)'
    )
    // Add an index for quick lookup when applying deals
    ->addIndex(
        $installer->getIdxName(array(
            'pricewaiter_buyer_id',
            'revoked',
            'order_id',
            'expires_at',
        )),
        array(
            'pricewaiter_buyer_id',
            'revoked',
            'order_id',
            'expires_at',
        ),
        array(
            'type' => Varien_Db_Adapter_Interface::INDEX_TYPE_INDEX,
        )
    )
    // Index on just order_id for quicker order listing
    ->addIndex(
        $installer->getIdxName(array('order_id')),
        array('order_id'),
        array(
            'type' => Varien_Db_Adapter_Interface::INDEX_TYPE_INDEX,
        )
    )
    ->setComment('PriceWaiter Deals');

$dealUsageTable = $connection
    ->newTable($installer->getTable('nypwidget/deal_usage'))
    ->setComment('Tracks PriceWaiter Deal usage on quotes')
    ->addColumn(
        'deal_id',
        Varien_Db_Ddl_Table::TYPE_VARCHAR,
        36,
        array(
            'nullable' => false,
        ),
        'PriceWaiter deal uuid'
    )
    ->addColumn(
        'quote_id',
        Varien_Db_Ddl_Table::TYPE_INTEGER,
        10,
        array(
            'nullable' => false,
            'unsigned' => true,
        ),
        'Quote deal was used on'
    )
    ->addForeignKey(
        $installer->getFkName(
            'nypwidget/deal_usage',
            'quote_id',
            'sales/quote',
            'entity_id'
        ),
        'quote_id',
        $installer->getTable('sales/quote'),
        'entity_id',
        // Cascade deletes when quote table is cleaned up
        Varien_Db_Ddl_Table::ACTION_CASCADE
    )
    ->addIndex(
        $installer->getIdxName(array(
            'deal_id',
            'quote_id',
        )),
        array(
            'deal_id',
            'quote_id',
        ),
        array(
            'type' => Varien_Db_Adapter_Interface::INDEX_TYPE_PRIMARY,
        )
    );

$connection->createTable($dealsTable);
$connection->createTable($dealUsageTable);

$installer->endSetup();
