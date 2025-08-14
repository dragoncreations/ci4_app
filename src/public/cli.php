<?php
// $ php cli.php
// $ REDIS_URI=localhost:6379 php cli.php environment channel

(PHP_SAPI !== 'cli' || isset($_SERVER['HTTP_USER_AGENT'])) && die('cli only');

require __DIR__ . '/../vendor/autoload.php';

use App\Libraries\Monitoring\Monitoring;
use App\Libraries\Monitoring\Strategies\DataStore\RedisStrategy;

$monitoring = new Monitoring();

if ($argc >= 3) {
    $strategy = new RedisStrategy($argv[1], $argv[2]);
} elseif ($argc == 2) {
    $strategy = new RedisStrategy($argv[1]);
} else {
    $strategy = new RedisStrategy();
}

$monitoring->setStrategy($strategy);

try {
    $monitoring->run();
} catch (Exception $e) {
    echo 'Error: ' . $e->getMessage() . PHP_EOL;
    exit(1);
}
