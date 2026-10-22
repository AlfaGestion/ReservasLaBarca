<?php

namespace App\Controllers;

use App\Libraries\PlayerPortalSettings;

class PlayerPortalAdmin extends BaseController
{
    private function guardSuperadmin()
    {
        if (! session()->get('logueado') || ! session()->get('superadmin')) {
            return redirect()->to(base_url('abmAdmin'))->with('msg', ['type' => 'danger', 'body' => 'No tenés permisos para configurar el portal de jugadores.']);
        }
        return null;
    }

    public function index()
    {
        if ($redirect = $this->guardSuperadmin()) return $redirect;
        return view('player_portal_admin/index', ['testMode' => (new PlayerPortalSettings())->isTestMode()]);
    }

    public function save()
    {
        if ($redirect = $this->guardSuperadmin()) return $redirect;
        $enabled = (bool) $this->request->getPost('test_mode');
        (new PlayerPortalSettings())->setTestMode($enabled);
        return redirect()->to(base_url('admin/portal'))->with('msg', ['type' => 'success', 'body' => $enabled ? 'Modo prueba activado para el portal de jugadores.' : 'Modo prueba desactivado. Las reservas volverán a utilizar Mercado Pago.']);
    }
}
