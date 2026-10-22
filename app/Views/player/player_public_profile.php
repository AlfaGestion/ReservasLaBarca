<?php
$title = 'Perfil de jugador';
helper('url');
$name = trim(($publicPlayer['first_name'] ?? '') . ' ' . ($publicPlayer['last_name'] ?? ''));
$initials = strtoupper(substr((string) ($publicPlayer['first_name'] ?? ''), 0, 1) . substr((string) ($publicPlayer['last_name'] ?? ''), 0, 1));
?>
<section class="public-player-page">
    <a class="back-link" href="<?= base_url('app/partidos/detalle/' . (int) $matchId) ?>">← Volver al partido</a>
    <article class="profile-card public-player-card">
        <span class="eyebrow orange">PERFIL DE JUGADOR</span>
        <div class="public-player-hero"><div class="avatar-large"><?= esc($initials) ?></div><div><h1><?= esc($name) ?></h1><p>Jugador de la comunidad La Barca</p></div></div>
        <div class="public-player-sections">
            <section><span class="eyebrow orange">DEPORTES FAVORITOS</span><?php if ($favoriteSports === []): ?><p class="booking-muted">Todavía no indicó sus deportes favoritos.</p><?php else: ?><div class="public-sport-list"><?php foreach ($favoriteSports as $sport): ?><span><?= esc($sport) ?></span><?php endforeach; ?></div><?php endif; ?></section>
            <?php if (! empty($publicPlayer['locality'])): ?><section><span class="eyebrow orange">LOCALIDAD</span><p class="public-player-locality"><i class="fa-solid fa-location-dot" aria-hidden="true"></i> <?= esc($publicPlayer['locality']) ?></p></section><?php endif; ?>
        </div>
        <p class="public-profile-note"><i class="fa-solid fa-shield-halved" aria-hidden="true"></i> Solo mostramos información pública del jugador. Sus datos de contacto permanecen privados.</p>
    </article>
</section>
