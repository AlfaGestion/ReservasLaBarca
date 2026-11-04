<?php

namespace App\Libraries;

use App\Models\PlayerAccountsModel;

class PlayerMatchNotificationService
{
    private $db;

    public function __construct()
    {
        $this->db = \Config\Database::connect();
    }

    public function enqueueForMatch(int $matchId): void
    {
        if ($matchId <= 0 || ! $this->db->tableExists('player_match_email_notifications') || ! $this->notificationsEnabled()) return;
        $match = $this->match($matchId);
        if (! $match || (int) ($match['annulled'] ?? 0) === 1 || ($match['status'] ?? '') !== 'open') return;
        $players = (new PlayerAccountsModel())->where('id !=', (int) $match['organizer_player_id'])->where('status', 'active')->where('notify_favorite_matches', 1)->where('email_verified_at IS NOT NULL', null, false)->findAll();
        $now = date('Y-m-d H:i:s');
        foreach ($players as $player) {
            if (! $this->likesSport($player, $match)) continue;
            try {
                $this->db->table('player_match_email_notifications')->ignore(true)->insert(['match_id' => $matchId, 'player_id' => (int) $player['id'], 'status' => 'pending', 'attempts' => 0, 'created_at' => $now, 'updated_at' => $now]);
            } catch (\Throwable $exception) {
                log_message('error', 'Portal de jugadores: no se pudo encolar un aviso de partido. ' . $exception->getMessage());
            }
        }
        $this->dispatchAfterResponse();
    }

    public function dispatchAfterResponse(): void
    {
        register_shutdown_function(function (): void {
            try { $this->processPending(25); } catch (\Throwable $exception) { log_message('error', 'Portal de jugadores: error procesando avisos pendientes. ' . $exception->getMessage()); }
        });
    }

    public function processPending(int $limit = 25): int
    {
        if (! $this->db->tableExists('player_match_email_notifications')) return 0;
        $rows = $this->db->table('player_match_email_notifications')->whereIn('status', ['pending', 'failed'])->where('attempts <', 3)->orderBy('id', 'ASC')->get(max(1, min($limit, 100)))->getResultArray();
        $sent = 0; $email = new PlayerEmailService();
        foreach ($rows as $row) {
            $player = (new PlayerAccountsModel())->find((int) $row['player_id']); $match = $this->match((int) $row['match_id']);
            if (! $player || ! $match || empty($player['email_verified_at']) || empty($player['notify_favorite_matches']) || ! $this->likesSport($player, $match)) { $this->db->table('player_match_email_notifications')->where('id', $row['id'])->update(['status' => 'cancelled', 'updated_at' => date('Y-m-d H:i:s')]); continue; }
            $attempts = (int) $row['attempts'] + 1; $ok = $email->sendFavoriteMatchNotification($player, $match);
            $data = ['attempts' => $attempts, 'status' => $ok ? 'sent' : ($attempts >= 3 ? 'failed' : 'pending'), 'updated_at' => date('Y-m-d H:i:s')];
            if ($ok) { $data['sent_at'] = date('Y-m-d H:i:s'); $data['last_error'] = null; $sent++; } else { $data['last_error'] = 'El servicio de correo no confirmó el envío.'; }
            $this->db->table('player_match_email_notifications')->where('id', $row['id'])->update($data);
        }
        return $sent;
    }

    private function notificationsEnabled(): bool { $row = $this->db->table('player_match_settings')->where('id', 1)->get()->getRowArray(); return ! $row || ! array_key_exists('favorite_notifications_enabled', $row) || (int) $row['favorite_notifications_enabled'] === 1; }
    private function match(int $id): ?array { return $this->db->table('player_matches m')->select('m.*, b.date, b.time_from, b.time_until, b.annulled, f.name AS field_name, s.name AS service_name, s.code AS service_code')->join('bookings b', 'b.id = m.booking_id')->join('fields f', 'f.id = b.id_field')->join('services s', 's.code = f.service_type')->where('m.id', $id)->get()->getRowArray() ?: null; }
    private function likesSport(array $player, array $match): bool { $favorites = json_decode((string) ($player['favorite_sports'] ?? '[]'), true); if (! is_array($favorites)) return false; return in_array($match['service_code'] ?? '', $favorites, true) || in_array($match['service_name'] ?? '', $favorites, true); }
}
