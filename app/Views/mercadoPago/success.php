<?php

use App\Models\FieldsModel;

$fieldsModel = new FieldsModel()

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pago aprobado!</title>
    <script src="https://kit.fontawesome.com/9bae38f407.js" crossorigin="anonymous"></script>
    <link rel="stylesheet" href="<?= base_url(PUBLIC_FOLDER . "assets/css/success.css") ?>">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-rbsA2VBKQhggwzxH7pPCaAqO46MgnOM80zW1RWuH61DGLwZJEdK2Kadq2F9CUG65" crossorigin="anonymous">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-kenU1KFdBIe4zVF0s0G1M5b4hcpxyD9F7jL+jjXkk+Q2h455rYXK/7HAuoJl+0I4" crossorigin="anonymous"></script>
</head>

<body>
    <div class="page">
        <div class="div-principal d-flex justify-content-center align-items-center flex-column">
            <h4 class="status-title">Reserva confirmada!</h4>
            <i class="fa-regular fa-circle-check status-icon"></i>
        </div>

        <div class="result">
            <div class="header d-flex align-items-center justify-content-center flex-column">
                <div class="brand">
                    <img src="<?= base_url(PUBLIC_FOLDER . "assets/images/logo.png") ?>" alt="Logo">
                </div>
                <h5 class="text-center">Detalle de la reserva</h5>
            </div>

            <hr>
            <?php
            $fechaReserva = $booking['date'];
            $fechaReservaFormateada = $fechaReserva;
            if (!empty($fechaReserva) && strpos($fechaReserva, '-') !== false) {
                $dt = DateTime::createFromFormat('Y-m-d', $fechaReserva);
                if ($dt) {
                    $fechaReservaFormateada = $dt->format('d/m/Y');
                }
            }
            $slotModel = new \App\Models\BookingSlotsModel();
            $fromMinutes = $slotModel->timeToMinutes($booking['time_from'] ?? '00:00');
            $untilMinutes = $slotModel->timeToMinutes($booking['time_until'] ?? '00:00');
            if ($untilMinutes <= $fromMinutes) {
                $untilMinutes += 24 * 60;
            }
            $duracion = minutesToHuman($untilMinutes - $fromMinutes);
            ?>
            <ul>
                <li><strong>Nombre:</strong> <?= $booking['name'] ?></li>
                <li><strong>Teléfono:</strong> <?= $booking['phone'] ?></li>
                <li><strong>Fecha:</strong> <?= $fechaReservaFormateada ?></li>
                <li><strong>Horario:</strong> <?= $booking['time_from'] . 'hs' . ' ' . $booking['time_until'] . 'hs' ?></li>
                <li><strong>Duración:</strong> <?= esc($duracion) ?></li>
                <li><strong>Tipo de reserva:</strong> <?= $fieldsModel->getField($booking['id_field'])['name'] ?></li>
                <hr>
                <li><strong>Id de pago de Mercado Pago:</strong> <?= $mercadoPago['payment_id'] ?> </li>
                <li><strong>Estado del pago:</strong> <?= $mercadoPago['status'] ?></li>
                <hr>
                <li><strong>Valor total de la reserva:</strong> <?= format_price_ar($booking['total']) ?></li>
                <li><strong>Pagado:</strong> <?= format_price_ar($booking['payment']) ?></li>
                <li><strong>Restan:</strong> <?= format_price_ar($booking['diference']) ?></li>
                <li><strong>Detalle:</strong> <?= $booking['description']  == '' || $booking['description'] == null ? 'Reserva' : $booking['description'] ?></li>
            </ul>
            <hr>

        </div>

        <div class="cta-row cta-row-bottom">
            <a class="cta-link cta-secondary" href="<?= base_url() ?>">Volver a la pantalla principal</a>
            <a class="cta-link cta-primary" href="<?= base_url('bookingPdf/' . $bookingId) ?>">Descargar detalle de la reserva en PDF</a>
        </div>
    </div>
</body>

</html>
