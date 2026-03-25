<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use App\Models\UsersModel;

class Users extends BaseController
{
    public function index()
    {
        //
    }

    public function getUser($id)
    {
        $modelUsers = new UsersModel();
        $user = $modelUsers->where('id', $id)->first();

        try {
            return $this->response->setJSON($this->setResponse(null, null, $user, 'Respuesta exitosa'));
        } catch (\Exception $e) {
            return $this->response->setJSON($this->setResponse(404, true, null, $e->getMessage()));
        }
    }

    public function editUser()
    {
        $modelUsers = new UsersModel();
        $isJsonRequest = str_contains((string) $this->request->getHeaderLine('Content-Type'), 'application/json');
        $isAjaxRequest = $this->request->isAJAX();

        if ($isJsonRequest) {
            $data = $this->request->getJSON();
            $id = $data->id ?? null;
            $user = trim((string) ($data->user ?? ''));
            $password = (string) ($data->password ?? '');
            $repeatPassword = (string) ($data->repeat_password ?? $password);
            $name = trim((string) ($data->name ?? ''));
            $superadmin = !empty($data->superadmin) ? 1 : 0;
        } else {
            $id = $this->request->getPost('id');
            $user = trim((string) $this->request->getPost('user'));
            $password = (string) $this->request->getPost('password');
            $repeatPassword = (string) $this->request->getPost('repeat_password');
            $name = trim((string) $this->request->getPost('name'));
            $superadmin = $this->request->getPost('superadmin') ? 1 : 0;
        }

        if ($password !== '' && $password !== $repeatPassword) {
            if ($isJsonRequest || $isAjaxRequest) {
                return $this->response
                    ->setStatusCode(400)
                    ->setJSON([
                        'error' => true,
                        'message' => 'Las contraseñas no coinciden',
                        'csrf' => [
                            'name' => csrf_token(),
                            'hash' => csrf_hash(),
                        ],
                    ]);
            }

            return redirect()->to('abmAdmin')->with('msg', ['type' => 'danger', 'body' => 'Las contraseñas no coinciden']);
        }

        $query = [
            'user' => $user,
            'name' => $name,
            'superadmin' => $superadmin,
        ];

        if ($password !== '') {
            $query['password'] = password_hash($password, PASSWORD_DEFAULT);
        }

        try {
            $modelUsers->update($id, $query);

            if ($isJsonRequest || $isAjaxRequest) {
                return $this->response->setJSON([
                    'error' => false,
                    'message' => 'Usuario actualizado correctamente',
                    'user' => [
                        'id' => $id,
                        'user' => $user,
                        'name' => $name,
                        'superadmin' => $superadmin,
                    ],
                    'csrf' => [
                        'name' => csrf_token(),
                        'hash' => csrf_hash(),
                    ],
                ]);
            }

            return redirect()->to('abmAdmin')->with('msg', ['type' => 'success', 'body' => 'Usuario actualizado correctamente']);
        } catch (\Exception $e) {
            if ($isJsonRequest || $isAjaxRequest) {
                return $this->response
                    ->setStatusCode(500)
                    ->setJSON([
                        'error' => true,
                        'message' => $e->getMessage(),
                        'csrf' => [
                            'name' => csrf_token(),
                            'hash' => csrf_hash(),
                        ],
                    ]);
            }

            return redirect()->to('abmAdmin')->with('msg', ['type' => 'danger', 'body' => 'Error al actualizar usuario: ' . $e->getMessage()]);
        }
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
