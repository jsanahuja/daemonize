<?php

use Sowe\Daemon\Service;

require_once(dirname(__DIR__) . "/vendor/autoload.php");

$service = new Service();

$service->on('start', function() use ($service) {
    echo "Daemon Starting..." . PHP_EOL;
    while(true){
        echo "Doing a daemon task" . PHP_EOL;
        sleep(5);
    }
});
$service->on('stop', function(){
    echo "Daemon Stopping..." . PHP_EOL;
});

$service->run();