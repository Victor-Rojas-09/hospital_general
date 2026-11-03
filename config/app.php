<?php

$localConfig = require __DIR__ . '/local.php';

// Hospital General is intended for a Colombian local-time deployment.
date_default_timezone_set('America/Bogota');

define('APP_NAME', 'Hospital General');
define('DB_HOST', $localConfig['db_host']);
define('DB_NAME', $localConfig['db_name']);
define('DB_USER', $localConfig['db_user']);
define('DB_PASS', $localConfig['db_pass']);
define('APP_ENCRYPTION_KEY_HEX', $localConfig['encryption_key_hex']);
