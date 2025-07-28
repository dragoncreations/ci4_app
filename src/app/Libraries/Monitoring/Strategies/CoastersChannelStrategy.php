<?php

namespace App\Libraries\Monitoring\Strategies;

use Exception;
use App\Libraries\Monitoring\Coasters\CoasterStatistics;

class CoastersChannelStrategy implements ChannelStrategy
{
    private string $message;

    private $redis;

    public function __construct(string $message = "")
    {
        $this->message = $message;
        $this->redis = \Config\Services::redis();
    }

    public function displayStatistics(): void
    {
        if (in_array(json_decode($this->message, true)['action'], ['add_coaster', 'add_wagon', 'update_coaster', 'delete_wagon'])) {
            $this->redis->keys('coaster:*')->then(function (?array $coasters) {
                $this->getCoastersData($coasters);
            }, function (Exception $e) {
                echo 'Error: ' . $e->getMessage() . PHP_EOL;
            });
        } else {
            echo 'No valid action found in message.' . PHP_EOL;
        }
    }

    private function getCoastersData(?array $coasters): void
    {
        if (!empty($coasters)) {
            foreach ($coasters as $coaster) {
                $this->redis->hgetall($coaster)->then(function (?array $coasterData) {
                    $this->displayCoasterStatistics($coasterData);
                }, function (Exception $e) {
                    echo 'Error: ' . $e->getMessage() . PHP_EOL;
                });
            }
        }
    }

    private function displayCoasterStatistics(?array $coasterData): void
    {
        if (!empty($coasterData)) {
            $coasterStatistics = new CoasterStatistics($coasterData);
            $coasterStatistics->display();
        }
    }
}
