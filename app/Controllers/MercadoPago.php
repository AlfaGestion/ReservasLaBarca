<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use App\Libraries\AvailabilityService;
use App\Libraries\MercadoPagoLibrary;
use App\Models\BookingSlotsModel;
use App\Models\BookingsModel;
use App\Models\CancelReservationsModel;
use App\Models\ConfigModel;
use App\Models\CustomersModel;
use App\Models\FieldsModel;
use App\Models\MercadoPagoModel;
use App\Models\MercadoPagoKeysModel;
use App\Models\PaymentsModel;
use App\Models\RateModel;
use App\Models\ServicesModel;
use App\Models\TimeModel;

class MercadoPago extends BaseController
{
    private const PENDING_SLOT_MINUTES = 15;

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
    private function normalizeTime($time): string
    {
        $slotModel = new BookingSlotsModel();
        return $slotModel->normalizeTime($time);
    }

    private function extractBookingItems($booking): array
    {
        $get = static function ($source, $key) {
            return is_array($source) ? ($source[$key] ?? null) : ($source->{$key} ?? null);
        };

        $items = [[
            'fecha' => $get($booking, 'fecha'),
            'cancha' => $get($booking, 'cancha'),
            'horarioDesde' => $get($booking, 'horarioDesde'),
            'horarioHasta' => $get($booking, 'horarioHasta'),
        ]];

        $additional = $get($booking, 'additionalQuincho');
        $enabled = is_array($additional) ? ($additional['enabled'] ?? false) : ($additional->enabled ?? false);
        if ($additional && $enabled) {
            $items[] = [
                'fecha' => is_array($additional) ? ($additional['fecha'] ?? $get($booking, 'fecha')) : ($additional->fecha ?? $get($booking, 'fecha')),
                'cancha' => is_array($additional) ? ($additional['cancha'] ?? null) : ($additional->cancha ?? null),
                'horarioDesde' => is_array($additional) ? ($additional['horarioDesde'] ?? null) : ($additional->horarioDesde ?? null),
                'horarioHasta' => is_array($additional) ? ($additional['horarioHasta'] ?? null) : ($additional->horarioHasta ?? null),
            ];
        }

        return $items;
    }

    private function hasBookingOverlap(BookingsModel $bookingsModel, BookingSlotsModel $bookingSlotsModel, $date, $fieldId, $timeFrom, $timeUntil): bool
    {
        $timeFrom = $bookingSlotsModel->normalizeTime($timeFrom);
        $timeUntil = $bookingSlotsModel->normalizeTime($timeUntil);
        $pendingThreshold = date('Y-m-d H:i:s', strtotime('-' . self::PENDING_SLOT_MINUTES . ' minutes'));

        $bookings = $bookingsModel->where('date', $date)
            ->where('id_field', $fieldId)
            ->where('annulled', 0)
            ->groupStart()
                ->where('approved', 1)
                ->orWhere('payment >', 0)
                ->orWhere('total_payment', 1)
                ->orWhere('booking_time >=', $pendingThreshold)
            ->groupEnd()
            ->findAll();

        foreach ($bookings as $booking) {
            if ($bookingSlotsModel->rangesOverlap($timeFrom, $timeUntil, $booking['time_from'], $booking['time_until'])) {
                return true;
            }
        }

        return $bookingSlotsModel->hasActiveOverlap($date, $fieldId, $timeFrom, $timeUntil);
    }

    private function buildSlotData(array $item, string $status): array
    {
        return [
            'date' => $item['fecha'],
            'id_field' => $item['cancha'],
            'time_from' => $this->normalizeTime($item['horarioDesde']),
            'time_until' => $this->normalizeTime($item['horarioHasta']),
            'status' => $status,
            'active' => 1,
            'expires_at' => $status === 'pending' ? date('Y-m-d H:i:s', strtotime('+' . self::PENDING_SLOT_MINUTES . ' minutes')) : null,
            'created_at' => date('Y-m-d H:i:s'),
        ];
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

    private function releaseBookingSlot(BookingSlotsModel $bookingSlotsModel, int $slotId): void
    {
        $slot = $bookingSlotsModel->find($slotId);
        if (!$slot) {
            return;
        }

        $hasInactiveDuplicate = $bookingSlotsModel
            ->where('date', $slot['date'])
            ->where('id_field', $slot['id_field'])
            ->where('time_from', $slot['time_from'])
            ->where('time_until', $slot['time_until'])
            ->where('active', 0)
            ->where('id !=', $slotId)
            ->first();

        // La unique key tambien cubre active=0. Si ya existe un historico inactivo
        // para ese horario, actualizar otro registro a 0 rompe la restriccion.
        if ($hasInactiveDuplicate) {
            $bookingSlotsModel->delete($slotId);
            return;
        }

        $bookingSlotsModel->update($slotId, ['active' => 0, 'status' => 'cancelled']);
    }

    private function releaseActiveSlots(BookingSlotsModel $bookingSlotsModel, array $conditions): void
    {
        $builder = $bookingSlotsModel->where('active', 1);
        foreach ($conditions as $field => $value) {
            $builder->where($field, $value);
        }

        $slots = $builder->findAll();
        foreach ($slots as $slot) {
            $slotId = (int)($slot['id'] ?? 0);
            if ($slotId > 0) {
                $this->releaseBookingSlot($bookingSlotsModel, $slotId);
            }
        }
    }

    private function getMercadoPagoPaidAmount($paymentId)
    {
        $payment = $this->fetchMercadoPagoPaymentData($paymentId);
        if (!$payment) {
            return null;
        }

        $amount = $payment['transaction_amount'] ?? null;
        if ($amount === null || $amount === '') {
            return null;
        }

        return (float) $amount;
    }

    private function getMercadoPagoAccessToken(): ?string
    {
        $mpKeysModel = new MercadoPagoKeysModel();
        $mpKeys = $mpKeysModel->first();
        $token = $mpKeys['access_token'] ?? null;

        return is_string($token) && trim($token) !== '' ? trim($token) : null;
    }

    private function buildMercadoPagoLockName(string $paymentId): string
    {
        return 'mp_payment_' . preg_replace('/[^A-Za-z0-9_\-]/', '_', $paymentId);
    }

    private function acquireMercadoPagoLock(string $paymentId, int $timeoutSeconds = 10): bool
    {
        $lockName = $this->buildMercadoPagoLockName($paymentId);
        $row = \Config\Database::connect()->query('SELECT GET_LOCK(?, ?) AS lck', [$lockName, $timeoutSeconds])->getRowArray();

        return isset($row['lck']) && (int)$row['lck'] === 1;
    }

    private function releaseMercadoPagoLock(string $paymentId): void
    {
        \Config\Database::connect()->query('SELECT RELEASE_LOCK(?)', [$this->buildMercadoPagoLockName($paymentId)]);
    }

    private function logMercadoPagoEvent(string $level, string $message, array $context = []): void
    {
        if (!empty($context)) {
            $safeContext = [];
            foreach ($context as $key => $value) {
                if ($value === null || $value === '') {
                    continue;
                }
                $safeContext[$key] = is_scalar($value)
                    ? (string) $value
                    : json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }
            if (!empty($safeContext)) {
                $message .= ' ' . json_encode($safeContext, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }
        }

        log_message($level, $message);
    }

    private function buildMercadoPagoViewRecord(array $paymentData, ?array $storedMercadoPago = null): array
    {
        if (is_array($storedMercadoPago) && $storedMercadoPago !== []) {
            return $storedMercadoPago;
        }

        return [
            'collection_id' => isset($paymentData['collection_id']) ? (string) $paymentData['collection_id'] : (isset($paymentData['id']) ? (string) $paymentData['id'] : null),
            'collection_status' => isset($paymentData['collection_status']) ? (string) $paymentData['collection_status'] : (isset($paymentData['status']) ? (string) $paymentData['status'] : null),
            'payment_id' => isset($paymentData['id']) ? (string) $paymentData['id'] : null,
            'status' => isset($paymentData['status']) ? (string) $paymentData['status'] : null,
            'external_reference' => isset($paymentData['external_reference']) ? (string) $paymentData['external_reference'] : null,
            'payment_type' => isset($paymentData['payment_type']) ? (string) $paymentData['payment_type'] : null,
            'merchant_order_id' => isset($paymentData['merchant_order_id']) ? (string) $paymentData['merchant_order_id'] : null,
            'preference_id' => isset($paymentData['preference_id']) ? (string) $paymentData['preference_id'] : null,
            'site_id' => isset($paymentData['site_id']) ? (string) $paymentData['site_id'] : null,
            'processing_mode' => isset($paymentData['processing_mode']) ? (string) $paymentData['processing_mode'] : null,
            'merchant_account_id' => isset($paymentData['merchant_account_id']) ? (string) $paymentData['merchant_account_id'] : null,
            'id_booking' => isset($paymentData['id_booking']) ? (int) $paymentData['id_booking'] : null,
            'annulled' => 0,
        ];
    }

    private function fetchMercadoPagoPaymentData($paymentId): ?array
    {
        $paymentId = trim((string) $paymentId);
        if ($paymentId === '') {
            return null;
        }

        $token = $this->getMercadoPagoAccessToken();
        if (!$token) {
            $this->logMercadoPagoEvent('error', 'MP API sin access token', ['payment_id' => $paymentId]);
            return null;
        }

        $url = 'https://api.mercadopago.com/v1/payments/' . urlencode($paymentId);
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json',
        ]);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_TIMEOUT, 20);

        $verifySsl = getenv('MP_VERIFY_SSL');
        if ($verifySsl === '0' || strtolower((string) $verifySsl) === 'false') {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        }

        $response = curl_exec($ch);
        $curlError = curl_error($ch);
        $statusCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false) {
            $this->logMercadoPagoEvent('error', 'Error consultando MP API', [
                'payment_id' => $paymentId,
                'curl_error' => $curlError,
            ]);
            return null;
        }

        $payload = json_decode($response, true);
        if (!is_array($payload)) {
            $this->logMercadoPagoEvent('error', 'Respuesta invalida de MP API', [
                'payment_id' => $paymentId,
                'http_status' => $statusCode,
                'body' => substr((string) $response, 0, 500),
            ]);
            return null;
        }

        $payload['id'] = isset($payload['id']) ? (string) $payload['id'] : $paymentId;
        if (isset($payload['preference_id'])) {
            $payload['preference_id'] = (string) $payload['preference_id'];
        }
        if (isset($payload['external_reference'])) {
            $payload['external_reference'] = (string) $payload['external_reference'];
        }

        if ($statusCode < 200 || $statusCode >= 300) {
            $this->logMercadoPagoEvent('error', 'MP API respondio error', [
                'payment_id' => $paymentId,
                'http_status' => $statusCode,
                'mp_status' => $payload['status'] ?? null,
                'mp_detail' => $payload['status_detail'] ?? null,
            ]);
            return null;
        }

        return $payload;
    }

    private function extractMercadoPagoPaymentIdFromRequest(): ?string
    {
        $candidates = [
            $this->request->getVar('payment_id'),
            $this->request->getVar('collection_id'),
            $this->request->getVar('id'),
            $this->request->getVar('data_id'),
            $this->request->getVar('resource'),
        ];

        $body = trim((string) $this->request->getBody());
        if ($body !== '') {
            $json = json_decode($body, true);
            if (is_array($json)) {
                $candidates[] = $json['data']['id'] ?? null;
                $candidates[] = $json['id'] ?? null;
                $candidates[] = $json['payment_id'] ?? null;
                $candidates[] = $json['collection_id'] ?? null;
                $candidates[] = $json['data_id'] ?? null;
                $candidates[] = $json['resource'] ?? null;
            }

            $parsed = [];
            parse_str($body, $parsed);
            if (is_array($parsed)) {
                $candidates[] = $parsed['data']['id'] ?? null;
                $candidates[] = $parsed['id'] ?? null;
                $candidates[] = $parsed['payment_id'] ?? null;
                $candidates[] = $parsed['collection_id'] ?? null;
                $candidates[] = $parsed['data_id'] ?? null;
                $candidates[] = $parsed['resource'] ?? null;
            }
        }

        if (!empty($_SERVER['QUERY_STRING'])) {
            $query = [];
            parse_str((string) $_SERVER['QUERY_STRING'], $query);
            $candidates[] = $query['data']['id'] ?? null;
            $candidates[] = $query['id'] ?? null;
            $candidates[] = $query['payment_id'] ?? null;
            $candidates[] = $query['collection_id'] ?? null;
            $candidates[] = $query['data_id'] ?? null;
            $candidates[] = $query['resource'] ?? null;
        }

        foreach ($candidates as $candidate) {
            if ($candidate === null) {
                continue;
            }

            $candidate = trim((string) $candidate);
            if ($candidate !== '') {
                if (strpos($candidate, '/') !== false) {
                    $parts = array_values(array_filter(explode('/', $candidate), static function ($part) {
                        return trim((string) $part) !== '';
                    }));
                    $candidate = $parts ? (string) end($parts) : $candidate;
                }
                return $candidate;
            }
        }

        return null;
    }

    private function resolveMercadoPagoBookings(BookingsModel $bookingsModel, array $paymentData, ?string $requestPreferenceId = null): array
    {
        $bookings = [];
        $preferenceId = trim((string) ($requestPreferenceId ?: ($paymentData['preference_id'] ?? '')));

        if ($preferenceId !== '') {
            $bookings = $bookingsModel
                ->groupStart()
                    ->where('id_preference_parcial', $preferenceId)
                    ->orWhere('id_preference_total', $preferenceId)
                ->groupEnd()
                ->findAll();
        }

        if ($bookings === []) {
            $externalReference = trim((string) ($paymentData['external_reference'] ?? ''));
            if ($externalReference !== '' && ctype_digit($externalReference)) {
                $primaryBooking = $bookingsModel->find((int) $externalReference);
                if ($primaryBooking) {
                    $bookings[] = $primaryBooking;
                    $related = [];
                    $primaryPreferenceParcial = trim((string) ($primaryBooking['id_preference_parcial'] ?? ''));
                    $primaryPreferenceTotal = trim((string) ($primaryBooking['id_preference_total'] ?? ''));

                    if ($primaryPreferenceParcial !== '' || $primaryPreferenceTotal !== '') {
                        $relatedQuery = $bookingsModel->groupStart();
                        if ($primaryPreferenceParcial !== '') {
                            $relatedQuery->where('id_preference_parcial', $primaryPreferenceParcial);
                        }
                        if ($primaryPreferenceTotal !== '') {
                            $relatedQuery->orWhere('id_preference_total', $primaryPreferenceTotal);
                        }
                        $related = $relatedQuery->groupEnd()->findAll();
                    }

                    $description = 'Quincho adicional de la reserva #' . (int) $primaryBooking['id'];
                    $descriptionBooking = $bookingsModel->where('description', $description)->first();
                    if ($descriptionBooking) {
                        $related[] = $descriptionBooking;
                    }

                    $bookings = array_merge($bookings, $related);
                }
            }
        }

        if ($bookings === []) {
            $intents = session()->get('mp_intents') ?? [];
            if ($preferenceId !== '' && isset($intents[$preferenceId]['booking'])) {
                $bookingData = $intents[$preferenceId]['booking'];
                $fallbackBooking = [
                    'id' => (int) ($bookingData['bookingId'] ?? 0),
                    'date' => $bookingData['fecha'] ?? null,
                    'id_field' => $bookingData['cancha'] ?? null,
                    'time_from' => $bookingData['horarioDesde'] ?? null,
                    'time_until' => $bookingData['horarioHasta'] ?? null,
                    'name' => $bookingData['nombre'] ?? null,
                    'phone' => $bookingData['telefono'] ?? null,
                    'locality' => $bookingData['localidad'] ?? null,
                    'total' => $bookingData['total'] ?? null,
                    'parcial' => $bookingData['parcial'] ?? null,
                    'id_preference_parcial' => $bookingData['preferenceIdParcial'] ?? null,
                    'id_preference_total' => $bookingData['preferenceIdTotal'] ?? null,
                    'approved' => 0,
                    'annulled' => 0,
                    'mp' => 0,
                ];
                if (!empty($fallbackBooking['id'])) {
                    $bookings[] = $fallbackBooking;
                }
            }
        }

        $unique = [];
        foreach ($bookings as $booking) {
            $bookingId = (int) ($booking['id'] ?? 0);
            if ($bookingId <= 0 || isset($unique[$bookingId])) {
                continue;
            }
            $unique[$bookingId] = $booking;
        }

        return array_values($unique);
    }

    private function selectPrimaryMercadoPagoBooking(array $bookings, ?string $requestPreferenceId = null, ?string $externalReference = null): ?array
    {
        $requestPreferenceId = trim((string) $requestPreferenceId);
        $externalReference = trim((string) $externalReference);

        if ($requestPreferenceId !== '') {
            foreach ($bookings as $booking) {
                if (($booking['id_preference_parcial'] ?? null) === $requestPreferenceId || ($booking['id_preference_total'] ?? null) === $requestPreferenceId) {
                    return $booking;
                }
            }
        }

        if ($externalReference !== '' && ctype_digit($externalReference)) {
            foreach ($bookings as $booking) {
                if ((int) ($booking['id'] ?? 0) === (int) $externalReference) {
                    return $booking;
                }
            }
        }

        return $bookings[0] ?? null;
    }

    private function confirmApprovedBookingSlot(array $booking, BookingSlotsModel $bookingSlotsModel): bool
    {
        $bookingId = (int) ($booking['id'] ?? 0);
        if ($bookingId <= 0) {
            return false;
        }

        $slot = $bookingSlotsModel->where('booking_id', $bookingId)->first();
        $slotData = [
            'date' => $booking['date'] ?? null,
            'id_field' => $booking['id_field'] ?? null,
            'time_from' => $booking['time_from'] ?? null,
            'time_until' => $booking['time_until'] ?? null,
            'booking_id' => $bookingId,
            'status' => 'confirmed',
            'active' => 1,
            'expires_at' => null,
            'created_at' => date('Y-m-d H:i:s'),
        ];

        if ($slot) {
            $conflict = $bookingSlotsModel
                ->where('date', $slot['date'])
                ->where('id_field', $slot['id_field'])
                ->where('time_from', $slot['time_from'])
                ->where('time_until', $slot['time_until'])
                ->where('active', 1)
                ->where('id !=', $slot['id'])
                ->first();

            if ($conflict) {
                try {
                    $bookingSlotsModel->delete($conflict['id']);
                } catch (\Throwable $e) {
                    $this->logMercadoPagoEvent('error', 'No se pudo liberar un slot conflictivo al confirmar MP', [
                        'booking_id' => $bookingId,
                        'slot_id' => $slot['id'] ?? null,
                        'conflict_slot_id' => $conflict['id'] ?? null,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            try {
                return (bool) $bookingSlotsModel->update($slot['id'], [
                    'booking_id' => $bookingId,
                    'status' => 'confirmed',
                    'active' => 1,
                    'expires_at' => null,
                ]);
            } catch (\Throwable $e) {
                $this->logMercadoPagoEvent('error', 'No se pudo reconfirmar slot existente de MP', [
                    'booking_id' => $bookingId,
                    'slot_id' => $slot['id'] ?? null,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        try {
            if ($bookingSlotsModel->createSlot($slotData, $bookingId)) {
                return true;
            }
        } catch (\Throwable $e) {
            $this->logMercadoPagoEvent('error', 'No se pudo crear slot confirmado de MP', [
                'booking_id' => $bookingId,
                'error' => $e->getMessage(),
            ]);
        }

        $conflict = $bookingSlotsModel
            ->where('date', $slotData['date'])
            ->where('id_field', $slotData['id_field'])
            ->where('time_from', $slotData['time_from'])
            ->where('time_until', $slotData['time_until'])
            ->where('active', 1)
            ->first();

        if ($conflict && (int) ($conflict['booking_id'] ?? 0) !== $bookingId) {
            try {
                $bookingSlotsModel->delete($conflict['id']);
                if ($bookingSlotsModel->createSlot($slotData, $bookingId)) {
                    return true;
                }
            } catch (\Throwable $e) {
                $this->logMercadoPagoEvent('error', 'No se pudo reemplazar un slot conflictivo para confirmar MP', [
                    'booking_id' => $bookingId,
                    'conflict_slot_id' => $conflict['id'] ?? null,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $existingInactive = $bookingSlotsModel
            ->where('date', $slotData['date'])
            ->where('id_field', $slotData['id_field'])
            ->where('time_from', $slotData['time_from'])
            ->where('time_until', $slotData['time_until'])
            ->where('active', 0)
            ->first();

        if ($existingInactive) {
            try {
                return (bool) $bookingSlotsModel->update($existingInactive['id'], [
                    'booking_id' => $bookingId,
                    'status' => 'confirmed',
                    'active' => 1,
                    'expires_at' => null,
                ]);
            } catch (\Throwable $e) {
                $this->logMercadoPagoEvent('error', 'No se pudo restaurar un slot inactivo de MP', [
                    'booking_id' => $bookingId,
                    'slot_id' => $existingInactive['id'] ?? null,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return false;
    }

    private function confirmApprovedPayment(array $paymentData, ?string $requestPreferenceId = null, string $source = 'success'): array
    {
        $paymentId = trim((string) ($paymentData['id'] ?? ''));
        $preferenceId = trim((string) ($requestPreferenceId ?: ($paymentData['preference_id'] ?? '')));
        $externalReference = trim((string) ($paymentData['external_reference'] ?? ''));
        $status = strtolower(trim((string) ($paymentData['status'] ?? '')));
        $transactionAmount = isset($paymentData['transaction_amount']) ? (float) $paymentData['transaction_amount'] : 0.0;

        $result = [
            'confirmed' => false,
            'duplicate' => false,
            'email_sent' => false,
            'booking' => null,
            'mercado_pago' => null,
            'bookings' => [],
            'status' => $status,
            'payment_id' => $paymentId,
            'preference_id' => $preferenceId,
            'external_reference' => $externalReference,
            'transaction_amount' => $transactionAmount,
        ];

        if ($paymentId === '') {
            $this->logMercadoPagoEvent('error', 'MP approved sin payment_id valido', [
                'source' => $source,
                'preference_id' => $preferenceId,
                'external_reference' => $externalReference,
                'transaction_amount' => $transactionAmount,
            ]);
            return $result;
        }

        if ($status !== 'approved') {
            return $result;
        }

        if (!$this->acquireMercadoPagoLock($paymentId)) {
            $this->logMercadoPagoEvent('info', 'MP PAYMENT APPROVED ya estaba en proceso', [
                'payment_id' => $paymentId,
                'preference_id' => $preferenceId,
                'external_reference' => $externalReference,
                'source' => $source,
            ]);
            $result['duplicate'] = true;
            return $result;
        }

        try {
            $bookingsModel = new BookingsModel();
            $bookingSlotsModel = new BookingSlotsModel();
            $customersModel = new CustomersModel();
            $mercadoPagoModel = new MercadoPagoModel();
            $paymentsModel = new PaymentsModel();

            $bookings = $this->resolveMercadoPagoBookings($bookingsModel, $paymentData, $preferenceId);
            if ($bookings === []) {
                $this->logMercadoPagoEvent('error', 'MP PAYMENT APPROVED sin booking asociado', [
                    'payment_id' => $paymentId,
                    'preference_id' => $preferenceId,
                    'transaction_amount' => $transactionAmount,
                    'external_reference' => $externalReference,
                    'source' => $source,
                ]);
                return $result;
            }

            $primaryBooking = $this->selectPrimaryMercadoPagoBooking($bookings, $preferenceId, $externalReference);
            if (!$primaryBooking) {
                $primaryBooking = $bookings[0];
            }

            $primaryBookingId = (int) ($primaryBooking['id'] ?? 0);
            if ($primaryBookingId <= 0) {
                $this->logMercadoPagoEvent('error', 'MP PAYMENT APPROVED sin booking primario valido', [
                    'payment_id' => $paymentId,
                    'preference_id' => $preferenceId,
                    'transaction_amount' => $transactionAmount,
                    'external_reference' => $externalReference,
                    'source' => $source,
                ]);
                return $result;
            }

            $bookingAmount = (float) ($primaryBooking['total'] ?? 0);
            $paidAmount = round($transactionAmount, 2);
            if ($paidAmount <= 0) {
                $this->logMercadoPagoEvent('error', 'MP PAYMENT APPROVED sin transaction_amount valido', [
                    'payment_id' => $paymentId,
                    'preference_id' => $preferenceId,
                    'booking_id' => $primaryBookingId,
                    'source' => $source,
                ]);
                return $result;
            }

            $isFirstConfirmation = (int) ($primaryBooking['approved'] ?? 0) !== 1 && (int) ($primaryBooking['mp'] ?? 0) !== 1;
            $customerId = null;
            $phone = trim((string) ($primaryBooking['phone'] ?? ''));
            if ($phone !== '') {
                try {
                    $customer = $customersModel->where('phone', $phone)->first();
                    $customerPayload = [
                        'name' => $primaryBooking['name'] ?? null,
                        'city' => $primaryBooking['locality'] ?? null,
                    ];

                    if (!$customer) {
                        $customersModel->insert([
                            'name' => $primaryBooking['name'] ?? null,
                            'phone' => $phone,
                            'offer' => 0,
                            'quantity' => 1,
                            'city' => $primaryBooking['locality'] ?? null,
                        ]);
                        $customerId = $customersModel->getInsertID();
                    } else {
                        $customerId = (int) ($customer['id'] ?? 0);
                        if ($customerId > 0) {
                            if ($isFirstConfirmation && array_key_exists('quantity', $customer)) {
                                $customerPayload['quantity'] = ((int) $customer['quantity']) + 1;
                            }
                            $customersModel->update($customerId, $customerPayload);
                        }
                    }
                } catch (\Throwable $e) {
                    $this->logMercadoPagoEvent('error', 'No se pudo sincronizar customer para MP aprobado', [
                        'payment_id' => $paymentId,
                        'booking_id' => $primaryBookingId,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            $totalPayment = abs($paidAmount - $bookingAmount) < 0.01 ? 1 : 0;
            $bookingsModel->update($primaryBookingId, [
                'approved' => 1,
                'annulled' => 0,
                'mp' => 1,
                'payment_method' => 'Mercado Pago',
                'payment' => $paidAmount,
                'reservation' => $paidAmount,
                'diference' => round($bookingAmount - $paidAmount, 2),
                'total_payment' => $totalPayment,
                'id_customer' => $customerId ?: ($primaryBooking['id_customer'] ?? null),
            ]);

            foreach ($bookings as $booking) {
                $bookingId = (int) ($booking['id'] ?? 0);
                if ($bookingId <= 0 || $bookingId === $primaryBookingId) {
                    continue;
                }

                $bookingsModel->update($bookingId, [
                    'approved' => 1,
                    'annulled' => 0,
                    'mp' => 1,
                    'payment_method' => 'Mercado Pago',
                    'id_customer' => $customerId ?: ($booking['id_customer'] ?? null),
                ]);
            }

            $result['bookings'] = $bookings;

            if (!$this->confirmApprovedBookingSlot($primaryBooking, $bookingSlotsModel)) {
                $this->logMercadoPagoEvent('error', 'MP PAYMENT APPROVED sin slot confirmado para booking principal', [
                    'payment_id' => $paymentId,
                    'booking_id' => $primaryBookingId,
                    'preference_id' => $preferenceId,
                    'external_reference' => $externalReference,
                ]);
            }

            foreach ($bookings as $booking) {
                $bookingId = (int) ($booking['id'] ?? 0);
                if ($bookingId <= 0 || $bookingId === $primaryBookingId) {
                    continue;
                }
                if (!$this->confirmApprovedBookingSlot($booking, $bookingSlotsModel)) {
                    $this->logMercadoPagoEvent('error', 'MP PAYMENT APPROVED sin slot confirmado para booking hermano', [
                        'payment_id' => $paymentId,
                        'booking_id' => $bookingId,
                        'preference_id' => $preferenceId,
                        'external_reference' => $externalReference,
                    ]);
                }
            }

            $mpPayload = [
                'collection_id' => (string) ($paymentData['collection_id'] ?? $paymentId),
                'collection_status' => (string) ($paymentData['collection_status'] ?? $paymentData['status'] ?? 'approved'),
                'payment_id' => $paymentId,
                'status' => (string) ($paymentData['status'] ?? 'approved'),
                'external_reference' => $externalReference !== '' ? $externalReference : null,
                'payment_type' => $paymentData['payment_type'] ?? null,
                'merchant_order_id' => isset($paymentData['merchant_order_id']) ? (string) $paymentData['merchant_order_id'] : null,
                'preference_id' => $preferenceId !== '' ? $preferenceId : null,
                'site_id' => isset($paymentData['site_id']) ? (string) $paymentData['site_id'] : null,
                'processing_mode' => isset($paymentData['processing_mode']) ? (string) $paymentData['processing_mode'] : null,
                'merchant_account_id' => isset($paymentData['merchant_account_id']) ? (string) $paymentData['merchant_account_id'] : null,
                'id_booking' => $primaryBookingId,
                'annulled' => 0,
            ];

            try {
                $existingMp = $mercadoPagoModel->where('payment_id', $paymentId)->first();
                if ($existingMp) {
                    $mercadoPagoModel->update($existingMp['id'], $mpPayload);
                    $result['duplicate'] = true;
                } else {
                    $mercadoPagoModel->insert($mpPayload);
                }
            } catch (\Throwable $e) {
                $this->logMercadoPagoEvent('error', 'No se pudo registrar MP en mercado_pago', [
                    'payment_id' => $paymentId,
                    'booking_id' => $primaryBookingId,
                    'error' => $e->getMessage(),
                ]);
            }

            try {
                $existingPayment = $paymentsModel
                    ->where('id_booking', $primaryBookingId)
                    ->where('id_mercado_pago', $paymentId)
                    ->first();

                if (!$existingPayment) {
                    $paymentsModel->insert([
                        'id_user' => session()->get('id_user') ?: null,
                        'id_booking' => $primaryBookingId,
                        'id_customer' => $customerId ?: ($primaryBooking['id_customer'] ?? null),
                        'id_mercado_pago' => $paymentId,
                        'amount' => $paidAmount,
                        'payment_method' => 'mercado_pago',
                        'date' => date('Y-m-d'),
                        'created_at' => date('Y-m-d H:i:s'),
                    ]);
                }
            } catch (\Throwable $e) {
                $this->logMercadoPagoEvent('error', 'No se pudo registrar pago MP en payments', [
                    'payment_id' => $paymentId,
                    'booking_id' => $primaryBookingId,
                    'error' => $e->getMessage(),
                ]);
            }

            $booking = $bookingsModel->find($primaryBookingId);
            $mercadoPago = $mercadoPagoModel->where('payment_id', $paymentId)->first();
            if (!$mercadoPago) {
                $mercadoPago = $this->buildMercadoPagoViewRecord($paymentData, $mpPayload);
            }
            $result['booking'] = $booking;
            $result['mercado_pago'] = $mercadoPago;
            $result['confirmed'] = true;
            $result['email_sent'] = $isFirstConfirmation;

            if ($isFirstConfirmation) {
                $this->sendBookingEmail($primaryBookingId);
            } else {
                $this->logMercadoPagoEvent('info', 'MP PAYMENT APPROVED duplicado ignorado', [
                    'payment_id' => $paymentId,
                    'booking_id' => $primaryBookingId,
                    'preference_id' => $preferenceId,
                    'external_reference' => $externalReference,
                    'source' => $source,
                ]);
            }

            $this->logMercadoPagoEvent('info', 'MP PAYMENT APPROVED payment_id=' . $paymentId . ' preference_id=' . ($preferenceId !== '' ? $preferenceId : 'N/A') . ' booking_id=' . $primaryBookingId, [
                'source' => $source,
                'transaction_amount' => $paidAmount,
                'external_reference' => $externalReference !== '' ? $externalReference : null,
                'duplicate' => $result['duplicate'] ? '1' : '0',
            ]);

            return $result;
        } finally {
            $this->releaseMercadoPagoLock($paymentId);
        }
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

    private function getNocturnalHours(): array
    {
        $timeModel = new TimeModel();
        $openingTime = $timeModel->getOpeningTime();
        $timeRow = $timeModel->first();
        if (!$timeRow || empty($openingTime) || empty($timeRow['nocturnal_time'])) {
            return [];
        }

        $index = array_search($timeRow['nocturnal_time'], $openingTime, true);
        if ($index === false) {
            return [];
        }

        return array_slice($openingTime, (int)$index);
    }

    private function isNocturnalSlot(string $from, string $until, array $nocturnalHours): bool
    {
        if ($nocturnalHours === []) {
            return false;
        }
        $slotModel = new BookingSlotsModel();
        $fromHour = substr($slotModel->normalizeTime($from), 0, 2);
        $untilHour = substr($slotModel->normalizeTime($until), 0, 2);

        return in_array($fromHour, $nocturnalHours, true) && in_array($untilHour, $nocturnalHours, true);
    }

    private function calculateBookingTotal(array $items): float
    {
        $fieldsModel = new FieldsModel();
        $slotModel = new BookingSlotsModel();
        $nocturnalHours = $this->getNocturnalHours();
        $total = 0.0;

        foreach ($items as $item) {
            $field = $fieldsModel->getField((int)$item['cancha']);
            if (!$field) {
                continue;
            }
            $fromMinutes = $slotModel->timeToMinutes($item['horarioDesde']);
            $untilMinutes = $slotModel->timeToMinutes($item['horarioHasta']);
            if ($untilMinutes <= $fromMinutes) {
                $untilMinutes += 24 * 60;
            }
            $minutes = max(0, $untilMinutes - $fromMinutes);
            $block = (int)($field['duration_minutes'] ?? $field['block_minutes'] ?? 60);
            $units = $minutes / max(1, $block);
            $baseAmount = (float)($field['value'] ?? 0);
            $nightAmount = (float)($field['ilumination_value'] ?? 0);
            $amount = $this->isNocturnalSlot((string)$item['horarioDesde'], (string)$item['horarioHasta'], $nocturnalHours) && $nightAmount > 0
                ? $nightAmount
                : $baseAmount;

            $total += $units * $amount;
        }

        return round($total, 2);
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

    public function setPreference()
    {
        $slotId = null;
        $additionalSlotId = null;
        $additionalBookingId = null;
        $bookingId = null;
        $createdBookingInThisAttempt = false;
        try {
            $rateModel = new RateModel();
            $rateRow = $rateModel->first();
            $bookingsModel = new BookingsModel();
            $data = $this->request->getJSON();
            $booking = $data->booking ?? null;
            $montoTotal = 0;
            $bookingSlotsModel = new BookingSlotsModel();
            $localidad = null;
            if (is_object($booking) && isset($booking->localidad)) {
                $localidad = $booking->localidad;
            } elseif (is_array($booking) && isset($booking['localidad'])) {
                $localidad = $booking['localidad'];
            }
            $this->ensureLocalityExists($localidad);

            if (!$rateRow || !isset($rateRow['value'])) {
                return $this->response->setJSON($this->setResponse(400, true, null, 'No existe tasa de reserva configurada.'));
            }
            if (!$booking) {
                return $this->response->setJSON($this->setResponse(400, true, null, 'Faltan datos de la reserva.'));
            }

            $bookingDate = $booking->fecha ?? $booking['fecha'] ?? null;
            $bookingField = $booking->cancha ?? $booking['cancha'] ?? null;
            $items = $this->extractBookingItems($booking);
            $fieldsModel = new \App\Models\FieldsModel();
            $primaryField = null;
            foreach ($items as $index => $item) {
                $field = $fieldsModel->getField($item['cancha']);
                if (!$field) {
                    return $this->response->setJSON($this->setResponse(409, true, null, 'El servicio seleccionado no existe.'));
                }
                if ((int)($field['service_active'] ?? 1) !== 1 || (string)($field['disabled'] ?? '0') === '1') {
                    return $this->response->setJSON($this->setResponse(409, true, null, 'El servicio seleccionado no esta activo.'));
                }
                if ((int)($field['online_available'] ?? 1) !== 1) {
                    return $this->response->setJSON($this->setResponse(409, true, null, 'El servicio seleccionado no esta disponible para reservar online.'));
                }
                if ($index === 0) {
                    $primaryField = $field;
                } elseif (($field['service_type'] ?? '') !== 'quincho' || (int)($primaryField['allows_quincho_addon'] ?? 0) !== 1) {
                    return $this->response->setJSON($this->setResponse(409, true, null, 'El quincho adicional no esta habilitado para este servicio.'));
                }
                $from = $bookingSlotsModel->timeToMinutes($item['horarioDesde']);
                $until = $bookingSlotsModel->timeToMinutes($item['horarioHasta']);
                if ($until <= $from) {
                    $until += 24 * 60;
                }
                $duration = $until - $from;
                $blockMinutes = (int)($field['duration_minutes'] ?? $field['block_minutes'] ?? $field['slot_interval_minutes'] ?? $field['booking_interval_minutes'] ?? 60);
                if ($duration <= 0 || $duration !== max(1, $blockMinutes)) {
                    return $this->response->setJSON($this->setResponse(409, true, null, 'La duración seleccionada no es válida para el servicio.'));
                }
                if (AvailabilityService::isReservationInPast($item['fecha'], $item['horarioDesde'])) {
                    return $this->response->setJSON($this->setResponse(409, true, null, 'No se puede reservar en una fecha u horario ya pasados.'));
                }
                if ($this->isClosedForDateField($item['fecha'], $item['cancha'])) {
                    return $this->response->setJSON($this->setResponse(409, true, null, 'No se puede reservar: hay un cierre informado para esa fecha.'));
                }
                if ($this->hasBookingOverlap($bookingsModel, $bookingSlotsModel, $item['fecha'], $item['cancha'], $item['horarioDesde'], $item['horarioHasta'])) {
                    $msg = $index === 0 ? 'El horario ya fue tomado por otra reserva. Actualiza e intenta nuevamente.' : 'El quincho no está disponible en el horario seleccionado.';
                    return $this->response->setJSON($this->setResponse(409, true, null, $msg));
                }
            }

            $montoTotal = $this->calculateBookingTotal($items);
            if ($montoTotal <= 0) {
                return $this->response->setJSON($this->setResponse(409, true, null, 'No se pudo calcular el monto de la reserva.'));
            }

            $rate = $rateRow['value'];
            $montoParcial = (floatval($montoTotal) * floatval($rate)) / 100;
            $bookingArr = json_decode(json_encode($booking), true);
            $bookingArr['total'] = $montoTotal;
            $bookingArr['parcial'] = $montoParcial;
            $bookingArr['diferencia'] = $montoTotal;
            $bookingArr['reservacion'] = 0;

            // Crear la reserva provisional antes de generar las preferencias MP.
            $slotId = $bookingSlotsModel->createSlot($this->buildSlotData($items[0], 'pending'));
            if (!$slotId) {
                return $this->response->setJSON($this->setResponse(409, true, null, 'El horario ya fue tomado por otra reserva. Actualiza e intenta nuevamente.'));
            }

            $bookingsModel->insert([
                'date' => $bookingArr['fecha'] ?? null,
                'id_field' => $bookingArr['cancha'] ?? null,
                'time_from' => $bookingArr['horarioDesde'] ?? null,
                'time_until' => $bookingArr['horarioHasta'] ?? null,
                'name' => $bookingArr['nombre'] ?? null,
                'phone' => $bookingArr['telefono'] ?? null,
                'locality' => $bookingArr['localidad'] ?? null,
                'payment' => 0,
                'approved' => 0,
                'total' => $bookingArr['total'] ?? 0,
                'parcial' => $bookingArr['parcial'] ?? 0,
                'diference' => $bookingArr['total'] ?? 0,
                'reservation' => 0,
                'total_payment' => 0,
                'payment_method' => 'Mercado Pago',
                'id_preference_parcial' => '',
                'id_preference_total' => '',
                'use_offer' => $bookingArr['oferta'] ?? 0,
                'booking_time' => date('Y-m-d H:i:s'),
                'mp' => 0,
                'annulled' => 0,
                'created_by_type' => 'CLIENTE',
                'created_by_name' => 'CLIENTE',
                'created_by_user_id' => null,
            ]);

            $bookingId = $bookingsModel->getInsertID();
            $createdBookingInThisAttempt = !empty($bookingId);
            if (!$bookingId) {
                $this->releaseBookingSlot($bookingSlotsModel, (int) $slotId);
                return $this->response->setJSON($this->setResponse(409, true, null, 'No se pudo crear la reserva provisional.'));
            }

            $bookingSlotsModel->update($slotId, ['booking_id' => $bookingId]);
            $bookingArr['slotId'] = $slotId;
            $bookingArr['bookingId'] = $bookingId;

            if (count($items) > 1) {
                $additional = $items[1];
                $additionalSlotId = $bookingSlotsModel->createSlot($this->buildSlotData($additional, 'pending'));
                if (!$additionalSlotId) {
                    $this->releaseBookingSlot($bookingSlotsModel, (int) $slotId);
                    $bookingsModel->delete($bookingId);
                    return $this->response->setJSON($this->setResponse(409, true, null, 'El quincho no está disponible en el horario seleccionado.'));
                }

                $bookingsModel->insert([
                    'date' => $additional['fecha'],
                    'id_field' => $additional['cancha'],
                    'time_from' => $this->normalizeTime($additional['horarioDesde']),
                    'time_until' => $this->normalizeTime($additional['horarioHasta']),
                    'name' => $bookingArr['nombre'] ?? null,
                    'phone' => $bookingArr['telefono'] ?? null,
                    'locality' => $bookingArr['localidad'] ?? null,
                    'payment' => 0,
                    'approved' => 0,
                    'total' => 0,
                    'parcial' => 0,
                    'diference' => 0,
                    'reservation' => 0,
                    'total_payment' => 0,
                    'payment_method' => 'Mercado Pago',
                    'id_preference_parcial' => '',
                    'id_preference_total' => '',
                    'use_offer' => $bookingArr['oferta'] ?? 0,
                    'description' => 'Quincho adicional de la reserva #' . $bookingId,
                    'booking_time' => date('Y-m-d H:i:s'),
                    'mp' => 0,
                    'annulled' => 0,
                    'created_by_type' => 'CLIENTE',
                    'created_by_name' => 'CLIENTE',
                    'created_by_user_id' => null,
                ]);
                $additionalBookingId = $bookingsModel->getInsertID();
                if (!$additionalBookingId) {
                    $this->releaseBookingSlot($bookingSlotsModel, (int) $additionalSlotId);
                    $this->releaseBookingSlot($bookingSlotsModel, (int) $slotId);
                    $bookingsModel->delete($bookingId);
                    return $this->response->setJSON($this->setResponse(409, true, null, 'No se pudo crear el quincho adicional.'));
                }
                $bookingSlotsModel->update($additionalSlotId, ['booking_id' => $additionalBookingId]);
                $bookingArr['additionalSlotId'] = $additionalSlotId;
                $bookingArr['additionalBookingId'] = $additionalBookingId;
            }

            $mp = new MercadoPagoLibrary();
            $mp->setPreference('Pago total de reserva', $montoTotal, 1, ['external_reference' => (string) $bookingId]);
            $preferenceIdTotal = $mp->preferenceId;

            $mp = new MercadoPagoLibrary();
            $mp->setPreference('Reserva de servicio', $montoParcial, 1, ['external_reference' => (string) $bookingId]);
            $preferenceIdParcial = $mp->preferenceId;

            $preferences = [
                'preferenceIdTotal' => $preferenceIdTotal,
                'preferenceIdParcial' => $preferenceIdParcial,
                'bookingId' => $bookingId,
            ];

            $bookingsModel->update($bookingId, [
                'id_preference_parcial' => $preferenceIdParcial,
                'id_preference_total' => $preferenceIdTotal,
            ]);
            if (!empty($additionalBookingId)) {
                $bookingsModel->update($additionalBookingId, [
                    'id_preference_parcial' => $preferenceIdParcial,
                    'id_preference_total' => $preferenceIdTotal,
                ]);
            }

            $bookingArr['preferenceIdParcial'] = $preferenceIdParcial;
            $bookingArr['preferenceIdTotal'] = $preferenceIdTotal;
            $bookingArr['bookingId'] = $bookingId;

            $intents = session()->get('mp_intents') ?? [];
            $intents[$preferenceIdParcial] = ['booking' => $bookingArr, 'paid_type' => 'parcial'];
            $intents[$preferenceIdTotal] = ['booking' => $bookingArr, 'paid_type' => 'total'];
            session()->set('mp_intents', $intents);

            return $this->response->setJSON($this->setResponse(null, null, $preferences, 'Respuesta exitosa'));
        } catch (\Throwable $e) {
            try {
                if (!empty($additionalSlotId)) {
                    $this->releaseBookingSlot($bookingSlotsModel ?? new BookingSlotsModel(), (int)$additionalSlotId);
                }
                if (!empty($slotId)) {
                    $this->releaseBookingSlot($bookingSlotsModel ?? new BookingSlotsModel(), (int)$slotId);
                }
                if (!empty($additionalBookingId)) {
                    $bookingsModel = $bookingsModel ?? new BookingsModel();
                    $bookingsModel->delete((int)$additionalBookingId);
                }
                if (!empty($bookingId) && $createdBookingInThisAttempt) {
                    $bookingsModel = $bookingsModel ?? new BookingsModel();
                    $bookingsModel->delete((int)$bookingId);
                }
            } catch (\Throwable $inner) {
                log_message('error', 'Error limpiando slot/reserva fallida en setPreference: ' . $inner->getMessage());
            }
            log_message('error', 'Error en setPreference: ' . $e->getMessage());
            return $this->response->setJSON($this->setResponse(409, true, null, 'No se pudo iniciar el pago con Mercado Pago. Revisar credenciales/configuracion de MP e intentar nuevamente.'));
        }
    }
    public function success()
    {
        $requestPreferenceId = trim((string) $this->request->getVar('preference_id'));
        $paymentId = $this->extractMercadoPagoPaymentIdFromRequest();

        if (!$paymentId) {
            $this->logMercadoPagoEvent('warning', 'MP success sin payment_id', [
                'preference_id' => $requestPreferenceId !== '' ? $requestPreferenceId : null,
            ]);

            return view('mercadoPago/pending', [
                'title' => 'Pago en proceso',
                'message' => 'Estamos esperando la confirmacion oficial de Mercado Pago. Si el pago fue aprobado, la reserva se confirmara automaticamente.',
            ]);
        }

        $paymentData = $this->fetchMercadoPagoPaymentData($paymentId);
        if (!$paymentData) {
            return view('mercadoPago/pending', [
                'title' => 'Pago en proceso',
                'message' => 'No pudimos consultar Mercado Pago en este momento. La reserva seguira sincronizandose en segundo plano.',
            ]);
        }

        if (trim((string) ($paymentData['status'] ?? '')) === 'approved') {
            $result = $this->confirmApprovedPayment($paymentData, $requestPreferenceId, 'success');
            if (!empty($result['confirmed']) && !empty($result['booking']['id'])) {
                $mercadoPago = $this->buildMercadoPagoViewRecord($paymentData, $result['mercado_pago'] ?? null);
                return view('mercadoPago/success', [
                    'bookingId' => $result['booking']['id'],
                    'booking' => $result['booking'],
                    'mercadoPago' => $mercadoPago,
                ]);
            }

            $this->logMercadoPagoEvent('error', 'MP PAYMENT APPROVED sin booking confirmado desde success', [
                'payment_id' => $paymentId,
                'preference_id' => $requestPreferenceId !== '' ? $requestPreferenceId : null,
                'external_reference' => $paymentData['external_reference'] ?? null,
                'transaction_amount' => $paymentData['transaction_amount'] ?? null,
            ]);

            return view('mercadoPago/pending', [
                'title' => 'Pago aprobado, sincronizando reserva',
                'message' => 'Mercado Pago confirmo el pago, pero la reserva todavia no pudo recuperarse en la web. La operacion quedo registrada en los logs para revision.',
            ]);
        }

        return view('mercadoPago/pending', [
            'title' => 'Pago en proceso',
            'message' => 'Mercado Pago todavia no reporta el pago como aprobado. La reserva no se anulara automaticamente hasta que el backend confirme el estado final.',
        ]);
    }

    public function webhook()
    {
        $requestPreferenceId = trim((string) $this->request->getVar('preference_id'));
        $paymentId = $this->extractMercadoPagoPaymentIdFromRequest();

        $this->logMercadoPagoEvent('info', 'MP webhook recibido', [
            'payment_id' => $paymentId !== '' ? $paymentId : null,
            'preference_id' => $requestPreferenceId !== '' ? $requestPreferenceId : null,
        ]);

        if (!$paymentId) {
            return $this->response->setJSON($this->setResponse(null, null, ['processed' => false], 'Webhook recibido sin payment_id.'));
        }

        $paymentData = $this->fetchMercadoPagoPaymentData($paymentId);
        if (!$paymentData) {
            return $this->response->setJSON($this->setResponse(null, null, ['processed' => false], 'Webhook recibido pero no se pudo consultar el pago en Mercado Pago.'));
        }

        if (trim((string) ($paymentData['status'] ?? '')) === 'approved') {
            $result = $this->confirmApprovedPayment($paymentData, $requestPreferenceId, 'webhook');
            return $this->response->setJSON($this->setResponse(null, null, [
                'processed' => true,
                'confirmed' => !empty($result['confirmed']),
                'duplicate' => !empty($result['duplicate']),
            ], 'Webhook procesado.'));
        }

        return $this->response->setJSON($this->setResponse(null, null, [
            'processed' => true,
            'confirmed' => false,
            'status' => $paymentData['status'] ?? null,
        ], 'Webhook procesado.'));
    }

    public function failure()
    {
        $requestPreferenceId = trim((string) $this->request->getVar('preference_id'));
        $paymentId = $this->extractMercadoPagoPaymentIdFromRequest();

        if ($paymentId) {
            $paymentData = $this->fetchMercadoPagoPaymentData($paymentId);
            if ($paymentData) {
                $status = trim((string) ($paymentData['status'] ?? ''));
                if ($status === 'approved') {
                    $result = $this->confirmApprovedPayment($paymentData, $requestPreferenceId, 'failure');
                    if (!empty($result['confirmed']) && !empty($result['booking']['id'])) {
                        $mercadoPago = $this->buildMercadoPagoViewRecord($paymentData, $result['mercado_pago'] ?? null);
                        return view('mercadoPago/success', [
                            'bookingId' => $result['booking']['id'],
                            'booking' => $result['booking'],
                            'mercadoPago' => $mercadoPago,
                        ]);
                    }

                    $this->logMercadoPagoEvent('error', 'MP PAYMENT APPROVED sin booking confirmado desde failure', [
                        'payment_id' => $paymentId,
                        'preference_id' => $requestPreferenceId !== '' ? $requestPreferenceId : null,
                        'external_reference' => $paymentData['external_reference'] ?? null,
                        'transaction_amount' => $paymentData['transaction_amount'] ?? null,
                    ]);

                    return view('mercadoPago/pending', [
                        'title' => 'Pago aprobado, sincronizando reserva',
                        'message' => 'Mercado Pago confirmo el pago, pero la reserva todavia no pudo recuperarse en la web. La operacion quedo registrada en los logs para revision.',
                    ]);
                }

                if (in_array($status, ['rejected', 'cancelled', 'voided'], true)) {
                    return view('mercadoPago/failure');
                }

                return view('mercadoPago/pending', [
                    'title' => 'Pago pendiente',
                    'message' => 'Mercado Pago aun no confirmo el pago como aprobado. No se anulara automaticamente mientras el estado siga en proceso.',
                ]);
            }
        }

        return view('mercadoPago/pending', [
            'title' => 'Pago pendiente',
            'message' => 'No se pudo validar el estado oficial del pago en Mercado Pago. La reserva seguira pendiente hasta que el backend complete la verificacion.',
        ]);
    }

    public function cancelPendingMpReservation()
    {
        $bookingsModel = new BookingsModel();
        $bookingSlotsModel = new BookingSlotsModel();
        $mercadoPagoModel = new MercadoPagoModel();
        $paymentsModel = new PaymentsModel();
        $data = $this->request->getJSON();
        $bookingId = $data->bookingId ?? null;
        $prefParcial = $data->preferenceIdParcial ?? null;
        $prefTotal = $data->preferenceIdTotal ?? null;
        $telefono = $data->telefono ?? null;
        $fecha = $data->fecha ?? null;
        $cancha = $data->cancha ?? null;
        $horarioDesde = $data->horarioDesde ?? null;
        $horarioHasta = $data->horarioHasta ?? null;

        if (!$bookingId && !$prefParcial && !$prefTotal && (!$fecha || !$cancha || !$horarioDesde || !$horarioHasta)) {
            return $this->response->setJSON($this->setResponse(400, true, null, 'No se recibieron datos para cancelar.'));
        }

        try {
            $bookings = [];
            $bookingIds = [];
            $slotPairs = [];

            if ($bookingId) {
                $b = $bookingsModel->find($bookingId);
                if ($b) {
                    $bookings[] = $b;
                }
            }

            if ($prefParcial || $prefTotal) {
                $query = $bookingsModel->groupStart();
                if ($prefParcial) {
                    $query->where('id_preference_parcial', $prefParcial);
                }
                if ($prefTotal) {
                    $query->orWhere('id_preference_total', $prefTotal);
                }
                $query->groupEnd();
                $prefBookings = $query->findAll();
                if (!empty($prefBookings)) {
                    $bookings = array_merge($bookings, $prefBookings);
                }
            }

            if ($fecha && $cancha && $horarioDesde && $horarioHasta) {
                $slotBookings = $bookingsModel->where('date', $fecha)
                    ->where('id_field', $cancha)
                    ->where('time_from', $horarioDesde)
                    ->where('time_until', $horarioHasta)
                    ->findAll();
                if (!empty($slotBookings)) {
                    $bookings = array_merge($bookings, $slotBookings);
                }
            }

            $uniqueBookings = [];
            foreach ($bookings as $booking) {
                $id = (int)($booking['id'] ?? 0);
                if ($id <= 0 || isset($uniqueBookings[$id])) {
                    continue;
                }
                $uniqueBookings[$id] = $booking;
            }
            $bookings = array_values($uniqueBookings);

            foreach ($bookings as $booking) {
                $currentBookingId = (int) ($booking['id'] ?? 0);
                if ($currentBookingId <= 0) {
                    continue;
                }

                if ((int) ($booking['approved'] ?? 0) === 1 || (int) ($booking['mp'] ?? 0) === 1) {
                    $this->logMercadoPagoEvent('info', 'MP cancelPending ignorado porque la reserva ya esta aprobada', [
                        'booking_id' => $currentBookingId,
                        'preference_id_parcial' => $booking['id_preference_parcial'] ?? null,
                        'preference_id_total' => $booking['id_preference_total'] ?? null,
                    ]);
                    return $this->response->setJSON($this->setResponse(null, null, [
                        'cancelled' => false,
                        'approved' => true,
                        ], 'La reserva ya fue confirmada y no se puede cancelar.'));
                }

                $approvedMp = $mercadoPagoModel->where('id_booking', $currentBookingId)
                    ->where('status', 'approved')
                    ->first();
                $storedPayment = $paymentsModel->where('id_booking', $currentBookingId)->first();
                $paymentEvidence = $approvedMp ?: $storedPayment;
                $paymentId = '';
                $requestPreferenceId = null;

                if ($approvedMp) {
                    $paymentId = trim((string) ($approvedMp['payment_id'] ?? ''));
                    $requestPreferenceId = trim((string) ($approvedMp['preference_id'] ?? '')) ?: null;
                } elseif ($storedPayment) {
                    $paymentId = trim((string) ($storedPayment['id_mercado_pago'] ?? ''));
                }

                if ($paymentId !== '') {
                    $paymentData = $this->fetchMercadoPagoPaymentData($paymentId);
                    if ($paymentData && trim((string) ($paymentData['status'] ?? '')) === 'approved') {
                        $result = $this->confirmApprovedPayment($paymentData, $requestPreferenceId, 'cancelPending');
                        return $this->response->setJSON($this->setResponse(null, null, [
                            'cancelled' => false,
                            'approved' => true,
                            'confirmed' => !empty($result['confirmed']),
                            'duplicate' => !empty($result['duplicate']),
                        ], 'La reserva ya tenia un pago aprobado y fue confirmada.'));
                    }
                }

                if ($paymentEvidence) {
                    $this->logMercadoPagoEvent('info', 'MP cancelPending ignorado por evidencia de pago', [
                        'booking_id' => $currentBookingId,
                        'payment_id' => $paymentId !== '' ? $paymentId : null,
                        'preference_id' => $requestPreferenceId,
                    ]);
                    return $this->response->setJSON($this->setResponse(null, null, [
                        'cancelled' => false,
                        'approved' => true,
                    ], 'La reserva ya registra un pago y no se puede cancelar.'));
                }
            }

            foreach ($bookings as $booking) {
                $isApproved = isset($booking['approved']) && (int)$booking['approved'] === 1;
                if ($isApproved) {
                    continue;
                }

                $bookingIds[] = (int)$booking['id'];
                $slotPairs[] = [
                    'date' => $booking['date'],
                    'id_field' => $booking['id_field'],
                    'time_from' => $booking['time_from'],
                    'time_until' => $booking['time_until'],
                ];
            }

            if ($fecha && $cancha && $horarioDesde && $horarioHasta) {
                $slotPairs[] = [
                    'date' => $fecha,
                    'id_field' => $cancha,
                    'time_from' => $horarioDesde,
                    'time_until' => $horarioHasta,
                ];
            }

            if (!empty($bookingIds)) {
                $bookingIds = array_values(array_unique($bookingIds));
                $bookingsModel->whereIn('id', $bookingIds)
                    ->where('approved !=', 1)
                    ->set(['annulled' => 1, 'approved' => 0, 'mp' => 0])
                    ->update();

                foreach ($bookingIds as $currentBookingId) {
                    $this->releaseActiveSlots($bookingSlotsModel, ['booking_id' => $currentBookingId]);
                }
            }

            if (!empty($slotPairs)) {
                foreach ($slotPairs as $pair) {
                    $this->releaseActiveSlots($bookingSlotsModel, [
                        'date' => $pair['date'],
                        'id_field' => $pair['id_field'],
                        'time_from' => $pair['time_from'],
                        'time_until' => $pair['time_until'],
                    ]);

                    $bookingsModel->where('date', $pair['date'])
                        ->where('id_field', $pair['id_field'])
                        ->where('time_from', $pair['time_from'])
                        ->where('time_until', $pair['time_until'])
                        ->where('approved !=', 1)
                        ->set(['annulled' => 1, 'approved' => 0, 'mp' => 0])
                        ->update();
                }
            }

            $intents = session()->get('mp_intents') ?? [];
            if ($prefParcial && isset($intents[$prefParcial])) {
                $slotId = $intents[$prefParcial]['booking']['slotId'] ?? null;
                if ($slotId) {
                    $this->releaseBookingSlot($bookingSlotsModel, (int)$slotId);
                }
                unset($intents[$prefParcial]);
            }
            if ($prefTotal && isset($intents[$prefTotal])) {
                $slotId = $intents[$prefTotal]['booking']['slotId'] ?? null;
                if ($slotId) {
                    $this->releaseBookingSlot($bookingSlotsModel, (int)$slotId);
                }
                unset($intents[$prefTotal]);
            }
            session()->set('mp_intents', $intents);

            // Respuesta idempotente: si no hubo excepcion, consideramos cancelacion procesada.
            return $this->response->setJSON($this->setResponse(null, null, ['cancelled' => true], 'Reserva pendiente cancelada.'));
        } catch (\Exception $e) {
            return $this->response->setJSON($this->setResponse(500, true, null, $e->getMessage()));
        }
    }

    public function savePreferenceIds()
    {
        $data = $this->request->getJSON(true);
        $this->logMercadoPagoEvent('info', 'MP savePreferenceIds recibido', [
            'preference_id_parcial' => $data['preferenceIdParcial'] ?? null,
            'preference_id_total' => $data['preferenceIdTotal'] ?? null,
        ]);

        return $this->response->setJSON($this->setResponse(null, null, [
            'saved' => true,
        ], 'Preferencias registradas.'));
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

    public function verPruebas()
    {
        return view('superadmin/reportes');
    }
}

