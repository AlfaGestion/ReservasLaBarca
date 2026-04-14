<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use App\Libraries\MercadoPagoLibrary;
use App\Models\BookingSlotsModel;
use App\Models\BookingsModel;
use App\Models\CancelReservationsModel;
use App\Models\ConfigModel;
use App\Models\CustomersModel;
use App\Models\MercadoPagoModel;
use App\Models\MercadoPagoKeysModel;
use App\Models\PaymentsModel;
use App\Models\RateModel;

class MercadoPago extends BaseController
{
    private function createEmailService()
    {
        $email = \Config\Services::email();
        $email->SMTPTimeout = 8;

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
                $email = $this->createEmailService();
                $email->fromEmail = $account['fromEmail'] ?? $emailConfig->fromEmail;
                $email->fromName = $account['fromName'] ?? $emailConfig->fromName;
                $email->SMTPUser = $account['SMTPUser'] ?? $emailConfig->SMTPUser;
                $email->SMTPPass = $account['SMTPPass'] ?? $emailConfig->SMTPPass;
                $email->setFrom($email->fromEmail, $email->fromName);
                $email->setTo($to);
                $email->setSubject($subject);
                $email->setMessage($message);

                if ($email->send()) {
                    return true;
                }

                log_message('error', 'Fallo envio SMTP con ' . ($email->fromEmail ?? 'sin cuenta') . ': ' . $email->printDebugger(['headers']));
            } catch (\Throwable $e) {
                log_message('error', 'Fallo envio SMTP con ' . (($account['fromEmail'] ?? '') ?: 'sin cuenta') . ': ' . $e->getMessage());
            }
        }

        return false;
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
        if (empty($paymentId)) {
            return null;
        }

        $mpKeysModel = new MercadoPagoKeysModel();
        $mpKeys = $mpKeysModel->first();
        $token = $mpKeys['access_token'] ?? null;
        if (!$token) {
            return null;
        }

        $url = 'https://api.mercadopago.com/v1/payments/' . urlencode((string)$paymentId);
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json',
        ]);

        $verifySsl = getenv('MP_VERIFY_SSL');
        if ($verifySsl === '0' || strtolower((string)$verifySsl) === 'false') {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        }

        $response = curl_exec($ch);
        if ($response === false) {
            curl_close($ch);
            return null;
        }

        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ((int)$statusCode < 200 || (int)$statusCode >= 300) {
            return null;
        }

        $payload = json_decode($response, true);
        if (!is_array($payload)) {
            return null;
        }

        $amount = $payload['transaction_amount'] ?? null;
        if ($amount === null || $amount === '') {
            return null;
        }

        return (float)$amount;
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
            return;
        }

        $toEmails = array_values(array_filter(array_map(
            static fn($email) => filter_var(trim((string) $email), FILTER_VALIDATE_EMAIL) ?: null,
            explode(';', $toEmail)
        )));
        if ($toEmails === []) {
            return;
        }

        $bookingsModel = new BookingsModel();
        $fieldsModel = new \App\Models\FieldsModel();
        $booking = $bookingsModel->getBooking($bookingId);
        if (!$booking) {
            return;
        }

        $fieldName = $fieldsModel->getField($booking['id_field'])['name'] ?? 'N/D';
        $fecha = $booking['date'] ? date('d/m/Y', strtotime($booking['date'])) : 'N/D';
        $horario = ($booking['time_from'] ?? '') . ' a ' . ($booking['time_until'] ?? '');
        $localidad = $booking['locality'] ?? '';

        $message = "Nueva reserva\n\n"
            . "Nombre: {$booking['name']}\n"
            . "Telefono: {$booking['phone']}\n"
            . "Localidad: " . ($localidad !== '' ? $localidad : 'N/D') . "\n"
            . "Fecha: {$fecha}\n"
            . "Horario: {$horario}\n"
            . "Cancha: {$fieldName}\n";

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
        $this->sendEmailWithFallback($toEmails, "Reserva: {$subjectName} - {$subjectDate}", $message);
    }
    public function setPreference()
    {
        try {
            $rateModel = new RateModel();
            $rateRow = $rateModel->first();
            $bookingsModel = new BookingsModel();
            $data = $this->request->getJSON();
            $booking = $data->booking ?? null;
            $montoTotal = $data->amount ?? 0;
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
            if ($this->isClosedForDateField($bookingDate, $bookingField)) {
                return $this->response->setJSON($this->setResponse(409, true, null, 'No se puede reservar: hay un cierre informado para esa fecha.'));
            }

            $rate = $rateRow['value'];
            $montoParcial = (floatval($montoTotal) * floatval($rate)) / 100;

            // Crear slot pendiente antes de generar la preferencia
            $slotData = [
                'date' => $booking->fecha ?? $booking['fecha'] ?? null,
                'id_field' => $booking->cancha ?? $booking['cancha'] ?? null,
                'time_from' => $booking->horarioDesde ?? $booking['horarioDesde'] ?? null,
                'time_until' => $booking->horarioHasta ?? $booking['horarioHasta'] ?? null,
                'status' => 'pending',
                'active' => 1,
                'expires_at' => date('Y-m-d H:i:s', strtotime('+5 minutes')),
                'created_at' => date('Y-m-d H:i:s'),
            ];

            $slotId = $bookingSlotsModel->insert($slotData, true);
            if (!$slotId) {
                return $this->response->setJSON($this->setResponse(409, true, null, 'El horario ya fue tomado por otra reserva. Actualiza e intenta nuevamente.'));
            }

            $mp = new MercadoPagoLibrary();
            $mp->setPreference('Pago total de cancha', $montoTotal, 1);
            $preferenceIdTotal = $mp->preferenceId;

            $mp = new MercadoPagoLibrary();
            $mp->setPreference('Reserva de cancha', $montoParcial, 1);
            $preferenceIdParcial = $mp->preferenceId;

            $preferences = [
                'preferenceIdTotal' => $preferenceIdTotal,
                'preferenceIdParcial' => $preferenceIdParcial,
            ];

            $bookingArr = json_decode(json_encode($booking), true);
            $bookingArr['preferenceIdParcial'] = $preferenceIdParcial;
            $bookingArr['preferenceIdTotal'] = $preferenceIdTotal;
            $bookingArr['slotId'] = $slotId;

            // Crear reserva provisional antes del checkout para no depender de la redireccion de retorno.
            $existingBooking = $bookingsModel->where('id_preference_parcial', $preferenceIdParcial)
                ->orWhere('id_preference_total', $preferenceIdTotal)
                ->first();
            $bookingId = $existingBooking['id'] ?? null;

            if (!$existingBooking) {
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
                    'id_preference_parcial' => $preferenceIdParcial,
                    'id_preference_total' => $preferenceIdTotal,
                    'use_offer' => $bookingArr['oferta'] ?? 0,
                    'booking_time' => date("Y-m-d H:i:s"),
                    'mp' => 0,
                    'annulled' => 0,
                    'created_by_type' => 'CLIENTE',
                    'created_by_name' => 'CLIENTE',
                    'created_by_user_id' => null,
                ]);

                $bookingId = $bookingsModel->getInsertID();
                if ($bookingId) {
                    $bookingSlotsModel->update($slotId, ['booking_id' => $bookingId]);
                }
            }
            $preferences['bookingId'] = $bookingId;
            $bookingArr['bookingId'] = $bookingId;

            $intents = session()->get('mp_intents') ?? [];
            $intents[$preferenceIdParcial] = ['booking' => $bookingArr, 'paid_type' => 'parcial'];
            $intents[$preferenceIdTotal] = ['booking' => $bookingArr, 'paid_type' => 'total'];
            session()->set('mp_intents', $intents);

            return $this->response->setJSON($this->setResponse(null, null, $preferences, 'Respuesta exitosa'));
        } catch (\Throwable $e) {
            log_message('error', 'Error en setPreference: ' . $e->getMessage());
            return $this->response->setJSON($this->setResponse(409, true, null, 'El horario ya fue tomado por otra reserva. Actualiza e intenta nuevamente.'));
        }
    }
    public function success()
    {
        $mercadoPagoModel = new MercadoPagoModel();
        $bookingsModel = new BookingsModel();
        $customersModel = new CustomersModel();
        $bookingSlotsModel = new BookingSlotsModel();
        $paymentsModel = new PaymentsModel();

        $preferenceId = $this->request->getVar('preference_id');

        $existingBooking = null;
        $createdFromIntent = false;
        $booking = null;
        $mercadoPago = null;

        if (!empty($preferenceId)) {
            $paid = '';
            $approved = '';
            $sendEmail = false;

            $data = [
                'collection_id' => $this->request->getVar('collection_id'),
                'collection_status' => $this->request->getVar('collection_status'),
                'payment_id' => $this->request->getVar('payment_id'),
                'status' => $this->request->getVar('status'),
                'external_reference' => $this->request->getVar('external_reference'),
                'payment_type' => $this->request->getVar('payment_type'),
                'merchant_order_id' => $this->request->getVar('merchant_order_id'),
                'preference_id' => $this->request->getVar('preference_id'),
                'site_id' => $this->request->getVar('site_id'),
                'processing_mode' => $this->request->getVar('processing_mode'),
                'merchant_account_id' => $this->request->getVar('merchant_account_id'),
            ];

            if ($data['status'] == 'approved') $approved = 1;

            $existingBooking = $bookingsModel->where('id_preference_parcial', $preferenceId)
                ->orWhere('id_preference_total', $preferenceId)
                ->first();

            if (!$existingBooking) {
                $intents = session()->get('mp_intents') ?? [];
                $intent = $intents[$preferenceId] ?? null;

                if ($intent) {
                    $bookingData = $intent['booking'];
                    $paidType = $intent['paid_type'] ?? 'parcial';
                    $this->ensureLocalityExists($bookingData['localidad'] ?? null);

                    $paid = $paidType === 'total' ? $bookingData['total'] : $bookingData['parcial'];
                    $totalPayment = $paid == $bookingData['total'];

                    $phone = $bookingData['telefono'];
                    $customer = $customersModel->where('phone', $phone)->first();
                    if (!$customer) {
                        $customersModel->insert([
                            'name' => $bookingData['nombre'],
                            'phone' => $phone,
                            'offer' => 0,
                            'quantity' => 1,
                            'city' => $bookingData['localidad'] ?? null,
                        ]);
                        $customerId = $customersModel->getInsertID();
                    } else {
                        $customerId = $customer['id'];
                        $customersModel->update($customerId, [
                            'name' => $bookingData['nombre'],
                            'city' => $bookingData['localidad'] ?? null,
                        ]);
                        if (array_key_exists('quantity', $customer)) {
                            $customersModel->update($customerId, ['quantity' => $customer['quantity'] + 1]);
                        }
                    }

                    $existingBooking = $bookingsModel->where('date', $bookingData['fecha'])
                        ->where('id_field', $bookingData['cancha'])
                        ->where('time_from', $bookingData['horarioDesde'])
                        ->where('time_until', $bookingData['horarioHasta'])
                        ->where('annulled', 0)
                        ->first();

                    if (!$existingBooking) {
                        $slotId = $bookingData['slotId'] ?? null;
                        if (!$slotId) {
                            $slotData = [
                                'date' => $bookingData['fecha'],
                                'id_field' => $bookingData['cancha'],
                                'time_from' => $bookingData['horarioDesde'],
                                'time_until' => $bookingData['horarioHasta'],
                                'status' => 'confirmed',
                                'active' => 1,
                                'expires_at' => null,
                                'created_at' => date('Y-m-d H:i:s'),
                            ];

                            $slotId = $bookingSlotsModel->insert($slotData, true);
                            if (!$slotId) {
                                return view('mercadoPago/failure');
                            }
                        }

                        $bookingsModel->insert([
                            'date' => $bookingData['fecha'],
                            'id_field' => $bookingData['cancha'],
                            'time_from' => $bookingData['horarioDesde'],
                            'time_until' => $bookingData['horarioHasta'],
                            'name' => $bookingData['nombre'],
                            'phone' => $phone,
                            'locality' => $bookingData['localidad'] ?? null,
                            'payment' => $paid,
                            'approved' => 1,
                            'total' => $bookingData['total'],
                            'parcial' => $bookingData['parcial'],
                            'diference' => $bookingData['total'] - $paid,
                            'reservation' => $paid,
                            'total_payment' => $totalPayment,
                            'payment_method' => 'Mercado Pago',
                            'id_preference_parcial' => $bookingData['preferenceIdParcial'],
                            'id_preference_total' => $bookingData['preferenceIdTotal'],
                            'use_offer' => $bookingData['oferta'] ?? 0,
                            'booking_time' => date("Y-m-d H:i:s"),
                            'mp' => 1,
                            'annulled' => 0,
                            'id_customer' => $customerId,
                            'created_by_type' => 'CLIENTE',
                            'created_by_name' => 'CLIENTE',
                            'created_by_user_id' => null,
                        ]);
                        $bookingId = $bookingsModel->getInsertID();
                        $existingBooking = $bookingsModel->find($bookingId);
                        $bookingSlotsModel->update($slotId, [
                            'booking_id' => $bookingId,
                            'status' => 'confirmed',
                            'expires_at' => null,
                        ]);
                        $createdFromIntent = true;
                        $sendEmail = true;
                    }

                    unset($intents[$bookingData['preferenceIdParcial']]);
                    unset($intents[$bookingData['preferenceIdTotal']]);
                    session()->set('mp_intents', $intents);
                }
            }

            if (!$existingBooking) {
                log_message('error', 'MP success sin booking asociado. preference_id=' . ($preferenceId ?? 'N/A') . ' payment_id=' . ($data['payment_id'] ?? 'N/A'));
                return view('mercadoPago/failure');
            }

            if ($preferenceId == $existingBooking['id_preference_parcial']) {
                $paid = $existingBooking['parcial'];
            } else {
                $paid = $existingBooking['total'];
            }

            $paidByGateway = $this->getMercadoPagoPaidAmount($data['payment_id'] ?? null);
            if ($paidByGateway !== null && $paidByGateway > 0) {
                $paid = $paidByGateway;
            }

            $total_payment = $paid == $existingBooking['total'];

        $customer = $customersModel->where('phone', $existingBooking['phone'])->first();
        $customerId = $customer ? $customer['id'] : null;
        $this->ensureLocalityExists($existingBooking['locality'] ?? null);
        if ($customerId) {
            $customersModel->update($customerId, [
                'name' => $existingBooking['name'],
                'city' => $existingBooking['locality'] ?? null,
            ]);
        }

            $queryBooking = [
                'mp' => 1,
                'total_payment' => $total_payment,
                'diference' => $existingBooking['total'] - $paid,
                'reservation' => $paid,
                'payment' => $paid,
                'approved' => 1
            ];
            if ($customerId !== null) {
                $queryBooking['id_customer'] = $customerId;
            }

            $bookingsModel->update($existingBooking['id'], $queryBooking);
            $bookingSlotsModel->where('booking_id', $existingBooking['id'])
                ->where('active', 1)
                ->set(['status' => 'confirmed', 'expires_at' => null])
                ->update();
            if (!$createdFromIntent && $customer && array_key_exists('quantity', $customer)) {
                $customersModel->update($customer['id'], ['quantity' => $customer['quantity'] + 1]);
            }
            if (!$createdFromIntent && (int)($existingBooking['approved'] ?? 0) !== 1) {
                $sendEmail = true;
            }

            $data['id_booking'] = $existingBooking['id'];
            $mercadoPagoModel->insert($data);

            $alreadyStoredMpPayment = null;
            if (!empty($data['payment_id'])) {
                $alreadyStoredMpPayment = $paymentsModel
                    ->where('id_booking', $existingBooking['id'])
                    ->where('id_mercado_pago', $data['payment_id'])
                    ->first();
            }

            if (!$alreadyStoredMpPayment) {
                try {
                    $paymentsModel->insert([
                        'id_user' => session()->get('id_user') ?: null,
                        'id_booking' => $existingBooking['id'],
                        'id_customer' => $customerId,
                        'id_mercado_pago' => $data['payment_id'] ?? null,
                        'amount' => $paid,
                        'payment_method' => 'mercado_pago',
                        'date' => date('Y-m-d'),
                        'created_at' => date('Y-m-d H:i:s'),
                    ]);
                } catch (\Throwable $e) {
                    log_message('error', 'No se pudo registrar pago MP en payments para booking ' . $existingBooking['id'] . ': ' . $e->getMessage());
                }
            }

            $booking = $bookingsModel->find($existingBooking['id']);
            $mercadoPago =  $mercadoPagoModel->where('id_booking', $existingBooking['id'])->first();
            if ($sendEmail) {
                $this->sendBookingEmail($existingBooking['id']);
            }
        }

        if (!$existingBooking) {
            return view('mercadoPago/failure');
        }

        return view('mercadoPago/success', ['bookingId' => $existingBooking['id'], 'booking' => $booking, 'mercadoPago' => $mercadoPago]);
    }

    public function failure()
    {
        $mercadoPagoModel = new MercadoPagoModel();
        $bookingsModel = new BookingsModel();
        $bookingSlotsModel = new BookingSlotsModel();

        $preferenceId = $this->request->getVar('preference_id');


        if (!empty($preferenceId)) {
            $data = [
                'collection_id' => $this->request->getVar('collection_id'),
                'collection_status' => $this->request->getVar('collection_status'),
                'payment_id' => $this->request->getVar('payment_id'),
                'status' => $this->request->getVar('status'),
                'external_reference' => $this->request->getVar('external_reference'),
                'payment_type' => $this->request->getVar('payment_type'),
                'merchant_order_id' => $this->request->getVar('merchant_order_id'),
                'preference_id' => $this->request->getVar('preference_id'),
                'site_id' => $this->request->getVar('site_id'),
                'processing_mode' => $this->request->getVar('processing_mode'),
                'merchant_account_id' => $this->request->getVar('merchant_account_id'),
            ];

            $existingBooking = $bookingsModel->where('id_preference_parcial', $data['preference_id'])
                ->orWhere('id_preference_total', $data['preference_id'])
                ->first();

            if ($existingBooking) {
                if ($data['status'] != 'approved') {
                    // Si el usuario cierra el checkout o falla el pago, anulamos la reserva.
                    $bookingsModel->update($existingBooking['id'], ['approved' => 0, 'annulled' => 1]);
                    $this->expireActiveBookingSlots($bookingSlotsModel, [
                        'booking_id' => $existingBooking['id'],
                    ]);
                }
            }

            if ($existingBooking) {
                $data['id_booking'] = $existingBooking['id'];
                $mercadoPagoModel->insert($data);
            }
            if (!$existingBooking && $data['status'] != 'approved') {
                $intents = session()->get('mp_intents') ?? [];
                $intent = $intents[$data['preference_id']] ?? null;
                if ($intent && isset($intent['booking']['slotId'])) {
                    $this->expireActiveBookingSlots($bookingSlotsModel, [
                        'id' => $intent['booking']['slotId'],
                    ]);
                }
            }
        }

        return view('mercadoPago/failure');
    }

    public function cancelPendingMpReservation()
    {
        $bookingsModel = new BookingsModel();
        $bookingSlotsModel = new BookingSlotsModel();
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
                    ->set(['annulled' => 1, 'approved' => 0])
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
                        ->set(['annulled' => 1, 'approved' => 0])
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

