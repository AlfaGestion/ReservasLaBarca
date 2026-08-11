<?php

$title = $title ?? 'Pago en proceso';
$message = $message ?? 'Estamos esperando la confirmacion oficial de Mercado Pago. Si el pago fue aprobado, la reserva se confirmara automaticamente.';

?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= esc($title) ?></title>
    <script src="https://kit.fontawesome.com/9bae38f407.js" crossorigin="anonymous"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            min-height: 100vh;
            margin: 0;
            font-family: Arial, sans-serif;
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 45%, #334155 100%);
            color: #f8fafc;
        }
        .pending-page {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
        }
        .pending-card {
            width: min(640px, 100%);
            background: rgba(15, 23, 42, 0.9);
            border: 1px solid rgba(148, 163, 184, 0.25);
            border-radius: 24px;
            padding: 32px;
            box-shadow: 0 20px 60px rgba(15, 23, 42, 0.4);
            backdrop-filter: blur(10px);
        }
        .pending-icon {
            width: 84px;
            height: 84px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: rgba(245, 158, 11, 0.16);
            color: #f59e0b;
            font-size: 36px;
            margin-bottom: 20px;
        }
        .pending-message {
            color: #cbd5e1;
        }
    </style>
</head>

<body>
    <div class="pending-page">
        <div class="pending-card text-center">
            <div class="pending-icon">
                <i class="fa-solid fa-circle-notch fa-spin"></i>
            </div>
            <h1 class="h3 mb-3"><?= esc($title) ?></h1>
            <p class="pending-message mb-4"><?= esc($message) ?></p>
            <div class="d-flex gap-3 justify-content-center flex-wrap">
                <a class="btn btn-warning fw-semibold" href="<?= base_url() ?>">Volver al inicio</a>
                <a class="btn btn-outline-light fw-semibold" href="<?= base_url('payment/success') ?>">Reintentar validacion</a>
            </div>
        </div>
    </div>
</body>

</html>
