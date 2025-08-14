<?php

namespace App\Libraries\Monitoring\Strategies\DataStore;

interface DataStoreStrategy
{
    public function do(): void;
}
