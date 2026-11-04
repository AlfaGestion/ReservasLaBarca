<?php $title = 'Iniciar sesión'; ?>
<section class="auth-layout login-reference-shell" aria-label="Acceso al Portal de Jugadores">
    <div class="login-poster">
        <div class="login-poster-inner">
            <div class="login-poster-copy">
                <h1><span>Tu próximo partido</span><span>empieza <em>acá.</em></span></h1>
                <p class="login-poster-tagline">El juego nos une.</p>
                <ul class="login-benefits">
                    <li><i class="fa-regular fa-calendar-days" aria-hidden="true"></i><span>Reservá tu cancha</span></li>
                    <li><i class="fa-solid fa-people-group" aria-hidden="true"></i><span>Sumate a partidos</span></li>
                    <li><i class="fa-regular fa-bell" aria-hidden="true"></i><span>Recibí novedades</span></li>
                </ul>
            </div>
        </div>
    </div>

    <div class="form-card auth-form-card">
        <?php if (! empty($message)): ?><div class="auth-message flash flash-<?= esc($message['type']) ?>" role="status"><?= esc($message['body']) ?></div><?php endif; ?>
        <div class="auth-form-heading">
            <span class="auth-form-kicker">BIENVENIDO DE NUEVO</span>
            <h2>Iniciar sesión</h2>
            <p class="form-intro">Ingresá a tu cuenta de jugador.</p>
        </div>
        <form method="post" action="<?= base_url('app/login') ?>">
            <input type="hidden" name="<?= csrf_token() ?>" value="<?= csrf_hash() ?>">
            <label for="player-login-email">
                <span><i class="fa-regular fa-envelope" aria-hidden="true"></i> Correo electrónico</span>
                <input id="player-login-email" type="email" name="email" required autocomplete="email">
            </label>
            <label for="player-login-password">
                <span><i class="fa-solid fa-lock" aria-hidden="true"></i> Contraseña</span>
                <div class="password-input">
                    <input id="player-login-password" type="password" name="password" required autocomplete="current-password">
                    <button type="button" class="password-toggle" data-password-target="player-login-password" aria-label="Mostrar contraseña" aria-pressed="false"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M2.2 12s3.5-6 9.8-6 9.8 6 9.8 6-3.5 6-9.8 6-9.8-6-9.8-6Z"></path><circle cx="12" cy="12" r="2.6"></circle></svg></button>
                </div>
            </label>
            <button class="button button-primary button-full" type="submit">Ingresar <span aria-hidden="true">→</span></button>
        </form>
        <a class="form-help" href="<?= base_url('app/recuperar-clave') ?>">¿Olvidaste tu contraseña?</a>
        <p class="form-footer">¿Todavía no tenés cuenta? <a href="<?= base_url('app/registro') ?>">Registrate</a></p>
    </div>
</section>
<script>
document.querySelectorAll('[data-password-target]').forEach(function (toggle) { toggle.addEventListener('click', function () { var input = document.getElementById(toggle.dataset.passwordTarget); var visible = input.type === 'password'; input.type = visible ? 'text' : 'password'; toggle.setAttribute('aria-pressed', visible ? 'true' : 'false'); toggle.setAttribute('aria-label', visible ? 'Ocultar contraseña' : 'Mostrar contraseña'); }); });
</script>
