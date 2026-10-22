<?php
$title = 'Detalle de reserva';
$booking = $booking ?? [];
$status = (int) ($booking['annulled'] ?? 0) === 1 ? 'Cancelada' : ((int) ($booking['total_payment'] ?? 0) === 1 ? 'Pagada' : ((int) ($booking['approved'] ?? 0) === 1 ? 'Confirmada' : 'Pendiente'));
?>
<section class="reservation-page narrow-form">
    <a class="back-link" href="<?= base_url('app/reservas#mis-reservas') ?>">← Volver a mis reservas</a>
    <div class="profile-card reservation-detail-card">
        <span class="eyebrow orange">RESERVA #<?= esc($booking['id'] ?? '') ?></span>
        <h1><?= esc($booking['service_name'] ?: 'Reserva') ?></h1>
        <span class="reservation-status"><?= esc($status) ?></span>
        <dl class="detail-list">
            <div><dt>Instalación</dt><dd><?= esc($booking['field_name'] ?? 'N/D') ?></dd></div>
            <div><dt>Fecha</dt><dd><?= esc(date('d/m/Y', strtotime($booking['date'] ?? 'now'))) ?></dd></div>
            <div><dt>Horario</dt><dd><?= esc(substr((string) ($booking['time_from'] ?? ''), 0, 5)) ?> a <?= esc(substr((string) ($booking['time_until'] ?? ''), 0, 5)) ?></dd></div>
            <div><dt>Total</dt><dd>$ <?= esc(number_format((float) ($booking['total'] ?? 0), 2, ',', '.')) ?></dd></div>
            <div><dt>Pagado</dt><dd>$ <?= esc(number_format((float) ($booking['payment'] ?? 0), 2, ',', '.')) ?></dd></div>
            <div><dt>Saldo</dt><dd>$ <?= esc(number_format((float) ($booking['diference'] ?? 0), 2, ',', '.')) ?></dd></div>
        </dl>
        <?php if ((int) ($booking['is_sport'] ?? 0) === 1 && (int) ($booking['annulled'] ?? 0) === 0 && ($booking['date'] ?? '') >= date('Y-m-d')): ?><a class="button button-primary" href="<?= base_url('app/partidos/crear?booking_id=' . (int) $booking['id']) ?>">Buscar jugadores</a><?php endif; ?>
    </div>
</section>
