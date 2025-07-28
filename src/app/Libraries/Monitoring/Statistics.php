<?php

namespace App\Libraries\Monitoring;

use App\Libraries\Monitoring\Strategies\ChannelStrategy;

/**
 * The Context defines the interface of interest to clients.
 */
class Statistics
{
    private $channelStrategy;

    public function setStrategy(ChannelStrategy $strategy): void
    {
        $this->channelStrategy = $strategy;
    }

    public function display(): void
    {
        $this->channelStrategy->displayStatistics();
    }
}
