<?php

$cfg['Servers'][1]['auth_type'] = 'config';
$cfg['Servers'][1]['host'] = 'mysql';
$cfg['Servers'][1]['connect_type'] = 'tcp';
$cfg['Servers'][1]['compress'] = false;
$cfg['Servers'][1]['user'] = 'root';
$cfg['Servers'][1]['password'] = 'password';

# Magento has a lot of tables in it, and paging through them sucks.
$cfg['MaxTableList'] = 1000;
