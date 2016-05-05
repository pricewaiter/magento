<?php

# NOTE: We assume we're running from $ROOT/.modman/pricewaiter/tests,
#       So app/Mage.php is actually two directories up.
require_once '../../app/Mage.php';

Mage::setIsDeveloperMode(true);
Mage::app('default');

error_reporting(E_ALL);

// Workaround to get exceptions on warnings etc.
// Courtesy https://gist.github.com/Vinai/64abcb4290a33807269e
$magentoHandler = set_error_handler(function () {
    $usePhpErrorHandlingReturnValue = false;
    return $usePhpErrorHandlingReturnValue;
});

set_error_handler(function ($errno, $errstr, $errfile)  use ($magentoHandler) {

    if (substr($errfile, -19) === 'Varien/Autoload.php') {
        $ignoreErrorReturnValue = null;
        return $ignoreErrorReturnValue;
    }

    return is_callable($magentoHandler) ?
        call_user_func_array($magentoHandler, func_get_args()) :
        false;
});

session_start();
