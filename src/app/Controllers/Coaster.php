<?php

namespace App\Controllers;

use App\Models\CoasterModelInterface;
use App\Models\RedisCoasterModel;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\API\ResponseTrait;
use CodeIgniter\Controller;

class Coaster extends Controller
{
    use ResponseTrait;

    private CoasterModelInterface $model;

    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->model = model(RedisCoasterModel::class);
    }

    public function create(): ResponseInterface
    {
        $data = $this->request->getJSON(true);

        $rules = [
            'liczba_personelu' => 'required|integer|greater_than[0]',
            'liczba_klientow' => 'required|integer|greater_than[0]',
            'dl_trasy' => 'required|integer|greater_than[0]',
            'godziny_od' => 'required|string|regex_match[/^([0-1]?[0-9]|2[0-3]):[0-5][0-9]$/]',
            'godziny_do' => 'required|string|regex_match[/^([0-1]?[0-9]|2[0-3]):[0-5][0-9]$/]',
        ];

        if (!$this->validateData($data, $rules)) {
            return $this->failValidationErrors($this->validator->getErrors());
        }

        try {
            $result = $this->model->createCoaster($data);

            return $this->respondCreated(["coasterId" => $result]);
        } catch (\Exception $e) {
            return $this->failServerError($e->getMessage());
        }
    }

    public function add(?int $coasterId = null): ResponseInterface
    {
        if (!$this->model->coasterExists($coasterId)) {
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

        try {
            $result = $this->model->addWagon($data, $coasterId);

            return $this->respondCreated(["wagonId" => $result]);
        } catch (\Exception $e) {
            return $this->failServerError($e->getMessage());
        }
    }

    public function update(?int $coasterId = null): ResponseInterface
    {
        if (!$this->model->coasterExists($coasterId)) {
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

        try {
            $this->model->updateCoaster($data, $coasterId);

            return $this->respondCreated(["coasterId" => $coasterId]);
        } catch (\Exception $e) {
            return $this->failServerError($e->getMessage());
        }
    }

    public function delete(?int $coasterId = null, ?int $wagonId = null): ResponseInterface
    {
        if (!$this->model->coasterExists($coasterId)) {
            return $this->failNotFound("Kolejka ID: $coasterId nie istnieje");
        }

        if (!$this->model->wagonExists($wagonId, $coasterId)) {
            return $this->failNotFound("Wagon ID: $wagonId nie istnieje lub nie znajduje się w kolejce ID: $coasterId");
        }

        try {
            $this->model->deleteWagon($wagonId, $coasterId);

            return $this->respondDeleted(["wagonId" => $wagonId]);
        } catch (\Exception $e) {
            return $this->failServerError($e->getMessage());
        }
    }
}
