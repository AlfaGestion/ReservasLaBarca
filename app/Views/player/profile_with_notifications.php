<?php
$title = 'Mi perfil';
$initials = strtoupper(substr($player['first_name'], 0, 1) . substr($player['last_name'], 0, 1));
$favoriteSports = json_decode($player['favorite_sports'] ?? '[]', true);
$favoriteSports = is_array($favoriteSports) ? $favoriteSports : [];
$favoriteServices = $favoriteServices ?? [];
$sportProfiles = json_decode($player['sport_profiles'] ?? '{}', true);
$sportProfiles = is_array($sportProfiles) ? $sportProfiles : [];
$availableCodes = [];
$favoriteCodes = [];
foreach ($favoriteServices as $service) {
    $code = (string) ($service['code'] ?? '');
    $availableCodes[] = $code;
    if (in_array($code, $favoriteSports, true) || in_array((string) ($service['name'] ?? ''), $favoriteSports, true)) $favoriteCodes[] = $code;
}
$hasFootballService = in_array('football', $availableCodes, true) || in_array('futbol', $availableCodes, true);
$hasPadelService = in_array('padel', $availableCodes, true);
$hasFootball = in_array('football', $favoriteCodes, true) || in_array('futbol', $favoriteCodes, true);
$hasPadel = in_array('padel', $favoriteCodes, true);
?>
<section class="profile-head"><div><span class="eyebrow orange">MI ESPACIO</span><h1>Hola, <?= esc($player['first_name']) ?>.</h1><p>Tu perfil de jugador, siempre listo para el próximo partido.</p></div><a class="button button-ghost" href="<?= base_url('app/logout') ?>">Cerrar sesión</a></section>
<section class="profile-grid">
    <div class="profile-card profile-summary"><div class="avatar-large" aria-hidden="true"><?= esc($initials) ?></div><h2><?= esc($player['first_name'] . ' ' . $player['last_name']) ?></h2><p><?= esc($player['email']) ?></p><span class="member-since">Jugador desde <?= esc(date('Y', strtotime($player['created_at']))) ?></span><nav class="profile-links" aria-label="Secciones de mi perfil"><a href="#datos">Editar mis datos <span aria-hidden="true">→</span></a><a href="<?= base_url('app/reservas') ?>">Mis reservas <span aria-hidden="true">→</span></a><a href="<?= base_url('app/partidos') ?>">Mis partidos <span aria-hidden="true">→</span></a></nav></div>
    <div class="profile-card" id="datos"><div class="card-title"><div><span class="eyebrow orange">DATOS PERSONALES</span><h2>Tu información</h2></div><span class="status-dot"><span aria-hidden="true">●</span> Activo</span></div>
        <form method="post" action="<?= base_url('app/perfil') ?>"><input type="hidden" name="<?= csrf_token() ?>" value="<?= csrf_hash() ?>">
            <div class="form-row"><label for="profile-first-name">Nombre<input id="profile-first-name" type="text" name="first_name" value="<?= esc($player['first_name']) ?>" required></label><label for="profile-last-name">Apellido<input id="profile-last-name" type="text" name="last_name" value="<?= esc($player['last_name']) ?>" required></label></div>
            <label for="profile-email">Correo electrónico<input id="profile-email" type="email" value="<?= esc($player['email']) ?>" disabled><small>El correo no se puede cambiar desde el perfil.</small></label>
            <label for="profile-phone">Teléfono<input id="profile-phone" type="tel" name="phone" value="<?= esc($player['phone'] ?? '') ?>" required></label>
            <label for="profile-locality">Localidad<input id="profile-locality" type="text" name="locality" value="<?= esc($player['locality'] ?? '') ?>"></label>
            <fieldset><legend>Deportes favoritos</legend><?php if ($favoriteServices === []): ?><p class="booking-muted">Todavía no hay deportes configurados para seleccionar.</p><?php else: ?><div class="check-grid"><?php foreach ($favoriteServices as $service): ?><?php $code = (string) ($service['code'] ?? ''); $profileSport = $code === 'futbol' ? 'football' : $code; ?><label class="check-pill"><input type="checkbox" name="favorite_sports[]" value="<?= esc($service['name']) ?>" data-favorite-sport="<?= esc($profileSport) ?>" <?= (in_array($service['name'], $favoriteSports, true) || in_array($code, $favoriteSports, true)) ? 'checked' : '' ?>><span><?= esc($service['name']) ?></span></label><?php endforeach; ?></div><?php endif; ?></fieldset>
            <?php if ($hasFootballService || $hasPadelService): ?><fieldset class="sport-profile-settings"><legend>Perfil deportivo</legend><p class="field-help">Estos datos ayudan a otros jugadores a conocerte mejor. Son opcionales.</p><p class="sport-profile-empty" data-no-sport-profile-message hidden>Elegí Fútbol, Pádel o ambos en Deportes favoritos para completar tu perfil deportivo.</p><div class="sport-profile-grid">
                <?php if ($hasFootballService): ?><div class="sport-profile-card" data-sport-profile-card="football" <?= $hasFootball ? '' : 'hidden' ?>><h3>Fútbol</h3><label for="football-position">Posición habitual<select id="football-position" name="football_position"><option value="">Elegí una posición</option><?php foreach (['arquero' => 'Arquero', 'defensor' => 'Defensor', 'mediocampista' => 'Mediocampista', 'delantero' => 'Delantero', 'cualquier' => 'Cualquier posición'] as $value => $label): ?><option value="<?= $value ?>" <?= ($sportProfiles['football']['position'] ?? '') === $value ? 'selected' : '' ?>><?= $label ?></option><?php endforeach; ?></select></label><label for="football-level">Nivel de juego<select id="football-level" name="football_level"><option value="">Elegí un nivel</option><?php foreach (['principiante' => 'Principiante', 'intermedio' => 'Intermedio', 'avanzado' => 'Avanzado'] as $value => $label): ?><option value="<?= $value ?>" <?= ($sportProfiles['football']['level'] ?? '') === $value ? 'selected' : '' ?>><?= $label ?></option><?php endforeach; ?></select></label></div><?php endif; ?>
                <?php if ($hasPadelService): ?><div class="sport-profile-card" data-sport-profile-card="padel" <?= $hasPadel ? '' : 'hidden' ?>><h3>Pádel</h3><label for="padel-position">Lado de juego<select id="padel-position" name="padel_position"><option value="">Elegí un lado</option><option value="drive" <?= ($sportProfiles['padel']['position'] ?? '') === 'drive' ? 'selected' : '' ?>>Drive</option><option value="reves" <?= ($sportProfiles['padel']['position'] ?? '') === 'reves' ? 'selected' : '' ?>>Revés</option><option value="ambos" <?= ($sportProfiles['padel']['position'] ?? '') === 'ambos' ? 'selected' : '' ?>>Ambos lados</option></select></label><label for="padel-category">Categoría de pádel<select id="padel-category" name="padel_category"><option value="">Elegí una categoría</option><option value="principiante" <?= ($sportProfiles['padel']['category'] ?? '') === 'principiante' ? 'selected' : '' ?>>Principiante</option><?php for ($category = 8; $category >= 1; $category--): ?><option value="<?= $category ?>" <?= (string) ($sportProfiles['padel']['category'] ?? '') === (string) $category ? 'selected' : '' ?>><?= $category ?>.ª categoría</option><?php endfor; ?><option value="sin_categoria" <?= ($sportProfiles['padel']['category'] ?? '') === 'sin_categoria' ? 'selected' : '' ?>>Sin categoría / No sé mi categoría</option></select></label></div><?php endif; ?>
            </div></fieldset><?php endif; ?>
            <label class="check-pill"><input type="checkbox" name="notify_favorite_matches" value="1" <?= ! empty($player['notify_favorite_matches']) ? 'checked' : '' ?>><span>Quiero recibir avisos de nuevos partidos de mis deportes favoritos</span></label><button class="button button-primary" type="submit">Guardar cambios <span aria-hidden="true">→</span></button>
        </form>
    </div>
</section>
<section class="future-grid"><article><span class="future-icon" aria-hidden="true">★</span><div><span class="eyebrow orange">PRÓXIMAMENTE</span><h3>Reputación y valoraciones</h3><p>Construí tu reputación jugando con otros participantes.</p></div></article><article><span class="future-icon" aria-hidden="true">→</span><div><span class="eyebrow orange">PRÓXIMAMENTE</span><h3>Estadísticas deportivas</h3><p>Tu evolución y tus partidos, en un solo lugar.</p></div></article></section>
<script>
(() => {
    const toggles = document.querySelectorAll('[data-favorite-sport]')
    const cards = document.querySelectorAll('[data-sport-profile-card]')
    const empty = document.querySelector('[data-no-sport-profile-message]')
    const sync = () => {
        let selectedAny = false
        cards.forEach((card) => {
            const selected = [...toggles].some((toggle) => toggle.checked && toggle.dataset.favoriteSport === card.dataset.sportProfileCard)
            selectedAny = selectedAny || selected
            card.hidden = !selected
            card.querySelectorAll('input, select, textarea').forEach((field) => { field.disabled = !selected })
        })
        if (empty) empty.hidden = selectedAny
    }
    toggles.forEach((toggle) => toggle.addEventListener('change', sync))
    sync()
})()
</script>
