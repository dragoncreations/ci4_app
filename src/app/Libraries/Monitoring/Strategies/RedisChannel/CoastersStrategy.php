<?php

namespace App\Libraries\Monitoring\Strategies\RedisChannel;

use Exception;
use App\Libraries\Monitoring\Coasters\Statistics;
use App\Libraries\Monitoring\Strategies\DataStore\RedisStrategy;
use App\Libraries\Monitoring\Strategies\RedisChannel\RedisChannelStrategy;

class CoastersStrategy implements RedisChannelStrategy
{
    /**
     * Coaster actions constants
     */
    public const string ACTION_ADD_COASTER = "add_coaster";

    public const string ACTION_ADD_WAGON = "add_wagon";

    public const string ACTION_UPDATE_COASTER = "update_coaster";

    public const string ACTION_DELETE_WAGON = "delete_wagon";

    private string $message;

    private $redis;

    private array $coastersData = [];

    public static array $actions = [
        self::ACTION_ADD_COASTER,
        self::ACTION_ADD_WAGON,
        self::ACTION_UPDATE_COASTER,
        self::ACTION_DELETE_WAGON,
    ];

    public function __construct(string $message = "", string $environment = "")
    {
        $this->message = $message;
        $this->redis = \Config\Services::redis();
        $this->redis->select(RedisStrategy::ENV_PRODUCTION === $environment ? 0 : 1);
    }

    public function displayStatistics(): void
    {
        if (in_array(json_decode($this->message, true)['action'], self::$actions)) {
            $this->redis->keys('coaster:*')->then(function (?array $coasters) {
                if (!empty($coasters)) {
                    echo "[Godzina " . date('H:i:s') . "]" . PHP_EOL;

                    foreach ($coasters as $coaster) {
                        $this->redis->hgetall($coaster)->then(function (?array $data) {
                            if (!empty($data)) {
                                $coasterData = new Statistics($data);
                                $coasterData->display();
                                if (!empty($coasterData->getProblems())) {
                                    $this->log($coasterData->getName() . " - Problem: " . implode(", ", $coasterData->getProblems()));
                                }
                            }
                        }, function (Exception $e) {
                            echo 'Error: ' . $e->getMessage() . PHP_EOL;
                        });
                    }
                } else {
                    echo 'No coasters' . PHP_EOL;
                }
            }, function (Exception $e) {
                echo 'Error: ' . $e->getMessage() . PHP_EOL;
            });
        } else {
            echo 'No valid action found in message.' . PHP_EOL;
        }
    }

    private function log(string $message): void
    {
        // Logika do zapisywania problemów lub informacji do pliku
        $file = '../writable/logs/coaster_statistics.log';

        $fp = fopen($file, 'a');

        fwrite($fp, "[" . date('Y-m-d H:i:s') . "] " . $message . PHP_EOL);

        fclose($fp);
    }
}
