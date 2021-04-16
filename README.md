# daemon
PHP Daemon wrapper

## Requirements
- PHP ^7.2
- POSIX (ext-posix) ^7.2
- PCNTL (ext-pcntl) ^7.2

## Install
```
composer require sowe/daemon
```
## Example
```
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
```

You can also define your own methods (note they will not run directly on daemon process):
```
[...]
$service->on('mymethod', function(){
    echo "My custom method" . PHP_EOL;
});
```

## Usage & Default methods

```
php examples/simple.php <method = help>
```

Start daemon
> php examples/simple.php start

Retrieve daemon status
> php examples/simple.php status

Stop daemon
> php examples/simple.php stop

Display daemon usage
> php examples/simple.php help
