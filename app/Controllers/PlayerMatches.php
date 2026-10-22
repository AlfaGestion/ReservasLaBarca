<?php

namespace App\Controllers;

use App\Models\PlayerAccountsModel;
use App\Models\PlayerBookingLinksModel;
use App\Models\PlayerMatchParticipantsModel;
use App\Models\PlayerMatchRequestsModel;
use App\Models\PlayerMatchesModel;
use App\Libraries\PlayerEmailService;
use App\Libraries\PlayerNotificationService;
use CodeIgniter\HTTP\RedirectResponse;

class PlayerMatches extends BaseController
{
    private function player(): ?array
    {
        $id = (int) session()->get('player_id');
        return $id > 0 ? (new PlayerAccountsModel())->find($id) : null;
    }

    private function page(string $view, array $data = [])
    {
        return view('player/layout', array_merge(['contentView' => 'player/' . $view, 'player' => $this->player()], $data));
    }

    public function index()
    {
        $requestedSport = trim((string) $this->request->getGet('deporte'));
        $requestedDate = trim((string) $this->request->getGet('fecha'));
        $sport = $requestedSport !== '' ? $requestedSport : $this->favoriteSportCode();
        $date = $requestedDate !== '' ? $requestedDate : date('Y-m-d');
        return $this->page('matches', [
            'matches' => $this->publicMatches($sport, $date),
            'sports' => $this->sports(),
            'filters' => ['sport' => $sport, 'date' => $date, 'explicit' => $requestedSport !== '' || $requestedDate !== ''],
        ]);
    }

    public function create()
    {
        return $this->page('match_create', ['bookings' => $this->eligibleBookings(), 'settings' => $this->settings(), 'selectedBookingId' => (int) $this->request->getGet('booking_id')]);
    }

    public function detail(int $matchId)
    {
        $match = $this->match($matchId);
        if (! $match) return redirect()->to(base_url('app/partidos'))->with('player_msg', ['type' => 'error', 'body' => 'No encontramos ese partido.']);
        return $this->page('match_detail', ['match' => $match, 'participants' => $this->participants($matchId), 'request' => $this->requestFor($matchId), 'pendingRequests' => $this->pendingForMatch($matchId)]);
    }

    public function publicProfile(int $playerId)
    {
        $viewerId = (int) $this->player()['id'];
        $matchId = (int) $this->request->getGet('partido');
        if ($matchId <= 0 || ! $this->canViewProfileInMatch($viewerId, $playerId, $matchId)) {
            return $this->backWithError('app/partidos', 'No podemos mostrar ese perfil desde este partido.');
        }

        $profile = (new PlayerAccountsModel())->find($playerId);
        if (! $profile) {
            return $this->backWithError('app/partidos', 'No encontramos ese jugador.');
        }

        $favoriteCodes = json_decode((string) ($profile['favorite_sports'] ?? '[]'), true);
        if (! is_array($favoriteCodes)) {
            $favoriteCodes = [];
        }
        $sports = $this->sports();
        $favoriteSports = [];
        foreach ($sports as $sport) {
            if (in_array($sport['code'], $favoriteCodes, true) || in_array($sport['name'], $favoriteCodes, true)) {
                $favoriteSports[] = $sport['name'];
            }
        }

        return $this->page('player_public_profile', [
            'publicPlayer' => $profile,
            'favoriteSports' => $favoriteSports,
            'matchId' => $matchId,
        ]);
    }

    public function mine()
    {
        $playerId = (int) $this->player()['id'];
        return $this->page('my_matches', ['organized' => $this->organized($playerId), 'joined' => $this->joined($playerId), 'requests' => $this->pendingRequests($playerId)]);
    }

    public function publish()
    {
        $playerId = (int) $this->player()['id'];
        $data = $this->request->getPost();
        $bookingId = (int) ($data['booking_id'] ?? 0);
        $booking = $this->eligibleBooking($bookingId, $playerId);
        if (! $booking) return $this->backWithError('app/partidos/crear', 'La reserva no se puede publicar como partido.');
        $max = (int) ($data['max_players'] ?? 0);
        $confirmed = (int) ($data['confirmed_players'] ?? 1);
        $mode = in_array(($data['join_mode'] ?? ''), ['auto', 'approval'], true) ? $data['join_mode'] : '';
        $settings = $this->settings();
        $minPlayers = max(2, (int) ($settings['min_players'] ?? 2)); $maxPlayers = max($minPlayers, (int) ($settings['max_players'] ?? 30));
        if ($max < $minPlayers || $max > $maxPlayers || $confirmed < 1 || $confirmed > $max) return $this->backWithError('app/partidos/crear', 'Revisá la cantidad de jugadores.');
        if (($mode === 'auto' && ! $settings['allow_auto_join']) || ($mode === 'approval' && ! $settings['allow_approval'])) return $this->backWithError('app/partidos/crear', 'Esta modalidad de ingreso no está habilitada.');
        if ((new PlayerMatchesModel())->where('booking_id', $bookingId)->whereIn('status', ['open', 'closed'])->first()) return $this->backWithError('app/partidos/crear', 'Esta reserva ya tiene un partido publicado.');
        $now = date('Y-m-d H:i:s');
        $db = \Config\Database::connect();
        $db->transStart();
        $matches = new PlayerMatchesModel();
        $matches->insert(['booking_id' => $bookingId, 'organizer_player_id' => $playerId, 'max_players' => $max, 'confirmed_players' => $confirmed, 'description' => trim((string) ($data['description'] ?? '')) ?: null, 'level' => trim((string) ($data['level'] ?? '')) ?: null, 'join_mode' => $mode, 'status' => 'open', 'created_at' => $now, 'updated_at' => $now]);
        $matchId = (int) $matches->getInsertID();
        (new PlayerMatchParticipantsModel())->insert(['match_id' => $matchId, 'player_id' => $playerId, 'role' => 'organizer', 'status' => 'confirmed', 'created_at' => $now, 'updated_at' => $now]);
        $db->transComplete();
        if ($db->transStatus() === false || $matchId <= 0) return $this->backWithError('app/partidos/crear', 'No pudimos publicar el partido. Intentá nuevamente.');
        return redirect()->to(base_url('app/mis-partidos'))->with('player_msg', ['type' => 'success', 'body' => 'El partido fue publicado correctamente.']);
    }

    public function join(int $matchId)
    {
        $playerId = (int) $this->player()['id'];
        $match = $this->match($matchId);
        if (! $match || ! $this->isOpen($match)) return $this->backWithError('app/partidos', 'Este partido ya no está disponible.');
        if ((int) $match['organizer_player_id'] === $playerId) return $this->backWithError('app/partidos/detalle/' . $matchId, 'Ya sos el organizador de este partido.');
        if ($this->participant($matchId, $playerId)) return $this->backWithError('app/partidos/detalle/' . $matchId, 'Ya estás incorporado a este partido.');
        $now = date('Y-m-d H:i:s');
        $db = \Config\Database::connect();
        $db->transStart();
        $mode = $match['join_mode'] === 'auto' ? 'confirmed' : 'pending';
        if ($mode === 'confirmed') {
            $updated = $db->table('player_matches')->where('id', $matchId)->where('status', 'open')->where('confirmed_players <', (int) $match['max_players'])->set('confirmed_players', 'confirmed_players + 1', false)->set('updated_at', $now)->update();
            if (! $updated) { $db->transRollback(); return $this->backWithError('app/partidos/detalle/' . $matchId, 'El partido se completó. Elegí otro.'); }
            (new PlayerMatchParticipantsModel())->insert(['match_id' => $matchId, 'player_id' => $playerId, 'role' => 'participant', 'status' => 'confirmed', 'created_at' => $now, 'updated_at' => $now]);
        } else {
            $requests = new PlayerMatchRequestsModel();
            $existing = $requests->where('match_id', $matchId)->where('player_id', $playerId)->first();
            if ($existing && in_array($existing['status'], ['pending', 'accepted'], true)) { $db->transRollback(); return $this->backWithError('app/partidos/detalle/' . $matchId, 'Ya enviaste una solicitud para este partido.'); }
            if ($existing) $requests->update($existing['id'], ['status' => 'pending', 'updated_at' => $now]); else $requests->insert(['match_id' => $matchId, 'player_id' => $playerId, 'status' => 'pending', 'created_at' => $now, 'updated_at' => $now]);
        }
        $db->transComplete();
        if ($db->transStatus() === false) return $this->backWithError('app/partidos/detalle/' . $matchId, 'No pudimos registrar tu solicitud. Intentá nuevamente.');
        $currentPlayer = $this->player();
        $organizer = (new PlayerAccountsModel())->find((int) $match['organizer_player_id']);
        $matchUrl = 'app/partidos/detalle/' . $matchId;
        if ($mode === 'confirmed') {
            $this->notifyMatchEvent($currentPlayer, 'partido_unido', 'Te sumaste a un partido', 'Tu lugar quedó confirmado en el partido de ' . $this->matchLabel($match) . '.', $matchUrl, 'Ver mi partido');
            $this->notifyMatchEvent($organizer, 'nuevo_participante', 'Nuevo jugador en tu partido', $this->playerName($currentPlayer) . ' se sumó a tu partido de ' . $this->matchLabel($match) . '.', $matchUrl, 'Ver participantes');
        } else {
            $this->notifyMatchEvent($currentPlayer, 'solicitud_enviada', 'Solicitud enviada', 'Tu solicitud para sumarte al partido de ' . $this->matchLabel($match) . ' quedó pendiente de aprobación.', $matchUrl, 'Ver solicitud');
            $this->notifyMatchEvent($organizer, 'nueva_solicitud', 'Nueva solicitud para tu partido', $this->playerName($currentPlayer) . ' quiere sumarse a tu partido de ' . $this->matchLabel($match) . '.', $matchUrl, 'Revisar solicitud');
        }
        $text = $mode === 'confirmed' ? 'Te sumaste al partido.' : 'Solicitud enviada. El organizador debe aprobarla.';
        return redirect()->to(base_url('app/partidos/detalle/' . $matchId))->with('player_msg', ['type' => 'success', 'body' => $text]);
    }

    public function cancelRequest(int $matchId)
    {
        $request = $this->requestFor($matchId);
        if ($request && $request['status'] === 'pending') (new PlayerMatchRequestsModel())->update($request['id'], ['status' => 'cancelled', 'updated_at' => date('Y-m-d H:i:s')]);
        return redirect()->to(base_url('app/partidos/detalle/' . $matchId))->with('player_msg', ['type' => 'success', 'body' => 'Solicitud cancelada.']);
    }

    public function decide(int $requestId, string $decision)
    {
        $playerId = (int) $this->player()['id']; $requests = new PlayerMatchRequestsModel(); $request = $requests->find($requestId); $match = $request ? $this->match((int) $request['match_id']) : null;
        if (! $request || ! $match || (int) $match['organizer_player_id'] !== $playerId || $request['status'] !== 'pending') return $this->backWithError('app/mis-partidos', 'No tenés permiso para gestionar esa solicitud.');
        if ($decision === 'reject') {
            $requests->update($requestId, ['status' => 'rejected', 'updated_at' => date('Y-m-d H:i:s')]);
            $requester = (new PlayerAccountsModel())->find((int) $request['player_id']);
            $this->notifyMatchEvent($requester, 'solicitud_rechazada', 'Solicitud no aprobada', 'El organizador no pudo aceptar tu solicitud para el partido de ' . $this->matchLabel($match) . '.', 'app/partidos/detalle/' . (int) $match['id'], 'Ver partido');
            return $this->backWithSuccess('app/mis-partidos', 'Solicitud rechazada.');
        }
        if ($decision !== 'accept' || ! $this->isOpen($match)) return $this->backWithError('app/mis-partidos', 'El partido ya no está disponible.');
        $db = \Config\Database::connect(); $db->transStart();
        $updated = $db->table('player_matches')->where('id', $match['id'])->where('confirmed_players <', (int) $match['max_players'])->set('confirmed_players', 'confirmed_players + 1', false)->set('updated_at', date('Y-m-d H:i:s'))->update();
        if (! $updated) { $db->transRollback(); return $this->backWithError('app/mis-partidos', 'No quedan lugares disponibles.'); }
        $participants = new PlayerMatchParticipantsModel();
        $existingParticipant = $participants->where('match_id', $match['id'])->where('player_id', $request['player_id'])->first();
        if ($existingParticipant) {
            $participants->update($existingParticipant['id'], ['status' => 'confirmed', 'updated_at' => date('Y-m-d H:i:s')]);
        } else {
            $participants->insert(['match_id' => $match['id'], 'player_id' => $request['player_id'], 'role' => 'participant', 'status' => 'confirmed', 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')]);
        }
        $requests->update($requestId, ['status' => 'accepted', 'updated_at' => date('Y-m-d H:i:s')]); $db->transComplete();
        if ($db->transStatus() === false) {
            return $this->backWithError('app/mis-partidos', 'No pudimos aceptar la solicitud.');
        }
        $requester = (new PlayerAccountsModel())->find((int) $request['player_id']);
        $this->notifyMatchEvent($requester, 'solicitud_aceptada', 'Solicitud aceptada', 'El organizador te aceptó en el partido de ' . $this->matchLabel($match) . '.', 'app/partidos/detalle/' . (int) $match['id'], 'Ver partido');
        return $this->backWithSuccess('app/mis-partidos', 'Jugador aceptado en el partido.');
    }

    public function close(int $matchId, string $action = 'close')
    {
        $match = $this->match($matchId); if (! $match || (int) $match['organizer_player_id'] !== (int) $this->player()['id']) return $this->backWithError('app/mis-partidos', 'No tenés permiso para modificar ese partido.');
        (new PlayerMatchesModel())->update($matchId, ['status' => $action === 'reopen' ? 'open' : 'closed', 'updated_at' => date('Y-m-d H:i:s')]);
        return $this->backWithSuccess('app/mis-partidos', $action === 'reopen' ? 'Búsqueda reabierta.' : 'Búsqueda cerrada.');
    }

    public function leave(int $matchId)
    {
        $playerId = (int) $this->player()['id']; $match = $this->match($matchId); $participant = $this->participant($matchId, $playerId);
        if (! $match || ! $participant || $participant['role'] === 'organizer' || $participant['status'] !== 'confirmed') return $this->backWithError('app/mis-partidos', 'No podés retirarte de este partido.');
        $db = \Config\Database::connect(); $db->transStart(); (new PlayerMatchParticipantsModel())->update($participant['id'], ['status' => 'withdrawn', 'updated_at' => date('Y-m-d H:i:s')]); $db->table('player_matches')->where('id', $matchId)->where('confirmed_players >', 1)->set('confirmed_players', 'confirmed_players - 1', false)->set('updated_at', date('Y-m-d H:i:s'))->update(); $db->transComplete();
        return $db->transStatus() === false ? $this->backWithError('app/mis-partidos', 'No pudimos registrar tu retiro.') : $this->backWithSuccess('app/mis-partidos', 'Te retiraste del partido.');
    }

    private function publicMatches(string $sport, string $date): array
    {
        $today = date('Y-m-d'); $builder = \Config\Database::connect()->table('player_matches m')->select('m.*, b.date, b.time_from, b.time_until, b.annulled, f.name AS field_name, s.name AS service_name, s.code AS service_code, o.first_name AS organizer_first_name, o.last_name AS organizer_last_name')->join('bookings b', 'b.id = m.booking_id')->join('fields f', 'f.id = b.id_field')->join('services s', 's.code = f.service_type')->join('player_accounts o', 'o.id = m.organizer_player_id')->where('m.status', 'open')->where('s.is_sport', 1)->where('b.annulled', 0)->where('b.date >=', $today)->where('m.confirmed_players < m.max_players', null, false)->orderBy('b.date', 'ASC')->orderBy('b.time_from', 'ASC');
        if ($sport !== '') $builder->where('s.code', $sport); if ($date !== '') $builder->where('b.date', $date); return $builder->get()->getResultArray();
    }

    private function eligibleBookings(): array
    {
        $playerId = (int) $this->player()['id']; $db = \Config\Database::connect(); $used = (new PlayerMatchesModel())->select('booking_id')->whereIn('status', ['open', 'closed'])->findColumn('booking_id'); $builder = $db->table('player_booking_links l')->select('b.id, b.date, b.time_from, b.time_until, f.name AS field_name, s.name AS service_name')->join('bookings b', 'b.id = l.booking_id')->join('fields f', 'f.id = b.id_field')->join('services s', 's.code = f.service_type')->where('l.player_id', $playerId)->where('s.is_sport', 1)->where('b.annulled', 0)->where('b.date >=', date('Y-m-d')); if (! empty($used)) $builder->whereNotIn('b.id', $used); return $builder->orderBy('b.date', 'ASC')->get()->getResultArray();
    }

    private function eligibleBooking(int $bookingId, int $playerId): ?array { foreach ($this->eligibleBookings() as $booking) if ((int) $booking['id'] === $bookingId) return $booking; return null; }
    private function match(int $id): ?array { return \Config\Database::connect()->table('player_matches m')->select('m.*, b.date, b.time_from, b.time_until, b.annulled, f.name AS field_name, s.name AS service_name, s.code AS service_code, s.is_sport, o.first_name AS organizer_first_name, o.last_name AS organizer_last_name')->join('bookings b', 'b.id = m.booking_id')->join('fields f', 'f.id = b.id_field')->join('services s', 's.code = f.service_type')->join('player_accounts o', 'o.id = m.organizer_player_id')->where('m.id', $id)->get()->getRowArray() ?: null; }
    private function participants(int $id): array { return \Config\Database::connect()->table('player_match_participants p')->select('p.*, a.first_name, a.last_name')->join('player_accounts a', 'a.id = p.player_id')->where('p.match_id', $id)->where('p.status', 'confirmed')->get()->getResultArray(); }
    private function participant(int $matchId, int $playerId): ?array { return (new PlayerMatchParticipantsModel())->where('match_id', $matchId)->where('player_id', $playerId)->first(); }
    private function canViewProfileInMatch(int $viewerId, int $playerId, int $matchId): bool
    {
        $match = $this->match($matchId);
        if (! $match || (int) $match['annulled'] === 1) {
            return false;
        }
        if ((int) $match['organizer_player_id'] === $viewerId) {
            return $this->participant($matchId, $playerId) !== null || (new PlayerMatchRequestsModel())->where('match_id', $matchId)->where('player_id', $playerId)->first() !== null;
        }
        return $this->participant($matchId, $viewerId) !== null && $this->participant($matchId, $playerId) !== null;
    }
    private function requestFor(int $matchId): ?array { return (new PlayerMatchRequestsModel())->where('match_id', $matchId)->where('player_id', (int) $this->player()['id'])->first(); }
    private function pendingForMatch(int $matchId): array { return \Config\Database::connect()->table('player_match_requests r')->select('r.*, a.first_name, a.last_name')->join('player_accounts a', 'a.id = r.player_id')->where('r.match_id', $matchId)->where('r.status', 'pending')->orderBy('r.created_at', 'ASC')->get()->getResultArray(); }
    private function organized(int $playerId): array { return (new PlayerMatchesModel())->where('organizer_player_id', $playerId)->orderBy('created_at', 'DESC')->findAll(); }
    private function joined(int $playerId): array { return \Config\Database::connect()->table('player_match_participants p')->select('m.*, b.date, b.time_from, b.time_until, f.name AS field_name, s.name AS service_name')->join('player_matches m', 'm.id = p.match_id')->join('bookings b', 'b.id = m.booking_id')->join('fields f', 'f.id = b.id_field')->join('services s', 's.code = f.service_type')->where('p.player_id', $playerId)->where('p.role', 'participant')->orderBy('b.date', 'DESC')->get()->getResultArray(); }
    private function pendingRequests(int $playerId): array { return \Config\Database::connect()->table('player_match_requests r')->select('r.*, m.max_players, m.confirmed_players, b.date, b.time_from, s.name AS service_name, f.name AS field_name, o.first_name AS organizer_first_name, o.last_name AS organizer_last_name')->join('player_matches m', 'm.id = r.match_id')->join('bookings b', 'b.id = m.booking_id')->join('fields f', 'f.id = b.id_field')->join('services s', 's.code = f.service_type')->join('player_accounts o', 'o.id = m.organizer_player_id')->where('r.player_id', $playerId)->where('r.status', 'pending')->get()->getResultArray(); }
    private function sports(): array { return \Config\Database::connect()->table('services')->select('code, name')->where('is_sport', 1)->where('active', 1)->orderBy('display_order', 'ASC')->get()->getResultArray(); }
    private function favoriteSportCode(): string { $player = $this->player(); if (! $player) return ''; $favorites = json_decode((string) ($player['favorite_sports'] ?? '[]'), true); if (! is_array($favorites)) return ''; foreach ($this->sports() as $sport) if (in_array($sport['code'], $favorites, true) || in_array($sport['name'], $favorites, true)) return (string) $sport['code']; return ''; }
    private function settings(): array { return \Config\Database::connect()->table('player_match_settings')->where('id', 1)->get()->getRowArray() ?: ['allow_auto_join' => 1, 'allow_approval' => 1, 'min_players' => 2, 'max_players' => 30]; }
    private function isOpen(array $match): bool { return (int) ($match['annulled'] ?? 0) === 0 && $match['status'] === 'open' && $match['date'] >= date('Y-m-d') && (int) $match['confirmed_players'] < (int) $match['max_players']; }
    private function notifyMatchEvent(?array $player, string $type, string $title, string $message, string $url, string $button): void
    {
        if (! $player || empty($player['id'])) {
            return;
        }

        (new \App\Libraries\PlayerNotificationService())->create((int) $player['id'], $type, $title, $message, $url);
        $email = new \App\Libraries\PlayerEmailService();
        if (! $email->sendMatchNotification($player, $title . ' · La Barca', $title, $message, $url, $button)) {
            log_message('error', 'Portal de jugadores: no se pudo enviar la notificación por email al jugador ID ' . (int) $player['id'] . '.');
        }
    }
    private function playerName(?array $player): string { return trim((string) ($player['first_name'] ?? '') . ' ' . (string) ($player['last_name'] ?? '')) ?: 'Un jugador'; }
    private function matchLabel(array $match): string { return trim((string) ($match['service_name'] ?? 'partido') . ' · ' . (string) ($match['field_name'] ?? 'cancha')); }
    private function backWithError(string $url, string $body): RedirectResponse { return redirect()->to(base_url($url))->with('player_msg', ['type' => 'error', 'body' => $body]); }
    private function backWithSuccess(string $url, string $body): RedirectResponse { return redirect()->to(base_url($url))->with('player_msg', ['type' => 'success', 'body' => $body]); }
}
