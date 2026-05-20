<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Datos de la reserva</title>
    <style>
        * {
            box-sizing: border-box;
        }

        body {
            font-family: Arial, sans-serif;
            background-color: #f3f5f4;
            margin: 0;
            padding: 28px;
            color: #1c2b22;
        }

        .card {
            background: #ffffff;
            border-radius: 10px;
            padding: 24px 26px;
            border: 1px solid #e3e9e6;
        }

        .header {
            text-align: center;
            margin-bottom: 14px;
        }

        .logo {
            width: 180px;
            margin: 0 auto 8px;
            display: block;
        }

        .title {
            font-size: 20px;
            font-weight: bold;
            margin: 6px 0 0;
        }

        .section {
            margin-top: 14px;
            padding-top: 12px;
            border-top: 1px solid #e3e9e6;
        }

        ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        li {
            margin: 6px 0;
            font-size: 14px;
        }

        .label {
            font-weight: bold;
            color: #1f6e43;
        }

        .footer {
            margin-top: 16px;
            font-size: 12px;
            color: #3b4b41;
            line-height: 1.4;
        }
    </style>
</head>

<body>
    <?php
    $logoPath = FCPATH . 'assets/images/logo_pdf.png';
    $logoDataUri = '';
    if (extension_loaded('gd') && is_file($logoPath)) {
        $logoData = base64_encode(file_get_contents($logoPath));
        $logoDataUri = 'data:image/png;base64,' . $logoData;
    }
    $brandFallback = 'La Barca Centro Deportivo';
    ?>

    <div class="card">
        <div class="header">
            <div class="title">Detalle de la reserva</div>
        </div>

        <ul>
            <li><span class="label">Nombre:</span> <?= $data['nombre'] ?></li>
            <li><span class="label">Tel&eacute;fono:</span> <?= $data['telefono'] ?></li>
            <?php
            $fecha = $data['fecha'];
            $fechaFormateada = $fecha;
            if (!empty($fecha) && strpos($fecha, '-') !== false) {
                $dt = DateTime::createFromFormat('Y-m-d', $fecha);
                if ($dt) {
                    $fechaFormateada = $dt->format('d/m/Y');
                }
            }
            ?>
            <li><span class="label">Fecha:</span> <?= $fechaFormateada ?></li>
            <li><span class="label">Horario:</span> <?= $data['horario'] ?></li>
            <li><span class="label">Duraci&oacute;n:</span> <?= $data['duracion'] ?? '' ?></li>
            <li><span class="label">Tipo de reserva:</span> <?= $data['cancha'] ?></li>
        </ul>

        <div class="section">
            <ul>
                <li><span class="label">Id de pago de Mercado Pago:</span> <?= $data['id_mercado_pago'] ?></li>
                <li><span class="label">Estado del pago:</span> <?= $data['estado_pago'] ?></li>
            </ul>
        </div>

        <div class="section">
            <ul>
                <li><span class="label">Valor total de la reserva:</span> <?= format_price_ar($data['total_cancha']) ?></li>
                <li><span class="label">Pagado:</span> <?= format_price_ar($data['pagado']) ?></li>
                <li><span class="label">Restan:</span> <?= format_price_ar($data['saldo']) ?></li>
                <li><span class="label">Detalle:</span> <?= $data['detalle']  == '' || $data['detalle'] == null ? 'Reserva' : $data['detalle'] ?></li>
            </ul>
        </div>

        <div class="footer">
            Sr/a <?= $data['nombre'] ?>, al abonar una reserva (sea de manera parcial o total) asume el compromiso y la responsabilidad de la asistencia.
            Caso contrario no hay devoluciones de dinero y los movimientos de reserva quedar&aacute;n sujetos a disponibilidad.
            <br><br>
            As&iacute; mismo, en caso de llegar tarde a la cancha, el tiempo de juego ser&aacute; hasta la hora reservada. Sin excepciones.
        </div>

        <div class="header" style="margin-top: 18px;">
            <?php if ($logoDataUri) : ?>
                <img class="logo" src="<?= $logoDataUri ?>" alt="Logo">
            <?php else : ?>
                <div class="title"><?= $brandFallback ?></div>
            <?php endif; ?>
        </div>
    </div>
</body>

</html>
