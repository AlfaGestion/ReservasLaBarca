<?php
$title = 'Detalle de reserva';
$booking = $booking ?? [];
$status = (int) ($booking['annulled'] ?? 0) === 1 ? 'Cancelada' : ((int) ($booking['total_payment'] ?? 0) === 1 ? 'Pagada' : ((int) ($booking['approved'] ?? 0) === 1 ? 'Confirmada' : 'Pendiente'));
$canShareReservation = empty($booking['match_id']) || (int) ($booking['match_organizer_player_id'] ?? 0) === (int) ($player['id'] ?? 0);
$categoryLabel = static function ($value): string {
    $labels = ['principiante' => 'Principiante', 'sin_categoria' => 'Sin categoría / No sé'];
    $value = (string) $value;
    return $labels[$value] ?? ($value !== '' ? $value . '.ª categoría' : 'Cualquier categoría');
};
?>
<section class="reservation-page narrow-form">
    <a class="back-link" href="<?= base_url('app/reservas#mis-reservas') ?>">← Volver a mis reservas</a>
    <div class="profile-card reservation-detail-card">
        <span class="eyebrow orange">RESERVA #<?= esc($booking['id'] ?? '') ?></span>
        <h1><?= esc($booking['service_name'] ?: 'Reserva') ?></h1>
        <span class="reservation-status"><?= esc($status) ?></span>
        <?php if ($canShareReservation): ?>
            <div class="reservation-detail-actions" aria-label="Acciones de la reserva">
                <a class="button button-primary" href="<?= base_url('app/reservas/detalle/' . (int) $booking['id'] . '/descargar') ?>">Descargar detalle</a>
                <button class="button button-ghost" type="button" data-share-reservation data-share-url="<?= esc($sharePdfUrl ?? '') ?>">Compartir PDF</button>
            </div>
            <p class="reservation-share-message" data-share-message role="status" hidden></p>
        <?php endif; ?>
        <dl class="detail-list">
            <div><dt>Instalación</dt><dd><?= esc($booking['field_name'] ?? 'N/D') ?></dd></div>
            <div><dt>Fecha</dt><dd><?= esc(date('d/m/Y', strtotime($booking['date'] ?? 'now'))) ?></dd></div>
            <div><dt>Horario</dt><dd><?= esc(substr((string) ($booking['time_from'] ?? ''), 0, 5)) ?> a <?= esc(substr((string) ($booking['time_until'] ?? ''), 0, 5)) ?></dd></div>
            <?php if ((float) ($booking['discount_amount'] ?? 0) > 0): ?><div><dt>Precio original</dt><dd>$ <?= esc(number_format((float) ($booking['original_total'] ?? 0), 2, ',', '.')) ?></dd></div><div><dt>Descuento</dt><dd class="booking-discount">- $ <?= esc(number_format((float) ($booking['discount_amount'] ?? 0), 2, ',', '.')) ?></dd></div><?php endif; ?>
            <div><dt>Total</dt><dd>$ <?= esc(number_format((float) ($booking['total'] ?? 0), 2, ',', '.')) ?></dd></div>
            <div><dt>Pagado</dt><dd>$ <?= esc(number_format((float) ($booking['payment'] ?? 0), 2, ',', '.')) ?></dd></div>
            <div><dt>Saldo</dt><dd>$ <?= esc(number_format((float) ($booking['diference'] ?? 0), 2, ',', '.')) ?></dd></div>
        </dl>
        <?php if (! empty($booking['match_id'])): ?>
            <?php
            $paymentModeLabels = [
                'organizer_deposit' => 'El organizador paga la seña',
                'split' => 'Cada jugador paga su parte',
                'venue' => 'Todos pagan en la cancha',
            ];
            $joinModeLabel = ($booking['join_mode'] ?? '') === 'auto' ? 'Ingreso automático' : 'Ingreso con aprobación';
            $paymentMode = (string) ($booking['payment_mode'] ?? '');
            $participantShare = (float) ($booking['participant_share'] ?? 0);
            $matchTotal = (float) ($booking['payment_total'] ?? $booking['total'] ?? 0);
            $matchMax = max(1, (int) ($booking['match_max_players'] ?? 0));
            $matchStarters = min($matchMax, max(1, (int) ($booking['match_max_starters'] ?? $matchMax)));
            ?>
            <section class="reservation-match-info" aria-labelledby="reservation-match-title">
                <h2 id="reservation-match-title">Partido abierto</h2>
                <dl class="detail-list">
                    <div><dt>Modalidad de incorporación</dt><dd><?= esc($joinModeLabel) ?></dd></div>
                    <div><dt>Modalidad de pago</dt><dd><?= esc($paymentModeLabels[$paymentMode] ?? 'Según la configuración del partido') ?></dd></div>
                    <?php if ($paymentMode === 'organizer_deposit'): ?>
                        <div><dt>Importe a cargo del organizador</dt><dd>$ <?= esc(number_format((float) ($booking['payment'] ?? 0), 2, ',', '.')) ?> de seña</dd></div>
                        <div><dt>Participantes</dt><dd>No pagan online</dd></div>
                    <?php elseif ($participantShare > 0): ?>
                        <div><dt>Importe por jugador</dt><dd>$ <?= esc(number_format($participantShare, 2, ',', '.')) ?> estimado</dd></div>
                        <div><dt>Momento del pago</dt><dd><?= $paymentMode === 'venue' ? 'En la cancha' : 'Por Mercado Pago' ?></dd></div>
                    <?php endif; ?>
                    <div><dt>Participantes confirmados</dt><dd><?= (int) ($booking['match_confirmed_players'] ?? 1) ?> de <?= (int) ($booking['match_max_players'] ?? 0) ?></dd></div>
                    <?php if (! empty($booking['match_padel_category_min']) || ! empty($booking['match_padel_category_max'])): ?><div><dt>Categoría de pádel</dt><dd><?= esc($categoryLabel($booking['match_padel_category_min'] ?? '')) ?> a <?= esc($categoryLabel($booking['match_padel_category_max'] ?? '')) ?></dd></div><?php endif; ?>
                </dl>
                <?php if ($paymentMode !== 'organizer_deposit' && $matchTotal > 0): ?>
                    <div class="reservation-payment-scenarios">
                        <strong>Importe según cantidad de jugadores</strong>
                        <p>El total se reparte entre los participantes confirmados. A mayor cantidad de jugadores, menor importe individual.</p>
                        <div class="payment-scenario-list">
                            <?php for ($playersCount = $matchStarters; $playersCount <= $matchMax; $playersCount++): ?>
                                <div><span><?= $playersCount ?> <?= $playersCount === 1 ? 'jugador' : 'jugadores' ?></span><strong>$ <?= esc(number_format(round($matchTotal / $playersCount, 2), 2, ',', '.')) ?> por jugador</strong></div>
                            <?php endfor; ?>
                        </div>
                    </div>
                <?php endif; ?>
                <a class="button button-ghost" href="<?= base_url('app/partidos/detalle/' . (int) $booking['match_id']) ?>">Ver partido y participantes</a>
            </section>
        <?php endif; ?>
        <?php if (empty($booking['match_id']) && (int) ($booking['is_sport'] ?? 0) === 1 && (int) ($booking['annulled'] ?? 0) === 0 && ($booking['date'] ?? '') >= date('Y-m-d')): ?><a class="button button-primary" href="<?= base_url('app/partidos/crear?booking_id=' . (int) $booking['id']) ?>">Buscar jugadores</a><?php endif; ?>
    </div>
</section>
<?php if ($canShareReservation): ?>
<script>
(() => {
    const shareButton = document.querySelector('[data-share-reservation]')
    const message = document.querySelector('[data-share-message]')
    if (!shareButton || !message) return
    shareButton.addEventListener('click', async () => {
        const shareData = {
            title: 'Detalle de mi reserva · La Barca',
            text: 'Te comparto el detalle de mi reserva en La Barca.',
            url: shareButton.dataset.shareUrl || window.location.href,
        }
        try {
            if (navigator.share) {
                await navigator.share(shareData)
                return
            }
            await navigator.clipboard.writeText(window.location.href)
            message.textContent = 'Enlace copiado. Ya podés compartirlo.'
        } catch (error) {
            if (error && error.name === 'AbortError') return
            message.textContent = 'No se pudo compartir automáticamente. Copiá el enlace de esta pantalla.'
        }
        message.hidden = false
    })
})()
</script>
<?php endif; ?>
