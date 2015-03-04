<?php

// Defaults for possibly-missing environment variables
$envDefaults = array(
    'PROTOCOL' => 'https',
    'VIRTUALHOST_NAME' => 'pricewaiter-magento.ngrok.com'
);

foreach ($envDefaults as $key => $value) {
    $exists = getenv($key);
    if ($exists === false) {
        putenv("{$key}={$value}");
    }
}
