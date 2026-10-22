<?php

namespace App\Libraries;

use Config\PlayerPortal;

class PlayerEmailService
{
    private $config;
    private PlayerPortal $portalConfig;

    public function __construct()
    {
        $this->config = config('Email');
        $this->portalConfig = config('PlayerPortal');
    }

    public function isConfigured(): bool
    {
        if (trim((string) env('player.email.enabled', 'true')) === 'false') {
            return false;
        }

        foreach ($this->accounts() as $account) {
            if ($this->accountIsConfigured($account)) {
                return true;
            }
        }

        return false;
    }

    public function sendVerification(array $player, string $token): bool
    {
        $url = base_url('app/verificar-email?token=' . urlencode($token));
        return $this->send((string) $player['email'], 'Confirmá tu email en La Barca', $this->layout(
            'Confirmá tu email',
            'Hola ' . esc((string) $player['first_name']) . ', confirmá tu dirección para activar tu cuenta de jugador.',
            $url,
            'Verificar mi email'
        ));
    }

    public function sendWelcome(array $player): bool
    {
        return $this->send((string) $player['email'], 'Bienvenido a La Barca', $this->layout(
            '¡Bienvenido a La Barca!',
            'Tu cuenta ya está activa. Prepará los botines y encontrá tu próximo partido.',
            base_url('app'),
            'Ir al portal'
        ));
    }

    public function sendPasswordReset(array $player, string $token): bool
    {
        $url = base_url('app/recuperar-clave?token=' . urlencode($token));
        return $this->send((string) $player['email'], 'Recuperá tu acceso a La Barca', $this->layout(
            'Recuperá tu acceso',
            'Recibimos una solicitud para cambiar tu contraseña. El enlace es válido por una hora y solo puede usarse una vez.',
            $url,
            'Cambiar contraseña'
        ));
    }

    public function sendMatchNotification(array $player, string $subject, string $title, string $copy, string $url, string $button = 'Ver partido'): bool
    {
        return $this->send((string) ($player['email'] ?? ''), $subject, $this->layout(
            $title,
            $copy,
            base_url(ltrim($url, '/')),
            $button
        ));
    }

    private function send(string $to, string $subject, string $message): bool
    {
        if (! $this->isConfigured()) {
            log_message('error', 'Portal de jugadores: servicio SMTP no configurado.');
            return false;
        }

        foreach ($this->accounts() as $account) {
            if (! $this->accountIsConfigured($account)) {
                continue;
            }

            try {
                $email = \Config\Services::email(null, false);
                $email->initialize([
                    'protocol' => $account['protocol'],
                    'SMTPHost' => $account['smtpHost'],
                    'SMTPUser' => $account['smtpUser'],
                    'SMTPPass' => str_replace(' ', '', (string) $account['smtpPassword']),
                    'SMTPPort' => $account['smtpPort'],
                    'SMTPTimeout' => $account['smtpTimeout'],
                    'SMTPKeepAlive' => false,
                    'SMTPCrypto' => $account['smtpCrypto'],
                    'SMTPOptions' => $this->config->SMTPOptions ?? [],
                    'mailType' => 'html',
                    'charset' => 'UTF-8',
                    'wordWrap' => true,
                    'CRLF' => "\r\n",
                    'newline' => "\r\n",
                ]);
                $email->setFrom($account['fromEmail'], $account['fromName']);
                $email->setTo($to);
                $email->setSubject($subject);
                $email->setMessage($message);
                if ($email->send()) {
                    return true;
                }
                log_message('error', 'Portal de jugadores: SMTP rechazó un email para la cuenta configurada.');
            } catch (\Throwable $exception) {
                log_message('error', 'Portal de jugadores: error técnico en un intento SMTP: ' . $exception->getMessage());
            }
        }
        return false;
    }

    private function layout(string $title, string $copy, string $url, string $button): string
    {
        $logo = base_url(PUBLIC_FOLDER . 'assets/images/logo.png');
        return '<!doctype html><html lang="es"><body style="margin:0;background:#171a1c;color:#f4f3ef;font-family:Arial,sans-serif;padding:28px 12px;">'
            . '<div style="max-width:560px;margin:auto;background:#24292b;border-radius:12px;padding:32px;text-align:center;">'
            . '<img src="' . esc($logo) . '" alt="La Barca" style="max-width:190px;margin-bottom:24px;">'
            . '<h1 style="font-size:28px;margin:0 0 14px;color:#f4f3ef;">' . esc($title) . '</h1>'
            . '<p style="font-size:16px;line-height:1.6;color:#c2c7c5;">' . $copy . '</p>'
            . '<a href="' . esc($url) . '" style="display:inline-block;background:#f47b20;color:#171a1c;padding:14px 22px;border-radius:6px;font-weight:bold;text-decoration:none;margin-top:12px;">' . esc($button) . '</a>'
            . '</div></body></html>';
    }

    private function envOrConfig(string $key, $fallback)
    {
        $value = env($key, null);
        return $value === null || $value === '' ? $fallback : $value;
    }

    private function accounts(): array
    {
        $configured = $this->config->accounts ?? [];
        $envAccount = [
            'fromEmail' => $this->envOrConfig('player.email.fromEmail', ''),
            'fromName' => $this->envOrConfig('player.email.fromName', ''),
            'smtpUser' => $this->envOrConfig('player.email.smtpUser', ''),
            'smtpPassword' => $this->envOrConfig('player.email.smtpPassword', ''),
        ];

        if ($envAccount['fromEmail'] !== '' || $envAccount['smtpUser'] !== '' || $envAccount['smtpPassword'] !== '') {
            $configured = [$envAccount];
        }

        $accounts = [];
        foreach ($configured as $account) {
            $accounts[] = [
                'fromEmail' => (string) ($account['fromEmail'] ?? $this->config->fromEmail ?? ''),
                'fromName' => (string) ($account['fromName'] ?? $this->config->fromName ?? 'La Barca'),
                'smtpUser' => (string) ($account['SMTPUser'] ?? $account['smtpUser'] ?? $this->config->SMTPUser ?? ''),
                'smtpPassword' => (string) ($account['SMTPPass'] ?? $account['smtpPassword'] ?? $this->config->SMTPPass ?? ''),
                'protocol' => (string) $this->envOrConfig('player.email.protocol', $this->config->protocol ?? 'smtp'),
                'smtpHost' => (string) $this->envOrConfig('player.email.smtpHost', $this->config->SMTPHost ?? ''),
                'smtpPort' => (int) $this->envOrConfig('player.email.smtpPort', $this->config->SMTPPort ?? 587),
                'smtpTimeout' => max(5, (int) $this->envOrConfig('player.email.smtpTimeout', $this->config->SMTPTimeout ?? 8)),
                'smtpCrypto' => (string) $this->envOrConfig('player.email.smtpCrypto', $this->config->SMTPCrypto ?? 'tls'),
            ];
        }

        if ($accounts === []) {
            $accounts[] = [
                'fromEmail' => (string) $this->envOrConfig('player.email.fromEmail', $this->config->fromEmail ?? ''),
                'fromName' => (string) $this->envOrConfig('player.email.fromName', $this->config->fromName ?? 'La Barca'),
                'smtpUser' => (string) $this->envOrConfig('player.email.smtpUser', $this->config->SMTPUser ?? ''),
                'smtpPassword' => (string) $this->envOrConfig('player.email.smtpPassword', $this->config->SMTPPass ?? ''),
                'protocol' => $this->protocolFallback(),
                'smtpHost' => (string) $this->envOrConfig('player.email.smtpHost', $this->config->SMTPHost ?? ''),
                'smtpPort' => (int) $this->envOrConfig('player.email.smtpPort', $this->config->SMTPPort ?? 587),
                'smtpTimeout' => max(5, (int) $this->envOrConfig('player.email.smtpTimeout', $this->config->SMTPTimeout ?? 8)),
                'smtpCrypto' => (string) $this->envOrConfig('player.email.smtpCrypto', $this->config->SMTPCrypto ?? 'tls'),
            ];
        }

        return $accounts;
    }

    private function accountIsConfigured(array $account): bool
    {
        return trim($account['fromEmail']) !== ''
            && trim($account['smtpHost']) !== ''
            && trim($account['smtpUser']) !== ''
            && trim($account['smtpPassword']) !== '';
    }

    private function protocolFallback(): string
    {
        return (string) $this->envOrConfig('player.email.protocol', $this->config->protocol ?? 'smtp');
    }
}
