<?php

// Defaults for possibly-missing environment variables
$envDefaults = array(
    'PROTOCOL' => 'https',
    'VIRTUALHOST_NAME' => 'pricewaiter-magento.ngrok.io',
    'PORT' => '80067',
);

foreach ($envDefaults as $key => $value) {
    $exists = getenv($key);
    if ($exists === false) {
        putenv("{$key}={$value}");
    }
}

require_once '../../app/Mage.php';
Mage::app();
