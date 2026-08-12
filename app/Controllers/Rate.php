<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use App\Models\RateModel;
use App\Models\RateHistoryModel;
use App\Models\UsersModel;

class Rate extends BaseController
{
    public function saveRate()
    {
        $rateModel = new RateModel();
        $rateHistoryModel = new RateHistoryModel();
        $usersModel = new UsersModel();
        $db = \Config\Database::connect();
        $data = $this->request->getJSON();

        $rawValue = is_object($data) ? ($data->value ?? null) : null;
        $normalizedValue = is_string($rawValue) ? str_replace(',', '.', trim($rawValue)) : $rawValue;
        if ($normalizedValue === null || $normalizedValue === '' || ! is_numeric($normalizedValue)) {
            return $this->response->setJSON($this->setResponse(422, true, null, 'Debe ingresar un porcentaje numérico válido.'));
        }

        $newValue = round((float) $normalizedValue, 2);
        if ($newValue < 0) {
            return $this->response->setJSON($this->setResponse(422, true, null, 'El porcentaje no puede ser negativo.'));
        }

        $existingRate = $rateModel->findAll();
        $currentRate = $existingRate[0] ?? null;

        if ($currentRate && isset($currentRate['value']) && (float) $currentRate['value'] === $newValue) {
            return $this->response->setJSON($this->setResponse(null, null, null, 'El porcentaje no cambió.'));
        }

        $userId = session()->get('id_user') ?? session()->get('id') ?? null;
        $userId = is_numeric($userId) ? (int) $userId : null;
        $userName = trim((string) (session()->get('name') ?? session()->get('user') ?? session()->get('email') ?? ''));
        if ($userId) {
            $user = $usersModel->find($userId);
            if ($user) {
                $userName = trim((string) ($user['name'] ?? $user['user'] ?? $userName));
            }
        }

        $query = [
            'value' => $newValue,
        ];

        $db->transBegin();

        try {
            if ($currentRate) {
                $rateId = (int) $currentRate['id'];
                $oldValue = isset($currentRate['value']) ? (float) $currentRate['value'] : null;
                if (! $rateModel->update($rateId, $query)) {
                    throw new \RuntimeException('No se pudo actualizar el porcentaje.');
                }

                if (! $rateHistoryModel->insert([
                    'rate_id' => $rateId,
                    'old_value' => $oldValue,
                    'new_value' => $newValue,
                    'user_id' => $userId,
                    'user_name' => $userName !== '' ? $userName : null,
                    'created_at' => date('Y-m-d H:i:s'),
                ])) {
                    throw new \RuntimeException('No se pudo registrar el historial del porcentaje.');
                }
            } else {
                if (! $rateModel->insert($query)) {
                    throw new \RuntimeException('No se pudo crear el porcentaje inicial.');
                }
            }

            if ($db->transStatus() === false) {
                $db->transRollback();
                return $this->response->setJSON($this->setResponse(500, true, null, 'No se pudo guardar el porcentaje con su historial.'));
            }

            $db->transCommit();
            return $this->response->setJSON($this->setResponse(null, null, null, 'Respuesta exitosa'));
        } catch (\Throwable $e) {
            $db->transRollback();
            return $this->response->setJSON($this->setResponse(500, true, null, $e->getMessage()));
        }
    }

    public function getRate(){
        $rateModel = new RateModel();

        $rate = $rateModel->first();

        try {
            return  $this->response->setJSON($this->setResponse(null, null, $rate, 'Respuesta exitosa'));
        } catch (\Exception $e) {
            return  $this->response->setJSON($this->setResponse(404, true, null, $e->getMessage()));
        }
    }

    public function getRateHistory()
    {
        $rateModel = new RateModel();
        $rateHistoryModel = new RateHistoryModel();
        $usersModel = new UsersModel();

        $rate = $rateModel->first();
        $rows = $rateHistoryModel->orderBy('created_at', 'DESC')
            ->orderBy('id', 'DESC')
            ->findAll();

        $history = [];
        foreach ($rows as $row) {
            $userName = trim((string) ($row['user_name'] ?? ''));
            if ($userName === '' && !empty($row['user_id'])) {
                $user = $usersModel->find((int) $row['user_id']);
                if ($user) {
                    $userName = trim((string) ($user['name'] ?? $user['user'] ?? ''));
                }
            }

            $history[] = [
                'id' => (int) ($row['id'] ?? 0),
                'created_at' => $row['created_at'] ?? null,
                'user_id' => !empty($row['user_id']) ? (int) $row['user_id'] : null,
                'user_name' => $userName !== '' ? $userName : 'N/D',
                'old_value' => isset($row['old_value']) ? (float) $row['old_value'] : null,
                'new_value' => isset($row['new_value']) ? (float) $row['new_value'] : null,
            ];
        }

        return $this->response->setJSON($this->setResponse(null, null, [
            'current_rate' => $rate,
            'history' => $history,
        ], 'Respuesta exitosa'));
    }

    public function setResponse($code = 200, $error = false, $data = null, $message = '')
    {
        $response = [
            'error' => $error,
            'code' => $code,
            'data' => $data,
            'message' => $message,
        ];

        return $response;
    }
}
