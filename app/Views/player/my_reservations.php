<?php
$title = 'Mis reservas';
helper('url');
?>
<section class="reservation-page reservations-only-page" aria-labelledby="my-bookings-title">
    <header class="reservation-head">
        <div><span class="eyebrow orange">TU ACTIVIDAD</span><h1 id="my-bookings-title">Mis reservas</h1><p>Consultá tus próximas reservas y el historial de tus actividades.</p></div>
        <a class="button button-primary" href="<?= base_url('app/reservas') ?>">Nueva reserva <span aria-hidden="true">→</span></a>
    </header>
    <section class="my-bookings" id="mis-reservas">
        <div class="section-heading"><div><span class="eyebrow orange">TU ACTIVIDAD</span><h2>Reservas asociadas</h2></div></div>
        <?php if ($bookings === []): ?>
            <div class="profile-card"><p class="booking-muted">Todavía no tenés reservas asociadas.</p></div>
        <?php else: ?>
            <div class="booking-list">
                <?php foreach ($bookings as $booking): ?>
                    <article class="booking-list-item">
                        <div><span class="booking-service"><?= esc($booking['service_name'] ?: 'Servicio') ?></span><h3><?= esc($booking['field_name'] ?: 'Instalación') ?></h3><p><?= esc(date('d/m/Y', strtotime($booking['date']))) ?> · <?= esc(substr($booking['time_from'], 0, 5)) ?> a <?= esc(substr($booking['time_until'], 0, 5)) ?></p></div>
                        <div class="booking-list-meta"><strong>$ <?= esc(number_format((float) $booking['total'], 2, ',', '.')) ?></strong><small>#<?= esc($booking['id']) ?></small><a href="<?= base_url('app/reservas/detalle/' . (int) $booking['id']) ?>">Ver detalle</a></div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</section>
