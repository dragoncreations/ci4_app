<?php

namespace App\Libraries\Monitoring;

use App\Libraries\Monitoring\Strategies\RedisChannel\RedisChannelStrategy;

/**
 * Context class 
 */
class RedisChannel
{
    private $channelStrategy;

    public function setStrategy(RedisChannelStrategy $strategy): void
    {
        $this->channelStrategy = $strategy;
    }

    public function run(): void
    {
        $this->channelStrategy->displayStatistics();
    }
}
