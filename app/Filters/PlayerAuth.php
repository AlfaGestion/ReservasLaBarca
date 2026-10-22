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
        if (! $validSession) {
            session()->remove(['player_logged_in', 'player_id', 'player_email', 'player_session_version']);
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
