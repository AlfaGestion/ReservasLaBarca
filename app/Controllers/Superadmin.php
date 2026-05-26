<?php

namespace App\Controllers;

use App\Models\BookingsModel;
use App\Models\CustomersModel;
use App\Models\FieldsModel;
use App\Models\CancelReservationsModel;
use App\Models\ConfigModel;
use App\Models\LocalitiesModel;
use App\Models\BookingSlotsModel;
use App\Models\MercadoPagoKeysModel;
use App\Models\MercadoPagoModel;
use App\Models\OffersModel;
use App\Models\PaymentsModel;
use App\Models\RateModel;
use App\Models\ServicesModel;
use App\Models\ServicePricesModel;
use App\Models\AdminLogsModel;
use App\Models\TimeModel;
use App\Models\UsersModel;

class Superadmin extends BaseController
{
    private function ensureAdminLogsTable(): void
    {
        $db = \Config\Database::connect();
        if ($db->tableExists('admin_logs')) {
            $alterStatements = [
                'ip_address' => "ALTER TABLE admin_logs ADD COLUMN ip_address VARCHAR(64) NULL AFTER entity_id",
                'admin_name' => "ALTER TABLE admin_logs ADD COLUMN admin_name VARCHAR(120) NULL AFTER admin_id",
                'user_agent' => "ALTER TABLE admin_logs ADD COLUMN user_agent VARCHAR(255) NULL AFTER ip_address",
                'host_name' => "ALTER TABLE admin_logs ADD COLUMN host_name VARCHAR(255) NULL AFTER user_agent",
                'client_device' => "ALTER TABLE admin_logs ADD COLUMN client_device VARCHAR(255) NULL AFTER host_name",
            ];
            foreach ($alterStatements as $field => $sql) {
                try {
                    if (! $db->fieldExists($field, 'admin_logs')) {
                        $db->query($sql);
                    }
                } catch (\Throwable $e) {
                    log_message('error', 'No se pudo agregar columna [' . $field . '] en admin_logs: ' . $e->getMessage());
                }
            }
            return;
        }
        try {
            $db->query("
                CREATE TABLE IF NOT EXISTS admin_logs (
                    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                    admin_id INT UNSIGNED NULL,
                    admin_name VARCHAR(120) NULL,
                    action VARCHAR(80) NOT NULL,
                    entity_type VARCHAR(80) NOT NULL,
                    entity_id VARCHAR(80) NULL,
                    ip_address VARCHAR(64) NULL,
                    user_agent VARCHAR(255) NULL,
                    host_name VARCHAR(255) NULL,
                    client_device VARCHAR(255) NULL,
                    old_data LONGTEXT NULL,
                    new_data LONGTEXT NULL,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (id),
                    KEY idx_admin_logs_admin_id (admin_id),
                    KEY idx_admin_logs_entity (entity_type, entity_id),
                    KEY idx_admin_logs_created_at (created_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        } catch (\Throwable $e) {
            log_message('error', 'No se pudo crear admin_logs: ' . $e->getMessage());
        }
    }

    private function normalizeServiceColor(?string $value): string
    {
        $color = trim((string)$value);
        if (preg_match('/^#([A-Fa-f0-9]{6})$/', $color)) {
            return strtoupper($color);
        }

        return '#F39323';
    }

    private function logAdminAction(string $action, string $entityType, $entityId, $oldData = null, $newData = null): void
    {
        try {
            $this->ensureAdminLogsTable();
            $request = service('request');
            $adminId = session()->get('id_user') ?? session()->get('id') ?? null;
            $adminId = is_numeric($adminId) ? (int)$adminId : null;
            $adminName = (string)(session()->get('name') ?? session()->get('user') ?? session()->get('email') ?? '');
            $adminEmail = (string)(session()->get('email') ?? '');
            if ($adminId) {
                $admin = (new UsersModel())->find($adminId);
                if ($admin) {
                    $adminName = (string)($admin['name'] ?? $admin['user'] ?? $adminName);
                    $adminEmail = (string)($admin['email'] ?? $adminEmail);
                }
            }
            if ($adminEmail !== '') {
                $adminName = trim($adminName . ' <' . $adminEmail . '>');
            }
            $ipAddress = method_exists($request, 'getIPAddress') ? (string)$request->getIPAddress() : null;
            $hostName = null;
            if (!empty($ipAddress) && filter_var($ipAddress, FILTER_VALIDATE_IP)) {
                $resolved = @gethostbyaddr($ipAddress);
                if ($resolved && $resolved !== $ipAddress) {
                    $hostName = $resolved;
                }
            }
            $payload = [
                'admin_id' => $adminId,
                'admin_name' => $adminName !== '' ? substr($adminName, 0, 120) : null,
                'action' => $action,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'ip_address' => null,
                'user_agent' => null,
                'host_name' => $hostName ? substr($hostName, 0, 255) : null,
                'client_device' => null,
                'old_data' => $oldData === null ? null : json_encode($oldData, JSON_UNESCAPED_UNICODE),
                'new_data' => $newData === null ? null : json_encode($newData, JSON_UNESCAPED_UNICODE),
                'created_at' => date('Y-m-d H:i:s'),
            ];
            $db = \Config\Database::connect();
            $tableFields = $db->getFieldNames('admin_logs');
            if (!is_array($tableFields) || empty($tableFields)) {
                return;
            }
            $allowedTableFields = array_flip($tableFields);
            foreach (array_keys($payload) as $key) {
                if (!isset($allowedTableFields[$key])) {
                    unset($payload[$key]);
                }
            }
            if (empty($payload['action']) || empty($payload['entity_type'])) {
                return;
            }
            $db->table('admin_logs')->insert($payload);
        } catch (\Throwable $e) {
            log_message('error', 'No se pudo guardar admin log: ' . $e->getMessage());
        }
    }

    private function syncServicePrice(string $serviceType, float $value, string $unitLabel): void
    {
        try {
            $servicesModel = new ServicesModel();
            $pricesModel = new ServicePricesModel();
            $db = \Config\Database::connect();
            if (! $db->tableExists('services') || ! $db->tableExists('service_prices')) {
                return;
            }

            $service = $servicesModel->getByCode($serviceType);
            if (! $service) {
                return;
            }

            $chargeType = str_contains($unitLabel, 'bloque') ? 'block' : 'hour';
            $current = $pricesModel->getActiveForService((int)$service['id']);
            $payload = [
                'service_id' => $service['id'],
                'base_price' => $value,
                'charge_type' => $chargeType,
                'deposit_price' => $current['deposit_price'] ?? null,
                'active' => 1,
                'updated_at' => date('Y-m-d H:i:s'),
            ];

            if ($current) {
                $pricesModel->update($current['id'], $payload);
                $this->logAdminAction('change_price', 'service_price', $current['id'], $current, $payload);
                return;
            }

            $payload['created_at'] = date('Y-m-d H:i:s');
            $pricesModel->insert($payload);
            $this->logAdminAction('change_price', 'service_price', $pricesModel->getInsertID(), null, $payload);
        } catch (\Throwable $e) {
            log_message('error', 'No se pudo sincronizar service_price: ' . $e->getMessage());
        }
    }

    private function bookingDurationLabel(array $booking): string
    {
        $slotModel = new BookingSlotsModel();
        $fromMinutes = $slotModel->timeToMinutes($booking['time_from'] ?? '00:00');
        $untilMinutes = $slotModel->timeToMinutes($booking['time_until'] ?? '00:00');
        if ($untilMinutes <= $fromMinutes) {
            $untilMinutes += 24 * 60;
        }

        return minutesToHuman($untilMinutes - $fromMinutes);
    }

    private function cleanupExpiredPendingBookings(): void
    {
        $bookingsModel = new BookingsModel();
        $bookingSlotsModel = new BookingSlotsModel();
        $mercadoPagoModel = new MercadoPagoModel();
        $paymentsModel = new PaymentsModel();

        $now = date('Y-m-d H:i:s');
        $threshold = date('Y-m-d H:i:s', strtotime('-5 minutes'));

        // 1) Tomar los slots pending vencidos para identificar reservas candidatas.
        $expiredPendingSlots = $bookingSlotsModel
            ->select('booking_id')
            ->where('active', 1)
            ->where('status', 'pending')
            ->where('expires_at <', $now)
            ->findAll();

        // 2) Expirar esos slots.
        $this->expireActiveBookingSlots($bookingSlotsModel, [
            'status' => 'pending',
            'expires_at <' => $now,
        ]);

        if (empty($expiredPendingSlots)) {
            return;
        }

        $candidateIds = [];
        foreach ($expiredPendingSlots as $slot) {
            $bookingId = (int)($slot['booking_id'] ?? 0);
            if ($bookingId > 0) {
                $candidateIds[$bookingId] = true;
            }
        }
        $candidateIds = array_keys($candidateIds);

        // Fallback: reservas provisionales vencidas por booking_time (aunque no tengan slot enlazado).
        $staleBookings = $bookingsModel
            ->select('id')
            ->where('annulled', 0)
            ->where('mp', 0)
            ->where('payment <=', 0)
            ->where('booking_time <', $threshold)
            ->groupStart()
            ->where('approved', 0)
            ->orWhere('approved', null)
            ->groupEnd()
            ->findAll();

        foreach ($staleBookings as $row) {
            $bookingId = (int)($row['id'] ?? 0);
            if ($bookingId > 0) {
                $candidateIds[$bookingId] = true;
            }
        }

        $candidateIds = array_keys($candidateIds);

        if (empty($candidateIds)) {
            return;
        }
        // 3) Solo reservas provisionales sin pago confirmado.
        $candidates = $bookingsModel->whereIn('id', $candidateIds)
            ->where('annulled', 0)
            ->where('mp', 0)
            ->where('payment <=', 0)
            ->groupStart()
            ->where('approved', 0)
            ->orWhere('approved', null)
            ->groupEnd()
            ->findAll();

        if (empty($candidates)) {
            return;
        }

        $idsToDelete = [];
        foreach ($candidates as $booking) {
            $bookingId = (int)$booking['id'];

            $hasApprovedMp = $mercadoPagoModel->where('id_booking', $bookingId)
                ->where('status', 'approved')
                ->first();

            $hasPayment = $paymentsModel->where('id_booking', $bookingId)->first();

            if (!$hasApprovedMp && !$hasPayment) {
                $idsToDelete[] = $bookingId;
            }
        }

        if (empty($idsToDelete)) {
            return;
        }

        // 4) Limpiar registros relacionados y luego la reserva.
        $this->expireActiveBookingSlots($bookingSlotsModel, [], [
            'booking_id' => $idsToDelete,
        ]);

        $paymentsModel->whereIn('id_booking', $idsToDelete)->delete();
        $mercadoPagoModel->whereIn('id_booking', $idsToDelete)->delete();
        $bookingsModel->delete($idsToDelete);
    }

    public function index()
    {
        $fieldsModel = new FieldsModel();
        $rateModel = new RateModel();
        $customersModel = new CustomersModel();
        $timeModel = new TimeModel();
        $usersModel = new UsersModel();
        $offerModel = new OffersModel();
        $localitiesModel = new LocalitiesModel();
        $configModel = new ConfigModel();

        $users = $usersModel->where('active', 1)
        ->where('user !=', 'testuser')
        ->findAll();

        // The bookings table is populated client-side via getActiveBookings().
        // Loading the full history here makes the admin page too slow.
        $bookings = [];

        $getTime = $timeModel->findAll();
        if ($getTime) {
            $time = $getTime[0];
        } else {
            $time = [
                'from' => 0,
                'until' => 0,
                'from_cut' => 0,
                'until_cut' => 0,
                'nocturnal_time' => 0
            ];
        }

        $openingTime = $timeModel->getOpeningTime();

        $getRate = $rateModel->findAll();
        if ($getRate) {
            $rate = $getRate[0];
        } else {
            $rate = 0;
        }

        $getOfferRate = $offerModel->findAll();
        if ($getOfferRate) {
            $offerRate = $getOfferRate[0];
        } else {
            $offerRate = 0;
        }

        $fields = $fieldsModel->enrichFields($fieldsModel->findAll());
        $servicesModel = new ServicesModel();
        $servicesModel->ensureDefaultServices();
        $services = $servicesModel->getServices();

        $customers = $customersModel->findAll();
        $localities = $localitiesModel->orderBy('name', 'ASC')->findAll();
        $closureTextRow = $configModel->where('clave', 'texto_cierre')->first();
        $closureText = $closureTextRow['valor'] ?? '';
        if (!is_string($closureText) || trim($closureText) === '') {
            $closureText = "Aviso importante\n\n"
                . "Queremos informarles que el dÃ­a <fecha> las canchas permanecerÃ¡n cerradas.\n"
                . "Pedimos disculpas por las molestias que esto pueda ocasionar.\n\n"
                . "De todas formas, ya pueden reservar normalmente las horas para fechas posteriores.\n"
                . "Muchas gracias por la comprensiÃ³n y por seguir eligiÃ©ndonos.";
        }
        $bookingEmailRow = $configModel->where('clave', 'email_reservas')->first();
        $bookingEmail = $bookingEmailRow['valor'] ?? '';

        return view('superadmin/index', [
            'bookings' => $bookings,
            'rate' => $rate,
            'customers' => $customers,
            'time' => $time,
            'openingTime' => $openingTime,
            'fields' => $fields,
            'users' => $users,
            'offerRate' => $offerRate,
            'localities' => $localities,
            'closureText' => $closureText,
            'bookingEmail' => $bookingEmail,
            'services' => $services,
        ]);
    }

    public function saveField()
    {
        $fieldsModel = new FieldsModel();
        $isAjax = $this->request->isAJAX();

        $this->request->getVar('iluminacion') ? $iluminacion = true : $iluminacion = false;
        $this->request->getVar('tipoTecho') ? $techada = true : $techada = false;

        $nombre = $this->request->getVar('nombre');
        $medidas = $this->request->getVar('medidas');
        $tipoPiso = $this->request->getVar('tipoPiso');
        $tipoCancha = $this->request->getVar('tipoCancha');
        $serviceType = $this->request->getVar('serviceType') ?: 'football';
        $service = (new ServicesModel())->getByCode($serviceType);
        $blockMinutes = (int)($service['duration_minutes'] ?? $this->request->getVar('blockMinutes') ?? 60);
        $priceUnitLabel = $blockMinutes === 60 ? 'por hora' : 'por bloque de ' . minutesToHuman($blockMinutes);
        $valor = parse_price_ar($this->request->getVar('valor'));
        $valorIluminacion = parse_price_ar($this->request->getVar('valorIluminacion'));
        if ($valorIluminacion <= 0) {
            $valorIluminacion = $valor;
        }


        $query = [
            'name' => $nombre,
            'sizes' => $medidas,
            'floor_type' => $tipoPiso,
            'field_type' => $tipoCancha,
            'service_type' => $serviceType,
            'block_minutes' => $blockMinutes,
            'price_unit_label' => $priceUnitLabel,
            'ilumination' => $iluminacion,
            'roofed' => $techada,
            'value' => $valor,
            'ilumination_value' => $valorIluminacion,
            'disabled' => 0,
        ];

        if ($nombre == '' || $valor == '') {
            if ($isAjax) {
                return $this->response->setStatusCode(422)->setJSON([
                    'error' => true,
                    'message' => 'Debe ingresar todos los datos',
                ]);
            }
            return redirect()->to('abmAdmin')->with('msg', ['type' => 'danger', 'body' => 'Debe ingresar todos los datos']);
        }


        try {
            $id = $fieldsModel->insert($query);
            $this->syncServicePrice($serviceType, $valor, $priceUnitLabel);
            $this->logAdminAction('create_field', 'field', $id, null, $query);
            if ($isAjax) {
                return $this->response->setJSON([
                    'error' => false,
                    'message' => 'Servicio creado correctamente',
                    'data' => $fieldsModel->find($id),
                ]);
            }
        } catch (\Exception $e) {
            if ($isAjax) {
                return $this->response->setStatusCode(500)->setJSON([
                    'error' => true,
                    'message' => 'Error al insertar datos: ' . $e->getMessage(),
                ]);
            }
            return redirect()->to('abmAdmin')->with('msg', ['type' => 'danger', 'body' => 'Error al insertar datos: ' . $e->getMessage()]);
        }

        return redirect()->to('abmAdmin')->with('msg', ['type' => 'success', 'body' => 'Servicio creado correctamente']);
    }

    public function editField($id)
    {
        $fieldsModel = new FieldsModel();
        $isAjax = $this->request->isAJAX();
        $oldField = $fieldsModel->find($id);

        $this->request->getVar('iluminacion') ? $iluminacion = true : $iluminacion = false;
        $this->request->getVar('tipoTecho') ? $techada = true : $techada = false;

        $nombre = $this->request->getVar('nombre');
        $medidas = $this->request->getVar('medidas');
        $tipoPiso = $this->request->getVar('tipoPiso');
        $tipoCancha = $this->request->getVar('tipoCancha');
        $serviceType = $this->request->getVar('serviceType') ?: 'football';
        $service = (new ServicesModel())->getByCode($serviceType);
        $blockMinutes = (int)($service['duration_minutes'] ?? $this->request->getVar('blockMinutes') ?? 60);
        $priceUnitLabel = $blockMinutes === 60 ? 'por hora' : 'por bloque de ' . minutesToHuman($blockMinutes);
        $valor = parse_price_ar($this->request->getVar('valor'));
        $valorIluminacion = parse_price_ar($this->request->getVar('valorIluminacion'));
        if ($valorIluminacion <= 0) {
            $valorIluminacion = $valor;
        }
        $disabled = $this->request->getVar('disabled') ? 1 : 0;


        $query = [
            'name' => $nombre,
            'sizes' => $medidas,
            'floor_type' => $tipoPiso,
            'field_type' => $tipoCancha,
            'service_type' => $serviceType,
            'block_minutes' => $blockMinutes,
            'price_unit_label' => $priceUnitLabel,
            'ilumination' => $iluminacion,
            'roofed' => $techada,
            'value' => $valor,
            'ilumination_value' => $valorIluminacion,
            'disabled' => $disabled,
        ];

        if ($nombre == '' || $valor == '') {
            if ($isAjax) {
                return $this->response->setStatusCode(422)->setJSON([
                    'error' => true,
                    'message' => 'Debe ingresar todos los datos',
                ]);
            }
            return redirect()->to('abmAdmin')->with('msg', ['type' => 'danger', 'body' => 'Debe ingresar todos los datos']);
        }


        try {
            $fieldsModel->update($id, $query);
            $this->syncServicePrice($serviceType, $valor, $priceUnitLabel);
            $this->logAdminAction('edit_field', 'field', $id, $oldField, $query);
            if ($isAjax) {
                return $this->response->setJSON([
                    'error' => false,
                    'message' => 'Servicio editado correctamente',
                    'data' => $fieldsModel->find($id),
                ]);
            }
        } catch (\Exception $e) {
            if ($isAjax) {
                return $this->response->setStatusCode(500)->setJSON([
                    'error' => true,
                    'message' => 'Error al insertar datos: ' . $e->getMessage(),
                ]);
            }
            return redirect()->to('abmAdmin')->with('msg', ['type' => 'danger', 'body' => 'Error al insertar datos: ' . $e->getMessage()]);
        }

        return redirect()->to('abmAdmin')->with('msg', ['type' => 'success', 'body' => 'Servicio editado correctamente']);
    }

    public function saveService()
    {
        $servicesModel = new ServicesModel();
        if (! \Config\Database::connect()->tableExists('services')) {
            return redirect()->to('abmAdmin')->with('msg', ['type' => 'danger', 'body' => 'Debe ejecutar la migracion SQL de servicios.']);
        }

        $durationMinutes = combine_duration_minutes(
            $this->request->getVar('duration_hours'),
            $this->request->getVar('duration_minutes_remainder')
        );
        $intervalMinutes = $durationMinutes;

        if ($durationMinutes <= 0) {
            $durationMinutes = 60;
        }
        if ($intervalMinutes <= 0) {
            $intervalMinutes = $durationMinutes;
        }

        if ($durationMinutes % 15 !== 0) {
            return redirect()->to('abmAdmin')->with('msg', ['type' => 'danger', 'body' => 'La duracion debe ser multiplo de 15 minutos.']);
        }

        $name = trim((string)$this->request->getVar('name'));
        $code = strtolower(trim((string)($this->request->getVar('code') ?: $name)));
        $code = preg_replace('/[^a-z0-9_]+/', '_', $code);
        $code = trim((string)$code, '_');

        if ($name === '' || $code === '') {
            return redirect()->to('abmAdmin')->with('msg', ['type' => 'danger', 'body' => 'El nombre y el codigo del servicio son obligatorios.']);
        }

        if ($servicesModel->getByCode($code)) {
            return redirect()->to('abmAdmin')->with('msg', ['type' => 'danger', 'body' => 'Ya existe un servicio con ese codigo.']);
        }

        $payload = [
            'name' => $name,
            'code' => $code,
            'opening_time' => $this->request->getVar('opening_time') ?: '07:00',
            'closing_time' => $this->request->getVar('closing_time') ?: '23:00',
            'duration_minutes' => $durationMinutes,
            'slot_interval_minutes' => $intervalMinutes,
            'minimum_duration_minutes' => $durationMinutes,
            'booking_interval_minutes' => $intervalMinutes,
            'active' => $this->request->getVar('active') ? 1 : 0,
            'online_available' => $this->request->getVar('online_available') ? 1 : 0,
            'allows_quincho_addon' => $this->request->getVar('allows_quincho_addon') ? 1 : 0,
            'display_order' => (int)($this->request->getVar('display_order') ?: 100),
            'color' => $this->normalizeServiceColor($this->request->getVar('color')),
            'offer_active' => $this->request->getVar('offer_active') ? 1 : 0,
            'offer_text' => trim((string)$this->request->getVar('offer_text')),
            'discount_type' => $this->request->getVar('discount_type') === 'fixed' ? 'fixed' : 'percentage',
            'discount_value' => parse_price_ar($this->request->getVar('discount_value')),
            'offer_start_date' => $this->request->getVar('offer_start_date') ?: null,
            'offer_end_date' => $this->request->getVar('offer_end_date') ?: null,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        try {
            $id = $servicesModel->insert($payload);
            $this->logAdminAction('create_service', 'service', $id, null, $payload);
            return redirect()->to('abmAdmin')->with('msg', ['type' => 'success', 'body' => 'Servicio creado correctamente']);
        } catch (\Throwable $e) {
            return redirect()->to('abmAdmin')->with('msg', ['type' => 'danger', 'body' => 'Error al crear servicio: ' . $e->getMessage()]);
        }
    }

    public function editService($id)
    {
        $servicesModel = new ServicesModel();
        if (! \Config\Database::connect()->tableExists('services')) {
            return redirect()->to('abmAdmin')->with('msg', ['type' => 'danger', 'body' => 'Debe ejecutar la migracion SQL de servicios.']);
        }

        $oldService = $servicesModel->find($id);
        if (! $oldService) {
            return redirect()->to('abmAdmin')->with('msg', ['type' => 'danger', 'body' => 'Servicio no encontrado.']);
        }

        $durationMinutes = combine_duration_minutes(
            $this->request->getVar('duration_hours'),
            $this->request->getVar('duration_minutes_remainder')
        );
        $intervalMinutes = $durationMinutes;

        if ($durationMinutes <= 0) {
            $durationMinutes = (int)($oldService['duration_minutes'] ?? $oldService['minimum_duration_minutes'] ?? 60);
        }
        if ($intervalMinutes <= 0) {
            $intervalMinutes = (int)($oldService['slot_interval_minutes'] ?? $oldService['booking_interval_minutes'] ?? $durationMinutes);
        }

        if ($durationMinutes % 15 !== 0) {
            return redirect()->to('abmAdmin')->with('msg', ['type' => 'danger', 'body' => 'La duracion debe ser multiplo de 15 minutos.']);
        }

        $payload = [
            'name' => trim((string)$this->request->getVar('name')),
            'opening_time' => $this->request->getVar('opening_time') ?: '07:00',
            'closing_time' => $this->request->getVar('closing_time') ?: '23:00',
            'duration_minutes' => $durationMinutes,
            'slot_interval_minutes' => $intervalMinutes,
            'minimum_duration_minutes' => $durationMinutes,
            'booking_interval_minutes' => $intervalMinutes,
            'active' => $this->request->getVar('active') ? 1 : 0,
            'online_available' => $this->request->getVar('online_available') ? 1 : 0,
            'allows_quincho_addon' => $this->request->getVar('allows_quincho_addon') ? 1 : 0,
            'color' => $this->normalizeServiceColor($this->request->getVar('color') ?: ($oldService['color'] ?? null)),
            'offer_active' => $this->request->getVar('offer_active') ? 1 : 0,
            'offer_text' => trim((string)$this->request->getVar('offer_text')),
            'discount_type' => $this->request->getVar('discount_type') === 'fixed' ? 'fixed' : 'percentage',
            'discount_value' => parse_price_ar($this->request->getVar('discount_value')),
            'offer_start_date' => $this->request->getVar('offer_start_date') ?: null,
            'offer_end_date' => $this->request->getVar('offer_end_date') ?: null,
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        if ($payload['name'] === '') {
            return redirect()->to('abmAdmin')->with('msg', ['type' => 'danger', 'body' => 'El nombre del servicio es obligatorio.']);
        }

        try {
            $servicesModel->update($id, $payload);
            $this->logAdminAction('edit_service', 'service', $id, $oldService, $payload);
            return redirect()->to('abmAdmin')->with('msg', ['type' => 'success', 'body' => 'Servicio editado correctamente']);
        } catch (\Throwable $e) {
            return redirect()->to('abmAdmin')->with('msg', ['type' => 'danger', 'body' => 'Error al editar servicio: ' . $e->getMessage()]);
        }
    }

    public function getActiveBookings()
    {
        $this->cleanupExpiredPendingBookings();

        $fieldsModel = new FieldsModel();
        $servicesModel = new ServicesModel();
        $bookingsModel = new BookingsModel();
        $paymentsModel = new PaymentsModel();
        $data = $this->request->getJSON();

        $getBookings = $bookingsModel->where('date >=', $data->fechaDesde)
            ->where('date <=', $data->fechaHasta)
            ->where('annulled', 0)
            ->orderBy('time_from', 'ASC')
            ->findAll();

        $bookings = [];
        $bookingIds = array_column($getBookings, 'id');
        $paidByBooking = [];

        if (!empty($bookingIds)) {
            $paymentsRows = $paymentsModel
                ->select('id_booking, SUM(amount) as paid_total')
                ->whereIn('id_booking', $bookingIds)
                ->groupBy('id_booking')
                ->findAll();

            foreach ($paymentsRows as $pr) {
                $paidByBooking[(int)$pr['id_booking']] = (float)($pr['paid_total'] ?? 0);
            }
        }

        foreach ($getBookings as $booking) {
            $fieldData = $fieldsModel->getField($booking['id_field']);
            $serviceType = (string)($fieldData['service_type'] ?? 'football');
            $serviceData = $servicesModel->getByCode($serviceType);
            $bookingId = (int)$booking['id'];
            $paymentsSum = $paidByBooking[$bookingId] ?? 0.0;
            $bookingPaid = (float)($booking['payment'] ?? 0);
            $paid = max($paymentsSum, $bookingPaid);
            $total = (float)($booking['total'] ?? 0);
            $difference = $total - $paid;
            if ($difference < 0) {
                $difference = 0;
            }

            $serviceColor = $serviceData['color'] ?? $fieldData['service_color'] ?? '#F39323';

            $reserva = [
                'id' => $booking['id'],
                'cancha' => $fieldData['name'] ?? 'N/D',
                'service_type' => $serviceType,
                'field_color' => $fieldData['color'] ?? null,
                'service_color' => $serviceColor,
                'color' => $serviceColor,
                'fecha' => date("d/m/Y", strtotime($booking['date'])),
                'horario' => $booking['time_from'] . ' a ' . $booking['time_until'],
                'nombre' => $booking['name'],
                'telefono' => $booking['phone'],
                'creado_por' => $booking['created_by_name'] ?? $booking['created_by_type'] ?? 'N/D',
                'editado_por' => $booking['edited_by_name'] ?? null,
                'editado_en' => $booking['edited_at'] ?? null,
                'pago_total' => $paid >= $total ? 'Si' : 'No',
                'total_reserva' => $booking['total'],
                'diferencia' => $difference,
                'monto_reserva' => $paid,
                'descripcion' => $booking['description'],
                'metodo_pago' => $booking['payment_method'],
                'anulada'     => $booking['annulled'],
                'mp'        => $booking['mp'],
            ];

            array_push($bookings, $reserva);
        }

        try {
            return  $this->response->setJSON($this->setResponse(null, null, $bookings, 'Respuesta exitosa'));
        } catch (\Exception $e) {
            return  $this->response->setJSON($this->setResponse(404, true, null, $e->getMessage()));
        }
    }

    public function getAnnulledBookings()
    {
        $fieldsModel = new FieldsModel();
        $servicesModel = new ServicesModel();
        $bookingsModel = new BookingsModel();
        $paymentsModel = new PaymentsModel();
        $data = $this->request->getJSON();

        $getBookings = $bookingsModel->where('date >=', $data->fechaDesde)
            ->where('date <=', $data->fechaHasta)
            ->where('annulled', 1)
            ->orderBy('time_from', 'ASC')
            ->findAll();

        $bookings = [];
        $bookingIds = array_column($getBookings, 'id');
        $paidByBooking = [];

        if (!empty($bookingIds)) {
            $paymentsRows = $paymentsModel
                ->select('id_booking, SUM(amount) as paid_total')
                ->whereIn('id_booking', $bookingIds)
                ->groupBy('id_booking')
                ->findAll();

            foreach ($paymentsRows as $pr) {
                $paidByBooking[(int)$pr['id_booking']] = (float)($pr['paid_total'] ?? 0);
            }
        }

        foreach ($getBookings as $booking) {
            $fieldData = $fieldsModel->getField($booking['id_field']);
            $serviceType = (string)($fieldData['service_type'] ?? 'football');
            $serviceData = $servicesModel->getByCode($serviceType);
            $bookingId = (int)$booking['id'];
            $paymentsSum = $paidByBooking[$bookingId] ?? 0.0;
            $bookingPaid = (float)($booking['payment'] ?? 0);
            $paid = max($paymentsSum, $bookingPaid);
            $total = (float)($booking['total'] ?? 0);
            $difference = $total - $paid;
            if ($difference < 0) {
                $difference = 0;
            }

            $serviceColor = $serviceData['color'] ?? $fieldData['service_color'] ?? '#F39323';

            $reserva = [
                'id' => $booking['id'],
                'cancha' => $fieldData['name'] ?? 'N/D',
                'service_type' => $serviceType,
                'field_color' => $fieldData['color'] ?? null,
                'service_color' => $serviceColor,
                'color' => $serviceColor,
                'fecha' => date("d/m/Y", strtotime($booking['date'])),
                'horario' => $booking['time_from'] . ' a ' . $booking['time_until'],
                'nombre' => $booking['name'],
                'telefono' => $booking['phone'],
                'creado_por' => $booking['created_by_name'] ?? $booking['created_by_type'] ?? 'N/D',
                'editado_por' => $booking['edited_by_name'] ?? null,
                'editado_en' => $booking['edited_at'] ?? null,
                'pago_total' => $paid >= $total ? 'Si' : 'No',
                'total_reserva' => $booking['total'],
                'diferencia' => $difference,
                'monto_reserva' => $paid,
                'descripcion' => $booking['description'],
                'metodo_pago' => $booking['payment_method'],
                'anulada'     => $booking['annulled'],
            ];

            array_push($bookings, $reserva);
        }

        try {
            return  $this->response->setJSON($this->setResponse(null, null, $bookings, 'Respuesta exitosa'));
        } catch (\Exception $e) {
            return  $this->response->setJSON($this->setResponse(404, true, null, $e->getMessage()));
        }
    }

    public function getBookingIssues()
    {
        $fieldsModel = new FieldsModel();
        $servicesModel = new ServicesModel();
        $bookingsModel = new BookingsModel();
        $paymentsModel = new PaymentsModel();
        $data = $this->request->getJSON();

        $getBookings = $bookingsModel->where('date >=', $data->fechaDesde)
            ->where('date <=', $data->fechaHasta)
            ->groupStart()
            ->where('annulled', 1)
            ->orWhere('approved', 0)
            ->groupEnd()
            ->orderBy('time_from', 'ASC')
            ->findAll();

        $bookings = [];
        $bookingIds = array_column($getBookings, 'id');
        $paidByBooking = [];

        if (!empty($bookingIds)) {
            $paymentsRows = $paymentsModel
                ->select('id_booking, SUM(amount) as paid_total')
                ->whereIn('id_booking', $bookingIds)
                ->groupBy('id_booking')
                ->findAll();

            foreach ($paymentsRows as $pr) {
                $paidByBooking[(int)$pr['id_booking']] = (float)($pr['paid_total'] ?? 0);
            }
        }

        foreach ($getBookings as $booking) {
            $fieldData = $fieldsModel->getField($booking['id_field']);
            $serviceType = (string)($fieldData['service_type'] ?? 'football');
            $serviceData = $servicesModel->getByCode($serviceType);
            $bookingId = (int)$booking['id'];
            $paymentsSum = $paidByBooking[$bookingId] ?? 0.0;
            $bookingPaid = (float)($booking['payment'] ?? 0);
            $paid = max($paymentsSum, $bookingPaid);
            $total = (float)($booking['total'] ?? 0);
            $difference = $total - $paid;
            if ($difference < 0) {
                $difference = 0;
            }

            $issueReason = [];
            if ((int)($booking['annulled'] ?? 0) === 1) {
                $issueReason[] = 'Cancelada';
            }
            if ((int)($booking['approved'] ?? 0) !== 1) {
                $issueReason[] = 'Pago no aprobado';
            }
            $issueLabel = implode(' · ', $issueReason);
            if ($issueLabel === '') {
                $issueLabel = 'Incidencia';
            }

            $serviceColor = $serviceData['color'] ?? $fieldData['service_color'] ?? '#F39323';

            $bookings[] = [
                'id' => $booking['id'],
                'cancha' => $fieldData['name'] ?? 'N/D',
                'service_type' => $serviceType,
                'field_color' => $fieldData['color'] ?? null,
                'service_color' => $serviceColor,
                'color' => $serviceColor,
                'fecha' => date("d/m/Y", strtotime($booking['date'])),
                'horario' => $booking['time_from'] . ' a ' . $booking['time_until'],
                'nombre' => $booking['name'],
                'telefono' => $booking['phone'],
                'creado_por' => $booking['created_by_name'] ?? $booking['created_by_type'] ?? 'N/D',
                'editado_por' => $booking['edited_by_name'] ?? null,
                'editado_en' => $booking['edited_at'] ?? null,
                'pago_total' => $paid >= $total ? 'Si' : 'No',
                'total_reserva' => $booking['total'],
                'diferencia' => $difference,
                'monto_reserva' => $paid,
                'descripcion' => $booking['description'],
                'metodo_pago' => $booking['payment_method'],
                'anulada' => $booking['annulled'],
                'mp' => $booking['mp'],
                'issue_reason' => $issueLabel,
            ];
        }

        return $this->response->setJSON($this->setResponse(null, null, $bookings, 'Respuesta exitosa'));
    }

    public function checkCancelReservations()
    {
        $data = $this->request->getJSON();
        $date = $data->fecha ?? null;
        $field = $data->cancha ?? 'all';

        if (!$date) {
            return $this->response->setJSON($this->setResponse(400, true, null, 'Debe ingresar una fecha.'));
        }

        $bookingsModel = new BookingsModel();
        $fieldsModel = new FieldsModel();

        $query = $bookingsModel->where('date', $date)->where('annulled', 0);
        if ($field !== 'all') {
            $query->where('id_field', $field);
        }

        $bookings = $query->findAll();

        $result = [];
        foreach ($bookings as $booking) {
            $fieldName = $fieldsModel->getField($booking['id_field'])['name'] ?? 'N/D';
            $result[] = [
                'nombre' => $booking['name'],
                'telefono' => $booking['phone'],
                'cancha' => $fieldName,
                'horario' => $booking['time_from'] . ' a ' . $booking['time_until'] . ' (' . $this->bookingDurationLabel($booking) . ')',
            ];
        }

        $fieldLabel = 'Todas';
        if ($field !== 'all') {
            $fieldLabel = $fieldsModel->getField($field)['name'] ?? 'N/D';
        }

        $payload = [
            'fecha' => $date,
            'canchaLabel' => $fieldLabel,
            'bookings' => $result,
        ];

        return $this->response->setJSON($this->setResponse(null, null, $payload, 'Respuesta exitosa'));
    }

    public function saveCancelReservations()
    {
        $data = $this->request->getJSON();
        $date = $data->fecha ?? null;
        $field = $data->cancha ?? 'all';

        if (!$date) {
            return $this->response->setJSON($this->setResponse(400, true, null, 'Debe ingresar una fecha.'));
        }

        $today = date('Y-m-d');
        if ($date < $today) {
            return $this->response->setJSON($this->setResponse(400, true, null, 'No se pueden informar cierres con fecha anterior a hoy.'));
        }

        $fieldsModel = new FieldsModel();
        $cancelModel = new CancelReservationsModel();

        $sameDateRows = $cancelModel->where('cancel_date', $date)->findAll();
        $hasAllClosure = false;
        $hasSameFieldClosure = false;
        foreach ($sameDateRows as $row) {
            if (empty($row['field_id'])) {
                $hasAllClosure = true;
            }
            if ($field !== 'all' && (int)($row['field_id'] ?? 0) === (int)$field) {
                $hasSameFieldClosure = true;
            }
        }

        if ($field === 'all' && !empty($sameDateRows)) {
            return $this->response->setJSON($this->setResponse(409, true, null, 'Ya existen cierres para esa fecha. Solo el primer registro puede editarse a "Todas".'));
        }
        if ($field !== 'all' && $hasAllClosure) {
            return $this->response->setJSON($this->setResponse(409, true, null, 'Ya existe un cierre para Todas las canchas en esa fecha.'));
        }
        if ($field !== 'all' && $hasSameFieldClosure) {
            return $this->response->setJSON($this->setResponse(409, true, null, 'Ya existe un cierre para esa cancha en esa fecha.'));
        }

        $fieldLabel = 'Todas';
        $fieldId = null;
        if ($field !== 'all') {
            $fieldLabel = $fieldsModel->getField($field)['name'] ?? 'N/D';
            $fieldId = $field;
        }

        $userName = session()->get('name') ?? session()->get('user') ?? 'N/D';

        $payload = [
            'cancel_date' => $date,
            'field_id' => $fieldId,
            'field_label' => $fieldLabel,
            'user_name' => $userName,
            'created_at' => date('Y-m-d H:i:s'),
        ];

        try {
            $cancelModel->insert($payload);
            return $this->response->setJSON($this->setResponse(null, null, null, 'CancelaciÃ³n registrada.'));
        } catch (\Exception $e) {
            return $this->response->setJSON($this->setResponse(500, true, null, $e->getMessage()));
        }
    }

    public function getCancelReservations()
    {
        $data = $this->request->getJSON();
        $date = $data->fecha ?? null;
        $dateFrom = $data->fechaDesde ?? null;
        $dateTo = $data->fechaHasta ?? null;
        $field = $data->cancha ?? 'all';

        $cancelModel = new CancelReservationsModel();
        $query = $cancelModel;
        if ($date) {
            $query = $query->where('cancel_date', $date);
        } elseif ($dateFrom || $dateTo) {
            if ($dateFrom) {
                $query = $query->where('cancel_date >=', $dateFrom);
            }
            if ($dateTo) {
                $query = $query->where('cancel_date <=', $dateTo);
            }
        } else {
            $query = $query->where('cancel_date >=', date('Y-m-d'));
        }
        if ($field !== 'all') {
            $query = $query->where('field_id', (int)$field);
        }
        $rows = $query
            ->orderBy('cancel_date', 'DESC')
            ->orderBy('field_label', 'ASC')
            ->findAll();

        return $this->response->setJSON($this->setResponse(null, null, $rows, 'Respuesta exitosa'));
    }

    public function updateCancelReservation()
    {
        $data = $this->request->getJSON();
        $id = $data->id ?? null;
        $date = $data->fecha ?? null;
        $field = $data->cancha ?? 'all';

        if (!$id) {
            return $this->response->setJSON($this->setResponse(400, true, null, 'ID invÃ¡lido.'));
        }
        if (!$date) {
            return $this->response->setJSON($this->setResponse(400, true, null, 'Debe ingresar una fecha.'));
        }

        $today = date('Y-m-d');
        if ($date < $today) {
            return $this->response->setJSON($this->setResponse(400, true, null, 'No se pueden editar cierres con fecha anterior a hoy.'));
        }

        $fieldsModel = new FieldsModel();
        $cancelModel = new CancelReservationsModel();
        $row = $cancelModel->find($id);
        if (!$row) {
            return $this->response->setJSON($this->setResponse(404, true, null, 'Cierre no encontrado.'));
        }

        $sameDateRows = $cancelModel->where('cancel_date', $date)->findAll();
        $firstId = null;
        $hasAllOther = false;
        $hasSameFieldOther = false;
        foreach ($sameDateRows as $r) {
            $rowId = (int)$r['id'];
            if ($firstId === null || $rowId < $firstId) {
                $firstId = $rowId;
            }
            if ($rowId === (int)$id) {
                continue;
            }
            if (empty($r['field_id'])) {
                $hasAllOther = true;
            }
            if ($field !== 'all' && (int)($r['field_id'] ?? 0) === (int)$field) {
                $hasSameFieldOther = true;
            }
        }

        if ($field === 'all') {
            if ($firstId !== null && (int)$id !== (int)$firstId) {
                return $this->response->setJSON($this->setResponse(409, true, null, 'Solo el primer cierre de la fecha puede cambiarse a "Todas".'));
            }
        } else {
            if ($hasAllOther) {
                return $this->response->setJSON($this->setResponse(409, true, null, 'Ya existe un cierre para Todas las canchas en esa fecha.'));
            }
            if ($hasSameFieldOther) {
                return $this->response->setJSON($this->setResponse(409, true, null, 'Ya existe un cierre para esa cancha en esa fecha.'));
            }
        }

        $fieldLabel = 'Todas';
        $fieldId = null;
        if ($field !== 'all') {
            $fieldLabel = $fieldsModel->getField($field)['name'] ?? 'N/D';
            $fieldId = $field;
        }

        $userName = session()->get('name') ?? session()->get('user') ?? 'N/D';
        $payload = [
            'cancel_date' => $date,
            'field_id' => $fieldId,
            'field_label' => $fieldLabel,
            'user_name' => $userName,
        ];

        try {
            $cancelModel->update($id, $payload);
            if ($field === 'all') {
                $cancelModel->where('cancel_date', $date)
                    ->where('id !=', $id)
                    ->delete();
            }
            return $this->response->setJSON($this->setResponse(null, null, null, 'Cierre actualizado.'));
        } catch (\Exception $e) {
            return $this->response->setJSON($this->setResponse(500, true, null, $e->getMessage()));
        }
    }

    public function deleteCancelReservation()
    {
        $data = $this->request->getJSON();
        $id = $data->id ?? null;

        if (!$id) {
            return $this->response->setJSON($this->setResponse(400, true, null, 'ID invÃ¡lido.'));
        }

        $cancelModel = new CancelReservationsModel();
        try {
            $row = $cancelModel->find($id);
            if (!$row) {
                return $this->response->setJSON($this->setResponse(404, true, null, 'Cierre no encontrado.'));
            }

            $today = date('Y-m-d');
            if (!empty($row['cancel_date']) && $row['cancel_date'] < $today) {
                return $this->response->setJSON($this->setResponse(403, true, null, 'No se pueden editar o eliminar cierres con fecha anterior a hoy.'));
            }

            $cancelModel->delete($id);
            return $this->response->setJSON($this->setResponse(null, null, null, 'Cierre eliminado.'));
        } catch (\Exception $e) {
            return $this->response->setJSON($this->setResponse(500, true, null, $e->getMessage()));
        }
    }

    public function saveConfigGeneral()
    {
        $data = $this->request->getJSON();
        $textoCierre = $data->textoCierre ?? '';
        $emailReservas = $data->emailReservas ?? '';

        $configModel = new ConfigModel();

        try {
            $existingText = $configModel->where('clave', 'texto_cierre')->first();
            if ($existingText) {
                $configModel->update($existingText['id'], ['valor' => $textoCierre]);
            } else {
                $configModel->insert(['clave' => 'texto_cierre', 'valor' => $textoCierre]);
            }

            $existingEmail = $configModel->where('clave', 'email_reservas')->first();
            if ($existingEmail) {
                $configModel->update($existingEmail['id'], ['valor' => $emailReservas]);
            } else {
                $configModel->insert(['clave' => 'email_reservas', 'valor' => $emailReservas]);
            }

            return $this->response->setJSON($this->setResponse(null, null, null, 'ConfiguraciÃ³n guardada.'));
        } catch (\Exception $e) {
            return $this->response->setJSON($this->setResponse(500, true, null, $e->getMessage()));
        }
    }

    public function getAdminLogs()
    {
        $data = $this->request->getJSON(true);
        $entityType = trim((string)($data['entityType'] ?? ''));
        $entityId = (int)($data['entityId'] ?? 0);
        $limit = max(1, min(100, (int)($data['limit'] ?? 25)));

        if ($entityType === '' || $entityId <= 0) {
            return $this->response->setJSON($this->setResponse(422, true, null, 'Datos de historial incompletos.'));
        }

        $this->ensureAdminLogsTable();

        $logsModel = new AdminLogsModel();
        $usersModel = new UsersModel();
        $logs = $logsModel->where('entity_type', $entityType)
            ->where('entity_id', $entityId)
            ->orderBy('created_at', 'DESC')
            ->orderBy('id', 'DESC')
            ->limit($limit)
            ->findAll();

        $actionLabels = [
            'create_field' => 'Creación de precio/espacio',
            'edit_field' => 'Edición de precio/espacio',
            'create_service' => 'Creación de servicio',
            'edit_service' => 'Edición de servicio',
            'change_price' => 'Cambio de tarifa',
            'create_booking' => 'Creación de reserva',
            'edit_booking' => 'Edición de reserva',
            'cancel_booking' => 'Reserva cancelada',
            'complete_payment' => 'Pago registrado',
            'booking_payment_approved' => 'Pago aprobado',
            'booking_payment_not_approved' => 'Pago no aprobado',
        ];

        $normalizeCompareValue = static function ($field, $value) {
            if ($value === null || $value === '') {
                return '';
            }
            $boolFields = ['disabled', 'ilumination', 'roofed', 'active', 'online_available', 'allows_quincho_addon', 'offer_active'];
            if (in_array((string)$field, $boolFields, true)) {
                $normalized = strtolower(trim((string)$value));
                return in_array($normalized, ['1', 'true', 'yes', 'si', 'on'], true) ? '1' : '0';
            }
            if (is_numeric($value)) {
                return number_format((float)$value, 4, '.', '');
            }
            return trim((string)$value);
        };

        $allowedFieldsByEntity = [
            'field' => ['name', 'service_type', 'block_minutes', 'floor_type', 'field_type', 'sizes', 'value', 'ilumination_value', 'disabled'],
            'service_price' => ['base_price', 'deposit_price', 'charge_type', 'active'],
            'service' => ['name', 'opening_time', 'closing_time', 'duration_minutes', 'active', 'online_available', 'allows_quincho_addon', 'color', 'offer_active', 'offer_text', 'discount_type', 'discount_value'],
            'booking' => ['date', 'id_field', 'time_from', 'time_until', 'payment', 'total', 'diference', 'payment_method', 'approved', 'mp', 'annulled', 'name', 'phone', 'locality', 'created_by_name', 'edited_by_name', 'edited_at', 'payment_delta'],
        ];
        $alwaysIncludeFieldsByEntity = [
            'field' => ['value', 'ilumination_value'],
        ];

        $responseRows = [];
        foreach ($logs as $log) {
            $oldData = json_decode((string)($log['old_data'] ?? ''), true);
            $newData = json_decode((string)($log['new_data'] ?? ''), true);
            if (!is_array($oldData)) $oldData = [];
            if (!is_array($newData)) $newData = [];

            $changed = [];
            $ignoredKeys = ['id', 'service_id', 'elements_rent', 'price_unit_label', 'updated_at', 'created_at', 'ilumination', 'roofed'];
            $allowedFields = $allowedFieldsByEntity[$entityType] ?? null;
            $keys = array_unique(array_merge(array_keys($oldData), array_keys($newData)));
            foreach ($keys as $key) {
                if (in_array($key, $ignoredKeys, true)) {
                    continue;
                }
                if (is_array($allowedFields) && !in_array($key, $allowedFields, true)) {
                    continue;
                }
                $oldValue = $oldData[$key] ?? null;
                $newValue = $newData[$key] ?? null;
                $alwaysInclude = in_array($key, $alwaysIncludeFieldsByEntity[$entityType] ?? [], true);
                if ($normalizeCompareValue($key, $oldValue) === $normalizeCompareValue($key, $newValue)) {
                    if (!$alwaysInclude) {
                        continue;
                    }
                }
                if ($alwaysInclude && !array_key_exists($key, $newData) && array_key_exists($key, $oldData)) {
                    $newValue = $oldValue;
                }
                if ($alwaysInclude && !array_key_exists($key, $oldData) && array_key_exists($key, $newData)) {
                    $oldValue = $newValue;
                }
                if (!$alwaysInclude && $normalizeCompareValue($key, $oldValue) === $normalizeCompareValue($key, $newValue)) {
                    continue;
                }
                $changed[] = [
                    'field' => (string)$key,
                    'old' => $oldValue,
                    'new' => $newValue,
                ];
            }

            $adminName = 'N/D';
            $adminId = (int)($log['admin_id'] ?? 0);
            if (!empty($log['admin_name'])) {
                $adminName = (string)$log['admin_name'];
            } elseif ($adminId > 0) {
                $admin = $usersModel->find($adminId);
                if ($admin) {
                    $adminName = $admin['name'] ?? $admin['user'] ?? ('#' . $adminId);
                }
            }

            $action = (string)($log['action'] ?? '');
            $responseRows[] = [
                'id' => (int)$log['id'],
                'action' => $action,
                'action_label' => $actionLabels[$action] ?? $action,
                'admin' => $adminName,
                'created_at' => $log['created_at'] ?? null,
                'host_name' => $log['host_name'] ?? null,
                'changes' => $changed,
            ];
        }

        return $this->response->setJSON($this->setResponse(null, null, $responseRows, 'Respuesta exitosa'));
    }

    public function configMpView()
    {
        return view('mercadoPago/config', ['errors' => []]);
    }

    public function configMp()
    {
        $mpKeysModel = new MercadoPagoKeysModel();

        $publicKey = $this->request->getVar('publicKeyMp');
        $accessToken = $this->request->getVar('accesTokenMp');

        $query = [
            'public_key'   => $publicKey,
            'access_token' => $accessToken,
        ];

        try {
            $existing = $mpKeysModel->first();
            if ($existing) {
                $mpKeysModel->update($existing['id'], $query);
            } else {
                $mpKeysModel->insert($query);
            }
            return redirect()->to('abmAdmin')->with('msg', ['type' => 'success', 'body' => 'Datos insertados con Ã©xito: ']);
        } catch (\Exception $e) {
            return redirect()->to('abmAdmin')->with('msg', ['type' => 'danger', 'body' => 'Error al insertar datos: ' . $e->getMessage()]);
        }
    }

    public function deleteUser($id)
    {
        $usersModel = new UsersModel();
        $isAjaxRequest = $this->request->isAJAX();
        try {
            $usersModel->update($id, ['active' => 0]);
            $deletedCurrentUser = (int) session()->get('id_user') === (int) $id;

            if ($deletedCurrentUser) {
                session()->destroy();
            }

            if ($isAjaxRequest) {
                return $this->response->setJSON([
                    'error' => false,
                    'message' => 'Usuario eliminado correctamente',
                    'user' => [
                        'id' => $id,
                    ],
                    'loggedOut' => $deletedCurrentUser,
                    'csrf' => [
                        'name' => csrf_token(),
                        'hash' => csrf_hash(),
                    ],
                ]);
            }

            return redirect()->to('abmAdmin')->with('msg', ['type' => 'success', 'body' => 'Usuario eliminado correctamente']);
        } catch (\Exception $e) {
            if ($isAjaxRequest) {
                return $this->response
                    ->setStatusCode(500)
                    ->setJSON([
                        'error' => true,
                        'message' => 'Error al eliminar usuario: ' . $e->getMessage(),
                        'csrf' => [
                            'name' => csrf_token(),
                            'hash' => csrf_hash(),
                        ],
                    ]);
            }

            return redirect()->to('abmAdmin')->with('msg', ['type' => 'danger', 'body' => 'Error al eliminar usuario: ' . $e->getMessage()]);
        }
    }

    public function setResponse($code = 200, $error = false, $data = null, $message = '')
    {
        $response = [
            'error' => $error,
            'code' => $code,
            'data' => $data,
            'message' => $message,
        ];

        return $response;
    }
}

