<?php

namespace App\Controllers;

use App\Libraries\PlayerNotificationService;
use App\Libraries\PlayerPortalSettings;
use App\Models\AdminLogsModel;
use App\Models\PlayerAccountDiscountsModel;
use App\Models\PlayerAccountsModel;
use App\Models\PlayerMatchParticipantsModel;
use App\Models\PlayerMatchRequestsModel;
use App\Models\PlayerMatchesModel;
use App\Models\ServicesModel;
use App\Models\ServicePricesModel;
use App\Models\ServicePriceWindowsModel;
use App\Models\FieldsModel;
use App\Models\TimeModel;

class PlayerPortalAdmin extends BaseController
{
    private function guardSuperadmin()
    {
        if (! session()->get('logueado') || ! session()->get('superadmin')) {
            return redirect()->to(base_url('abmAdmin'))->with('msg', ['type' => 'danger', 'body' => 'No tenés permisos para administrar el portal de jugadores.']);
        }
        return null;
    }

    private function db() { return \Config\Database::connect(); }
    private function adminId(): ?int { $id = session()->get('id_user') ?? session()->get('id'); return is_numeric($id) ? (int) $id : null; }
    private function back(string $section, string $type, string $body) { return redirect()->to(base_url('admin/portal?section=' . urlencode($section)))->with('msg', ['type' => $type, 'body' => $body]); }

    private function normalizeServiceColor($value): string
    {
        $color = trim((string) $value);
        return preg_match('/^#[0-9a-fA-F]{6}$/', $color) ? strtolower($color) : '#198754';
    }

    private function parsePortalFieldPrice($value): ?float
    {
        if (is_array($value) || is_object($value)) return null;
        $raw = trim((string) $value);
        if ($raw === '') return null;
        $raw = str_replace(["$", ' ', "\xc2\xa0"], '', $raw);
        if (preg_match('/^\d+(?:[.,]\d{1,2})?$/', $raw)) {
            $normalized = str_replace(',', '.', $raw);
        } elseif (preg_match('/^\d{1,3}(?:\.\d{3})+(?:,\d{1,2})?$/', $raw)) {
            $normalized = str_replace(',', '.', str_replace('.', '', $raw));
        } else {
            return null;
        }
        $price = filter_var($normalized, FILTER_VALIDATE_FLOAT);
        return $price === false || ! is_finite((float) $price) ? null : (float) $price;
    }

    private function logAction(string $action, string $type, $id, ?array $old = null, ?array $new = null): void
    {
        try {
            if (! $this->db()->tableExists('admin_logs')) return;
            (new AdminLogsModel())->insert(['admin_id' => $this->adminId(), 'admin_name' => (string) (session()->get('name') ?? session()->get('user') ?? ''), 'action' => $action, 'entity_type' => $type, 'entity_id' => (string) $id, 'old_data' => $old ? json_encode($old, JSON_UNESCAPED_UNICODE) : null, 'new_data' => $new ? json_encode($new, JSON_UNESCAPED_UNICODE) : null, 'created_at' => date('Y-m-d H:i:s')]);
        } catch (\Throwable $e) { log_message('error', 'No se pudo registrar una acción administrativa del portal: ' . $e->getMessage()); }
    }

    public function index()
    {
        if ($redirect = $this->guardSuperadmin()) return $redirect;
        $section = (string) ($this->request->getGet('section') ?: 'resumen');
        if ($section === 'servicios') return view('player_portal_admin/services', ['services' => $this->portalServicesCatalog(), 'search' => (string) ($this->request->getGet('buscar') ?? ''), 'status' => (string) ($this->request->getGet('estado') ?? ''), 'nocturnalTime' => (new TimeModel())->first()['nocturnal_time'] ?? null]);
        if ($section === 'configuracion') return view('player_portal_admin/settings', ['section' => $section, 'testMode' => (new PlayerPortalSettings())->isTestMode(), 'settings' => $this->matchSettings(), 'services' => $this->portalServices(), 'nocturnalTime' => (new TimeModel())->first()['nocturnal_time'] ?? null]);
        return view('player_portal_admin/index', ['section' => $section, 'search' => (string) ($this->request->getGet('buscar') ?? ''), 'testMode' => (new PlayerPortalSettings())->isTestMode(), 'stats' => $this->stats(), 'players' => $section === 'jugadores' ? $this->players() : [], 'bookings' => $section === 'reservas' ? $this->portalBookings() : [], 'matches' => $section === 'partidos' ? $this->matches() : [], 'services' => $this->services(), 'discounts' => $section === 'descuentos' ? $this->discounts() : []]);
    }

    private function stats(): array
    {
        $db = $this->db(); $count = static function ($builder): int { try { return (int) $builder->countAllResults(); } catch (\Throwable $e) { return 0; } }; $s = ['players' => 0, 'active_players' => 0, 'blocked_players' => 0, 'new_players' => 0, 'bookings' => 0, 'matches' => 0, 'pending' => 0, 'complete' => 0, 'finished' => 0];
        if ($db->tableExists('player_accounts')) { $s['players'] = $count($db->table('player_accounts')); $s['active_players'] = $count($db->table('player_accounts')->where('status', 'active')); $s['blocked_players'] = $count($db->table('player_accounts')->where('status', 'blocked')); $s['new_players'] = $count($db->table('player_accounts')->where('created_at >=', date('Y-m-d', strtotime('-30 days')))); }
        if ($db->tableExists('player_booking_links')) $s['bookings'] = $count($db->table('player_booking_links'));
        if ($db->tableExists('player_matches')) { $s['matches'] = $count($db->table('player_matches')->where('status', 'open')); $s['complete'] = $count($db->table('player_matches')->where('confirmed_players >= max_players', null, false)); $s['finished'] = $count($db->table('player_matches')->where('status', 'finished')); }
        if ($db->tableExists('player_match_requests')) $s['pending'] = $count($db->table('player_match_requests')->where('status', 'pending'));
        return $s;
    }

    private function players(): array
    {
        $search = trim((string) $this->request->getGet('buscar')); $b = $this->db()->table('player_accounts')->select('player_accounts.*, COUNT(DISTINCT l.booking_id) AS booking_count')->join('player_booking_links l', 'l.player_id = player_accounts.id', 'left')->groupBy('player_accounts.id')->orderBy('player_accounts.created_at', 'DESC');
        if ($search !== '') $b->groupStart()->like('first_name', $search)->orLike('last_name', $search)->orLike('email', $search)->orLike('phone', $search)->groupEnd(); return $b->get(100)->getResultArray();
    }

    private function portalBookings(): array
    {
        return $this->db()->table('player_booking_links l')->select('l.booking_id, l.player_id, l.created_at AS linked_at, p.first_name, p.last_name, p.email, b.date, b.time_from, b.time_until, b.total, b.original_total, b.discount_amount, b.payment, b.diference, b.payment_method, b.annulled, f.name AS field_name, s.name AS service_name')->join('player_accounts p', 'p.id = l.player_id')->join('bookings b', 'b.id = l.booking_id')->join('fields f', 'f.id = b.id_field', 'left')->join('services s', 's.code = f.service_type', 'left')->orderBy('b.date', 'DESC')->orderBy('b.time_from', 'DESC')->get(150)->getResultArray();
    }

    private function matches(): array
    {
        $rows = $this->db()->table('player_matches m')->select("m.*, b.date, b.time_from, b.time_until, b.annulled, f.name AS field_name, s.name AS service_name, p.first_name, p.last_name, (SELECT COUNT(*) FROM player_match_requests r WHERE r.match_id = m.id AND r.status = 'pending') AS pending_requests")->join('bookings b', 'b.id = m.booking_id')->join('fields f', 'f.id = b.id_field', 'left')->join('services s', 's.code = f.service_type', 'left')->join('player_accounts p', 'p.id = m.organizer_player_id', 'left')->orderBy('b.date', 'DESC')->get(150)->getResultArray();
        foreach ($rows as &$row) {
            $row['pending_rows'] = $this->db()->table('player_match_requests r')->select('r.id, a.first_name, a.last_name, a.email')->join('player_accounts a', 'a.id = r.player_id')->where('r.match_id', $row['id'])->where('r.status', 'pending')->get()->getResultArray();
            $row['participant_rows'] = $this->db()->table('player_match_participants p')->select('p.id, p.role, p.player_id, a.first_name, a.last_name, a.email')->join('player_accounts a', 'a.id = p.player_id')->where('p.match_id', $row['id'])->where('p.status', 'confirmed')->get()->getResultArray();
        }
        return $rows;
    }

    private function services(): array { return $this->db()->tableExists('services') ? $this->db()->table('services')->select('code, name')->where('active', 1)->orderBy('display_order', 'ASC')->get()->getResultArray() : []; }
    private function portalServices(): array
    {
        $services = (new ServicesModel())->getServices();
        if (! $this->db()->tableExists('player_service_match_settings')) return $services;
        foreach ($services as &$service) {
            $service['portal_settings'] = $this->db()->table('player_service_match_settings')->where('service_id', (int) $service['id'])->get()->getRowArray() ?: [];
        }
        return $services;
    }
    private function portalServicesCatalog(): array
    {
        $services = $this->portalServices();
        $prices = new ServicePricesModel();
        foreach ($services as &$service) {
            $service['current_price'] = $this->db()->tableExists('service_prices') ? $prices->getActiveForService((int) $service['id']) : null;
        }
        return $services;
    }
    public function serviceSettings(int $serviceId)
    {
        if ($redirect = $this->guardSuperadmin()) return $redirect;
        $service = (new ServicesModel())->find($serviceId);
        if (! $service) return redirect()->to(base_url('admin/portal?section=servicios'))->with('msg', ['type' => 'danger', 'body' => 'No encontramos el servicio seleccionado.']);
        $service['portal_settings'] = $this->db()->tableExists('player_service_match_settings') ? ($this->db()->table('player_service_match_settings')->where('service_id', $serviceId)->get()->getRowArray() ?: []) : [];
        // La tabla legacy `fields` relaciona los recursos mediante service_type.
        // No consultar service_id porque no existe en instalaciones legacy existentes.
        $fields = (new FieldsModel())->where('service_type', $service['code'])->orderBy('id', 'ASC')->findAll();
        return view('player_portal_admin/service_settings', ['service' => $service, 'settings' => $this->matchSettings(), 'fields' => $fields, 'backgroundServices' => $this->portalServicesCatalog(), 'nocturnalTime' => (new TimeModel())->first()['nocturnal_time'] ?? null]);
    }

    public function fieldSettings(int $serviceId, int $fieldId)
    {
        if ($redirect = $this->guardSuperadmin()) return $redirect;
        $service = (new ServicesModel())->find($serviceId);
        $field = (new FieldsModel())->find($fieldId);
        if (! $service || ! $field || ((int) ($field['service_id'] ?? 0) !== $serviceId && (string) ($field['service_type'] ?? '') !== (string) $service['code'])) return $this->back('servicios', 'danger', 'La cancha seleccionada no pertenece al servicio.');
        $timeRow = (new TimeModel())->first() ?: [];
        $serviceFields = (new FieldsModel())->where('service_type', $service['code'])->orderBy('id', 'ASC')->findAll();
        return view('player_portal_admin/field_settings', [
            'service' => $service,
            'field' => $field,
            'serviceFields' => $serviceFields,
            'nocturnalTime' => $timeRow['nocturnal_time'] ?? null,
        ]);
    }

    public function saveFieldSettings(int $serviceId, int $fieldId)
    {
        if ($redirect = $this->guardSuperadmin()) return $redirect;
        $services = new ServicesModel(); $fields = new FieldsModel();
        $service = $services->find($serviceId); $field = $fields->find($fieldId);
        if (! $service || ! $field || ((int) ($field['service_id'] ?? 0) !== $serviceId && (string) ($field['service_type'] ?? '') !== (string) $service['code'])) return $this->back('servicios', 'danger', 'La cancha seleccionada no pertenece al servicio.');
        $name = trim((string) $this->request->getPost('name'));
        $base = $this->parsePortalFieldPrice($this->request->getPost('value'));
        $night = $this->parsePortalFieldPrice($this->request->getPost('ilumination_value'));
        if ($name === '' || $base === null || $night === null || $base < 0 || $night < 0) return redirect()->to(base_url('admin/portal/servicios/' . $serviceId . '/canchas/' . $fieldId))->with('msg', ['type' => 'danger', 'body' => 'Completá el nombre y los precios con importes válidos. Usá números positivos, por ejemplo 30.000,00.']);
        $data = ['name' => $name, 'sizes' => trim((string) $this->request->getPost('sizes')), 'floor_type' => trim((string) $this->request->getPost('floor_type')), 'elements_rent' => trim((string) $this->request->getPost('elements_rent')), 'roofed' => $this->request->getPost('roofed') ? 1 : 0, 'disabled' => $this->request->getPost('disabled') ? 1 : 0, 'value' => round($base, 2), 'ilumination_value' => round($night, 2)];
        if (! $fields->update($fieldId, $data)) return redirect()->to(base_url('admin/portal/servicios/' . $serviceId . '/canchas/' . $fieldId))->with('msg', ['type' => 'danger', 'body' => 'No se pudieron guardar los datos de la cancha.']);
        $this->logAction('editar_cancha_servicio', 'field', $fieldId, $field, $data);
        return redirect()->to(base_url('admin/portal/servicios/' . $serviceId))->with('msg', ['type' => 'success', 'body' => 'Cancha actualizada correctamente.']);
    }

    public function saveServicePriceSlots(int $serviceId)
    {
        if ($redirect = $this->guardSuperadmin()) return $redirect;
        $service = (new ServicesModel())->find($serviceId);
        $model = new ServicePriceWindowsModel();
        if (! $service || ! $this->db()->tableExists('service_price_windows')) return redirect()->to(base_url('admin/portal/servicios/' . $serviceId))->with('msg', ['type' => 'danger', 'body' => 'No se puede guardar la grilla de precios. Aplicá las migraciones pendientes.']);
        $enabled = $this->request->getPost('price_schedule_enabled') ? 1 : 0;
        $weekdays = implode('', array_values(array_intersect(str_split('1234567'), str_split((string) $this->request->getPost('weekdays')))));
        if ($weekdays === '') return redirect()->to(base_url('admin/portal/servicios/' . $serviceId))->with('msg', ['type' => 'danger', 'body' => 'Seleccioná al menos un día para los precios por horario.']);
        $this->db()->transStart();
        if (! $enabled) {
            $this->db()->table('service_price_windows')->where('service_id', $serviceId)->update(['active' => 0, 'updated_at' => date('Y-m-d H:i:s')]);
        } else {
            $this->db()->table('service_price_windows')->where('service_id', $serviceId)->update(['active' => 0, 'updated_at' => date('Y-m-d H:i:s')]);
            $starts = (array) $this->request->getPost('slot_start');
            $ends = (array) $this->request->getPost('slot_end');
            $prices = (array) $this->request->getPost('slot_price');
            foreach ($starts as $key => $start) {
                $price = parse_price_ar($prices[$key] ?? null);
                if ($price <= 0) continue;
                $end = substr(trim((string) ($ends[$key] ?? '')), 0, 5);
                $start = substr(trim((string) $start), 0, 5);
                if (! preg_match('/^([01]\\d|2[0-3]):[0-5]\\d$/', $start) || ! preg_match('/^([01]\\d|2[0-3]):[0-5]\\d$/', $end)) {
                    $this->db()->transRollback();
                    return redirect()->to(base_url('admin/portal/servicios/' . $serviceId))->with('msg', ['type' => 'danger', 'body' => 'La grilla contiene un horario inválido.']);
                }
                $model->insert(['service_id' => $serviceId, 'weekdays' => $weekdays, 'start_time' => $start . ':00', 'end_time' => $end . ':00', 'price' => round($price, 2), 'active' => 1, 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')]);
            }
        }
        $this->db()->transComplete();
        if (! $this->db()->transStatus()) return redirect()->to(base_url('admin/portal/servicios/' . $serviceId))->with('msg', ['type' => 'danger', 'body' => 'No se pudieron guardar los precios por horario.']);
        $this->logAction('guardar_grilla_precios_servicio', 'service', $serviceId, null, ['enabled' => $enabled, 'weekdays' => $weekdays]);
        return redirect()->to(base_url('admin/portal/servicios/' . $serviceId))->with('msg', ['type' => 'success', 'body' => $enabled ? 'Grilla de precios guardada.' : 'Precios por horario desactivados. Se usa el precio general.']);
    }

    public function saveServicePriceWindow(int $serviceId)
    {
        if ($redirect = $this->guardSuperadmin()) return $redirect;
        if (! $this->db()->tableExists('service_price_windows') || ! (new ServicesModel())->find($serviceId)) return redirect()->to(base_url('admin/portal/servicios/' . $serviceId))->with('msg', ['type' => 'danger', 'body' => 'No se puede guardar la franja seleccionada.']);
        $start = substr(trim((string) $this->request->getPost('start_time')), 0, 5);
        $end = substr(trim((string) $this->request->getPost('end_time')), 0, 5);
        $price = (float) $this->request->getPost('price');
        $weekdays = implode('', array_values(array_intersect(str_split('1234567'), str_split((string) $this->request->getPost('weekdays')))));
        $toMinutes = static function (string $value): int { [$hours, $minutes] = array_map('intval', explode(':', $value)); return ($hours * 60) + $minutes; };
        if (! preg_match('/^\d{2}:\d{2}$/', $start) || ! preg_match('/^\d{2}:\d{2}$/', $end) || $toMinutes($start) >= $toMinutes($end) || $price <= 0 || $weekdays === '') return redirect()->to(base_url('admin/portal/servicios/' . $serviceId))->with('msg', ['type' => 'danger', 'body' => 'La franja debe tener horario válido, fin posterior al inicio y un precio mayor a cero.']);
        $id = (int) $this->request->getPost('window_id');
        foreach ((new ServicePriceWindowsModel())->forService($serviceId) as $window) {
            if ((int) $window['id'] === $id) continue;
            $daysOverlap = strpbrk($weekdays, (string) ($window['weekdays'] ?? '')) !== false;
            $overlap = max($toMinutes($start), $toMinutes(substr($window['start_time'], 0, 5))) < min($toMinutes($end), $toMinutes(substr($window['end_time'], 0, 5)));
            if ($daysOverlap && $overlap) return redirect()->to(base_url('admin/portal/servicios/' . $serviceId))->with('msg', ['type' => 'danger', 'body' => 'La franja se superpone con otra del mismo servicio y día.']);
        }
        $data = ['service_id' => $serviceId, 'weekdays' => $weekdays, 'start_time' => $start . ':00', 'end_time' => $end . ':00', 'price' => round($price, 2), 'active' => 1, 'updated_at' => date('Y-m-d H:i:s')];
        $model = new ServicePriceWindowsModel();
        if ($id > 0 && $model->find($id)) $model->update($id, $data); else $model->insert(array_merge($data, ['created_at' => date('Y-m-d H:i:s')]));
        $this->logAction('guardar_franja_precio_servicio', 'service_price_window', $id ?: $model->getInsertID(), null, $data);
        return redirect()->to(base_url('admin/portal/servicios/' . $serviceId))->with('msg', ['type' => 'success', 'body' => 'Franja horaria guardada.']);
    }

    public function deleteServicePriceWindow(int $serviceId, int $windowId)
    {
        if ($redirect = $this->guardSuperadmin()) return $redirect;
        if ($this->db()->tableExists('service_price_windows')) (new ServicePriceWindowsModel())->where('id', $windowId)->where('service_id', $serviceId)->update($windowId, ['active' => 0, 'updated_at' => date('Y-m-d H:i:s')]);
        return redirect()->to(base_url('admin/portal/servicios/' . $serviceId))->with('msg', ['type' => 'success', 'body' => 'Franja horaria eliminada.']);
    }
    private function discounts(): array { return ! $this->db()->tableExists('player_account_discounts') ? [] : $this->db()->table('player_account_discounts d')->select('d.*, p.first_name, p.last_name, p.email')->join('player_accounts p', 'p.id = d.player_id')->orderBy('d.active', 'DESC')->orderBy('d.created_at', 'DESC')->get(150)->getResultArray(); }
    private function matchSettings(): array { return $this->db()->table('player_match_settings')->where('id', 1)->get()->getRowArray() ?: ['allow_auto_join' => 1, 'allow_approval' => 1, 'min_players' => 2, 'max_players' => 30, 'allow_leave' => 1, 'leave_hours_limit' => null, 'allow_free_join' => 1, 'allow_paid_join' => 0, 'organizer_choose_payment' => 0, 'payment_mode_organizer_deposit' => 1, 'payment_mode_split' => 0, 'payment_mode_venue' => 0, 'participant_payment_expiry_hours' => 24, 'favorite_notifications_enabled' => 1, 'search_players_in_reservation' => 1]; }
    private function updateMatchSettings(array $data): bool
    {
        if (!$this->db()->tableExists('player_match_settings')) {
            log_message('error', 'No existe la tabla player_match_settings al guardar configuración del portal.');
            return false;
        }
        if (!$this->db()->table('player_match_settings')->where('id', 1)->get()->getRowArray()) {
            $this->db()->table('player_match_settings')->insert(['id' => 1, 'updated_at' => date('Y-m-d H:i:s')]);
        }
        $available = [];
        foreach ($data as $field => $value) {
            if ($this->db()->fieldExists($field, 'player_match_settings')) $available[$field] = $value;
        }
        if (count($available) !== count($data)) {
            log_message('error', 'Faltan columnas de configuración del portal: ' . implode(', ', array_diff(array_keys($data), array_keys($available))));
            return false;
        }
        return !empty($available) && (bool) $this->db()->table('player_match_settings')->where('id', 1)->update($available);
    }

    public function savePortalSettings()
    {
        if ($redirect = $this->guardSuperadmin()) return $redirect;
        $submittedNocturnalTime = substr(trim((string) $this->request->getPost('nocturnal_time')), 0, 5);
        if ($submittedNocturnalTime !== '' && ! preg_match('/^(?:[01]\\d|2[0-3]):00$/', $submittedNocturnalTime)) return $this->back('configuracion', 'danger', 'El horario nocturno no es válido. Usá una hora exacta, por ejemplo 19:00.');
        (new PlayerPortalSettings())->setTestMode((bool) $this->request->getPost('test_mode'));
        $min = max(2, (int) $this->request->getPost('min_players')); $max = max($min, (int) $this->request->getPost('max_players'));
        $data = ['allow_auto_join' => $this->request->getPost('allow_auto_join') ? 1 : 0, 'allow_approval' => $this->request->getPost('allow_approval') ? 1 : 0, 'min_players' => $min, 'max_players' => $max, 'allow_leave' => $this->request->getPost('allow_leave') ? 1 : 0, 'leave_hours_limit' => $this->request->getPost('leave_hours_limit') === '' ? null : max(0, (int) $this->request->getPost('leave_hours_limit')), 'allow_free_join' => $this->request->getPost('allow_free_join') ? 1 : 0, 'allow_paid_join' => $this->request->getPost('allow_paid_join') ? 1 : 0, 'organizer_choose_payment' => $this->request->getPost('organizer_choose_payment') ? 1 : 0, 'payment_mode_organizer_deposit' => $this->request->getPost('payment_mode_organizer_deposit') ? 1 : 0, 'payment_mode_split' => $this->request->getPost('payment_mode_split') ? 1 : 0, 'payment_mode_venue' => $this->request->getPost('payment_mode_venue') ? 1 : 0, 'participant_payment_expiry_hours' => max(1, (int) ($this->request->getPost('participant_payment_expiry_hours') ?: 24)), 'favorite_notifications_enabled' => $this->request->getPost('favorite_notifications_enabled') ? 1 : 0, 'search_players_in_reservation' => $this->request->getPost('search_players_in_reservation') ? 1 : 0, 'updated_at' => date('Y-m-d H:i:s')];
        if (!$this->updateMatchSettings($data)) return $this->back('configuracion', 'danger', 'No se pudo guardar la configuración. Verificá que las migraciones del portal estén aplicadas.');
        $nocturnalTime = substr(trim((string) $this->request->getPost('nocturnal_time')), 0, 5);
        if ($nocturnalTime !== '' && ! preg_match('/^(?:[01]\\d|2[0-3]):00$/', $nocturnalTime)) return $this->back('configuracion', 'danger', 'El horario nocturno debe indicar una hora válida, por ejemplo 19:00.');
        if ($nocturnalTime !== '') {
            $timeModel = new TimeModel(); $timeRow = $timeModel->first();
            if ($timeRow) $timeModel->update((int) $timeRow['id'], ['nocturnal_time' => substr($nocturnalTime, 0, 2)]);
            else $timeModel->insert(['from' => '07', 'until' => '23', 'from_cut' => '07', 'until_cut' => '23', 'nocturnal_time' => substr($nocturnalTime, 0, 2), 'is_sunday' => 0]);
        }
        return $this->back('configuracion', 'success', 'Configuración del portal actualizada.');
    }

    public function paymentSettings()
    {
        if ($redirect = $this->guardSuperadmin()) return $redirect;
        return view('player_portal_admin/payment_settings', ['settings' => $this->matchSettings(), 'testMode' => (new PlayerPortalSettings())->isTestMode()]);
    }

    public function savePaymentSettings()
    {
        if ($redirect = $this->guardSuperadmin()) return $redirect;
        $data = [
            'payment_mode_organizer_deposit' => $this->request->getPost('payment_mode_organizer_deposit') ? 1 : 0,
            'payment_mode_split' => $this->request->getPost('payment_mode_split') ? 1 : 0,
            'payment_mode_venue' => $this->request->getPost('payment_mode_venue') ? 1 : 0,
            'participant_payment_expiry_hours' => max(1, (int) ($this->request->getPost('participant_payment_expiry_hours') ?: 24)),
            'updated_at' => date('Y-m-d H:i:s'),
        ];
        if (!$this->updateMatchSettings($data)) return redirect()->to(base_url('admin/portal/configuracion/pagos'))->with('msg', ['type' => 'danger', 'body' => 'No se pudo guardar la configuración. Verificá que la migración del portal esté aplicada.']);
        return redirect()->to(base_url('admin/portal/configuracion/pagos'))->with('msg', ['type' => 'success', 'body' => 'Configuración de pagos actualizada.']);
    }

    public function saveServiceSettings(int $serviceId)
    {
        if ($redirect = $this->guardSuperadmin()) return $redirect;
        if ($serviceId <= 0 || ! $this->db()->tableExists('player_service_match_settings')) return $this->back('configuracion', 'danger', 'La configuración por servicio todavía no está disponible. Aplicá las migraciones del portal.');
        $servicesModel = new ServicesModel();
        $service = $servicesModel->find($serviceId);
        if (! $service) return $this->back('servicios', 'danger', 'No encontramos el servicio seleccionado.');

        $timeToMinutes = static function (string $value): ?int {
            if (! preg_match('/^(?:[01]\\d|2[0-3]):[0-5]\\d$/', $value)) return null;
            [$hours, $minutes] = array_map('intval', explode(':', $value));
            return ($hours * 60) + $minutes;
        };
        $opening = substr(trim((string) $this->request->getPost('opening_time')), 0, 5);
        $closing = substr(trim((string) $this->request->getPost('closing_time')), 0, 5);
        $openingMinutes = $timeToMinutes($opening);
        $closingMinutes = $timeToMinutes($closing);
        $durationHoursInput = $this->request->getPost('duration_hours');
        $durationPartInput = $this->request->getPost('duration_minutes_part');
        if ($durationHoursInput !== null || $durationPartInput !== null) {
            $durationHours = max(0, (int) $durationHoursInput);
            $durationPart = (int) $durationPartInput;
            $durationMinutes = ($durationHours * 60) + $durationPart;
        } else {
            $durationMinutes = (int) $this->request->getPost('duration_minutes');
        }
        if ($openingMinutes === null || $closingMinutes === null || $closingMinutes <= $openingMinutes || $durationMinutes < 15 || $durationMinutes % 15 !== 0 || ($durationPartInput !== null && ($durationPart < 0 || $durationPart >= 60)) || $durationMinutes > ($closingMinutes - $openingMinutes)) {
            return redirect()->to(base_url('admin/portal/servicios/' . $serviceId))->with('msg', ['type' => 'danger', 'body' => 'Revisá apertura, cierre y duración: deben formar turnos válidos dentro del horario del servicio.']);
        }

        $maxPlayers = max(2, (int) $this->request->getPost('max_players'));
        $allowSubstitutes = $this->request->getPost('allow_substitutes') ? 1 : 0;
        $maxStarters = min($maxPlayers, max(1, (int) $this->request->getPost('max_starters')));
        $maxSubstitutes = $allowSubstitutes ? max(0, min($maxPlayers - $maxStarters, (int) $this->request->getPost('max_substitutes'))) : 0;
        $oldPortal = $this->db()->table('player_service_match_settings')->where('service_id', $serviceId)->get()->getRowArray() ?: [];
        $serviceData = [
            'name' => trim((string) $this->request->getPost('name')),
            'color' => $this->normalizeServiceColor($this->request->getPost('color') ?: ($service['color'] ?? null)),
            'icon' => trim((string) $this->request->getPost('icon')),
            'opening_time' => $opening . ':00',
            'closing_time' => $closing . ':00',
            'duration_minutes' => $durationMinutes,
            'slot_interval_minutes' => $durationMinutes,
            'minimum_duration_minutes' => $durationMinutes,
            'booking_interval_minutes' => $durationMinutes,
            'active' => $this->request->getPost('active') ? 1 : 0,
            'online_available' => $this->request->getPost('online_available') ? 1 : 0,
            'updated_at' => date('Y-m-d H:i:s'),
        ];
        if ($this->db()->fieldExists('is_sport', 'services')) $serviceData['is_sport'] = $this->request->getPost('is_sport') ? 1 : 0;
        if ($serviceData['name'] === '') return redirect()->to(base_url('admin/portal/servicios/' . $serviceId))->with('msg', ['type' => 'danger', 'body' => 'El nombre del servicio es obligatorio.']);

        $uploadedImage = $this->request->getFile('service_image');
        $newImagePath = null;
        if ($uploadedImage && $uploadedImage->getError() !== UPLOAD_ERR_NO_FILE) {
            $validMimeTypes = ['image/jpeg', 'image/png', 'image/webp'];
            if (! $uploadedImage->isValid() || ! in_array($uploadedImage->getMimeType(), $validMimeTypes, true) || $uploadedImage->getSizeByUnit('mb') > 2) {
                return redirect()->to(base_url('admin/portal/servicios/' . $serviceId))->with('msg', ['type' => 'danger', 'body' => 'La imagen debe ser JPG, PNG o WebP y no superar los 2 MB.']);
            }
            $directory = FCPATH . 'assets/uploads/services';
            if (! is_dir($directory)) mkdir($directory, 0755, true);
            $filename = 'service_' . $serviceId . '_' . bin2hex(random_bytes(8)) . '.' . $uploadedImage->getExtension();
            if (! $uploadedImage->move($directory, $filename)) return redirect()->to(base_url('admin/portal/servicios/' . $serviceId))->with('msg', ['type' => 'danger', 'body' => 'No se pudo guardar la imagen del servicio.']);
            $newImagePath = 'assets/uploads/services/' . $filename;
            $serviceData['image_path'] = $newImagePath;
        }

        $portalData = [
            'portal_enabled' => $this->request->getPost('portal_enabled') ? 1 : 0,
            'min_players' => min($maxPlayers, max(2, (int) $this->request->getPost('min_players'))),
            'max_players' => $maxPlayers,
            'default_starters' => min($maxStarters, max(1, (int) $this->request->getPost('default_starters'))),
            'max_starters' => $maxStarters,
            'default_substitutes' => min($maxSubstitutes, max(0, (int) $this->request->getPost('default_substitutes'))),
            'max_substitutes' => $maxSubstitutes,
            'allow_substitutes' => $allowSubstitutes,
            'count_organizer' => $this->request->getPost('count_organizer') ? 1 : 0,
            'allow_auto_join' => (int) ($oldPortal['allow_auto_join'] ?? 1),
            'allow_approval' => (int) ($oldPortal['allow_approval'] ?? 1),
            'allow_leave' => (int) ($oldPortal['allow_leave'] ?? 1),
            'leave_hours_limit' => $oldPortal['leave_hours_limit'] ?? null,
            'updated_at' => date('Y-m-d H:i:s'),
        ];
        $db = $this->db();
        $db->transStart();
        $servicesModel->update($serviceId, $serviceData);
        if ($oldPortal) $db->table('player_service_match_settings')->where('service_id', $serviceId)->update($portalData);
        else $db->table('player_service_match_settings')->insert(array_merge(['service_id' => $serviceId, 'created_at' => date('Y-m-d H:i:s')], $portalData));
        $db->transComplete();
        if (! $db->transStatus()) return redirect()->to(base_url('admin/portal/servicios/' . $serviceId))->with('msg', ['type' => 'danger', 'body' => 'No se pudieron guardar los datos del servicio.']);
        if ($newImagePath && ! empty($service['image_path']) && strpos((string) $service['image_path'], 'assets/uploads/services/') === 0) {
            $oldPath = FCPATH . ltrim($service['image_path'], '/');
            if (is_file($oldPath)) @unlink($oldPath);
        }
        $this->logAction('editar_servicio_portal', 'service', $serviceId, $service, $serviceData);
        return redirect()->to(base_url('admin/portal/servicios/' . $serviceId))->with('msg', ['type' => 'success', 'body' => 'Datos del servicio actualizados correctamente.']);
    }

    public function participantPayments(int $matchId)
    {
        if ($redirect = $this->guardSuperadmin()) return $redirect;
        $match = (new PlayerMatchesModel())->find($matchId);
        if (!$match) return $this->back('partidos', 'danger', 'No encontramos el partido.');
        $participants = $this->db()->table('player_match_participants p')->select('p.*, a.first_name, a.last_name, a.email')->join('player_accounts a', 'a.id = p.player_id')->where('p.match_id', $matchId)->whereIn('p.status', ['confirmed', 'payment_pending'])->get()->getResultArray();
        return view('player_portal_admin/participant_payments', ['match' => $match, 'participants' => $participants]);
    }

    public function recordParticipantPayment(int $matchId, int $participantId)
    {
        if ($redirect = $this->guardSuperadmin()) return $redirect;
        $participant = (new PlayerMatchParticipantsModel())->find($participantId);
        $match = (new PlayerMatchesModel())->find($matchId);
        if (!$participant || !$match || (int) $participant['match_id'] !== $matchId) return $this->back('partidos', 'danger', 'El participante no es válido.');
        $status = (string) $this->request->getPost('status');
        if (!in_array($status, ['paid', 'pending', 'not_required'], true)) return $this->back('partidos', 'danger', 'Estado de pago no válido.');
        $amount = $status === 'paid' ? min(max(0, (float) $this->request->getPost('amount')), (float) $participant['amount_due']) : 0;
        if ($status === 'paid' && $amount <= 0) return $this->back('partidos', 'danger', 'Indicá un importe mayor a cero para registrar un pago.');
        $now = date('Y-m-d H:i:s');
        (new PlayerMatchParticipantsModel())->update($participantId, ['amount_paid' => $amount, 'payment_status' => $status, 'payment_confirmed_at' => $status === 'paid' ? $now : null, 'updated_at' => $now]);
        if ($status === 'paid') (new \App\Models\PlayerMatchParticipantPaymentsModel())->insert(['match_id' => $matchId, 'participant_id' => $participantId, 'player_id' => $participant['player_id'], 'booking_id' => $match['booking_id'], 'amount' => $amount, 'status' => 'approved', 'provider' => 'venue', 'external_reference' => 'venue:' . $matchId . ':' . $participantId . ':' . uniqid(), 'received_by' => $this->adminId(), 'received_at' => $now, 'confirmed_at' => $now, 'created_at' => $now, 'updated_at' => $now]);
        $this->logAction('registrar_pago_participante', 'player_match_participant', $participantId, $participant, ['status' => $status, 'amount' => $amount, 'provider' => 'venue']);
        return redirect()->to(base_url('admin/portal/partidos/' . $matchId . '/pagos'))->with('msg', ['type' => 'success', 'body' => 'Estado de pago actualizado.']);
    }

    public function retryMatchNotifications()
    {
        if ($redirect = $this->guardSuperadmin()) return $redirect;
        $sent = (new \App\Libraries\PlayerMatchNotificationService())->processPending(50);
        return $this->back('configuracion', 'success', $sent . ' avisos de partidos procesados.');
    }

    public function save()
    {
        if ($redirect = $this->guardSuperadmin()) return $redirect; $enabled = (bool) $this->request->getPost('test_mode'); (new PlayerPortalSettings())->setTestMode($enabled); return $this->back('configuracion', 'success', $enabled ? 'Modo prueba activado para el portal.' : 'Modo prueba desactivado. Las reservas volverán a utilizar Mercado Pago.');
    }

    public function updatePlayer(int $id)
    {
        if ($redirect = $this->guardSuperadmin()) return $redirect; $model = new PlayerAccountsModel(); $player = $model->find($id); if (! $player) return $this->back('jugadores', 'danger', 'No encontramos la cuenta seleccionada.');
        $data = ['first_name' => trim((string) $this->request->getPost('first_name')), 'last_name' => trim((string) $this->request->getPost('last_name')), 'phone' => trim((string) $this->request->getPost('phone')), 'locality' => trim((string) $this->request->getPost('locality')), 'updated_at' => date('Y-m-d H:i:s')]; if ($data['first_name'] === '' || $data['last_name'] === '') return $this->back('jugadores', 'danger', 'El nombre y el apellido son obligatorios.'); $model->update($id, $data); $this->logAction('editar_jugador', 'player_account', $id, $player, $data); return $this->back('jugadores', 'success', 'Datos del jugador actualizados.');
    }

    public function togglePlayer(int $id)
    {
        if ($redirect = $this->guardSuperadmin()) return $redirect; $model = new PlayerAccountsModel(); $player = $model->find($id); if (! $player) return $this->back('jugadores', 'danger', 'No encontramos la cuenta seleccionada.'); $status = ($player['status'] ?? 'active') === 'blocked' ? 'active' : 'blocked'; $model->update($id, ['status' => $status, 'session_version' => ((int) ($player['session_version'] ?? 0)) + 1, 'updated_at' => date('Y-m-d H:i:s')]); $this->logAction($status === 'blocked' ? 'bloquear_jugador' : 'desbloquear_jugador', 'player_account', $id, $player, ['status' => $status]); return $this->back('jugadores', 'success', $status === 'blocked' ? 'Cuenta bloqueada.' : 'Cuenta desbloqueada.');
    }

    public function verifyPlayerEmail(int $id)
    {
        if ($redirect = $this->guardSuperadmin()) return $redirect;

        $model = new PlayerAccountsModel();
        $player = $model->find($id);
        if (! $player) return $this->back('jugadores', 'danger', 'No encontramos la cuenta seleccionada.');
        if (! empty($player['email_verified_at'])) return $this->back('jugadores', 'info', 'El correo de este jugador ya estaba verificado.');

        $now = date('Y-m-d H:i:s');
        $data = ['email_verified_at' => $now, 'verification_token_hash' => null, 'verification_expires_at' => null, 'verification_sent_at' => null, 'updated_at' => $now];
        if (! $model->update($id, $data)) return $this->back('jugadores', 'danger', 'No pudimos verificar el correo. Intentá nuevamente.');

        $this->logAction('verificar_email_jugador', 'player_account', $id, [
            'email_verified_at' => $player['email_verified_at'] ?? null,
            'verification_token_hash' => ! empty($player['verification_token_hash']) ? '[presente]' : null,
        ], ['email_verified_at' => $now, 'verification_token_hash' => null, 'verification_expires_at' => null, 'verification_sent_at' => null]);

        return $this->back('jugadores', 'success', 'Correo verificado. El jugador ya puede ingresar al portal.');
    }

    public function saveDiscount()
    {
        if ($redirect = $this->guardSuperadmin()) return $redirect; if (! $this->db()->tableExists('player_account_discounts')) return $this->back('descuentos', 'danger', 'Falta ejecutar la migración del módulo administrativo.');
        $playerId = (int) $this->request->getPost('player_id'); $type = (string) $this->request->getPost('discount_type'); $value = (float) $this->request->getPost('value'); if (! (new PlayerAccountsModel())->find($playerId) || ! in_array($type, ['percentage', 'fixed'], true) || $value <= 0 || ($type === 'percentage' && $value > 100)) return $this->back('descuentos', 'danger', 'Revisá el jugador y los valores del descuento.');
        $codes = array_values(array_filter(array_map('trim', explode(',', (string) $this->request->getPost('service_codes'))))); $data = ['player_id' => $playerId, 'discount_type' => $type, 'value' => $value, 'starts_at' => $this->request->getPost('starts_at') ?: null, 'ends_at' => $this->request->getPost('ends_at') ?: null, 'service_codes' => $codes ? json_encode($codes, JSON_UNESCAPED_UNICODE) : null, 'description' => trim((string) $this->request->getPost('description')) ?: null, 'active' => $this->request->getPost('active') ? 1 : 0, 'created_by' => $this->adminId(), 'updated_by' => $this->adminId(), 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')]; $model = new PlayerAccountDiscountsModel(); $id = $model->insert($data); $this->logAction('crear_descuento_jugador', 'player_account_discount', $id, null, $data); return $this->back('descuentos', 'success', 'Descuento asignado correctamente.');
    }

    public function toggleDiscount(int $id)
    {
        if ($redirect = $this->guardSuperadmin()) return $redirect; $model = new PlayerAccountDiscountsModel(); $row = $model->find($id); if (! $row) return $this->back('descuentos', 'danger', 'No encontramos el descuento.'); $active = (int) ! (int) $row['active']; $model->update($id, ['active' => $active, 'updated_by' => $this->adminId(), 'updated_at' => date('Y-m-d H:i:s')]); $this->logAction('cambiar_descuento_jugador', 'player_account_discount', $id, $row, ['active' => $active]); return $this->back('descuentos', 'success', $active ? 'Descuento activado.' : 'Descuento desactivado.');
    }

    public function matchDecision(int $requestId, string $decision)
    {
        if ($redirect = $this->guardSuperadmin()) return $redirect; $requests = new PlayerMatchRequestsModel(); $request = $requests->find($requestId); if (! $request || $request['status'] !== 'pending') return $this->back('partidos', 'danger', 'La solicitud ya fue procesada.'); $match = (new PlayerMatchesModel())->find((int) $request['match_id']); if (! $match) return $this->back('partidos', 'danger', 'No encontramos el partido.'); $db = $this->db(); $now = date('Y-m-d H:i:s'); $db->transStart();
        if ($decision === 'accept') { $requiresPayment = ($match['payment_mode'] ?? '') === 'split'; $counter = $requiresPayment ? 'reserved_players' : 'confirmed_players'; $updated = $db->table('player_matches')->where('id', $match['id'])->where('status', 'open')->where('confirmed_players + reserved_players <', (int) $match['max_players'])->set($counter, $counter . ' + 1', false)->set('updated_at', $now)->update(); if (! $updated) { $db->transRollback(); return $this->back('partidos', 'danger', 'No quedan lugares disponibles.'); } (new PlayerMatchParticipantsModel())->insert(['match_id' => $match['id'], 'player_id' => $request['player_id'], 'role' => 'participant', 'status' => $requiresPayment ? 'payment_pending' : 'confirmed', 'created_at' => $now, 'updated_at' => $now]); $status = 'accepted'; } elseif ($decision === 'reject') { $status = 'rejected'; } else { $db->transRollback(); return $this->back('partidos', 'danger', 'Acción no válida.'); }
        $requests->update($requestId, ['status' => $status, 'updated_at' => $now]); $db->transComplete(); if ($db->transStatus() === false) return $this->back('partidos', 'danger', 'No pudimos actualizar la solicitud.'); (new PlayerNotificationService())->create((int) $request['player_id'], 'admin_partido', $status === 'accepted' ? 'Solicitud aceptada' : 'Solicitud no aprobada', $status === 'accepted' ? 'El administrador aceptó tu incorporación al partido.' : 'El administrador rechazó tu solicitud para el partido.', 'app/partidos/detalle/' . (int) $match['id']); $this->logAction($status === 'accepted' ? 'aceptar_solicitud_partido' : 'rechazar_solicitud_partido', 'player_match_request', $requestId, $request, ['status' => $status]); return $this->back('partidos', 'success', $status === 'accepted' ? 'Jugador incorporado al partido.' : 'Solicitud rechazada.');
    }

    public function addParticipant(int $matchId)
    {
        if ($redirect = $this->guardSuperadmin()) return $redirect;
        $email = strtolower(trim((string) $this->request->getPost('player_email'))); $player = (new PlayerAccountsModel())->where('email', $email)->first(); $match = (new PlayerMatchesModel())->find($matchId);
        if (! $player || ! $match) return $this->back('partidos', 'danger', 'No encontramos el jugador o el partido.');
        if ((int) $match['confirmed_players'] >= (int) $match['max_players']) return $this->back('partidos', 'danger', 'El partido ya está completo.');
        $participants = new PlayerMatchParticipantsModel(); if ($participants->where('match_id', $matchId)->where('player_id', $player['id'])->where('status', 'confirmed')->first()) return $this->back('partidos', 'danger', 'El jugador ya participa del partido.');
        $db = $this->db(); $db->transStart(); $requiresPayment = ($match['payment_mode'] ?? '') === 'split'; $status = $requiresPayment ? 'payment_pending' : 'confirmed'; $participants->insert(['match_id' => $matchId, 'player_id' => $player['id'], 'role' => 'participant', 'status' => $status, 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')]); $counter = $requiresPayment ? 'reserved_players' : 'confirmed_players'; $db->table('player_matches')->where('id', $matchId)->where('confirmed_players + reserved_players <', (int) $match['max_players'])->set($counter, $counter . ' + 1', false)->set('updated_at', date('Y-m-d H:i:s'))->update(); $db->transComplete();
        if ($db->transStatus() === false) return $this->back('partidos', 'danger', 'No pudimos agregar al jugador.'); (new PlayerNotificationService())->create((int) $player['id'], 'admin_partido', 'Te incorporaron a un partido', 'Un administrador te incorporó a un partido abierto.', 'app/partidos/detalle/' . $matchId); $this->logAction('agregar_participante', 'player_match', $matchId, null, ['player_id' => $player['id']]); return $this->back('partidos', 'success', 'Jugador agregado al partido.');
    }

    public function removeParticipant(int $matchId, int $participantId)
    {
        if ($redirect = $this->guardSuperadmin()) return $redirect; $model = new PlayerMatchParticipantsModel(); $participant = $model->find($participantId); if (! $participant || (int) $participant['match_id'] !== $matchId || $participant['role'] === 'organizer') return $this->back('partidos', 'danger', 'No se puede quitar a este participante.'); $db = $this->db(); $db->transStart(); $model->update($participantId, ['status' => 'withdrawn', 'updated_at' => date('Y-m-d H:i:s')]); $db->table('player_matches')->where('id', $matchId)->where('confirmed_players >', 1)->set('confirmed_players', 'confirmed_players - 1', false)->set('updated_at', date('Y-m-d H:i:s'))->update(); $db->transComplete(); if ($db->transStatus() === false) return $this->back('partidos', 'danger', 'No pudimos quitar al jugador.'); (new PlayerNotificationService())->create((int) $participant['player_id'], 'admin_partido', 'Participación actualizada', 'Un administrador quitó tu participación del partido.', 'app/partidos/detalle/' . $matchId); $this->logAction('quitar_participante', 'player_match_participant', $participantId, $participant, ['status' => 'withdrawn']); return $this->back('partidos', 'success', 'Jugador quitado del partido.');
    }

    public function toggleMatch(int $matchId)
    {
        if ($redirect = $this->guardSuperadmin()) return $redirect; $model = new PlayerMatchesModel(); $match = $model->find($matchId); if (! $match) return $this->back('partidos', 'danger', 'No encontramos el partido.'); $status = ($match['status'] ?? 'open') === 'open' ? 'closed' : 'open'; $model->update($matchId, ['status' => $status, 'updated_at' => date('Y-m-d H:i:s')]); $this->logAction($status === 'open' ? 'reabrir_partido' : 'cerrar_partido', 'player_match', $matchId, $match, ['status' => $status]); return $this->back('partidos', 'success', $status === 'open' ? 'Partido reabierto.' : 'Partido cerrado.');
    }
}
