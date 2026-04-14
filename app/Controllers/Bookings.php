<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use App\Libraries\PrintBookings;
use App\Models\BookingSlotsModel;
use App\Models\BookingsModel;
use App\Models\CancelReservationsModel;
use App\Models\ConfigModel;
use App\Models\CustomersModel;
use App\Models\FieldsModel;
use App\Models\MercadoPagoModel;
use App\Models\PaymentsModel;
use App\Models\TimeModel;
use App\Models\UsersModel;
use CodeIgniter\I18n\Time;

class Bookings extends BaseController
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
        $fieldsModel = new FieldsModel();
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
            . "Teléfono: {$booking['phone']}\n"
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

    public function saveBooking()
    {
        $bookingsModel = new BookingsModel();
        $bookingSlotsModel = new BookingSlotsModel();
        $customersModel = new CustomersModel();

        $data = $this->request->getJSON();
        $db = \Config\Database::connect();
        $this->ensureLocalityExists($data->localidad ?? null);

        $queryBooking = [
            'date'                  => $data->fecha,
            'id_field'              => $data->cancha,
            'time_from'             => $data->horarioDesde,
            'time_until'            => $data->horarioHasta,
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

        if ($this->isClosedForDateField($data->fecha, $data->cancha)) {
            return $this->response->setJSON($this->setResponse(409, true, null, 'No se puede reservar: hay un cierre informado para esa fecha.'));
        }

        // Verificar si ya existe una reserva activa
        $existingBooking = $bookingsModel->where('date', $data->fecha)
            ->where('id_field', $data->cancha)
            ->where('time_from', $data->horarioDesde)
            ->where('time_until', $data->horarioHasta)
            ->where('annulled', 0) // Solo trae las no anuladas
            ->first();

        if ($existingBooking) {
            return $this->response->setJSON($this->setResponse(400, true, null, 'Ya existe una reserva activa para esa fecha, cancha y horario.'));
        }

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

                $slotData = [
                    'date' => $data->fecha,
                    'id_field' => $data->cancha,
                    'time_from' => $data->horarioDesde,
                    'time_until' => $data->horarioHasta,
                    'status' => 'pending',
                    'active' => 1,
                    'expires_at' => date('Y-m-d H:i:s', strtotime('+5 minutes')),
                    'created_at' => date('Y-m-d H:i:s'),
                ];

                $slotId = $bookingSlotsModel->insert($slotData, true);
                if (!$slotId) {
                    $db->transRollback();
                    return $this->response->setJSON($this->setResponse(409, true, null, 'El horario ya está en proceso de reserva.'));
                }

                $bookingsModel->insert($queryBooking);
                $bookingId = $bookingsModel->getInsertID();

                $bookingSlotsModel->update($slotId, ['booking_id' => $bookingId]);

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
            $found = false;

            foreach ($timeBookings as &$timeBooking) {
                if (intval($timeBooking['id_cancha']) === intval($slot['id_field'])) {
                    $indexFrom = array_search($slot['time_from'], $time);
                    $indexUntil = array_search($slot['time_until'], $time);

                    for ($currentTime = $indexFrom; $currentTime <= $indexUntil; $currentTime++) {
                        $timeBooking['time'][] = strval(sprintf("%02d", $time[$currentTime]));
                    }
                    $found = true;
                    break;
                }
            }

            if (!$found) {
                $reserva = [
                    'id_cancha' => $slot['id_field'],
                    'nombre_cancha' => $fieldsModel->getField($slot['id_field'])['name'],
                    'time' => [],
                ];

                $indexFrom = array_search($slot['time_from'], $time);
                $indexUntil = array_search($slot['time_until'], $time);

                for ($currentTime = $indexFrom; $currentTime <= $indexUntil; $currentTime++) {
                    $reserva['time'][] = strval(sprintf("%02d", $time[$currentTime]));
                }

                $timeBookings[] = $reserva;
            }
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
            'time_from' => $data->horarioDesde,
            'time_until' => $data->horarioHasta,
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
                $slotData = [
                    'date' => $data->fecha,
                    'id_field' => $data->cancha,
                    'time_from' => $data->horarioDesde,
                    'time_until' => $data->horarioHasta,
                    'status' => 'confirmed',
                    'active' => 1,
                    'expires_at' => null,
                    'created_at' => date('Y-m-d H:i:s'),
                    'booking_id' => $idBooking,
                ];

                $slotId = $bookingSlotsModel->insert($slotData, true);
                if (!$slotId) {
                    $db->transRollback();
                    return $this->response->setJSON($this->setResponse(409, true, null, 'El horario ya está ocupado o en proceso.'));
                }
            }

            $bookingsModel->update($idBooking, $queryUpdate);

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
            $bookingsModel->update($data->bookingId, ['mp' => $data->confirm]);

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

        $queryBooking = [
            'date'            => $data->fecha,
            'id_field'        => $data->cancha,
            'time_from'       => $data->horarioDesde,
            'time_until'      => $data->horarioHasta,
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

            $slotData = [
                'date' => $data->fecha,
                'id_field' => $data->cancha,
                'time_from' => $data->horarioDesde,
                'time_until' => $data->horarioHasta,
                'status' => 'confirmed',
                'active' => 1,
                'expires_at' => null,
                'created_at' => date('Y-m-d H:i:s'),
            ];

            $slotId = $bookingSlotsModel->insert($slotData, true);
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
            $bookingSlotsModel->update($slotId, ['booking_id' => $bookingId]);

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

        //Generar PDF
        $printData = [
            'nombre' => $booking['name'],
            'telefono' => $booking['phone'],
            'fecha' => $booking['date'],
            'horario' => $booking['time_from'] . 'hs' . ' a ' . $booking['time_until'] . 'hs',
            'cancha' => $fieldsModel->getField($booking['id_field'])['name'],
            'id_mercado_pago' => $mpPayment['payment_id'],
            'estado_pago' => $mpPayment['status'],
            'total_cancha' => '$' . $booking['total'],
            'pagado' => '$' . $booking['payment'],
            'saldo' => '$' . $booking['diference'],
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
            $pago = [
                'fecha' => date("d/m/Y", strtotime($payment['date'])),
                'pago' => $monto,
                'usuario' => $usersModel->getUserName($payment['id_user']) || 'No informado',
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
