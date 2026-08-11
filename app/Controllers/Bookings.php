<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use App\Libraries\AvailabilityService;
use App\Libraries\PrintBookings;
use App\Models\BookingSlotsModel;
use App\Models\BookingsModel;
use App\Models\AdminLogsModel;
use App\Models\CancelReservationsModel;
use App\Models\ConfigModel;
use App\Models\CustomersModel;
use App\Models\FieldsModel;
use App\Models\MercadoPagoModel;
use App\Models\PaymentsModel;
use App\Models\ServicesModel;
use App\Models\TimeModel;
use App\Models\UsersModel;
use CodeIgniter\I18n\Time;

class Bookings extends BaseController
{
    private function normalizeColorHex(?string $color): string
    {
        $value = strtoupper(trim((string)$color));
        return preg_match('/^#[0-9A-F]{6}$/', $value) ? $value : '#F39323';
    }

    private function bookingEmailHtml(array $booking, string $fieldName, string $serviceColor, string $fecha, string $horario, string $duracion, string $localidad): string
    {
        $cliente = htmlspecialchars((string)($booking['name'] ?? 'N/D'), ENT_QUOTES, 'UTF-8');
        $telefono = htmlspecialchars((string)($booking['phone'] ?? 'N/D'), ENT_QUOTES, 'UTF-8');
        $localidadLabel = htmlspecialchars($localidad !== '' ? $localidad : 'N/D', ENT_QUOTES, 'UTF-8');
        $tipoReserva = htmlspecialchars($fieldName, ENT_QUOTES, 'UTF-8');
        $fechaLabel = htmlspecialchars($fecha, ENT_QUOTES, 'UTF-8');
        $horarioLabel = htmlspecialchars($horario, ENT_QUOTES, 'UTF-8');
        $duracionLabel = htmlspecialchars($duracion, ENT_QUOTES, 'UTF-8');
        $total = format_price_ar((float)($booking['total'] ?? 0));
        $pagado = format_price_ar((float)($booking['payment'] ?? 0));
        $saldo = format_price_ar((float)($booking['diference'] ?? 0));
        $detalle = htmlspecialchars((string)($booking['description'] ?? 'Reserva'), ENT_QUOTES, 'UTF-8');

        return "
        <div style=\"margin:0;padding:24px;background:#f3f6fb;font-family:Segoe UI,Arial,sans-serif;color:#1f2937;\">
            <table role=\"presentation\" width=\"100%\" cellspacing=\"0\" cellpadding=\"0\" style=\"max-width:680px;margin:0 auto;background:#ffffff;border-radius:16px;overflow:hidden;border:1px solid #e5e7eb;\">
                <tr>
                    <td style=\"padding:22px 24px;background:linear-gradient(135deg,#0f172a,#1e3a8a);color:#ffffff;\">
                        <div style=\"font-size:22px;font-weight:700;\">Nueva reserva recibida</div>
                        <div style=\"font-size:13px;opacity:.9;margin-top:4px;\">Detalle completo de la operación</div>
                    </td>
                </tr>
                <tr>
                    <td style=\"padding:20px 24px;\">
                        <div style=\"margin-bottom:14px;\">
                            <span style=\"display:inline-block;background:{$serviceColor};color:#fff;padding:8px 12px;border-radius:999px;font-size:12px;font-weight:700;letter-spacing:.3px;\">{$tipoReserva}</span>
                        </div>
                        <table role=\"presentation\" width=\"100%\" cellspacing=\"0\" cellpadding=\"0\" style=\"font-size:14px;border-collapse:collapse;\">
                            <tr><td style=\"padding:8px 0;color:#6b7280;\">Cliente</td><td style=\"padding:8px 0;font-weight:600;\">{$cliente}</td></tr>
                            <tr><td style=\"padding:8px 0;color:#6b7280;\">Teléfono</td><td style=\"padding:8px 0;font-weight:600;\">{$telefono}</td></tr>
                            <tr><td style=\"padding:8px 0;color:#6b7280;\">Localidad</td><td style=\"padding:8px 0;font-weight:600;\">{$localidadLabel}</td></tr>
                            <tr><td style=\"padding:8px 0;color:#6b7280;\">Fecha</td><td style=\"padding:8px 0;font-weight:600;\">{$fechaLabel}</td></tr>
                            <tr><td style=\"padding:8px 0;color:#6b7280;\">Horario</td><td style=\"padding:8px 0;font-weight:600;\">{$horarioLabel}</td></tr>
                            <tr><td style=\"padding:8px 0;color:#6b7280;\">Duración</td><td style=\"padding:8px 0;font-weight:600;\">{$duracionLabel}</td></tr>
                            <tr><td style=\"padding:8px 0;color:#6b7280;\">Total</td><td style=\"padding:8px 0;font-weight:700;\">{$total}</td></tr>
                            <tr><td style=\"padding:8px 0;color:#6b7280;\">Pagado</td><td style=\"padding:8px 0;font-weight:700;color:#065f46;\">{$pagado}</td></tr>
                            <tr><td style=\"padding:8px 0;color:#6b7280;\">Saldo</td><td style=\"padding:8px 0;font-weight:700;color:#b45309;\">{$saldo}</td></tr>
                            <tr><td style=\"padding:8px 0;color:#6b7280;vertical-align:top;\">Detalle</td><td style=\"padding:8px 0;font-weight:600;\">{$detalle}</td></tr>
                        </table>
                    </td>
                </tr>
            </table>
        </div>";
    }

    private function logAdminAction(string $action, string $entityType, $entityId, $oldData = null, $newData = null): void
    {
        try {
            $db = \Config\Database::connect();
            if (! $db->tableExists('admin_logs')) {
                return;
            }
            $adminId = session()->get('id_user') ?: null;
            $adminName = session()->get('name') ?? session()->get('user') ?? null;
            if (!$adminName && !$adminId) {
                $adminName = 'CLIENTE WEB';
            }
            $payload = [
                'admin_id' => $adminId,
                'admin_name' => $adminName,
                'action' => $action,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'old_data' => $oldData === null ? null : json_encode($oldData, JSON_UNESCAPED_UNICODE),
                'new_data' => $newData === null ? null : json_encode($newData, JSON_UNESCAPED_UNICODE),
                'created_at' => date('Y-m-d H:i:s'),
            ];
            $tableFields = $db->getFieldNames('admin_logs');
            if (!is_array($tableFields) || empty($tableFields)) {
                return;
            }
            $allowed = array_flip($tableFields);
            foreach (array_keys($payload) as $key) {
                if (!isset($allowed[$key])) {
                    unset($payload[$key]);
                }
            }
            $db->table('admin_logs')->insert($payload);
        } catch (\Throwable $e) {
            log_message('error', 'No se pudo guardar admin log: ' . $e->getMessage());
        }
    }

    private function normalizeTime($time): string
    {
        $slotModel = new BookingSlotsModel();
        return $slotModel->normalizeTime($time);
    }

    private function hasBookingOverlap(BookingsModel $bookingsModel, BookingSlotsModel $bookingSlotsModel, $date, $fieldId, $timeFrom, $timeUntil, ?int $ignoreBookingId = null, bool $onlineOnly = true): bool
    {
        $timeFrom = $bookingSlotsModel->normalizeTime($timeFrom);
        $timeUntil = $bookingSlotsModel->normalizeTime($timeUntil);
        $pendingThreshold = date('Y-m-d H:i:s', strtotime('-15 minutes'));

        $builder = $bookingsModel->where('date', $date)
            ->where('id_field', $fieldId)
            ->where('annulled', 0)
            ->groupStart()
                ->where('approved', 1)
                ->orWhere('payment >', 0)
                ->orWhere('total_payment', 1)
                ->orWhere('booking_time >=', $pendingThreshold)
            ->groupEnd();

        if ($ignoreBookingId !== null) {
            $builder->where('id !=', $ignoreBookingId);
        }

        foreach ($builder->findAll() as $booking) {
            if ($bookingSlotsModel->rangesOverlap($timeFrom, $timeUntil, $booking['time_from'], $booking['time_until'])) {
                return true;
            }
        }

        return $bookingSlotsModel->hasActiveOverlap($date, $fieldId, $timeFrom, $timeUntil, $ignoreBookingId);
    }

    private function extractBookingItems($data): array
    {
        $items = [[
            'fecha' => $data->fecha ?? null,
            'cancha' => $data->cancha ?? null,
            'horarioDesde' => $data->horarioDesde ?? null,
            'horarioHasta' => $data->horarioHasta ?? null,
            'is_additional' => false,
        ]];

        $additional = $data->additionalQuincho ?? null;
        if ($additional && !empty($additional->enabled)) {
            $items[] = [
                'fecha' => $additional->fecha ?? ($data->fecha ?? null),
                'cancha' => $additional->cancha ?? null,
                'horarioDesde' => $additional->horarioDesde ?? null,
                'horarioHasta' => $additional->horarioHasta ?? null,
                'is_additional' => true,
            ];
        }

        return $items;
    }

    private function validateItemsAvailability(array $items, BookingsModel $bookingsModel, BookingSlotsModel $bookingSlotsModel, ?int $ignoreBookingId = null, bool $onlineOnly = true): ?string
    {
        $fieldsModel = new FieldsModel();
        $primaryField = null;
        foreach ($items as $index => $item) {
            if (empty($item['fecha']) || empty($item['cancha']) || empty($item['horarioDesde']) || empty($item['horarioHasta'])) {
                return 'Faltan datos de fecha, servicio u horario.';
            }
            $field = $fieldsModel->getField($item['cancha']);
            if (!$field) {
                return 'El servicio seleccionado no existe.';
            }
            if ((int)($field['service_active'] ?? 1) !== 1 || (string)($field['disabled'] ?? '0') === '1') {
                return 'El servicio seleccionado no esta activo.';
            }
            if ($onlineOnly && (int)($field['online_available'] ?? 1) !== 1) {
                return 'El servicio seleccionado no esta disponible para reservar online.';
            }
            if ($index === 0) {
                $primaryField = $field;
            } elseif (($field['service_type'] ?? '') !== 'quincho' || (int)($primaryField['allows_quincho_addon'] ?? 0) !== 1) {
                return 'El quincho adicional no esta habilitado para este servicio.';
            }
            $from = $bookingSlotsModel->timeToMinutes($item['horarioDesde']);
            $until = $bookingSlotsModel->timeToMinutes($item['horarioHasta']);
            if ($until <= $from) {
                $until += 24 * 60;
            }
            $duration = $until - $from;
            $blockMinutes = (int)($field['duration_minutes'] ?? $field['block_minutes'] ?? $field['slot_interval_minutes'] ?? $field['booking_interval_minutes'] ?? 60);
            if ($duration <= 0 || $duration !== max(1, $blockMinutes)) {
                return 'La duración seleccionada no es válida para el servicio.';
            }
            if (AvailabilityService::isReservationInPast($item['fecha'], $item['horarioDesde'])) {
                return 'No se puede reservar en una fecha u horario ya pasados.';
            }
            if ($this->isClosedForDateField($item['fecha'], $item['cancha'])) {
                return 'No se puede reservar: hay un cierre informado para esa fecha.';
            }
            if ($this->hasBookingOverlap($bookingsModel, $bookingSlotsModel, $item['fecha'], $item['cancha'], $item['horarioDesde'], $item['horarioHasta'], $ignoreBookingId, $onlineOnly)) {
                return $index === 0
                    ? 'El horario seleccionado ya está ocupado o en proceso.'
                    : 'El quincho no está disponible en el horario seleccionado.';
            }
        }

        return null;
    }

    private function buildSlotData(array $item, string $status, ?int $bookingId = null): array
    {
        $slot = [
            'date' => $item['fecha'],
            'id_field' => $item['cancha'],
            'time_from' => $this->normalizeTime($item['horarioDesde']),
            'time_until' => $this->normalizeTime($item['horarioHasta']),
            'status' => $status,
            'active' => 1,
            'expires_at' => $status === 'pending' ? date('Y-m-d H:i:s', strtotime('+15 minutes')) : null,
            'created_at' => date('Y-m-d H:i:s'),
        ];

        if ($bookingId !== null) {
            $slot['booking_id'] = $bookingId;
        }

        return $slot;
    }

    private function createEmailService(array $account = [])
    {
        $emailConfig = config('Email');
        $email = \Config\Services::email(null, false);
        $email->initialize([
            'protocol' => $emailConfig->protocol,
            'SMTPHost' => $emailConfig->SMTPHost,
            'SMTPUser' => $account['SMTPUser'] ?? $emailConfig->SMTPUser,
            'SMTPPass' => str_replace(' ', '', (string)($account['SMTPPass'] ?? $emailConfig->SMTPPass)),
            'SMTPPort' => $emailConfig->SMTPPort,
            'SMTPTimeout' => $emailConfig->SMTPTimeout ?: 8,
            'SMTPKeepAlive' => false,
            'SMTPCrypto' => $emailConfig->SMTPCrypto,
            'SMTPOptions' => $emailConfig->SMTPOptions,
            'mailType' => 'html',
            'charset' => $emailConfig->charset,
            'wordWrap' => $emailConfig->wordWrap,
            'CRLF' => $emailConfig->CRLF,
            'newline' => $emailConfig->newline,
            'validate' => $emailConfig->validate,
        ]);

        return $email;
    }

    private function sendEmailWithFallback($to, string $subject, string $message): bool
    {
        $emailConfig = config('Email');
        $accounts = $emailConfig->accounts ?? [];

        if ($accounts === []) {
            $accounts = [[
                'fromEmail' => $emailConfig->fromEmail,
                'fromName' => $emailConfig->fromName,
                'SMTPUser' => $emailConfig->SMTPUser,
                'SMTPPass' => $emailConfig->SMTPPass,
            ]];
        }

        foreach ($accounts as $account) {
            try {
                $email = $this->createEmailService($account);
                $fromEmail = $account['fromEmail'] ?? $emailConfig->fromEmail;
                $fromName = $account['fromName'] ?? $emailConfig->fromName;
                $email->setFrom($fromEmail, $fromName);
                $email->setTo($to);
                $email->setSubject($subject);
                $email->setMessage($message);

                if ($email->send()) {
                    return true;
                }

                log_message('error', 'Fallo envio SMTP con ' . ($fromEmail ?: 'sin cuenta') . ': ' . $email->printDebugger(['headers']));
            } catch (\Throwable $e) {
                log_message('error', 'Fallo envio SMTP con ' . (($account['fromEmail'] ?? '') ?: 'sin cuenta') . ': ' . $e->getMessage());
            }
        }

        return false;
    }

    private function parseEmailRecipients(string $toEmail): array
    {
        return array_values(array_filter(array_map(
            static fn($email) => filter_var(trim((string) $email), FILTER_VALIDATE_EMAIL) ?: null,
            preg_split('/[;,\s]+/', $toEmail) ?: []
        )));
    }

    private function isClosedForDateField($date, $fieldId)
    {
        if (empty($date)) {
            return false;
        }
        $cancelModel = new CancelReservationsModel();
        $closures = $cancelModel->where('cancel_date', $date)->findAll();
        if (empty($closures)) {
            return false;
        }
        foreach ($closures as $c) {
            if (empty($c['field_id'])) {
                return true;
            }
            if (!empty($fieldId) && !empty($c['field_id']) && (int)$c['field_id'] === (int)$fieldId) {
                return true;
            }
        }
        return false;
    }

    private function sendBookingEmail($bookingId)
    {
        $configModel = new ConfigModel();
        $toRow = $configModel->where('clave', 'email_reservas')->first();
        $toEmail = $toRow['valor'] ?? '';
        if (!is_string($toEmail) || trim($toEmail) === '') {
            log_message('warning', 'No se envia email de reserva: no hay destinatarios configurados en email_reservas.');
            return;
        }

        $toEmails = $this->parseEmailRecipients($toEmail);
        if ($toEmails === []) {
            log_message('warning', 'No se envia email de reserva: email_reservas no contiene destinatarios validos.');
            return;
        }

        $bookingsModel = new BookingsModel();
        $fieldsModel = new FieldsModel();
        $servicesModel = new ServicesModel();
        $booking = $bookingsModel->getBooking($bookingId);
        if (!$booking) {
            return;
        }

        $field = $fieldsModel->getField($booking['id_field']);
        $fieldName = $field['name'] ?? 'N/D';
        $serviceType = (string)($field['service_type'] ?? 'football');
        $service = $servicesModel->getByCode($serviceType);
        $serviceColor = $this->normalizeColorHex($service['color'] ?? null);
        $fecha = $booking['date'] ? date('d/m/Y', strtotime($booking['date'])) : 'N/D';
        $horario = ($booking['time_from'] ?? '') . ' a ' . ($booking['time_until'] ?? '');
        $slotModel = new BookingSlotsModel();
        $fromMinutes = $slotModel->timeToMinutes($booking['time_from'] ?? '00:00');
        $untilMinutes = $slotModel->timeToMinutes($booking['time_until'] ?? '00:00');
        if ($untilMinutes <= $fromMinutes) {
            $untilMinutes += 24 * 60;
        }
        $duracion = minutesToHuman($untilMinutes - $fromMinutes);
        $localidad = $booking['locality'] ?? '';
        $message = $this->bookingEmailHtml($booking, $fieldName, $serviceColor, $fecha, $horario, $duracion, $localidad);

        $caPath = ROOTPATH . 'cacert.pem';
        if (is_file($caPath)) {
            ini_set('openssl.cafile', $caPath);
            ini_set('openssl.capath', $caPath);
        }
        stream_context_set_default([
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true,
            ],
        ]);
        $subjectName = trim((string)($booking['name'] ?? 'Cliente'));
        $subjectDate = $booking['date'] ? date('d/m/Y', strtotime($booking['date'])) : 'Sin fecha';
        if (!$this->sendEmailWithFallback($toEmails, "Reserva: {$subjectName} - {$subjectDate}", $message)) {
            log_message('error', 'No se pudo enviar email de reserva #' . $bookingId . ' con ninguna cuenta SMTP configurada.');
        }
    }

    public function saveBooking()
    {
        $bookingsModel = new BookingsModel();
        $bookingSlotsModel = new BookingSlotsModel();
        $customersModel = new CustomersModel();

        $data = $this->request->getJSON();
        $db = \Config\Database::connect();
        $this->ensureLocalityExists($data->localidad ?? null);
        $items = $this->extractBookingItems($data);
        $availabilityError = $this->validateItemsAvailability($items, $bookingsModel, $bookingSlotsModel, null, true);
        if ($availabilityError !== null) {
            return $this->response->setJSON($this->setResponse(409, true, null, $availabilityError));
        }

        $queryBooking = [
            'date'                  => $data->fecha,
            'id_field'              => $data->cancha,
            'time_from'             => $this->normalizeTime($data->horarioDesde),
            'time_until'            => $this->normalizeTime($data->horarioHasta),
            'name'                  => $data->nombre,
            'phone'                 => $data->telefono,
            'locality'              => $data->localidad ?? null,
            'payment'               => $data->monto,
            'approved'              => 0,
            'total'                 => $data->total,
            'parcial'               => $data->parcial,
            'diference'             => $data->diferencia,
            'reservation'           => $data->reservacion,
            'total_payment'         => $data->pagoTotal,
            'payment_method'        => $data->metodoDePago,
            'id_preference_parcial' => $data->preferenceIdParcial,
            'id_preference_total'   => $data->preferenceIdTotal,
            'use_offer'             => $data->oferta,
            'booking_time'          => date("Y-m-d H:i:s"),
            'mp'                    => 0,
            'annulled'              => 0, // Aseguramos que este nuevo registro no esté anulado
            'created_by_type'       => 'CLIENTE',
            'created_by_name'       => 'CLIENTE',
            'created_by_user_id'    => null,
        ];

        $queryCustomer = [
            'name'  => $data->nombre,
            'phone' => $data->telefono,
            'offer' => 0,
            'city'  => $data->localidad ?? null,
        ];

        $existingCustomer = $customersModel->findAll();
        $exist = true;

        if ($existingCustomer) {
            foreach ($existingCustomer as $customer) {
                if ($customer['phone'] == $data->telefono) {
                    $exist = false;

                    $updateCustomer = [
                        'name' => $data->nombre,
                        'city' => $data->localidad ?? null,
                    ];

                    if ($data->oferta == 1) {
                        $updateCustomer['offer'] = 0;
                    }

                    $customersModel->update($customer['id'], $updateCustomer);
                    break;
                }
            }
        }

        if ($exist) {
            $customersModel->insert($queryCustomer);
        }

        try {
            if (count($queryBooking) != 0) {
                $db->transBegin();

                $slotId = $bookingSlotsModel->createSlot($this->buildSlotData($items[0], 'pending'));
                if (!$slotId) {
                    $db->transRollback();
                    return $this->response->setJSON($this->setResponse(409, true, null, 'El horario ya está en proceso de reserva.'));
                }

                $bookingsModel->insert($queryBooking);
                $bookingId = $bookingsModel->getInsertID();
                $this->logAdminAction('create_booking', 'booking', $bookingId, null, $queryBooking);

                $bookingSlotsModel->update($slotId, ['booking_id' => $bookingId]);

                if (count($items) > 1) {
                    $additional = $items[1];
                    $additionalQuery = $queryBooking;
                    $additionalQuery['date'] = $additional['fecha'];
                    $additionalQuery['id_field'] = $additional['cancha'];
                    $additionalQuery['time_from'] = $this->normalizeTime($additional['horarioDesde']);
                    $additionalQuery['time_until'] = $this->normalizeTime($additional['horarioHasta']);
                    $additionalQuery['total'] = 0;
                    $additionalQuery['parcial'] = 0;
                    $additionalQuery['diference'] = 0;
                    $additionalQuery['reservation'] = 0;
                    $additionalQuery['payment'] = 0;
                    $additionalQuery['description'] = trim(($additionalQuery['description'] ?? '') . ' Quincho adicional de la reserva #' . $bookingId);

                    $additionalSlotId = $bookingSlotsModel->createSlot($this->buildSlotData($additional, 'pending'));
                    if (!$additionalSlotId) {
                        $db->transRollback();
                        return $this->response->setJSON($this->setResponse(409, true, null, 'El quincho no está disponible en el horario seleccionado.'));
                    }

                    $bookingsModel->insert($additionalQuery);
                    $additionalBookingId = $bookingsModel->getInsertID();
                    $this->logAdminAction('create_booking', 'booking', $additionalBookingId, null, $additionalQuery);
                    $bookingSlotsModel->update($additionalSlotId, ['booking_id' => $additionalBookingId]);
                }

                $db->transCommit();
                $this->sendBookingEmail($bookingId);
                return $this->response->setJSON($this->setResponse(null, null, null, 'Respuesta exitosa'));
            }
        } catch (\Exception $e) {
            if ($db->transStatus() === false) {
                $db->transRollback();
            }
            return $this->response->setJSON($this->setResponse(404, true, null, $e->getMessage()));
        }
    }



    public function getBookings($fecha)
    {
        $bookingsModel = new BookingsModel();
        $bookingSlotsModel = new BookingSlotsModel();
        $fieldsModel = new FieldsModel();
        $timeModel = new TimeModel();

        $time = $timeModel->getOpeningTime();

        $occupied = [];
        if ($fecha != '') {
            $now = date('Y-m-d H:i:s');

            // Limpiar locks vencidos para no mostrar falsos bloqueos.
            $this->expireActiveBookingSlots($bookingSlotsModel, [
                'status' => 'pending',
                'expires_at <' => $now,
            ]);

            // 1) Reservas reales (tabla bookings): siempre bloquean si no están anuladas.
            $bookings = $bookingsModel->where('date', $fecha)
                ->where('annulled', 0)
                ->findAll();

            foreach ($bookings as $b) {
                $occupied[] = [
                    'id_field' => $b['id_field'],
                    'time_from' => $b['time_from'],
                    'time_until' => $b['time_until'],
                ];
            }

            // 2) Locks temporales (tabla booking_slots): solo pendientes vigentes.
            $pendingSlots = $bookingSlotsModel->where('date', $fecha)
                ->where('active', 1)
                ->where('status', 'pending')
                ->where('expires_at >=', $now)
                ->findAll();

            foreach ($pendingSlots as $s) {
                $occupied[] = [
                    'id_field' => $s['id_field'],
                    'time_from' => $s['time_from'],
                    'time_until' => $s['time_until'],
                ];
            }
        }

        $timeBookings = [];

        foreach ($occupied as $slot) {
            $field = $fieldsModel->getField($slot['id_field']);
            $timeBookings[] = [
                'id_cancha' => $slot['id_field'],
                'nombre_cancha' => $field['name'] ?? 'N/D',
                'service_type' => $field['service_type'] ?? 'football',
                'time_from' => $bookingSlotsModel->normalizeTime($slot['time_from']),
                'time_until' => $bookingSlotsModel->normalizeTime($slot['time_until']),
                'time' => [
                    $bookingSlotsModel->normalizeTime($slot['time_from']),
                    $bookingSlotsModel->normalizeTime($slot['time_until']),
                ],
            ];
        }

        try {
            return $this->response->setJSON($this->setResponse(null, null, $timeBookings, 'Respuesta exitosa'));
        } catch (\Exception $e) {
            return $this->response->setJSON($this->setResponse(404, true, null, $e->getMessage()));
        }
    }


    public function completePayment($id)
    {
        $bookingsModel = new BookingsModel();
        $paymentsModel = new PaymentsModel();
        $data = $this->request->getJSON();
        $booking = $bookingsModel->getBooking($id);

        // log_message('info', 'Datos recibidos: ' . print_r($idUser, true));

        $pagoTotal =  $data->pago + $booking['payment'] == $booking['total'] ? 1 : 0;

        $queryBookings = [
            'total_payment' => $pagoTotal,
            'payment' => $booking['payment'] + $data->pago,
            'diference' => $booking['total'] - ($booking['payment'] + $data->pago),
        ];

        $queryPayments = [
            'id_user' => $data->idUser,
            'id_booking' => $id,
            'id_customer' => $data->idCustomer,
            'amount' => $data->pago,
            'payment_method' => $data->medioPago,
            'date' => Time::now()->toDateString(),
            'created_at' => Time::now(),
        ];

        try {
            $bookingsModel->update($id, $queryBookings);
            $paymentsModel->insert($queryPayments);
            $this->logAdminAction('complete_payment', 'booking', $id, $booking, array_merge($queryBookings, [
                'payment_delta' => $data->pago,
                'payment_method' => $data->medioPago,
            ]));
            return  $this->response->setJSON($this->setResponse(null, null, null, 'Respuesta exitosa'));
        } catch (\Exception $e) {
            return  $this->response->setJSON($this->setResponse(404, true, null, $e->getMessage()));
        }
    }


    public function getBooking($id)
    {
        $bookingsModel = new BookingsModel();
        $booking = $bookingsModel->getBooking($id);

        if ($booking) {
            try {
                return  $this->response->setJSON($this->setResponse(null, null, $booking, 'Respuesta exitosa'));
            } catch (\Exception $e) {
                return  $this->response->setJSON($this->setResponse(404, true, null, $e->getMessage()));
            }
        }
    }

    public function getReports()
    {
        $paymentsModel = new PaymentsModel();
        $bookingsModel = new BookingsModel();
        $data = $this->request->getJSON();

        // 1. Limpieza básica de filtros
        $user = (empty($data->user) || $data->user == '') ? 'all' : $data->user;

        // 2. Consulta con JOINs para traer datos de Usuario y Cliente de un solo golpe
        $query = $paymentsModel->select('
            payments.date, 
            payments.amount, 
            payments.id_user, 
            payments.payment_method, 
            payments.id_mercado_pago,
            users.user as nombre_usuario, 
            customers.name as nombre_cliente, 
            customers.phone as telefono_cliente,
            bookings.id as booking_id,
            bookings.name as booking_name,
            bookings.phone as booking_phone,
            bookings.payment as booking_payment,
            bookings.total as booking_total,
            bookings.total_payment as booking_total_payment
        ')
            ->join('users', 'users.id = payments.id_user', 'left')
            ->join('customers', 'customers.id = payments.id_customer', 'left')
            ->join('bookings', 'bookings.id = payments.id_booking', 'left')
            ->where('payments.date >=', $data->fechaDesde)
            ->where('payments.date <=', $data->fechaHasta);

        if ($user !== 'all') {
            $query->where('payments.id_user', $user);
        }

        $paymentsResult = $query->findAll();

        // 3. Formateo de salida (mucho más ligero)
        $payments = array_map(function ($p) {
            $monto = (float)($p['amount'] ?? 0);
            $metodo = strtolower(str_replace(' ', '_', (string)($p['payment_method'] ?? '')));
            if ($monto <= 0 && $metodo === 'mercado_pago') {
                $monto = ($p['booking_total_payment'] ?? 0) ? ($p['booking_total'] ?? 0) : ($p['booking_payment'] ?? 0);
            }
            return [
                'fecha'           => date("d/m/Y", strtotime($p['date'])),
                'pago'            => $monto,
                'usuario'         => $p['nombre_usuario'] ?? 'N/A',
                'idUsuario'       => $p['id_user'],
                'cliente'         => $p['nombre_cliente'] ?? $p['booking_name'] ?? 'N/A',
                'telefonoCliente' => $p['telefono_cliente'] ?? $p['booking_phone'] ?? 'N/A',
                'metodoPago'      => $p['payment_method'],
                'idMercadoPago'   => $p['id_mercado_pago'],
                'bookingId'       => $p['booking_id'],
                'totalReserva'    => $p['booking_total'],
            ];
        }, $paymentsResult);

        // Agregar pagos de Mercado Pago que no estén en la tabla payments
        $mpBookings = $bookingsModel->select('bookings.date, bookings.payment, bookings.total, bookings.total_payment, bookings.payment_method, bookings.id, bookings.name as booking_name, bookings.phone as booking_phone, customers.name as customer_name, customers.phone as customer_phone')
            ->join('customers', 'customers.id = bookings.id_customer', 'left')
            ->join('payments', 'payments.id_booking = bookings.id', 'left')
            ->where('bookings.date >=', $data->fechaDesde)
            ->where('bookings.date <=', $data->fechaHasta)
            ->where('bookings.mp', 1)
            ->whereIn('bookings.payment_method', ['Mercado Pago', 'mercado_pago'])
            ->where('payments.id', null)
            ->findAll();

        foreach ($mpBookings as $b) {
            $monto = ($b['total_payment'] ?? 0) ? $b['total'] : $b['payment'];
            $payments[] = [
                'fecha'           => date("d/m/Y", strtotime($b['date'])),
                'pago'            => $monto,
                'usuario'         => 'CLIENTE',
                'idUsuario'       => null,
                'cliente'         => $b['customer_name'] ?? $b['booking_name'] ?? 'N/A',
                'telefonoCliente' => $b['customer_phone'] ?? $b['booking_phone'] ?? 'N/A',
                'metodoPago'      => 'mercado_pago',
                'idMercadoPago'   => null,
                'bookingId'       => $b['id'],
                'totalReserva'    => $b['total'],
            ];
        }

        // Agregar el pago de seña por Mercado Pago si existe y no está en payments
        $mpReservations = $bookingsModel->select('bookings.date, bookings.reservation, bookings.total, bookings.total_payment, bookings.id, bookings.name as booking_name, bookings.phone as booking_phone, customers.name as customer_name, customers.phone as customer_phone')
            ->join('customers', 'customers.id = bookings.id_customer', 'left')
            ->join('payments as pmp', "pmp.id_booking = bookings.id AND (pmp.payment_method = 'mercado_pago' OR pmp.payment_method = 'Mercado Pago')", 'left')
            ->where('bookings.date >=', $data->fechaDesde)
            ->where('bookings.date <=', $data->fechaHasta)
            ->where('bookings.mp', 1)
            ->where('bookings.reservation >', 0)
            ->where('bookings.reservation < bookings.total')
            ->where('pmp.id', null)
            ->findAll();

        foreach ($mpReservations as $b) {
            $payments[] = [
                'fecha'           => date("d/m/Y", strtotime($b['date'])),
                'pago'            => $b['reservation'],
                'usuario'         => 'CLIENTE',
                'idUsuario'       => null,
                'cliente'         => $b['customer_name'] ?? $b['booking_name'] ?? 'N/A',
                'telefonoCliente' => $b['customer_phone'] ?? $b['booking_phone'] ?? 'N/A',
                'metodoPago'      => 'mercado_pago',
                'idMercadoPago'   => null,
                'bookingId'       => $b['id'],
                'totalReserva'    => $b['total'],
            ];
        }

        // Ajuste de consistencia: garantizar que el pagado del reporte no sea menor al pago acumulado de la reserva.
        $paidByBooking = [];
        foreach ($payments as $p) {
            $bid = (int)($p['bookingId'] ?? 0);
            if ($bid <= 0) continue;
            $paidByBooking[$bid] = ($paidByBooking[$bid] ?? 0) + (float)($p['pago'] ?? 0);
        }

        $bookingsRangeQuery = $bookingsModel
            ->select('bookings.id, bookings.date, bookings.payment, bookings.total, bookings.payment_method, bookings.name as booking_name, bookings.phone as booking_phone, customers.name as customer_name, customers.phone as customer_phone')
            ->join('customers', 'customers.id = bookings.id_customer', 'left')
            ->where('bookings.date >=', $data->fechaDesde)
            ->where('bookings.date <=', $data->fechaHasta)
            ->where('bookings.annulled', 0);

        if ($user !== 'all') {
            $bookingsRangeQuery->where('bookings.created_by_user_id', $user);
        }

        $bookingsRange = $bookingsRangeQuery->findAll();

        foreach ($bookingsRange as $b) {
            $bookingId = (int)$b['id'];
            $bookingPaid = (float)($b['payment'] ?? 0);
            $alreadyPaid = (float)($paidByBooking[$bookingId] ?? 0);

            if ($bookingPaid > ($alreadyPaid + 0.0001)) {
                $missing = $bookingPaid - $alreadyPaid;
                $payments[] = [
                    'fecha'           => date("d/m/Y", strtotime($b['date'])),
                    'pago'            => $missing,
                    'usuario'         => 'AJUSTE',
                    'idUsuario'       => null,
                    'cliente'         => $b['customer_name'] ?? $b['booking_name'] ?? 'N/A',
                    'telefonoCliente' => $b['customer_phone'] ?? $b['booking_phone'] ?? 'N/A',
                    'metodoPago'      => $b['payment_method'] ?? 'N/D',
                    'idMercadoPago'   => null,
                    'bookingId'       => $bookingId,
                    'totalReserva'    => $b['total'],
                ];
                $paidByBooking[$bookingId] = $bookingPaid;
            }
        }

        // 4. Respuesta
        try {
            return $this->response->setJSON($this->setResponse(null, null, $payments, 'Respuesta exitosa'));
        } catch (\Exception $e) {
            return $this->response->setJSON($this->setResponse(500, true, null, $e->getMessage()));
        }
    }

    public function cancelBooking()
    {
        $mercadoPagoModel = new MercadoPagoModel();
        $bookingsModel = new BookingsModel();
        $bookingSlotsModel = new BookingSlotsModel();
        $data = $this->request->getJSON();
        $idBooking = $data->idBooking;
        $oldBooking = $bookingsModel->getBooking($idBooking);
        $mpPayment = $mercadoPagoModel->where('id_booking', $idBooking)->first();

        try {
            if (isset($mpPayment)) {
                $mercadoPagoModel->update($mpPayment['id'], ['annulled' => 1]);
            }
            $bookingsModel->update($idBooking, ['annulled' => 1]);
            $bookingSlotsModel->where('booking_id', $idBooking)
                ->where('active', 1)
                ->set(['active' => 0, 'status' => 'cancelled'])
                ->update();
            $this->logAdminAction('cancel_booking', 'booking', $idBooking, $oldBooking, ['annulled' => 1]);

            return  $this->response->setJSON($this->setResponse(null, null, null, 'Respuesta exitosa'));
        } catch (\Exception $e) {
            return  $this->response->setJSON($this->setResponse(404, true, null, $e->getMessage()));
        }
    }

    public function editBooking()
    {
        $bookingsModel = new BookingsModel();
        $bookingSlotsModel = new BookingSlotsModel();
        $data = $this->request->getJSON();
        $idBooking = $data->bookingId;
        $db = \Config\Database::connect();
        $this->ensureLocalityExists($data->localidad ?? null);

        $currentBooking = $bookingsModel->getBooking($idBooking);
        if (!$currentBooking) {
            return $this->response->setJSON($this->setResponse(404, true, null, 'Reserva no encontrada.'));
        }

        $queryUpdate = [
            'id_field' => $data->cancha,
            'diference' => $data->diferencia,
            'date' => $data->fecha,
            'time_from' => $this->normalizeTime($data->horarioDesde),
            'time_until' => $this->normalizeTime($data->horarioHasta),
            'total_payment' => $data->pagoTotal,
            'parcial' => $data->parcial,
            'total' => $data->total,
            'locality' => $data->localidad ?? null,
            'edited_by_user_id' => session()->get('id_user'),
            'edited_by_name' => session()->get('name') ?? session()->get('user'),
            'edited_at' => date('Y-m-d H:i:s'),
        ];

        try {
            $changedSlot = $currentBooking['date'] != $data->fecha
                || $currentBooking['id_field'] != $data->cancha
                || $currentBooking['time_from'] != $data->horarioDesde
                || $currentBooking['time_until'] != $data->horarioHasta;

            $db->transBegin();

            if ($changedSlot) {
                $availabilityError = $this->validateItemsAvailability([[
                    'fecha' => $data->fecha,
                    'cancha' => $data->cancha,
                    'horarioDesde' => $data->horarioDesde,
                    'horarioHasta' => $data->horarioHasta,
                ]], $bookingsModel, $bookingSlotsModel, (int)$idBooking, false);
                if ($availabilityError !== null) {
                    $db->transRollback();
                    return $this->response->setJSON($this->setResponse(409, true, null, $availabilityError));
                }

                $slotData = [
                    'date' => $data->fecha,
                    'id_field' => $data->cancha,
                    'time_from' => $this->normalizeTime($data->horarioDesde),
                    'time_until' => $this->normalizeTime($data->horarioHasta),
                    'status' => 'confirmed',
                    'active' => 1,
                    'expires_at' => null,
                    'created_at' => date('Y-m-d H:i:s'),
                    'booking_id' => $idBooking,
                ];

                $slotId = $bookingSlotsModel->createSlot($slotData, (int)$idBooking);
                if (!$slotId) {
                    $db->transRollback();
                    return $this->response->setJSON($this->setResponse(409, true, null, 'El horario ya está ocupado o en proceso.'));
                }
            }

            $bookingsModel->update($idBooking, $queryUpdate);
            $this->logAdminAction('edit_booking', 'booking', $idBooking, $currentBooking, $queryUpdate);

            if ($changedSlot) {
                $bookingSlotsModel->where('booking_id', $idBooking)
                    ->where('active', 1)
                    ->where('id !=', $slotId)
                    ->set(['active' => 0, 'status' => 'cancelled'])
                    ->update();
            }

            $db->transCommit();

            return  $this->response->setJSON($this->setResponse(null, null, null, 'Respuesta exitosa'));
        } catch (\Exception $e) {
            if ($db->transStatus() === false) {
                $db->transRollback();
            }
            return  $this->response->setJSON($this->setResponse(404, true, null, $e->getMessage()));
        }
    }

    public function getMpPayments()
    {
        $bookingsModel = new BookingsModel();
        $data = $this->request->getJSON();

        $bookings = $bookingsModel->where('date >=', $data->fechaDesde)
            ->where('date <=', $data->fechaHasta)
            ->findAll();

        $reservations = [];

        foreach ($bookings as $booking) {
            $fecha = date("d/m/Y", strtotime($booking['date']));
            $reservation = intval($booking['reservation']);

            if (array_key_exists($fecha, $reservations)) {
                $reservations[$fecha] += $reservation;
            } else {
                $reservations[$fecha] = $reservation;
            }
        }

        $result = [];

        foreach ($reservations as $fecha => $pago) {
            $result[] = [
                'fecha' => $fecha,
                'reserva' => $pago
            ];
        }

        try {
            return  $this->response->setJSON($this->setResponse(null, null, $result, 'Respuesta exitosa'));
        } catch (\Exception $e) {
            return  $this->response->setJSON($this->setResponse(404, true, null, $e->getMessage()));
        }
    }

    public function confirmMP()
    {
        $bookingsModel = new BookingsModel();
        $data = $this->request->getJSON();

        try {
            $currentBooking = $bookingsModel->getBooking($data->bookingId);
            $bookingsModel->update($data->bookingId, ['mp' => $data->confirm]);
            $this->logAdminAction($data->confirm ? 'booking_payment_approved' : 'booking_payment_not_approved', 'booking', $data->bookingId, $currentBooking, ['mp' => $data->confirm]);

            return $this->response->setJSON($this->setResponse(null, null, null, 'Respuesta exitosa'));
        } catch (\Exception $e) {
            return $this->response->setJSON($this->setResponse(404, true, null, $e->getMessage()));
        }
    }

    public function saveAdminBooking()
    {
        $bookingsModel = new BookingsModel();
        $bookingSlotsModel = new BookingSlotsModel();
        $customersModel = new CustomersModel();
        $data = $this->request->getJSON();
        $db = \Config\Database::connect();
        $this->ensureLocalityExists($data->localidad ?? null);
        $pagoTotal = $data->monto == $data->total ? 1 : 0;
        $items = $this->extractBookingItems($data);
        $availabilityError = $this->validateItemsAvailability($items, $bookingsModel, $bookingSlotsModel, null, false);
        if ($availabilityError !== null) {
            return $this->response->setJSON($this->setResponse(409, true, null, $availabilityError));
        }

        $queryBooking = [
            'date'            => $data->fecha,
            'id_field'        => $data->cancha,
            'time_from'       => $this->normalizeTime($data->horarioDesde),
            'time_until'      => $this->normalizeTime($data->horarioHasta),
            'name'            => $data->nombre,
            'phone'           => $data->telefono,
            'locality'        => $data->localidad ?? null,
            'payment'         => $data->monto,
            'total'           => $data->total,
            'description'     => $data->descripcion,
            'diference'       => $data->total - $data->monto,
            'total_payment'   => $pagoTotal,
            'payment_method'  => $data->metodoDePago,
            'approved'        => 1,
            'mp'              => 1,
            'annulled'        => 0,
            'created_by_type' => 'CREADO POR ADMIN',
            'created_by_name' => session()->get('name') ?? session()->get('user'),
            'created_by_user_id' => session()->get('id_user'),
        ];

        try {
            $db->transBegin();

            $slotId = $bookingSlotsModel->createSlot($this->buildSlotData($items[0], 'confirmed'));
            if (!$slotId) {
                $db->transRollback();
                return $this->response->setJSON($this->setResponse(409, true, null, 'Ya existe una reserva activa o en proceso para esa fecha, cancha y horario.'));
            }

            $insertOk = $bookingsModel->insert($queryBooking);
            if (!$insertOk) {
                $db->transRollback();
                return $this->response->setJSON($this->setResponse(500, true, null, 'No se pudo guardar la reserva. Verifica los datos e intenta nuevamente.'));
            }
            $bookingId = $bookingsModel->getInsertID();
            $this->logAdminAction('create_booking', 'booking', $bookingId, null, $queryBooking);
            $bookingSlotsModel->update($slotId, ['booking_id' => $bookingId]);

            if (count($items) > 1) {
                $additional = $items[1];
                $additionalQuery = $queryBooking;
                $additionalQuery['date'] = $additional['fecha'];
                $additionalQuery['id_field'] = $additional['cancha'];
                $additionalQuery['time_from'] = $this->normalizeTime($additional['horarioDesde']);
                $additionalQuery['time_until'] = $this->normalizeTime($additional['horarioHasta']);
                $additionalQuery['total'] = 0;
                $additionalQuery['payment'] = 0;
                $additionalQuery['diference'] = 0;
                $additionalQuery['description'] = trim(($additionalQuery['description'] ?? '') . ' Quincho adicional de la reserva #' . $bookingId);

                $additionalSlotId = $bookingSlotsModel->createSlot($this->buildSlotData($additional, 'confirmed'));
                if (!$additionalSlotId) {
                    $db->transRollback();
                    return $this->response->setJSON($this->setResponse(409, true, null, 'El quincho no está disponible en el horario seleccionado.'));
                }
                $bookingsModel->insert($additionalQuery);
                $additionalBookingId = $bookingsModel->getInsertID();
                $this->logAdminAction('create_booking', 'booking', $additionalBookingId, null, $additionalQuery);
                $bookingSlotsModel->update($additionalSlotId, ['booking_id' => $additionalBookingId]);
            }

            if (!empty($data->telefono)) {
                $existingCustomer = $customersModel->where('phone', $data->telefono)->first();
                $customerPayload = [
                    'name' => $data->nombre,
                    'phone' => $data->telefono,
                    'offer' => 0,
                    'city' => $data->localidad ?? null,
                ];

                if ($existingCustomer) {
                    $customersModel->update($existingCustomer['id'], [
                        'name' => $data->nombre,
                        'city' => $data->localidad ?? null,
                    ]);
                } else {
                    $customersModel->insert($customerPayload);
                }
            }

            $db->transCommit();
            $this->sendBookingEmail($bookingId);
            return  $this->response->setJSON($this->setResponse(null, null, null, 'Respuesta exitosa'));
        } catch (\Exception $e) {
            if ($db->transStatus() === false) {
                $db->transRollback();
            }
            return  $this->response->setJSON($this->setResponse(404, true, null, $e->getMessage()));
        }
    }

    public function bookingPdf($bookingId)
    {
        $pdfLibrary = new PrintBookings();
        $bookingsModel = new BookingsModel();
        $mercadoPagoModel = new MercadoPagoModel();
        $fieldsModel = new FieldsModel();

        $booking = $bookingsModel->getBooking($bookingId);
        if (!$booking) {
            return $this->response->setStatusCode(404)->setBody('Reserva no encontrada.');
        }
        $mpPayment = $mercadoPagoModel->where('id_booking', $bookingId)->first();
        $mpPayment = $mpPayment ?? ['payment_id' => 'N/A', 'status' => 'N/A'];
        $slotModel = new BookingSlotsModel();
        $fromMinutes = $slotModel->timeToMinutes($booking['time_from']);
        $untilMinutes = $slotModel->timeToMinutes($booking['time_until']);
        if ($untilMinutes <= $fromMinutes) {
            $untilMinutes += 24 * 60;
        }
        $durationMinutes = $untilMinutes - $fromMinutes;

        //Generar PDF
        $printData = [
            'nombre' => $booking['name'],
            'telefono' => $booking['phone'],
            'fecha' => $booking['date'],
            'horario' => $this->normalizeTime($booking['time_from']) . ' a ' . $this->normalizeTime($booking['time_until']),
            'duracion' => minutesToHuman($durationMinutes),
            'cancha' => $fieldsModel->getField($booking['id_field'])['name'],
            'id_mercado_pago' => $mpPayment['payment_id'],
            'estado_pago' => $mpPayment['status'],
            'total_cancha' => $booking['total'],
            'pagado' => $booking['payment'],
            'saldo' => $booking['diference'],
            'detalle' => $booking['description'] ?? '',
        ];

        $pdf = $pdfLibrary->renderBooking($printData);

        return $this->response
            ->setHeader('Content-Type', 'application/pdf')
            ->setHeader('Content-Disposition', 'attachment; filename="' . $pdf['name'] . '"')
            ->setBody($pdf['content']);
    }

    public function generateReportPdf($user, $fechaDesde, $fechaHasta)
    {
        $usersModel = new UsersModel();
        $paymentsModel = new PaymentsModel();
        $customersModel = new CustomersModel();
        $bookingsModel = new BookingsModel();
        $pdfLibrary = new PrintBookings();

        $query = $paymentsModel->select('
            payments.*,
            bookings.payment as booking_payment,
            bookings.total as booking_total,
            bookings.total_payment as booking_total_payment
        ')
            ->join('bookings', 'bookings.id = payments.id_booking', 'left')
            ->where('date >=', $fechaDesde)
            ->where('date <=', $fechaHasta);

        if ($user !== 'all') {
            $query->where('id_user', $user);
        }

        $paymentsResult = $query->findAll();

        $payments = [];

        foreach ($paymentsResult as $payment) {
            $monto = (float)($payment['amount'] ?? 0);
            $metodo = strtolower(str_replace(' ', '_', (string)($payment['payment_method'] ?? '')));
            if ($monto <= 0 && $metodo === 'mercado_pago') {
                $monto = ($payment['booking_total_payment'] ?? 0) ? ($payment['booking_total'] ?? 0) : ($payment['booking_payment'] ?? 0);
            }
            $userName = null;
            if (!empty($payment['id_user'])) {
                $userName = $usersModel->getUserName($payment['id_user']);
            }
            $pago = [
                'fecha' => date("d/m/Y", strtotime($payment['date'])),
                'pago' => $monto,
                'usuario' => $userName ?: 'No informado',
                'idUsuario' => $payment['id_user'],
                'cliente' => $customersModel->getCustomerName($payment['id_customer']),
                'telefonoCliente' => $customersModel->getCustomerPhone($payment['id_customer']),
                'metodoPago' => $payment['payment_method'],
                'idMercadoPago' => $payment['id_mercado_pago'],
            ];

            array_push($payments, $pago);
        }

        $mpBookings = $bookingsModel->select('bookings.date, bookings.payment, bookings.total, bookings.total_payment, bookings.payment_method, bookings.id, bookings.name as booking_name, bookings.phone as booking_phone, customers.name as customer_name, customers.phone as customer_phone')
            ->join('customers', 'customers.id = bookings.id_customer', 'left')
            ->join('payments', 'payments.id_booking = bookings.id', 'left')
            ->where('bookings.date >=', $fechaDesde)
            ->where('bookings.date <=', $fechaHasta)
            ->where('bookings.mp', 1)
            ->whereIn('bookings.payment_method', ['Mercado Pago', 'mercado_pago'])
            ->where('payments.id', null);

        if ($user !== 'all') {
            $mpBookings->where('bookings.created_by_user_id', $user);
        }

        $mpBookingsResult = $mpBookings->findAll();

        foreach ($mpBookingsResult as $b) {
            $monto = ($b['total_payment'] ?? 0) ? $b['total'] : $b['payment'];
            $pago = [
                'fecha' => date("d/m/Y", strtotime($b['date'])),
                'pago' => $monto,
                'usuario' => 'CLIENTE',
                'idUsuario' => null,
                'cliente' => $b['customer_name'] ?? $b['booking_name'] ?? 'N/A',
                'telefonoCliente' => $b['customer_phone'] ?? $b['booking_phone'] ?? 'N/A',
                'metodoPago' => 'mercado_pago',
                'idMercadoPago' => null,
            ];

            array_push($payments, $pago);
        }

        $mpReservations = $bookingsModel->select('bookings.date, bookings.reservation, bookings.total, bookings.total_payment, bookings.id, bookings.name as booking_name, bookings.phone as booking_phone, customers.name as customer_name, customers.phone as customer_phone')
            ->join('customers', 'customers.id = bookings.id_customer', 'left')
            ->join('payments as pmp', "pmp.id_booking = bookings.id AND (pmp.payment_method = 'mercado_pago' OR pmp.payment_method = 'Mercado Pago')", 'left')
            ->where('bookings.date >=', $fechaDesde)
            ->where('bookings.date <=', $fechaHasta)
            ->where('bookings.mp', 1)
            ->where('bookings.reservation >', 0)
            ->where('bookings.reservation < bookings.total')
            ->where('pmp.id', null);

        if ($user !== 'all') {
            $mpReservations->where('bookings.created_by_user_id', $user);
        }

        $mpReservationsResult = $mpReservations->findAll();

        foreach ($mpReservationsResult as $b) {
            $pago = [
                'fecha' => date("d/m/Y", strtotime($b['date'])),
                'pago' => $b['reservation'],
                'usuario' => 'CLIENTE',
                'idUsuario' => null,
                'cliente' => $b['customer_name'] ?? $b['booking_name'] ?? 'N/A',
                'telefonoCliente' => $b['customer_phone'] ?? $b['booking_phone'] ?? 'N/A',
                'metodoPago' => 'mercado_pago',
                'idMercadoPago' => null,
                'bookingId' => $b['id'],
                'totalReserva' => $b['total'],
            ];

            array_push($payments, $pago);
        }

        // Ajuste de consistencia para PDF: incluir diferencia faltante hasta bookings.payment.
        $paidByBooking = [];
        foreach ($payments as $p) {
            $bid = (int)($p['bookingId'] ?? 0);
            if ($bid <= 0) continue;
            $paidByBooking[$bid] = ($paidByBooking[$bid] ?? 0) + (float)($p['pago'] ?? 0);
        }

        $bookingsRangeQuery = $bookingsModel
            ->select('bookings.id, bookings.date, bookings.payment, bookings.total, bookings.payment_method, bookings.name as booking_name, bookings.phone as booking_phone, customers.name as customer_name, customers.phone as customer_phone')
            ->join('customers', 'customers.id = bookings.id_customer', 'left')
            ->where('bookings.date >=', $fechaDesde)
            ->where('bookings.date <=', $fechaHasta)
            ->where('bookings.annulled', 0);

        if ($user !== 'all') {
            $bookingsRangeQuery->where('bookings.created_by_user_id', $user);
        }

        $bookingsRange = $bookingsRangeQuery->findAll();

        foreach ($bookingsRange as $b) {
            $bookingId = (int)$b['id'];
            $bookingPaid = (float)($b['payment'] ?? 0);
            $alreadyPaid = (float)($paidByBooking[$bookingId] ?? 0);
            if ($bookingPaid > ($alreadyPaid + 0.0001)) {
                $missing = $bookingPaid - $alreadyPaid;
                $payments[] = [
                    'fecha' => date("d/m/Y", strtotime($b['date'])),
                    'pago' => $missing,
                    'usuario' => 'AJUSTE',
                    'idUsuario' => null,
                    'cliente' => $b['customer_name'] ?? $b['booking_name'] ?? 'N/A',
                    'telefonoCliente' => $b['customer_phone'] ?? $b['booking_phone'] ?? 'N/A',
                    'metodoPago' => $b['payment_method'] ?? 'N/D',
                    'idMercadoPago' => null,
                    'bookingId' => $bookingId,
                    'totalReserva' => $b['total'],
                ];
                $paidByBooking[$bookingId] = $bookingPaid;
            }
        }

        $pdf = $pdfLibrary->renderReports($payments);

        return $this->response
            ->setHeader('Content-Type', 'application/pdf')
            ->setHeader('Content-Disposition', 'attachment; filename="' . $pdf['name'] . '"')
            ->setBody($pdf['content']);
    }

    public function generatePaymentsReportPdf($fechaDesde, $fechaHasta)
    {
        $bookingsModel = new BookingsModel();
        $pdfLibrary = new PrintBookings();

        $bookings = $bookingsModel->where('date >=', $fechaDesde)
            ->where('date <=', $fechaHasta)
            ->findAll();

        $reservations = [];

        foreach ($bookings as $booking) {
            $fecha = date("d/m/Y", strtotime($booking['date']));
            $reservation = intval($booking['reservation']);

            if (array_key_exists($fecha, $reservations)) {
                $reservations[$fecha] += $reservation;
            } else {
                $reservations[$fecha] = $reservation;
            }
        }

        $result = [];

        foreach ($reservations as $fecha => $pago) {
            $result[] = [
                'fecha' => $fecha,
                'reserva' => $pago
            ];
        }

        $pdf = $pdfLibrary->renderPaymentsReports($result);

        return $this->response
            ->setHeader('Content-Type', 'application/pdf')
            ->setHeader('Content-Disposition', 'attachment; filename="' . $pdf['name'] . '"')
            ->setBody($pdf['content']);
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
