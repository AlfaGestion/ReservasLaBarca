<?php
$embedded = ! empty($embedded);
$customerRegisterEditorPath = FCPATH . 'assets/js/customerRegisterEditor.js';
$customerRegisterEditorVersion = is_file($customerRegisterEditorPath) ? filemtime($customerRegisterEditorPath) : time();
$customerRegisterCssPath = FCPATH . 'assets/css/customer-editor.css';
$customerRegisterCssVersion = is_file($customerRegisterCssPath) ? filemtime($customerRegisterCssPath) : time();
?>
<?php echo $this->extend('templates/dashboard_panel') ?>

<?php echo $this->section('title') ?>
<title>Ingresar cliente</title>
<?php echo $this->endSection() ?>

<?php echo $this->section('bodyClass') ?>
customer-editor-page<?= $embedded ? ' customer-editor-frame customer-register-frame' : '' ?>
<?php echo $this->endSection() ?>

<?php echo $this->section('styles') ?>
<link rel="stylesheet" href="<?= base_url(PUBLIC_FOLDER . 'assets/css/customer-editor.css?v=' . $customerRegisterCssVersion) ?>">
<?php echo $this->endSection() ?>

<?php echo $this->section('content') ?>
<div class="customer-editor-wrap">
    <div class="customer-editor-shell">
        <header class="customer-editor-header">
            <div class="customer-editor-header__brand">
                <a href="<?= base_url() ?>" class="customer-editor-logo" aria-label="Ir al inicio">
                    <img src="<?= base_url(PUBLIC_FOLDER . 'assets/images/logo.png') ?>" width="200" alt="La Barca Centro Deportivo">
                </a>
                <div>
                    <p class="customer-editor-kicker">Administracion de clientes</p>
                    <h1 class="customer-editor-title">Ingresar cliente</h1>
                    <p class="customer-editor-subtitle">
                        Cargá un nuevo cliente sin salir del panel. El alta se procesa en segundo plano y la grilla se refresca al instante.
                    </p>
                </div>
            </div>
            <div class="customer-editor-header__meta">
                <span class="customer-status-badge customer-status-badge--secondary" id="customerRegisterStatusBadge">
                    Nuevo registro
                </span>
                <p class="customer-editor-note">
                    El formulario mantiene el mismo estilo del panel y se adapta al modo oscuro.
                </p>
            </div>
        </header>

        <main class="customer-editor-body">
            <form action="<?= base_url($embedded ? 'customers/registerAjax' : 'customers/register') ?>" method="POST" id="customerRegisterForm">
                <div id="customerRegisterFeedback" class="mb-3"></div>

                <section class="customer-editor-card">
                    <div class="customer-editor-card__header">
                        <div>
                            <h2 class="customer-editor-card__title">Datos del cliente</h2>
                            <p class="customer-editor-card__subtitle">Completá los datos básicos para dar de alta al cliente.</p>
                        </div>
                    </div>
                    <div class="customer-editor-card__body">
                        <div class="customer-editor-grid customer-editor-grid--client">
                            <div class="customer-editor-field">
                                <label for="customer_name">Nombre</label>
                                <input type="text" id="customer_name" name="name" class="form-control" placeholder="Nombre">
                            </div>
                            <div class="customer-editor-field">
                                <label for="customer_last_name">Apellido</label>
                                <input type="text" id="customer_last_name" name="last_name" class="form-control" placeholder="Apellido">
                            </div>
                            <div class="customer-editor-field">
                                <label for="customer_dni">DNI</label>
                                <input type="text" id="customer_dni" name="dni" class="form-control" placeholder="DNI">
                            </div>
                            <div class="customer-editor-field">
                                <label for="customer_city">Localidad</label>
                                <input type="text" id="customer_city" name="city" class="form-control" placeholder="Localidad">
                            </div>
                            <div class="customer-editor-field">
                                <label for="customer_area_code">Codigo de area</label>
                                <input type="text" id="customer_area_code" name="areaCode" class="form-control" placeholder="Codigo de area">
                            </div>
                            <div class="customer-editor-field">
                                <label for="customer_phone">Telefono</label>
                                <input type="text" id="customer_phone" name="phone" class="form-control" placeholder="Telefono">
                            </div>
                        </div>

                        <div class="customer-offer-helper mt-4">
                            El numero de telefono se normaliza antes de guardar para evitar duplicados por formato.
                        </div>
                    </div>
                </section>

                <div class="customer-editor-actions">
                    <button type="submit" class="customer-editor-btn customer-editor-btn--primary">Registrar</button>
                </div>
            </form>
        </main>
    </div>
</div>
<?php echo $this->endSection() ?>

<?php echo $this->section('scripts') ?>
    <script>
        window.customerRegisterEditorState = {
            frameMode: <?= $embedded ? 'true' : 'false' ?>
        };
    </script>
<script src="<?= base_url(PUBLIC_FOLDER . 'assets/js/customerRegisterEditor.js?v=' . $customerRegisterEditorVersion) ?>"></script>
<?php echo $this->endSection() ?>
