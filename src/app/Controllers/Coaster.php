<?php

namespace App\Controllers;

use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\API\ResponseTrait;
use CodeIgniter\Controller;

class Coaster extends Controller
{
    use ResponseTrait;

    private $predis;

    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->predis = service('predis');
    }

    public function create(): ResponseInterface
    {
        $data = $this->request->getJSON(true);

        $rule = [
            'liczba_personelu' => 'required|integer',
            'liczba_klientow' => 'required|integer',
            'dl_trasy' => 'required|integer',
            'godziny_od' => 'required|string|regex_match[/^([0-1]?[0-9]|2[0-3]):[0-5][0-9]$/]',
            'godziny_do' => 'required|string|regex_match[/^([0-1]?[0-9]|2[0-3]):[0-5][0-9]$/]',
        ];

        if (!$this->validateData($data, $rule)) {
            return $this->failValidationErrors($this->validator->getErrors());
        }

        try {
            if (!$this->predis->exists("coasterId")) {
                $this->predis->set('coasterId', 1);
            }

            $coasterId = $this->predis->get("coasterId");

            $this->predis->hmset("coaster_" . $coasterId, [
                "id" => $coasterId,
                "staff" => $data["liczba_personelu"],
                "customers" => $data["liczba_klientow"],
                "route_length" => $data["dl_trasy"],
                "from" => $data["godziny_od"],
                "to" => $data["godziny_do"],
            ]);

            $this->predis->incr("coasterId");

            $this->predis->publish("coasters_" . ENVIRONMENT, json_encode([
                "action" => "add_coaster",
                "coasterId" => $coasterId,
            ]));

            return $this->respondCreated(["coasterId" => $coasterId]);
        } catch (\Exception $e) {
            return $this->fail($e->getMessage(), 400);
        }
    }

    public function add(?int $coasterId = null): ResponseInterface
    {
        try {
            if (!$this->predis->exists('coaster_' . $coasterId)) {
                return $this->failNotFound("Kolejka ID: $coasterId nie istnieje");
            }

            $data = $this->request->getJSON(true);

            $rule = [
                'ilosc_miejsc' => 'required|integer',
                'predkosc_wagonu' => 'required|numeric',
            ];

            if (!$this->validateData($data, $rule)) {
                return $this->failValidationErrors($this->validator->getErrors());
            }

            if (!$this->predis->exists("wagonId")) {
                $this->predis->set('wagonId', 1);
            }

            $wagonId = $this->predis->get("wagonId");

            $this->predis->hmset("wagon_" . $wagonId,  [
                "id" => $wagonId,
                "coasterId" => $coasterId,
                "seats" => $data["ilosc_miejsc"],
                "speed" => $data["predkosc_wagonu"],
            ]);

            $this->predis->sadd("coaster_" . $coasterId . "_wagons", $wagonId);

            $this->predis->incr("wagonId");

            $this->predis->publish("coasters_" . ENVIRONMENT, json_encode([
                "action" => "add_wagon",
                "wagonId" => $wagonId,
            ]));

            return $this->respondCreated(["wagonId" => $wagonId]);
        } catch (\Exception $e) {
            return $this->fail($e->getMessage(), 400);
        }
    }

    public function update(?int $coasterId = null): ResponseInterface
    {
        try {
            if (!$this->predis->exists('coaster_' . $coasterId)) {
                return $this->failNotFound("Kolejka ID: $coasterId nie istnieje");
            }

            $data = $this->request->getJSON(true);

            $rule = [
                'liczba_personelu' => 'required|integer',
                'liczba_klientow' => 'required|integer',
                'godziny_od' => 'required|string|regex_match[/^([0-1]?[0-9]|2[0-3]):[0-5][0-9]$/]',
                'godziny_do' => 'required|string|regex_match[/^([0-1]?[0-9]|2[0-3]):[0-5][0-9]$/]',
            ];

            if (!$this->validateData($data, $rule)) {
                return $this->failValidationErrors($this->validator->getErrors());
            }

            $this->predis->hmset("coaster_" . $coasterId, [
                "staff" => $data["liczba_personelu"],
                "customers" => $data["liczba_klientow"],
                "from" => $data["godziny_od"],
                "to" => $data["godziny_do"],
            ]);

            $this->predis->publish("coasters_" . ENVIRONMENT, json_encode([
                "action" => "update_coaster",
                "coasterId" => $coasterId,
            ]));

            return $this->respondCreated(["coasterId" => $coasterId]);
        } catch (\Exception $e) {
            return $this->fail($e->getMessage(), 400);
        }
    }

    public function delete(?int $coasterId = null, ?int $wagonId = null): ResponseInterface
    {
        try {
            if (!$this->predis->exists('coaster_' . $coasterId)) {
                return $this->failNotFound("Kolejka ID: $coasterId nie istnieje");
            }

            if (!$this->predis->exists('wagon_' . $wagonId)) {
                return $this->failNotFound("Wagon ID: $wagonId nie istnieje");
            }

            if (0 === $this->predis->sismember("coaster_" . $coasterId . "_wagons", $wagonId)) {
                return $this->failNotFound("Wagon ID: $wagonId nie znajduje się w kolejce ID: $coasterId");
            }

            $this->predis->srem("coaster_" . $coasterId . "_wagons", $wagonId);

            $this->predis->del("wagon_" . $wagonId);

            $this->predis->publish("coasters_" . ENVIRONMENT, json_encode([
                "action" => "delete_wagon",
                "wagonId" => $wagonId,
            ]));

            return $this->respondDeleted(["wagonId" => $wagonId]);
        } catch (\Exception $e) {
            return $this->fail($e->getMessage(), 400);
        }
    }
}
