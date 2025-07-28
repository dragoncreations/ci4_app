<?php

namespace App\Controllers;

use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\API\ResponseTrait;
use CodeIgniter\Controller;

class Coaster extends Controller
{
    use ResponseTrait;

    /**
     * Actions
     */
    public const string ACTION_ADD_COASTER = 'add_coaster';
    public const string ACTION_ADD_WAGON = 'add_wagon';
    public const string ACTION_UPDATE_COASTER = 'update_coaster';
    public const string ACTION_DELETE_WAGON = 'delete_wagon';

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
            'liczba_personelu' => 'required|integer|greater_than[0]',
            'liczba_klientow' => 'required|integer|greater_than[0]',
            'dl_trasy' => 'required|integer|greater_than[0]',
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

            $this->predis->hmset("coaster:" . $coasterId, [
                "id" => $coasterId,
                "staff" => $data["liczba_personelu"],
                "customers" => $data["liczba_klientow"],
                "distance" => $data["dl_trasy"],
                "from" => $data["godziny_od"],
                "to" => $data["godziny_do"],
            ]);

            $this->predis->incr("coasterId");

            $this->predis->publish("coasters_" . ENVIRONMENT, json_encode([
                "action" => self::ACTION_ADD_COASTER,
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
            if (!$this->predis->exists('coaster:' . $coasterId)) {
                return $this->failNotFound("Kolejka ID: $coasterId nie istnieje");
            }

            $data = $this->request->getJSON(true);

            $rule = [
                'ilosc_miejsc' => 'required|integer|greater_than[0]',
                'predkosc_wagonu' => 'required|numeric|greater_than[0]',
            ];

            if (!$this->validateData($data, $rule)) {
                return $this->failValidationErrors($this->validator->getErrors());
            }

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
                "action" => self::ACTION_ADD_WAGON,
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
            if (!$this->predis->exists('coaster:' . $coasterId)) {
                return $this->failNotFound("Kolejka ID: $coasterId nie istnieje");
            }

            $data = $this->request->getJSON(true);

            $rule = [
                'liczba_personelu' => 'required|integer|greater_than[0]',
                'liczba_klientow' => 'required|integer|greater_than[0]',
                'godziny_od' => 'required|string|regex_match[/^([0-1]?[0-9]|2[0-3]):[0-5][0-9]$/]',
                'godziny_do' => 'required|string|regex_match[/^([0-1]?[0-9]|2[0-3]):[0-5][0-9]$/]',
            ];

            if (!$this->validateData($data, $rule)) {
                return $this->failValidationErrors($this->validator->getErrors());
            }

            $this->predis->hmset("coaster:" . $coasterId, [
                "staff" => $data["liczba_personelu"],
                "customers" => $data["liczba_klientow"],
                "from" => $data["godziny_od"],
                "to" => $data["godziny_do"],
            ]);

            $this->predis->publish("coasters_" . ENVIRONMENT, json_encode([
                "action" => self::ACTION_UPDATE_COASTER,
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
            if (!$this->predis->exists('coaster:' . $coasterId)) {
                return $this->failNotFound("Kolejka ID: $coasterId nie istnieje");
            }

            if (!$this->predis->hget('coaster:' . $coasterId, 'wagon_' . $wagonId)) {
                return $this->failNotFound("Wagon ID: $wagonId nie istnieje lub nie znajduje się w kolejce ID: $coasterId");
            }

            $this->predis->hdel("coaster:" . $coasterId, "wagon_" . $wagonId);

            $this->predis->publish("coasters_" . ENVIRONMENT, json_encode([
                "action" => self::ACTION_DELETE_WAGON,
                "wagonId" => $wagonId,
            ]));

            return $this->respondDeleted(["wagonId" => $wagonId]);
        } catch (\Exception $e) {
            return $this->fail($e->getMessage(), 400);
        }
    }
}
