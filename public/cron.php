<?php

use GAEDrive\Server;

define('ROOT_PATH', dirname(__DIR__));
require ROOT_PATH . '/vendor/autoload.php';

$server = new Server();
$server->cron();
