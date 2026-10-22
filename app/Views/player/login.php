<?php $title = 'Iniciar sesión'; ?>
<section class="auth-layout auth-login-layout">
    <div class="auth-poster"><div class="auth-poster-content"><span class="eyebrow orange">LA BARCA · JUGADORES</span><h1>Volvé a<br><em>jugar.</em></h1><p>Tus reservas, tus partidos y tu equipo, en un solo lugar.</p></div></div>
    <div class="form-card auth-form-card">
        <?= view('player/form_header') ?>
        <?php if (! empty($message)): ?><div class="auth-message flash flash-<?= esc($message['type']) ?>" role="status"><?= esc($message['body']) ?></div><?php endif; ?>
        <div class="auth-form-heading"><span class="auth-form-kicker">BIENVENIDO DE NUEVO</span><h2>Iniciar sesión</h2><p class="form-intro">Ingresá a tu cuenta de jugador.</p></div>
        <form method="post" action="<?= base_url('app/login') ?>">
            <input type="hidden" name="<?= csrf_token() ?>" value="<?= csrf_hash() ?>">
            <label for="player-login-email">Correo electrónico<input id="player-login-email" type="email" name="email" required autocomplete="email"></label>
            <label for="player-login-password">Contraseña<div class="password-input"><input id="player-login-password" type="password" name="password" required autocomplete="current-password"><button type="button" class="password-toggle" data-password-target="player-login-password" aria-label="Mostrar contraseña" aria-pressed="false"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M2.2 12s3.5-6 9.8-6 9.8 6 9.8 6-3.5 6-9.8 6-9.8-6-9.8-6Z"></path><circle cx="12" cy="12" r="2.6"></circle></svg></button></div></label>
            <button class="button button-primary button-full" type="submit">Ingresar <span>→</span></button>
        </form>
        <a class="form-help" href="<?= base_url('app/recuperar-clave') ?>">¿Olvidaste tu contraseña?</a><p class="form-footer">¿Todavía no tenés cuenta? <a href="<?= base_url('app/registro') ?>">Registrate</a></p>
    </div>
</section>
<script>
document.querySelectorAll('[data-password-target]').forEach(function (toggle) { toggle.addEventListener('click', function () { var input = document.getElementById(toggle.dataset.passwordTarget); var visible = input.type === 'password'; input.type = visible ? 'text' : 'password'; toggle.setAttribute('aria-pressed', visible ? 'true' : 'false'); toggle.setAttribute('aria-label', visible ? 'Ocultar contraseña' : 'Mostrar contraseña'); }); });
</script>
