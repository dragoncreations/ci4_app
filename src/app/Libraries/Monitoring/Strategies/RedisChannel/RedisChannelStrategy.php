<?php

namespace App\Libraries\Monitoring\Strategies\RedisChannel;

interface RedisChannelStrategy
{
    public function displayStatistics(): void;
}
