<?php $title = 'Crear cuenta'; ?>
<style>
    .password-row { align-items: start; }
    .password-field { min-width: 0; display: grid; gap: 6px; align-content: start; }
    .password-field > label { display: block; min-height: 17px; }
    .password-input { position: relative; }
    .password-input input { padding-right: 46px; }
    .password-toggle { position: absolute; top: 50%; right: 9px; width: 30px; height: 30px; transform: translateY(-50%); border: 0; border-radius: 4px; background: transparent; color: #7a817f; cursor: pointer; font-size: .95rem; line-height: 1; }
    .password-toggle svg { width: 18px; height: 18px; display: block; margin: auto; fill: none; stroke: currentColor; stroke-width: 1.8; stroke-linecap: round; stroke-linejoin: round; }
    .password-toggle:hover, .password-toggle[aria-pressed="true"] { background: #fff0e5; color: #c26117; }
    .player-trap { position: absolute; width: 1px; height: 1px; overflow: hidden; clip: rect(0 0 0 0); white-space: nowrap; }
    .cf-turnstile { margin: 2px 0; }
    @media (max-width: 760px) { .password-row { gap: 15px; } }
</style>
<section class="auth-layout">
    <div class="auth-poster poster-register">
        <span class="eyebrow">UNA COMUNIDAD EN MOVIMIENTO</span>
        <h1>Tu juego,<br><em>tu lugar.</em></h1>
        <p>Creá tu perfil de jugador y empezá a ser parte de La Barca.</p>
    </div>
    <div class="form-card">
        <?= view('player/form_header') ?>
        <h2>Crear cuenta</h2>
        <p class="form-intro">Es gratis y te lleva menos de un minuto.</p>
        <?php if ($turnstileRequired && ! $turnstileConfigured): ?>
            <div class="flash flash-error">El registro está temporalmente deshabilitado por falta de configuración de seguridad.</div>
        <?php endif; ?>
        <form method="post" action="<?= base_url('app/registro') ?>">
            <input type="hidden" name="<?= csrf_token() ?>" value="<?= csrf_hash() ?>">
            <div class="player-trap" aria-hidden="true"><label>Website<input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>
            <div class="form-row">
                <label>Nombre<input type="text" name="first_name" required autocomplete="given-name"></label>
                <label>Apellido<input type="text" name="last_name" required autocomplete="family-name"></label>
            </div>
            <label>Correo electrónico<input type="email" name="email" required autocomplete="email"></label>
            <label>Teléfono<input type="tel" name="phone" required autocomplete="tel"></label>
            <div class="form-row password-row">
                <div class="password-field">
                    <label for="player-password">Contraseña</label>
                    <div class="password-input">
                        <input id="player-password" type="password" name="password" minlength="8" required autocomplete="new-password">
                        <button type="button" class="password-toggle" data-password-target="player-password" aria-label="Mostrar contraseña" aria-pressed="false"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M2.2 12s3.5-6 9.8-6 9.8 6 9.8 6-3.5 6-9.8 6-9.8-6-9.8-6Z"></path><circle cx="12" cy="12" r="2.6"></circle></svg></button>
                    </div>
                    <small>Mínimo 8 caracteres.</small>
                </div>
                <div class="password-field">
                    <label for="player-password-confirmation">Repetir contraseña</label>
                    <div class="password-input">
                        <input id="player-password-confirmation" type="password" name="password_confirmation" minlength="8" required autocomplete="new-password">
                        <button type="button" class="password-toggle" data-password-target="player-password-confirmation" aria-label="Mostrar contraseña" aria-pressed="false"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M2.2 12s3.5-6 9.8-6 9.8 6 9.8 6-3.5 6-9.8 6-9.8-6-9.8-6Z"></path><circle cx="12" cy="12" r="2.6"></circle></svg></button>
                    </div>
                    <small>&nbsp;</small>
                </div>
            </div>
            <?php if ($turnstileConfigured): ?><div class="cf-turnstile" data-sitekey="<?= esc($turnstileSiteKey) ?>"></div><?php endif; ?>
            <button class="button button-primary button-full">Crear mi cuenta <span>→</span></button>
        </form>
        <p class="form-footer">¿Ya tenés cuenta? <a href="<?= base_url('app/login') ?>">Iniciá sesión</a></p>
    </div>
</section>
<?php if ($turnstileConfigured): ?><script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script><?php endif; ?>
<script>
document.querySelectorAll('[data-password-target]').forEach(function (toggle) {
    toggle.addEventListener('click', function () {
        var input = document.getElementById(toggle.dataset.passwordTarget);
        var visible = input.type === 'password';
        input.type = visible ? 'text' : 'password';
        toggle.setAttribute('aria-pressed', visible ? 'true' : 'false');
        toggle.setAttribute('aria-label', visible ? 'Ocultar contraseña' : 'Mostrar contraseña');
    });
});
</script>
