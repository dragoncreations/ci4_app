<?php

namespace App\Libraries\Monitoring;

use App\Libraries\Monitoring\Strategies\DataStore\DataStoreStrategy;

/**
 * Context class
 */
class Monitoring
{
    private $dataStoreStrategy;

    public function setStrategy(DataStoreStrategy $strategy): void
    {
        $this->dataStoreStrategy = $strategy;
    }

    public function run(): void
    {
        $this->dataStoreStrategy->do();
    }
}
