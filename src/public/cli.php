<?php
// $ php cli.php
// $ REDIS_URI=localhost:6379 php cli.php environment channel

(PHP_SAPI !== 'cli' || isset($_SERVER['HTTP_USER_AGENT'])) && die('cli only');

require __DIR__ . '/../vendor/autoload.php';

use App\Libraries\Monitoring\Monitoring;

try {
    if ($argc >= 3) {
        $monitoring = new Monitoring($argv[1], $argv[2]);
    } elseif ($argc == 2) {
        $monitoring = new Monitoring($argv[1]);
    } else {
        $monitoring = new Monitoring();
    }
} catch (Exception $e) {
    echo 'Error: ' . $e->getMessage() . PHP_EOL;
    exit(1);
}

$monitoring->subscribe();
$monitoring->display();
$monitoring->unsubscribe();
