<?php

namespace App\Filters;

use App\Models\PlayerAccountsModel;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class PlayerAuth implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $playerId = (int) session()->get('player_id');
        $player = $playerId > 0 ? (new PlayerAccountsModel())->find($playerId) : null;
        $validSession = session()->get('player_logged_in')
            && $player
            && ! empty($player['email_verified_at'])
            && (int) session()->get('player_session_version') === (int) ($player['session_version'] ?? 0);

        // Recuperar la sesión del portal si el proxy o el navegador perdió
        // la cookie de sesión PHP, sin afectar la sesión del administrador.
        if (! $validSession) {
            $token = (string) $request->getCookie('player_portal_auth');
            $parts = explode('.', $token, 2);
            $payload = '';
            if (count($parts) === 2) {
                $padding = strlen($parts[0]) % 4;
                $payload = (string) base64_decode(strtr($parts[0] . ($padding ? str_repeat('=', 4 - $padding) : ''), '-_', '+/'), true);
                $payloadParts = explode('|', $payload);
                $tokenPlayer = count($payloadParts) === 3 && (int) ($payloadParts[0] ?? 0) > 0
                    ? (new PlayerAccountsModel())->find((int) $payloadParts[0])
                    : null;
                $signature = hash_hmac('sha256', $payload, (string) ($tokenPlayer['password_hash'] ?? ''));
                if ($payload !== '' && $tokenPlayer && hash_equals($signature, $parts[1])
                    && (int) $payloadParts[2] >= time()) {
                    if ($tokenPlayer && ! empty($tokenPlayer['email_verified_at']) && ($tokenPlayer['status'] ?? 'active') !== 'blocked'
                        && (int) $payloadParts[1] === (int) ($tokenPlayer['session_version'] ?? 0)) {
                        session()->set([
                            'player_logged_in' => true,
                            'player_id' => (int) $tokenPlayer['id'],
                            'player_email' => $tokenPlayer['email'],
                            'player_session_version' => (int) ($tokenPlayer['session_version'] ?? 0),
                        ]);
                        $player = $tokenPlayer;
                        $playerId = (int) $tokenPlayer['id'];
                        $validSession = true;
                    }
                }
            }
        }
        if (! $validSession) {
            log_message('warning', 'Portal: sesión inválida. player_id={playerId}, logged={logged}, player_found={playerFound}, verified={verified}, version_match={versionMatch}', [
                'playerId' => $playerId,
                'logged' => session()->get('player_logged_in') ? 1 : 0,
                'playerFound' => $player ? 1 : 0,
                'verified' => $player && ! empty($player['email_verified_at']) ? 1 : 0,
                'versionMatch' => $player && (int) session()->get('player_session_version') === (int) ($player['session_version'] ?? 0) ? 1 : 0,
            ]);
            session()->remove(['player_logged_in', 'player_id', 'player_email', 'player_session_version']);
            $acceptsJson = strpos(strtolower((string) $request->getHeaderLine('Accept')), 'application/json') !== false
                || strtolower((string) $request->getHeaderLine('X-Requested-With')) === 'xmlhttprequest';
            if ($acceptsJson) {
                return service('response')->setStatusCode(401)->setJSON([
                    'error' => true,
                    'message' => 'Tu sesion vencio. Inicia sesion nuevamente para continuar.',
                ]);
            }
            return redirect()->to(base_url('app/login'))->with('player_msg', [
                'type' => 'info',
                'body' => 'Iniciá sesión para continuar.',
            ]);
        }
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
    }
}
