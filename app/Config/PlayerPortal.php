<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

class PlayerPortal extends BaseConfig
{
    public int $verificationTokenTtl = 86400;
    public int $verificationResendCooldown = 300;
    public int $resetTokenTtl = 3600;
    public int $resetRequestCooldown = 900;
    public string $turnstileSiteKey = '';
    public string $turnstileSecretKey = '';

    public function __construct()
    {
        parent::__construct();
        $this->verificationTokenTtl = max(300, (int) env('player.verificationTokenTtl', 86400));
        $this->verificationResendCooldown = max(60, (int) env('player.verificationResendCooldown', 300));
        $this->resetTokenTtl = max(300, (int) env('player.resetTokenTtl', 3600));
        $this->resetRequestCooldown = max(60, (int) env('player.resetRequestCooldown', 900));
        $this->turnstileSiteKey = trim((string) env('turnstile.siteKey', ''));
        $this->turnstileSecretKey = trim((string) env('turnstile.secretKey', ''));
    }

    public function turnstileConfigured(): bool
    {
        return $this->turnstileSiteKey !== '' && $this->turnstileSecretKey !== '';
    }

    public function turnstileRequired(): bool
    {
        // Se habilita explícitamente mediante .env cuando estén disponibles las claves.
        return filter_var(env('turnstile.required', false), FILTER_VALIDATE_BOOLEAN);
    }
}
