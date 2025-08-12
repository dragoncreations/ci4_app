<?php

namespace App\Models;

interface CoasterModelInterface
{
    public function createCoaster(array $data): string;

    public function addWagon(array $data, int $coasterId): string;

    public function updateCoaster(array $data, int $coasterId): void;

    public function deleteWagon(int $wagonId, int $coasterId): void;

    public function coasterExists(int $coasterId): bool;

    public function wagonExists(int $wagonId, int $coasterId): bool;
}
