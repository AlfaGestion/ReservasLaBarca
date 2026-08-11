<?php

namespace App\Controllers;

use App\Models\UsersModel;

class Auth extends BaseController
{
    private function renderRegisterWindow(bool $embedded = false)
    {
        $modelUsers = new UsersModel();
        $users = $modelUsers->findAll();
        $selectedUserId = (int) $this->request->getGet('user_id');
        $view = $embedded ? 'auth/register_window' : 'auth/register';

        return view($view, [
            'users' => $users,
            'embedded' => $embedded,
            'selectedUserId' => $selectedUserId > 0 ? $selectedUserId : null,
        ]);
    }

    public function index()
    {
        return view('auth/login');
    }

    public function login()
    {
        $modelUsers = new UsersModel();

        $user = $this->request->getVar('user');
        $password = $this->request->getVar('password');

        $userData = $modelUsers->where('user', $user)->first();
        $invalidCredentials = redirect()->to('auth/login')->with('msg', [
            'type' => 'danger',
            'body' => 'El usuario o la contrasena no son correctos',
        ]);

        if (! isset($userData)) {
            return $invalidCredentials;
        }

        if (array_key_exists('active', $userData) && $userData['active'] !== null && (int) $userData['active'] === 0) {
            return $invalidCredentials;
        }

        if (! password_verify($password, $userData['password'])) {
            return $invalidCredentials;
        }

        $sessionData = [
            'id_user'    => $userData['id'],
            'user'       => $userData['user'],
            'active'     => $userData['active'],
            'name'       => $userData['name'],
            'superadmin' => $userData['superadmin'],
            'logueado'   => true,
        ];

        session()->remove('msg');
        session()->set($sessionData);

        return redirect()->to(base_url('abmAdmin'));
    }

    public function log_out()
    {
        session()->destroy();

        return redirect()->route('auth/login');
    }

    public function register()
    {
        return $this->renderRegisterWindow(false);
    }

    public function registerWindow()
    {
        return $this->renderRegisterWindow(true);
    }

    public function dbRegister()
    {
        $modelUsers = new UsersModel();
        $redirectOnError = session()->get('logueado') ? 'abmAdmin' : 'auth/register';
        $redirectOnSuccess = session()->get('logueado') ? 'abmAdmin' : 'auth/login';
        $isAjaxRequest = $this->request->isAJAX();

        $password = (string) $this->request->getVar('password');
        $repeatPassword = (string) $this->request->getVar('repeat_password');
        $superadmin = $this->request->getVar('superadmin') ? 1 : 0;
        $user = trim((string) $this->request->getVar('user'));
        $name = trim((string) $this->request->getVar('name'));

        $respondError = function (string $message, int $statusCode = 400) use ($isAjaxRequest, $redirectOnError) {
            if ($isAjaxRequest) {
                return $this->response
                    ->setStatusCode($statusCode)
                    ->setJSON([
                        'error' => true,
                        'message' => $message,
                        'csrf' => [
                            'name' => csrf_token(),
                            'hash' => csrf_hash(),
                        ],
                    ]);
            }

            return redirect()->to($redirectOnError)->with('msg', ['type' => 'danger', 'body' => $message]);
        };

        if ($password !== $repeatPassword) {
            return $respondError('Las contrasenas no coinciden');
        }

        if ($user === '' || $name === '' || $password === '') {
            return $respondError('Debe completar todos los datos');
        }

        $query = [
            'user' => $user,
            'password' => password_hash($password, PASSWORD_DEFAULT),
            'superadmin' => $superadmin,
            'name' => $name,
            'active' => 1,
        ];

        try {
            $insertId = $modelUsers->insert($query);
        } catch (\Exception $e) {
            return $respondError('Error al insertar datos: ' . $e->getMessage(), 500);
        }

        if ($isAjaxRequest) {
            return $this->response->setJSON([
                'error' => false,
                'message' => 'Usuario creado correctamente',
                'user' => [
                    'id' => $insertId,
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

        return redirect()->to($redirectOnSuccess)->with('msg', ['type' => 'success', 'body' => 'Usuario creado correctamente']);
    }
}
