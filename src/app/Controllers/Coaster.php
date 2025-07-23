<?php

namespace App\Controllers;

use CodeIgniter\API\ResponseTrait;
use CodeIgniter\Controller;

class Coaster extends Controller
{
    use ResponseTrait;

    public function create()
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
            $client = service('predis');

            if(!$client->exists("coasterId")){
                $client->set('coasterId', 1);
            }

            $coasterId = $client->get("coasterId");

            $client->hmset("coaster_" . $coasterId, [
                "id" => $coasterId,
                "staff" => $data["liczba_personelu"],
                "customers" => $data["liczba_klientow"],
                "route_length" => $data["dl_trasy"],
                "from" => $data["godziny_od"],
                "to" => $data["godziny_do"],
            ]);

            $client->incr("coasterId");

            return $this->respondCreated(["coasterId" => $coasterId]);
        } catch (\Exception $e) {
            return $this->fail($e->getMessage(), 400);
        }
    }

    public function add(?int $coasterId = null)
    {
        try {
            $client = service('predis'); 

            if(!$client->exists('coaster_' . $coasterId)){
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

            if(!$client->exists("wagonId")){
                $client->set('wagonId', 1);
            }

            $wagonId = $client->get("wagonId");

            $client->hmset("wagon_" . $wagonId,  [
                "id" => $wagonId,
                "coasterId" => $coasterId,
                "seats" => $data["ilosc_miejsc"],
                "speed" => $data["predkosc_wagonu"],
            ]);

            $client->sadd("coaster_" . $coasterId . "_wagons", $wagonId);

            $client->incr("wagonId");

            return $this->respondCreated(["wagonId" => $wagonId]);
        } catch (\Exception $e) {
            return $this->fail($e->getMessage(), 400);
        }
    }

    public function update(?int $coasterId = null)
    {
        try {
            $client = service('predis'); 

            if(!$client->exists('coaster_' . $coasterId)){
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

            $client->hmset("coaster_" . $coasterId, [
                "staff" => $data["liczba_personelu"],
                "customers" => $data["liczba_klientow"],
                "from" => $data["godziny_od"],
                "to" => $data["godziny_do"],
            ]);

            return $this->respondCreated(["coasterId" => $coasterId]);
        } catch (\Exception $e) {
            return $this->fail($e->getMessage(), 400);
        }
    }

    public function delete(?int $coasterId = null, ?int $wagonId = null)
    {
        try {
            $client = service('predis'); 

            if(!$client->exists('coaster_' . $coasterId)){
                return $this->failNotFound("Kolejka ID: $coasterId nie istnieje");
            }

            if(!$client->exists('wagon_' . $wagonId)){
                return $this->failNotFound("Wagon ID: $wagonId nie istnieje");
            }

            if(0 === $client->sismember("coaster_" . $coasterId . "_wagons", $wagonId)){
                return $this->failNotFound("Wagon ID: $wagonId nie znajduje się w kolejce ID: $coasterId");
            }

            $client->srem("coaster_" . $coasterId . "_wagons", $wagonId);

            $client->del("wagon_" . $wagonId);

            return $this->respondDeleted(["wagonId" => $wagonId]);
        } catch (\Exception $e) {
            return $this->fail($e->getMessage(), 400);
        }
    }
}