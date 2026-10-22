<?php echo $this->extend('templates/dashboard_panel') ?>
<?php echo $this->section('title') ?><title>Configurar portal</title><?php echo $this->endSection() ?>
<?php echo $this->section('content') ?>
<div class="container py-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4"><div><h2>Configurar portal</h2><p class="text-muted mb-0">Administrá la experiencia de los jugadores en <code>/app</code>.</p></div><a class="btn btn-outline-secondary" href="<?= base_url('abmAdmin') ?>">Volver al panel</a></div>
    <?php if (session('msg')): ?><div class="alert alert-<?= esc(session('msg.type')) ?>"><?= esc(session('msg.body')) ?></div><?php endif; ?>
    <div class="row g-4">
        <div class="col-lg-6"><div class="card h-100"><div class="card-body"><h5><i class="fa-solid fa-images me-2"></i>Imágenes de inicio</h5><p class="text-muted">Cargá y ordená hasta 10 imágenes para el carrusel de la portada del portal.</p><a class="btn btn-outline-primary" href="<?= base_url('admin/carrusel') ?>">Administrar imágenes</a></div></div></div>
        <div class="col-lg-6"><div class="card h-100 border-warning"><div class="card-body"><h5><i class="fa-solid fa-flask me-2"></i>Modo prueba</h5><p class="text-muted">Permite confirmar reservas creadas desde <code>/app</code> sin abrir Mercado Pago. No modifica las reservas de la web pública.</p><form method="post" action="<?= base_url('admin/portal/guardar') ?>"><?= csrf_field() ?><div class="form-check form-switch mb-3"><input class="form-check-input" type="checkbox" role="switch" id="test_mode" name="test_mode" value="1" <?= $testMode ? 'checked' : '' ?>><label class="form-check-label" for="test_mode">Activar modo prueba</label></div><div class="alert alert-warning small">Usalo solamente para demostraciones o pruebas. Desactivá esta opción antes de trabajar con reservas reales.</div><button class="btn btn-warning" type="submit">Guardar configuración</button></form></div></div></div>
    </div>
</div>
<?php echo $this->endSection() ?>
