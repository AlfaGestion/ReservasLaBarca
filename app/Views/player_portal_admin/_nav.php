<?php $portalSection = (string) ($section ?? ''); ?>
<style>
    .portal-admin-nav {
        width: calc(100% - 2rem);
        max-width: 1500px;
        margin-left: auto;
        margin-right: auto;
    }
    .portal-admin-nav .nav-link {
        color: var(--portal-text, inherit);
        border: 1px solid transparent;
        font-weight: 600;
    }
    .portal-admin-nav .nav-link:hover,
    .portal-admin-nav .nav-link:focus {
        color: var(--portal-text, inherit);
        border-color: var(--portal-border, #b1d4f0);
    }
    .portal-admin-nav .nav-link.active {
        color: #17324d;
        background: #f39323;
        border-color: #f39323;
    }
    body.theme-dark .portal-admin-nav .nav-link,
    [data-theme="dark"] .portal-admin-nav .nav-link {
        color: #f5f8fb;
    }
    body.theme-dark .portal-admin-nav .nav-link.active,
    [data-theme="dark"] .portal-admin-nav .nav-link.active {
        color: #10263b;
    }
</style>
<nav class="nav nav-pills flex-wrap gap-2 mb-4 portal-admin-nav" aria-label="Administración del Portal de Jugadores">
    <?php foreach (['resumen' => 'Resumen', 'reservas' => 'Reservas', 'partidos' => 'Partidos abiertos', 'jugadores' => 'Jugadores', 'servicios' => 'Servicios / Precios', 'descuentos' => 'Descuentos', 'configuracion' => 'Configuración del portal'] as $key => $label): ?>
        <a class="nav-link <?= $portalSection === $key ? 'active' : '' ?>" href="<?= base_url('admin/portal?section=' . $key) ?>"><?= esc($label) ?></a>
    <?php endforeach; ?>
</nav>
