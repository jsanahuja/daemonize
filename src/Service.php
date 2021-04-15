<?php

namespace Sowe\Daemon;

use Psr\Log\LoggerInterface;
use Monolog\Logger;
use Monolog\ErrorHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Formatter\LineFormatter;

class Service
{
    protected $pid;
    protected $pidFile;
    protected $logger;
    protected $events;

    public function __construct(LoggerInterface $logger = null){
        if (php_sapi_name() !== 'cli') {
            die("Invalid SAPI. Only CLI is allowed." . PHP_EOL);
        }
        if (is_null($logger)) {
            $this->logger = $this->getDefaultLogger();
        } else {
            $this->logger = $logger;
        }
        
        $this->events = [
            'start' =>  ['description' => "Start daemon"],
            'status' => ['description' => "Display daemon status"],
            'stop' =>   ['description' => "Stop daemon gracefully"],
            'help' =>   ['description' => "Display this information message"],
        ];
        
        $this->pidFile = $_SERVER['PWD'] . '/'. basename($_SERVER['PHP_SELF'], 'php') . 'pid';

        if (file_exists($this->pidFile)) {
            $this->pid = file_get_contents($this->pidFile);
            if (!file_exists('/proc/' . $this->pid)) {
                $this->logger->info("Removing pidFile as process is was not running");
                unlink($this->pidFile);
                $this->pid = null;
            }
        }else{
            $this->pid = null;
        }
    }

    public function on(string $event, callable $closure, string $description = null)
    {
        if (!isset($this->events[$event])) {
            $this->events[$event] = [];
        }
        if (!is_null($description)) {
            $this->events[$event]['description'] = $description;
        }
        $this->events[$event]['closure'] = $closure;
    }

    protected function getDefaultLogger(): LoggerInterface
    {
        $logger = new Logger("sowe/daemon");
        $formatter = new LineFormatter(
            "[%datetime%]:%level_name%: %message% %context%\n",
            "Y-m-d\TH:i:s",
            true, /* allow break lines */
            true /* ignore empty contexts */
        );
        $stream = new StreamHandler(dirname(__DIR__) . "/service.log", Logger::DEBUG);
        $stream->setFormatter($formatter);
        $logger->pushHandler($stream);
        $handler = new ErrorHandler($logger);
        $handler->registerErrorHandler([], false);
        $handler->registerExceptionHandler();
        $handler->registerFatalHandler();
        return $logger;
    }

    protected function runChild()
    {
        $sid = posix_setsid();
        if ($sid < 0) {
            echo "Error: Unable to make child process the session leader";
            exit;
        }
        $this->pid = getmypid();
        file_put_contents($this->pidFile, $this->pid);
        
        declare(ticks = 1);
        set_time_limit(0);

        $outputHandler = function($buffer) {
            $buffer = explode(PHP_EOL, $buffer);
            foreach ($buffer as $b) {
                if (!empty($b)) {
                    $this->logger->debug($b);
                }
            }
        };
        ob_implicit_flush(true); // doesn't seem to work
        ob_start($outputHandler, 1024);
        
        $signalHandler = function($signal) {
            if($signal === SIGTERM){
                $this->trigger('stop');
                ob_end_flush();
                $this->logger->info("Stopping daemon");
                unlink($this->pidFile);
                exit;
            }
        };
        pcntl_async_signals(true);
        pcntl_signal(SIGTERM, $signalHandler, false);
        
        $this->logger->info("Starting daemon");ob_flush();
        $this->trigger('start');
    }

    protected function start()
    {
        if (is_null($this->pid)) {
            $pid = pcntl_fork();
            if ($pid == 0) {
                // Child
                $this->runChild();
            } else if ($pid > 0) {
                // Parent
                exit;
            } else {
                echo "Error: Unable to start daemon" . PHP_EOL;
            }
        } else {
            echo "Error: Daemon is already running" . PHP_EOL;
        }
    }
    
    protected function status()
    {

    }
    protected function restart()
    {
        $this->stop();
        $this->start();
    }

    protected function stop()
    {
        if (!is_null($this->pid)) {
            echo "Stopping " . $this->pid . "..." . PHP_EOL; 
            posix_kill($this->pid, SIGTERM);
        } else {
            echo "Error: Daemon is not running" . PHP_EOL;
        }
    }

    protected function help()
    {
        global $argv;
        $msg = "Usage: php " . $argv[0] . " <command>" . PHP_EOL;
        $msg .= "Commands:" . PHP_EOL;

        foreach ($this->events as $key => $event) {
            $msg .= "\t" . $key . "\t\t" . $event['description'] . PHP_EOL;
        }
        $msg .= PHP_EOL;
        echo $msg;
    }

    protected function trigger($event){
        if (isset($this->events[$event]) && isset($this->events[$event]['closure'])) {
            $this->events[$event]['closure']();
        }
    }

    public function run(){
        global $argv;

        if(sizeof($argv) == 1){
            $argv[1] = "help";
        }

        switch ($argv[1]) {
            case "start":
                $this->start();
                break;
            case "status":
                $this->status();
                break;
            case "stop":
                $this->stop();
                break;
            case "help":
                $this->help();
                break;
            default:
                $this->trigger($argv[1]);
        }
    }
}
