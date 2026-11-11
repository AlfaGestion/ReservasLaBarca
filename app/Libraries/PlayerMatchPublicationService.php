<?php

namespace App\Libraries;

use App\Models\PlayerMatchParticipantsModel;
use App\Models\PlayerMatchesModel;

class PlayerMatchPublicationService
{
    private $db;

    public function __construct()
    {
        $this->db = \Config\Database::connect();
    }

    public function queue(int $bookingId, int $organizerId, array $options): bool
    {
        // Defensa del lado del servidor: una reserva común nunca puede
        // convertirse en un partido abierto por datos enviados desde el
        // navegador. El checkbox de búsqueda debe estar explícitamente activo.
        if ($bookingId <= 0 || $organizerId <= 0 || empty($options['enabled']) || ! $this->db->tableExists('player_match_publication_intents')) return false;
        $sportBooking = $this->db->table('bookings b')->select('b.id, s.id AS service_id, s.is_sport')->join('fields f', 'f.id = b.id_field')->join('services s', 's.code = f.service_type')->where('b.id', $bookingId)->where('s.is_sport', 1)->where('b.annulled', 0)->get()->getRowArray();
        if (! $sportBooking) return false;
        $settings = $this->settingsForService((int) ($sportBooking['service_id'] ?? 0));
        if (empty($settings['portal_enabled']) || empty($settings['search_players'])) return false;
        $max = (int) ($options['max_players'] ?? 0); $min = max(2, (int) ($settings['min_players'] ?? 2)); $upper = max($min, (int) ($settings['max_players'] ?? 30));
        $mode = in_array(($options['join_mode'] ?? ''), ['auto', 'approval'], true) ? $options['join_mode'] : '';
        if ($max < $min || $max > $upper || $mode === '') return false;
        if (($mode === 'auto' && empty($settings['allow_auto_join'])) || ($mode === 'approval' && empty($settings['allow_approval']))) return false;
        $paymentMode = (string) ($options['payment_mode'] ?? PlayerMatchPaymentService::ORGANIZER_DEPOSIT);
        if (! (new PlayerMatchPaymentService())->isEnabled($paymentMode, $settings)) return false;
        $now = date('Y-m-d H:i:s'); $row = $this->db->table('player_match_publication_intents')->where('booking_id', $bookingId)->get()->getRowArray();
        $data = ['organizer_player_id' => $organizerId, 'max_players' => $max, 'join_mode' => $mode, 'payment_mode' => $paymentMode, 'description' => trim((string) ($options['description'] ?? '')) ?: null, 'level' => trim((string) ($options['level'] ?? '')) ?: null, 'padel_category_min' => trim((string) ($options['padel_category_min'] ?? '')) ?: null, 'padel_category_max' => trim((string) ($options['padel_category_max'] ?? '')) ?: null, 'status' => 'pending', 'updated_at' => $now];
        if ($row) return ($row['status'] ?? '') === 'pending' ? (bool) $this->db->table('player_match_publication_intents')->where('id', $row['id'])->update($data) : false;
        return (bool) $this->db->table('player_match_publication_intents')->insert(array_merge(['booking_id' => $bookingId, 'created_at' => $now], $data));
    }

    public function publishIfEligible(int $bookingId): ?int
    {
        if ($bookingId <= 0 || ! $this->db->tableExists('player_match_publication_intents')) return null;
        $intent = $this->db->table('player_match_publication_intents')->where('booking_id', $bookingId)->where('status', 'pending')->get()->getRowArray();
        if (! $intent) return null;
        $booking = $this->db->table('bookings')->where('id', $bookingId)->where('annulled', 0)->where('approved', 1)->get()->getRowArray();
        if (! $booking) return null;
        if ((new PlayerMatchesModel())->where('booking_id', $bookingId)->first()) { $this->db->table('player_match_publication_intents')->where('id', $intent['id'])->update(['status' => 'published', 'updated_at' => date('Y-m-d H:i:s')]); return null; }
        $now = date('Y-m-d H:i:s'); $this->db->transStart(); $matches = new PlayerMatchesModel(); $amounts = (new PlayerMatchPaymentService())->bookingAmounts($bookingId); $matches->insert(['booking_id' => $bookingId, 'organizer_player_id' => $intent['organizer_player_id'], 'max_players' => $intent['max_players'], 'confirmed_players' => 1, 'description' => $intent['description'], 'level' => $intent['level'], 'padel_category_min' => $intent['padel_category_min'] ?? null, 'padel_category_max' => $intent['padel_category_max'] ?? null, 'join_mode' => $intent['join_mode'], 'payment_mode' => $intent['payment_mode'], 'payment_total' => $amounts['total'], 'participant_share' => round($amounts['total'] / max(1, (int) $intent['max_players']), 2), 'reserved_players' => 0, 'payment_policy' => (new PlayerMatchPaymentService())->paymentPolicy((string) $intent['payment_mode']), 'status' => 'open', 'created_at' => $now, 'updated_at' => $now]); $matchId = (int) $matches->getInsertID();
        if ($matchId > 0) (new PlayerMatchParticipantsModel())->insert(['match_id' => $matchId, 'player_id' => $intent['organizer_player_id'], 'role' => 'organizer', 'status' => 'confirmed', 'created_at' => $now, 'updated_at' => $now]);
        $this->db->table('player_match_publication_intents')->where('id', $intent['id'])->update(['status' => $matchId > 0 ? 'published' : 'failed', 'match_id' => $matchId > 0 ? $matchId : null, 'updated_at' => $now]); $this->db->transComplete();
        if ($this->db->transStatus() === false || $matchId <= 0) return null;
        (new PlayerMatchPaymentService())->syncParticipants($matchId); (new PlayerMatchNotificationService())->enqueueForMatch($matchId); return $matchId;
    }

    private function settings(): array { return $this->db->table('player_match_settings')->where('id', 1)->get()->getRowArray() ?: ['search_players_in_reservation' => 1, 'allow_free_join' => 1, 'payment_mode_organizer_deposit' => 1, 'payment_mode_split' => 0, 'payment_mode_venue' => 0, 'min_players' => 2, 'max_players' => 30]; }

    private function settingsForService(int $serviceId): array
    {
        $global = $this->settings();
        if ($serviceId <= 0 || ! $this->db->tableExists('player_service_match_settings')) {
            return array_merge($global, ['portal_enabled' => 1, 'search_players' => (int) ($global['search_players_in_reservation'] ?? 1)]);
        }
        $local = $this->db->table('player_service_match_settings')->where('service_id', $serviceId)->get()->getRowArray() ?: [];
        $merged = array_merge($global, ['portal_enabled' => 1, 'search_players' => (int) ($global['search_players_in_reservation'] ?? 1)], $local);
        // La configuración global debe poder desactivar la publicación para
        // todos los servicios, incluso si un servicio quedó habilitado.
        $merged['search_players'] = (int) ($global['search_players_in_reservation'] ?? 0);
        $merged['allow_auto_join'] = (int) ($global['allow_auto_join'] ?? 0);
        $merged['allow_approval'] = (int) ($global['allow_approval'] ?? 0);
        return $merged;
    }
}
