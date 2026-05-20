<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reporte de cobro</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f0f0f0;
            margin: 0;
            padding: 0;
            text-align: center;
        }

        div {
            margin-top: 20px;
        }

        img {
            max-width: 100%;
        }

        ul {
            list-style: none;
            padding: 0;
        }

        li {
            margin: 10px 0;
            font-size: 18px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
            margin-top: 20px;
        }

        th,
        td {
            padding: 10px;
            text-align: center; /* Centro de texto horizontal */
        }

        th {
            background-color: #f2f2f2;
        }

        tr:nth-child(even) {
            background-color: #f2f2f2;
        }

        /* Estilos para las celdas del encabezado */
        th {
            background-color: #f09424;
            color: white;
        }

        /* Estilos para el contenido de la tabla */
        td {
            border-bottom: 1px solid #ddd;
        }

        /* Estilos para la fila al pasar el cursor por encima */
        tr:hover {
            background-color: #e2e2e2;
        }
    </style>
</head>
<body>
    <div>
        <img src="<?= base_url(PUBLIC_FOLDER . "assets/images/logo_pdf.png") ?>" style="width: 300px;" alt="Logo">
    </div>

    <?php $total = 0 ?> 

    <table>
        <thead>
            <tr>
                <th scope="col">Fecha</th>
                <th scope="col">Total de ingresos por Mercado Pago</th>
            </tr>
        </thead>

        <tbody>

            <?php foreach ($data as $cobro) : ?>
                <?php $total = (float)($cobro['reserva'] ?? 0) + $total ?> 
                <tr>
                    <td><?= $cobro['fecha'] ?></td>
                    <td><?= format_price_ar($cobro['reserva']) ?></td>
                </tr>
            <?php endforeach; ?>

        </tbody>
    </table>

    <p>Total de cobros: <strong><?= format_price_ar($total) ?></strong> </p>
</body>
</html>
