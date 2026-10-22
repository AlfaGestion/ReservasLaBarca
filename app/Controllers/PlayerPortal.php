<?php

namespace App\Controllers;

use App\Libraries\PlayerEmailService;
use App\Models\PlayerAccountsModel;
use App\Models\ServicesModel;
use Config\PlayerCarousel as PlayerCarouselConfig;
use Config\PlayerPortal as PlayerPortalConfig;

class PlayerPortal extends BaseController
{
    private function players(): PlayerAccountsModel
    {
        return new PlayerAccountsModel();
    }

    private function portalConfig(): PlayerPortalConfig
    {
        return config('PlayerPortal');
    }

    private function emailService(): PlayerEmailService
    {
        return new PlayerEmailService();
    }

    private function viewPage(string $page, array $data = [])
    {
        return view('player/layout', array_merge([
            'contentView' => 'player/' . $page,
            'turnstileSiteKey' => $this->portalConfig()->turnstileSiteKey,
            'turnstileConfigured' => $this->portalConfig()->turnstileConfigured(),
            'turnstileRequired' => $this->portalConfig()->turnstileRequired(),
        ], $data));
    }

    private function input(string $key): string
    {
        return trim((string) $this->request->getPost($key));
    }

    private function redirectWithError(string $route, string $message)
    {
        return redirect()->to(base_url($route))->with('player_msg', ['type' => 'error', 'body' => $message]);
    }

    public function index()
    {
        $services = new ServicesModel();
        $carousel = config('PlayerCarousel');
        $player = $this->currentPlayer();
        return $this->viewPage('home', ['player' => $player, 'activeBooking' => $this->upcomingBooking($player), 'services' => $services->getActiveServices(true), 'carouselSlides' => $carousel->activeSlides()]);
    }

    public function login()
    {
        if ($this->currentPlayer()) {
            return redirect()->to(base_url('app'));
        }
        return $this->viewPage('login', ['minimalLayout' => true]);
    }

    public function authenticate()
    {
        $email = strtolower($this->input('email'));
        $password = (string) $this->request->getPost('password');
        if (! $this->allowRequest('login', $this->request->getIPAddress() . '|' . $email, 5)) {
            return $this->redirectWithError('app/login', 'Esperá unos segundos antes de volver a intentarlo.');
        }
        $player = $this->players()->where('email', $email)->first();
        if (! $player || ! password_verify($password, $player['password_hash'])) {
            return $this->redirectWithError('app/login', 'El correo o la contraseña no son correctos.');
        }
        if (empty($player['email_verified_at'])) {
            return redirect()->to(base_url('app/verificar-email'))->with('player_msg', [
                'type' => 'info',
                'body' => 'Confirmá tu email antes de ingresar al portal.',
            ])->with('player_verification_email', $player['email']);
        }

        session()->regenerate(true);
        session()->set([
            'player_logged_in' => true,
            'player_id' => (int) $player['id'],
            'player_email' => $player['email'],
            'player_session_version' => (int) ($player['session_version'] ?? 0),
        ]);
        return redirect()->to(base_url('app'))->with('player_msg', [
            'type' => 'success',
            'body' => '¡Bienvenido, ' . trim($player['first_name'] . ' ' . $player['last_name']) . '! Nos alegra volver a verte.',
        ]);
    }

    public function register()
    {
        return $this->viewPage('register', ['minimalLayout' => true]);
    }

    public function createAccount()
    {
        $data = [
            'first_name' => $this->input('first_name'),
            'last_name' => $this->input('last_name'),
            'email' => strtolower($this->input('email')),
            'phone' => $this->input('phone'),
        ];
        $password = (string) $this->request->getPost('password');
        $confirmation = (string) $this->request->getPost('password_confirmation');
        if ((string) $this->request->getPost('website') !== '') {
            return $this->redirectWithError('app/registro', 'No se pudo validar el registro.');
        }
        if (! $this->allowRequest('register', $this->request->getIPAddress(), 300)) {
            return $this->redirectWithError('app/registro', 'Esperá unos minutos antes de volver a intentarlo.');
        }
        if (in_array('', $data, true) || $password === '' || strlen($password) < 8) {
            return $this->redirectWithError('app/registro', 'Completá todos los campos y usá una contraseña de al menos 8 caracteres.');
        }
        if (! filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            return $this->redirectWithError('app/registro', 'Ingresá un correo electrónico válido.');
        }
        if ($password !== $confirmation) {
            return $this->redirectWithError('app/registro', 'Las contraseñas no coinciden.');
        }
        if (! $this->verifyTurnstile()) {
            return $this->redirectWithError('app/registro', $this->turnstileError());
        }
        if ($this->players()->where('email', $data['email'])->first()) {
            return $this->redirectWithError('app/registro', 'Ya existe una cuenta con ese correo.');
        }

        $now = date('Y-m-d H:i:s');
        $data['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
        $data['created_at'] = $now;
        $data['updated_at'] = $now;
        $data['session_version'] = 0;
        $model = $this->players();
        $id = $model->insert($data);
        $player = $model->find($id);
        $this->issueVerification($player);

        return redirect()->to(base_url('app/verificar-email'))->with('player_msg', [
            'type' => 'success',
            'body' => 'Cuenta creada. Revisá tu correo para activarla.',
        ])->with('player_verification_email', $data['email']);
    }

    public function verification()
    {
        $token = trim((string) $this->request->getGet('token'));
        if ($token !== '') {
            return $this->confirmEmail($token);
        }
        return $this->viewPage('verification_pending', [
            'minimalLayout' => true,
            'verificationEmail' => session('player_verification_email') ?: '',
        ]);
    }

    public function resendVerification()
    {
        $email = strtolower($this->input('email'));
        if ((string) $this->request->getPost('website') !== '') {
            return redirect()->to(base_url('app/verificar-email'))->with('player_msg', [
                'type' => 'info',
                'body' => 'Si la cuenta está pendiente, recibirás un nuevo enlace de verificación.',
            ]);
        }
        if (! $this->allowRequest('verification', $this->request->getIPAddress() . '|' . $email, $this->portalConfig()->verificationResendCooldown)) {
            return redirect()->to(base_url('app/verificar-email'))->with('player_msg', [
                'type' => 'info',
                'body' => 'Si la cuenta existe, recibirás un nuevo enlace cuando termine el período de espera.',
            ]);
        }
        if ($this->verifyTurnstile()) {
            $player = $this->players()->where('email', $email)->first();
            if ($player && empty($player['email_verified_at'])) {
                $this->issueVerification($player, true);
            }
        }
        return redirect()->to(base_url('app/verificar-email'))->with('player_msg', [
            'type' => 'success',
            'body' => 'Si la cuenta está pendiente, recibirás un nuevo enlace de verificación.',
        ])->with('player_verification_email', $email);
    }

    private function confirmEmail(string $token)
    {
        $model = $this->players();
        $player = $model->where('verification_token_hash', hash('sha256', $token))->first();
        if (! $player) {
            return $this->viewPage('verification_result', ['minimalLayout' => true, 'verified' => false, 'message' => 'El enlace no es válido o ya fue utilizado.']);
        }
        if (! empty($player['email_verified_at'])) {
            return $this->viewPage('verification_result', ['minimalLayout' => true, 'verified' => true, 'message' => 'Tu email ya estaba verificado.']);
        }
        if (empty($player['verification_expires_at']) || strtotime($player['verification_expires_at']) <= time()) {
            return $this->viewPage('verification_result', ['minimalLayout' => true, 'verified' => false, 'message' => 'El enlace de verificación venció. Solicitá uno nuevo.']);
        }

        $model->update($player['id'], [
            'email_verified_at' => date('Y-m-d H:i:s'),
            'verification_token_hash' => null,
            'verification_expires_at' => null,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $player['email_verified_at'] = date('Y-m-d H:i:s');
        $this->emailService()->sendWelcome($player);
        return $this->viewPage('verification_result', ['minimalLayout' => true, 'verified' => true, 'message' => 'Email verificado. Ya podés iniciar sesión en La Barca.']);
    }

    public function forgotPassword()
    {
        $token = trim((string) $this->request->getGet('token'));
        if ($token !== '') {
            $player = $this->players()->where('reset_token_hash', hash('sha256', $token))->first();
            if ($player && empty($player['reset_used_at']) && ! empty($player['reset_expires_at']) && strtotime($player['reset_expires_at']) > time()) {
                return $this->viewPage('reset_password', ['minimalLayout' => true, 'token' => $token]);
            }
            return $this->redirectWithError('app/recuperar-clave', 'El enlace no es válido o ya venció.');
        }
        return $this->viewPage('forgot_password', ['minimalLayout' => true]);
    }

    public function sendReset()
    {
        $token = trim((string) $this->request->getPost('token'));
        if ($token !== '') {
            return $this->resetPassword($token);
        }
        $email = strtolower($this->input('email'));
        $this->allowRequest('reset', $this->request->getIPAddress() . '|' . $email, $this->portalConfig()->resetRequestCooldown);
        $player = $this->players()->where('email', $email)->first();
        if ($player && ! empty($player['email_verified_at']) && $this->emailService()->isConfigured()) {
            $token = bin2hex(random_bytes(32));
            $this->players()->update($player['id'], [
                'reset_token_hash' => hash('sha256', $token),
                'reset_expires_at' => date('Y-m-d H:i:s', time() + $this->portalConfig()->resetTokenTtl),
                'reset_used_at' => null,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
            $this->emailService()->sendPasswordReset($player, $token);
        }
        return redirect()->to(base_url('app/recuperar-clave'))->with('player_msg', ['type' => 'success', 'body' => 'Si el correo está registrado, recibirás instrucciones para recuperar tu acceso.']);
    }

    private function resetPassword(string $token)
    {
        $password = (string) $this->request->getPost('password');
        $confirmation = (string) $this->request->getPost('password_confirmation');
        $player = $this->players()->where('reset_token_hash', hash('sha256', $token))->first();
        if (! $player || ! empty($player['reset_used_at']) || empty($player['reset_expires_at']) || strtotime($player['reset_expires_at']) <= time()) {
            return $this->redirectWithError('app/recuperar-clave', 'El enlace no es válido o ya venció.');
        }
        if (strlen($password) < 8 || $password !== $confirmation) {
            return $this->redirectWithError('app/recuperar-clave?token=' . urlencode($token), 'Las contraseñas deben coincidir y tener al menos 8 caracteres.');
        }
        $this->players()->update($player['id'], [
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'reset_used_at' => date('Y-m-d H:i:s'),
            'reset_token_hash' => null,
            'session_version' => ((int) ($player['session_version'] ?? 0)) + 1,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        session()->remove(['player_logged_in', 'player_id', 'player_email', 'player_session_version']);
        return redirect()->to(base_url('app/login'))->with('player_msg', ['type' => 'success', 'body' => 'Contraseña actualizada. Ya podés iniciar sesión.']);
    }

    public function profile()
    {
        $services = new ServicesModel();
        $services->ensureDefaultServices();
        return $this->viewPage('profile', ['player' => $this->currentPlayer(), 'favoriteServices' => $services->where('active', 1)->where('is_sport', 1)->orderBy('display_order', 'ASC')->findAll()]);
    }

    public function updateProfile()
    {
        $player = $this->currentPlayer();
        $data = [
            'first_name' => $this->input('first_name'),
            'last_name' => $this->input('last_name'),
            'phone' => $this->input('phone'),
            'locality' => $this->input('locality'),
            'favorite_sports' => json_encode($this->validFavoriteSports($this->request->getPost('favorite_sports') ?? []), JSON_UNESCAPED_UNICODE),
            'updated_at' => date('Y-m-d H:i:s'),
        ];
        if (! $player || $data['first_name'] === '' || $data['last_name'] === '') {
            return $this->redirectWithError('app/perfil', 'Nombre y apellido son obligatorios.');
        }
        $this->players()->update($player['id'], $data);
        return redirect()->to(base_url('app/perfil'))->with('player_msg', ['type' => 'success', 'body' => 'Perfil actualizado.']);
    }

    public function logout()
    {
        session()->remove(['player_logged_in', 'player_id', 'player_email', 'player_session_version']);
        return redirect()->to(base_url('app'));
    }

    private function validFavoriteSports($values): array
    {
        if (! is_array($values)) return [];
        $services = new ServicesModel();
        $services->ensureDefaultServices();
        $allowed = $services->where('active', 1)->where('is_sport', 1)->findAll();
        $names = [];
        foreach ($allowed as $service) $names[(string) $service['name']] = true;
        return array_values(array_filter(array_map('trim', $values), static function ($value) use ($names) { return isset($names[$value]); }));
    }

    public function placeholder(string $section)
    {
        $titles = ['reservas' => 'Mis reservas', 'partidos' => 'Buscar partidos', 'crear-partido' => 'Crear partido'];
        return $this->viewPage('placeholder', ['title' => $titles[$section] ?? 'Próximamente']);
    }

    private function currentPlayer(): ?array
    {
        $id = (int) session()->get('player_id');
        if ($id <= 0 || ! session()->get('player_logged_in')) {
            return null;
        }
        $player = $this->players()->find($id);
        if (! $player || empty($player['email_verified_at']) || (int) session()->get('player_session_version') !== (int) ($player['session_version'] ?? 0)) {
            session()->remove(['player_logged_in', 'player_id', 'player_email', 'player_session_version']);
            return null;
        }
        return $player;
    }

    private function upcomingBooking(?array $player): ?array
    {
        if (! $player) {
            return null;
        }

        return \Config\Database::connect()->table('bookings b')
            ->select('b.id, b.date, b.time_from, b.time_until, b.annulled, f.name AS field_name, s.name AS service_name, pm.id AS match_id, pm.status AS match_status')
            ->join('fields f', 'f.id = b.id_field', 'left')
            ->join('services s', 's.code = f.service_type', 'left')
            ->join('player_matches pm', "pm.booking_id = b.id AND pm.status IN ('open', 'closed')", 'left', false)
            ->join('player_booking_links l', 'l.booking_id = b.id AND l.player_id = ' . (int) $player['id'], 'left', false)
            ->join('player_match_participants p', "p.match_id = pm.id AND p.player_id = " . (int) $player['id'] . " AND p.status = 'confirmed'", 'left', false)
            ->groupStart()
                ->where('l.id IS NOT NULL', null, false)
                ->orWhere('p.id IS NOT NULL', null, false)
            ->groupEnd()
            ->where('b.annulled', 0)
            ->where('b.date >=', date('Y-m-d'))
            ->orderBy('b.date', 'ASC')
            ->orderBy('b.time_from', 'ASC')
            ->get()->getRowArray() ?: null;
    }

    private function issueVerification(array $player, bool $respectCooldown = false): bool
    {
        if ($respectCooldown && ! empty($player['verification_sent_at']) && strtotime($player['verification_sent_at']) > time() - $this->portalConfig()->verificationResendCooldown) {
            return false;
        }
        $token = bin2hex(random_bytes(32));
        $this->players()->update($player['id'], [
            'verification_token_hash' => hash('sha256', $token),
            'verification_expires_at' => date('Y-m-d H:i:s', time() + $this->portalConfig()->verificationTokenTtl),
            'verification_sent_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        return $this->emailService()->sendVerification($player, $token);
    }

    private function allowRequest(string $type, string $value, int $ttl): bool
    {
        $key = 'player_' . $type . '_' . hash('sha256', $value);
        $cache = cache();
        if ($cache->get($key)) {
            return false;
        }
        $cache->save($key, 1, $ttl);
        return true;
    }

    private function verifyTurnstile(): bool
    {
        $config = $this->portalConfig();
        $token = trim((string) $this->request->getPost('cf-turnstile-response'));
        if (! $config->turnstileConfigured()) {
            if ($config->turnstileRequired()) {
                log_message('error', 'Portal de jugadores: Turnstile requerido pero no configurado.');
                return false;
            }
            return true;
        }
        if ($token === '') {
            return false;
        }
        try {
            $response = service('curlrequest')->post('https://challenges.cloudflare.com/turnstile/v0/siteverify', [
                'form_params' => ['secret' => $config->turnstileSecretKey, 'response' => $token, 'remoteip' => $this->request->getIPAddress()],
                'http_errors' => false,
                'timeout' => 8,
            ]);
            $result = json_decode($response->getBody(), true);
            return is_array($result) && ! empty($result['success']);
        } catch (\Throwable $exception) {
            log_message('error', 'Portal de jugadores: no se pudo validar Turnstile: ' . $exception->getMessage());
            return false;
        }
    }

    private function turnstileError(): string
    {
        return $this->portalConfig()->turnstileRequired()
            ? 'El registro no está disponible hasta completar la configuración de seguridad.'
            : 'No se pudo validar la protección antispam. Intentá nuevamente.';
    }
}
