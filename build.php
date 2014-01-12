<?php
/*
 * This script attempts to build the package based on the package.xml
 * in the current directory. It assumes that it is two directories below the
 * Magento root.
 *
 */
require_once("../../app/Mage.php");
umask(0);
Mage::app();

$xml = file_get_contents('package.xml');

$package = new Mage_Connect_Package($xml);
$package->save(getcwd());
