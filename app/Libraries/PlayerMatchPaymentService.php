<?php

namespace App\Libraries;

use App\Models\PlayerMatchParticipantPaymentsModel;
use App\Models\PlayerMatchParticipantsModel;
use App\Models\PlayerMatchesModel;

/**
 * Pagos de participantes independientes de la seña de bookings.
 * La seña original se toma como crédito del organizador, pero nunca se modifica.
 */
class PlayerMatchPaymentService
{
    public const ORGANIZER_DEPOSIT = 'organizer_deposit';
    public const SPLIT = 'split';
    public const VENUE = 'venue';

    private $db;

    public function __construct()
    {
        $this->db = \Config\Database::connect();
    }

    public function enabledModes(array $settings): array
    {
        $modes = [];
        if (!empty($settings['payment_mode_organizer_deposit'])) $modes[] = self::ORGANIZER_DEPOSIT;
        if (!empty($settings['payment_mode_split']) && !empty($settings['allow_paid_join'])) $modes[] = self::SPLIT;
        if (!empty($settings['payment_mode_venue'])) $modes[] = self::VENUE;
        return $modes;
    }

    public function isEnabled(string $mode, array $settings): bool
    {
        return in_array($mode, $this->enabledModes($settings), true);
    }

    public function bookingAmounts(int $bookingId): array
    {
        $booking = $this->db->table('bookings')->where('id', $bookingId)->get()->getRowArray() ?: [];
        $total = (float) ($booking['total'] ?? $booking['total_payment'] ?? 0);
        $paid = (float) ($booking['payment'] ?? $booking['parcial'] ?? 0);
        return ['total' => max(0, round($total, 2)), 'reservation_paid' => max(0, round($paid, 2))];
    }

    public function paymentPolicy(string $mode): string
    {
        if ($mode === self::SPLIT) return 'reparto_incluye_organizador_seña_como_crédito';
        if ($mode === self::VENUE) return 'pago_presencial_seña_como_crédito_del_organizador';
        return 'organizador_abona_seña_reserva';
    }

    public function syncParticipants(int $matchId): void
    {
        $match = (new PlayerMatchesModel())->find($matchId);
        if (!$match) return;
        $mode = (string) ($match['payment_mode'] ?? self::ORGANIZER_DEPOSIT);
        $amounts = $this->bookingAmounts((int) $match['booking_id']);
        $total = (float) ($match['payment_total'] ?: $amounts['total']);
        $slots = max(1, (int) $match['max_players']);
        $share = round($total / $slots, 2);
        $participants = (new PlayerMatchParticipantsModel())->where('match_id', $matchId)->whereIn('status', ['confirmed', 'payment_pending'])->orderBy('role', 'DESC')->orderBy('id', 'ASC')->findAll();
        foreach ($participants as $index => $participant) {
            $due = $share;
            if ($participant['role'] === 'organizer') $due = max(0, round($share - $amounts['reservation_paid'], 2));
            $status = $mode === self::ORGANIZER_DEPOSIT ? 'not_required' : ((float) ($participant['amount_paid'] ?? 0) >= $due && $due > 0 ? 'paid' : ($due <= 0 ? 'not_required' : 'pending'));
            (new PlayerMatchParticipantsModel())->update($participant['id'], ['amount_due' => $due, 'payment_status' => $status, 'updated_at' => date('Y-m-d H:i:s')]);
        }
        (new PlayerMatchesModel())->update($matchId, ['payment_total' => $total, 'participant_share' => $share, 'payment_policy' => $this->paymentPolicy($mode), 'updated_at' => date('Y-m-d H:i:s')]);
    }

    public function participantDue(array $participant): float
    {
        return max(0, round((float) ($participant['amount_due'] ?? 0) - (float) ($participant['amount_paid'] ?? 0), 2));
    }

    public function createPayment(int $matchId, int $participantId, int $playerId): ?array
    {
        $row = $this->db->table('player_match_participant_payments')->where('match_id', $matchId)->where('participant_id', $participantId)->where('status', 'pending')->get()->getRowArray();
        if ($row && (float) $row['amount'] > 0) {
            $meta = json_decode((string) ($row['metadata'] ?? ''), true);
            if (empty($meta['expires_at']) || strtotime($meta['expires_at']) > time()) return $row;
            (new PlayerMatchParticipantPaymentsModel())->update($row['id'], ['status' => 'cancelled', 'updated_at' => date('Y-m-d H:i:s')]);
        }
        $participant = (new PlayerMatchParticipantsModel())->find($participantId);
        $match = (new PlayerMatchesModel())->find($matchId);
        if (!$participant || !$match || (int) $participant['player_id'] !== $playerId || (string) $match['payment_mode'] !== self::SPLIT) return null;
        $amounts = $this->bookingAmounts((int) $match['booking_id']);
        $approved = (float) $this->db->table('player_match_participant_payments')->selectSum('amount')->where('match_id', $matchId)->where('status', 'approved')->get()->getRow('amount');
        $remaining = max(0, round($amounts['total'] - $amounts['reservation_paid'] - $approved, 2));
        $amount = min($this->participantDue($participant), $remaining);
        if ($amount <= 0) return null;
        $now = date('Y-m-d H:i:s');
        $external = 'match_participant:' . $matchId . ':' . $participantId . ':' . bin2hex(random_bytes(8));
        $expires = date('Y-m-d H:i:s', time() + (max(1, (int) ($this->settings()['participant_payment_expiry_hours'] ?? 24)) * 3600));
        $payments = new PlayerMatchParticipantPaymentsModel();
        $payments->insert(['match_id' => $matchId, 'participant_id' => $participantId, 'player_id' => $playerId, 'booking_id' => $match['booking_id'], 'amount' => $amount, 'status' => 'pending', 'provider' => 'mercado_pago', 'external_reference' => $external, 'metadata' => json_encode(['expires_at' => $expires]), 'created_at' => $now, 'updated_at' => $now]);
        (new PlayerMatchParticipantsModel())->update($participantId, ['payment_expires_at' => $expires, 'payment_status' => 'pending', 'updated_at' => $now]);
        return $payments->find((int) $payments->getInsertID());
    }

    public function releaseExpiredHolds(int $matchId): void
    {
        $rows = (new PlayerMatchParticipantsModel())->where('match_id', $matchId)->where('status', 'payment_pending')->where('payment_expires_at <=', date('Y-m-d H:i:s'))->findAll();
        foreach ($rows as $row) {
            (new PlayerMatchParticipantsModel())->update($row['id'], ['status' => 'payment_expired', 'payment_status' => 'pending', 'updated_at' => date('Y-m-d H:i:s')]);
            (new PlayerMatchParticipantPaymentsModel())->where('participant_id', $row['id'])->where('status', 'pending')->set(['status' => 'cancelled', 'updated_at' => date('Y-m-d H:i:s')])->update();
            $this->db->table('player_matches')->where('id', $matchId)->where('reserved_players >', 0)->set('reserved_players', 'reserved_players - 1', false)->set('updated_at', date('Y-m-d H:i:s'))->update();
        }
    }

    public function confirmMercadoPago(array $paymentData): array
    {
        $reference = trim((string) ($paymentData['external_reference'] ?? ''));
        if (strpos($reference, 'match_participant:') !== 0) return ['confirmed' => false, 'ignored' => true];
        $payment = (new PlayerMatchParticipantPaymentsModel())->where('external_reference', $reference)->first();
        if (!$payment) return ['confirmed' => false, 'error' => 'payment_not_found'];
        if ($payment['status'] === 'approved') return ['confirmed' => true, 'duplicate' => true, 'payment' => $payment];
        $expected = round((float) $payment['amount'], 2); $received = round((float) ($paymentData['transaction_amount'] ?? 0), 2);
        if (trim((string) ($paymentData['status'] ?? '')) !== 'approved' || abs($expected - $received) > 0.01) return ['confirmed' => false, 'error' => 'amount_or_status_invalid'];
        $participant = (new PlayerMatchParticipantsModel())->find((int) $payment['participant_id']);
        $match = (new PlayerMatchesModel())->find((int) $payment['match_id']);
        if (!$participant || !$match) return ['confirmed' => false, 'error' => 'participant_not_found'];
        $wasPending = ($participant['status'] ?? '') === 'payment_pending';
        $now = date('Y-m-d H:i:s'); $db = $this->db; $db->transStart();
        (new PlayerMatchParticipantPaymentsModel())->update($payment['id'], ['status' => 'approved', 'provider_payment_id' => (string) ($paymentData['id'] ?? ''), 'confirmed_at' => $now, 'updated_at' => $now]);
        (new PlayerMatchParticipantsModel())->update($participant['id'], ['amount_paid' => round((float) $participant['amount_paid'] + $expected, 2), 'payment_status' => 'paid', 'status' => 'confirmed', 'payment_confirmed_at' => $now, 'updated_at' => $now]);
        if ($wasPending) $db->table('player_matches')->where('id', $match['id'])->where('reserved_players >', 0)->set('reserved_players', 'reserved_players - 1', false)->set('confirmed_players', 'confirmed_players + 1', false)->set('updated_at', $now)->update();
        $db->transComplete();
        return ['confirmed' => $db->transStatus() !== false, 'payment' => $payment];
    }

    private function settings(): array
    {
        return $this->db->table('player_match_settings')->where('id', 1)->get()->getRowArray() ?: ['participant_payment_expiry_hours' => 24];
    }
}
