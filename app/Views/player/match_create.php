<?php
$title = 'Publicar un partido';
helper('url');
$selectedId = (int) ($selectedBookingId ?? 0);
$bookingData = array_map(static function (array $booking): array {
    return [
        'id' => (int) $booking['id'],
        'sport' => (string) ($booking['service_name'] ?? 'Deporte'),
        'field' => (string) ($booking['field_name'] ?? 'Espacio'),
        'date' => date('d/m/Y', strtotime($booking['date'])),
        'time' => substr($booking['time_from'], 0, 5) . ' a ' . substr($booking['time_until'], 0, 5),
    ];
}, $bookings ?? []);
?>
<section class="match-create-page match-wizard-page">
    <header class="match-create-header"><a class="back-link" href="<?= base_url('app/reservas#mis-reservas') ?>">← Volver a mis reservas</a><div class="match-create-title"><span class="eyebrow orange">NUEVO PARTIDO</span><h1>Buscá jugadores</h1><p>Elegí una reserva deportiva y armá tu partido.</p></div></header>
    <?php if ($bookings === []): ?>
        <div class="profile-card matches-empty match-empty-create"><h2>No tenés reservas deportivas disponibles</h2><p>Primero necesitás una reserva deportiva vigente para publicar un partido.</p><a class="button button-primary" href="<?= base_url('app/reservas') ?>">Reservar una cancha</a></div>
    <?php else: ?>
        <div class="match-wizard-progress"><span id="match-step-label">Paso 1 de 2</span><div><b data-match-progress="1"></b><b data-match-progress="2"></b></div></div>
        <form class="match-form match-wizard-form profile-card" method="post" action="<?= base_url('app/partidos/publicar') ?>" id="match-create-form">
            <input type="hidden" name="<?= csrf_token() ?>" value="<?= csrf_hash() ?>">
            <section class="match-wizard-screen" data-match-step="1">
                <span class="form-section-kicker">PASO 1 · RESERVA Y JUGADORES</span>
                <h2>¿Con qué reserva vas a jugar?</h2><p class="wizard-intro">Elegí la reserva deportiva que querés compartir.</p>
                <label for="match-booking">Reserva deportiva<select id="match-booking" name="booking_id" required><?php foreach ($bookings as $booking): ?><option value="<?= (int) $booking['id'] ?>" <?= $selectedId === (int) $booking['id'] ? 'selected' : '' ?>><?= esc($booking['service_name']) ?> · <?= esc($booking['field_name']) ?> · <?= esc(date('d/m/Y', strtotime($booking['date']))) ?> · <?= esc(substr($booking['time_from'], 0, 5)) ?> a <?= esc(substr($booking['time_until'], 0, 5)) ?></option><?php endforeach; ?></select></label>
                <div class="selected-booking-card" id="selected-booking-card"></div>
                <div class="match-form-grid compact-number-grid"><label for="match-max">Cupos totales<input id="match-max" type="number" name="max_players" min="2" max="30" value="10" required><small>Incluye a quienes ya están confirmados.</small></label><label for="match-confirmed">Confirmados inicialmente<input id="match-confirmed" type="number" name="confirmed_players" min="1" max="30" value="1" required><small>Como mínimo, vos.</small></label></div>
                <p class="places-summary" id="places-summary">Quedan 9 lugares disponibles.</p>
                <div class="match-wizard-actions"><a class="button button-ghost" href="<?= base_url('app/reservas#mis-reservas') ?>">Volver</a><button class="button button-primary" type="button" data-match-next="2">Continuar <span>→</span></button></div>
            </section>
            <section class="match-wizard-screen" data-match-step="2" hidden>
                <span class="form-section-kicker">PASO 2 · DETALLES DEL PARTIDO</span>
                <h2>Contales cómo querés jugar</h2><p class="wizard-intro">Podés definir cómo se incorporan los demás jugadores.</p>
                <label for="match-mode">Modalidad de incorporación<select id="match-mode" name="join_mode" required><?php if (! empty($settings['allow_auto_join'])): ?><option value="auto">Ingreso automático</option><?php endif; ?><?php if (! empty($settings['allow_approval'])): ?><option value="approval">Ingreso con aprobación</option><?php endif; ?></select></label><p class="field-help" id="mode-help"></p>
                <label for="match-level">Nivel de juego<select id="match-level" name="level"><option value="">Sin especificar</option><option value="Inicial">Inicial</option><option value="Intermedio">Intermedio</option><option value="Avanzado">Avanzado</option></select></label>
                <label for="match-description">Descripción<textarea id="match-description" name="description" rows="4" maxlength="500" placeholder="Contá qué tipo de partido querés organizar y qué jugadores estás buscando"></textarea></label><div class="description-counter" id="description-counter">0/500</div>
                <div class="match-wizard-actions"><button class="button button-ghost" type="button" data-match-back="1">Atrás</button><button class="button button-primary" type="button" data-match-review="true">Revisar partido <span>→</span></button></div>
            </section>
            <section class="match-wizard-screen match-review-screen" data-match-step="3" hidden>
                <span class="form-section-kicker">REVISIÓN</span><h2>Revisá tu partido</h2><p class="wizard-intro">Todavía no se publicó. Confirmá los datos para continuar.</p>
                <div class="match-review-card"><div><span>Reserva</span><strong id="review-booking"></strong></div><div><span>Fecha y horario</span><strong id="review-date"></strong></div><div><span>Cupos</span><strong id="review-capacity"></strong></div><div><span>Modalidad</span><strong id="review-mode"></strong></div><div><span>Nivel</span><strong id="review-level"></strong></div><div><span>Descripción</span><strong id="review-description"></strong></div></div>
                <div class="match-wizard-actions"><button class="button button-ghost" type="button" data-match-back="2">Editar</button><button class="button button-primary" type="submit" id="publish-match-button">Publicar partido <span>→</span></button></div>
            </section>
        </form>
    <?php endif; ?>
</section>
<?php if ($bookings !== []): ?><script>window.playerMatchBookings = <?= json_encode($bookingData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;</script><script src="<?= base_url('assets/player/player-match-create.js') ?>"></script><?php endif; ?>
