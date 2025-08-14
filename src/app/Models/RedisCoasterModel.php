<?php

namespace App\Models;

use App\Libraries\Monitoring\Strategies\DataStore\RedisStrategy;
use App\Libraries\Monitoring\Strategies\RedisChannel\CoastersStrategy;
use App\Libraries\Monitoring\Strategies\RedisChannel\RedisChannelStrategy;
use CodeIgniter\Model;

class RedisCoasterModel implements CoasterModelInterface
{
    protected $predis;

    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->predis = service('predis');
    }

    public function createCoaster($row): string
    {
        if (!$this->predis->exists("coasterId")) {
            $this->predis->set('coasterId', 1);
        }

        $coasterId = $this->predis->get("coasterId");

        $this->predis->hmset("coaster:" . $coasterId, [
            "id" => $coasterId,
            "staff" => $row["liczba_personelu"],
            "customers" => $row["liczba_klientow"],
            "distance" => $row["dl_trasy"],
            "from" => $row["godziny_od"],
            "to" => $row["godziny_do"],
        ]);

        $this->predis->incr("coasterId");

        $this->predis->publish("coasters_" . ENVIRONMENT, json_encode([
            "action" => CoastersStrategy::ACTION_ADD_COASTER,
            "coasterId" => $coasterId,
        ]));

        return $coasterId;
    }

    public function addWagon(array $data, int $coasterId): string
    {
        if (!$this->predis->exists("wagonId")) {
            $this->predis->set('wagonId', 1);
        }

        $wagonId = $this->predis->get("wagonId");

        $this->predis->hset("coaster:" . $coasterId, "wagon_" . $wagonId,  json_encode(
            [
                "id" => $wagonId,
                "capacity" => $data["ilosc_miejsc"],
                "speed" => $data["predkosc_wagonu"],
            ]
        ));

        $this->predis->incr("wagonId");

        $this->predis->publish("coasters_" . ENVIRONMENT, json_encode([
            "action" => CoastersStrategy::ACTION_ADD_WAGON,
            "wagonId" => $wagonId,
        ]));

        return $wagonId;
    }

    public function updateCoaster(array $data, int $coasterId): void
    {
        $this->predis->hmset("coaster:" . $coasterId, [
            "staff" => $data["liczba_personelu"],
            "customers" => $data["liczba_klientow"],
            "from" => $data["godziny_od"],
            "to" => $data["godziny_do"],
        ]);

        $this->predis->publish("coasters_" . ENVIRONMENT, json_encode([
            "action" => CoastersStrategy::ACTION_UPDATE_COASTER,
            "coasterId" => $coasterId,
        ]));
    }

    public function deleteWagon(int $wagonId, int $coasterId): void
    {
        $this->predis->hdel("coaster:" . $coasterId, "wagon_" . $wagonId);

        $this->predis->publish("coasters_" . ENVIRONMENT, json_encode([
            "action" => CoastersStrategy::ACTION_DELETE_WAGON,
            "wagonId" => $wagonId,
        ]));
    }

    public function coasterExists(int $coasterId): bool
    {
        return $this->predis->exists('coaster:' . $coasterId);
    }

    public function wagonExists(int $wagonId, int $coasterId): bool
    {
        return $this->predis->hexists('coaster:' . $coasterId, 'wagon_' . $wagonId);
    }
}
