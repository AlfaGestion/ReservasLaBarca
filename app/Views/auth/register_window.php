<?php
$embedded = ! empty($embedded);
$selectedUserId = isset($selectedUserId) ? (int) $selectedUserId : null;
$usersScriptPath = FCPATH . 'assets/js/users.js';
$usersScriptVersion = is_file($usersScriptPath) ? filemtime($usersScriptPath) : time();
$customerEditorCssPath = FCPATH . 'assets/css/customer-editor.css';
$customerEditorCssVersion = is_file($customerEditorCssPath) ? filemtime($customerEditorCssPath) : time();
?>
<?php echo $this->extend('templates/dashboard_panel') ?>

<?php echo $this->section('title') ?>
<title>Usuarios del panel</title>
<?php echo $this->endSection() ?>

<?php echo $this->section('bodyClass') ?>
customer-editor-page<?= $embedded ? ' customer-editor-frame' : '' ?>
<?php echo $this->endSection() ?>

<?php echo $this->section('styles') ?>
<link rel="stylesheet" href="<?= base_url(PUBLIC_FOLDER . 'assets/css/customer-editor.css?v=' . $customerEditorCssVersion) ?>">
<?php echo $this->endSection() ?>

<?php echo $this->section('content') ?>
<div class="customer-editor-wrap">
    <div class="customer-editor-shell">
        <header class="customer-editor-header">
            <div class="customer-editor-header__brand">
                <a href="<?= base_url('abmAdmin') ?>" class="customer-editor-logo" aria-label="Ir al panel">
                    <img src="<?= base_url(PUBLIC_FOLDER . 'assets/images/logo.png') ?>" width="200" alt="La Barca Centro Deportivo">
                </a>
                <div>
                    <p class="customer-editor-kicker">Administracion de usuarios</p>
                    <h1 class="customer-editor-title">Usuarios del panel</h1>
                    <p class="customer-editor-subtitle">
                        Crea o edita usuarios dentro del mismo marco visual del panel, sin salir de la navegacion actual.
                    </p>
                </div>
            </div>
            <div class="customer-editor-header__meta">
                <span class="customer-status-badge customer-status-badge--secondary" id="userRegisterStatusBadge">
                    Nuevo usuario
                </span>
                <p class="customer-editor-note">
                    La pantalla se adapta al modo oscuro y mantiene el mismo estilo del resto del sistema.
                </p>
            </div>
        </header>

        <main class="customer-editor-body">
            <section class="customer-editor-card">
                <div class="customer-editor-card__header">
                    <div>
                        <h2 class="customer-editor-card__title">Seleccionar usuario</h2>
                        <p class="customer-editor-card__subtitle">
                            Elegi un usuario para cargarlo en modo edicion, o dejalo vacio para crear uno nuevo.
                        </p>
                    </div>
                </div>
                <div class="customer-editor-card__body">
                    <div class="customer-editor-field">
                        <label for="selectUser">Usuario existente</label>
                        <select class="form-select" name="users" id="selectUser" aria-label="Seleccionar usuario">
                            <option value="">Crear usuario nuevo</option>
                            <?php if (isset($users)) : ?>
                                <?php foreach ($users as $user) : ?>
                                    <option value="<?= $user['id'] ?>" <?= $selectedUserId && (int) $selectedUserId === (int) $user['id'] ? 'selected' : '' ?>>
                                        <?= $user['name'] ?> (<?= $user['user'] ?>)
                                    </option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                        <p class="customer-editor-note" style="text-align:left; max-width:none; margin-top:8px;">
                            Si seleccionas un usuario, el formulario de abajo cambia automaticamente a modo edicion.
                        </p>
                    </div>
                </div>
            </section>

            <section class="customer-editor-card">
                <div class="customer-editor-card__header">
                    <div>
                        <h2 class="customer-editor-card__title" id="userFormTitle">Crear usuario nuevo</h2>
                        <p class="customer-editor-card__subtitle">
                            Cargalo una sola vez o editalo sin salir del modal.
                        </p>
                    </div>
                    <div class="customer-offer-pill" id="userManagementPreviewText">
                        Nuevo usuario
                    </div>
                </div>

                <div class="customer-editor-card__body">
                    <div id="userRegisterFeedback" class="mb-3"></div>

                    <form action="<?= base_url('auth/register') ?>" method="POST" id="formUsers">
                        <?= csrf_field() ?>
                        <input type="hidden" name="id" id="selectedUserId" value="<?= esc((string) ($selectedUserId ?? '')) ?>">

                        <div class="customer-editor-grid customer-editor-grid--client">
                            <div class="customer-editor-field">
                                <label for="userCreate">Usuario</label>
                                <input type="text" name="user" class="form-control" id="userCreate" placeholder="Usuario">
                            </div>

                            <div class="customer-editor-field">
                                <label for="nameCreate">Nombre visible</label>
                                <input type="text" name="name" class="form-control" id="nameCreate" placeholder="Nombre visible">
                            </div>

                            <div class="customer-editor-field">
                                <label for="passwordCreate">Contraseña</label>
                                <input type="password" name="password" class="form-control" id="passwordCreate" placeholder="Nueva contrasena">
                            </div>

                            <div class="customer-editor-field">
                                <label for="repeatPasswordCreate">Repetir contraseña</label>
                                <input type="password" name="repeat_password" class="form-control" id="repeatPasswordCreate" placeholder="Repetir contrasena">
                            </div>

                            <div class="customer-editor-field">
                                <div class="customer-editor-switch" style="height:100%;">
                                    <input class="form-check-input" type="checkbox" name="superadmin" id="superadminRadio">
                                    <label class="form-check-label" for="superadminRadio">
                                        Superadmin
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div class="customer-editor-actions">
                            <a href="<?= base_url('abmAdmin') ?>" class="customer-editor-btn customer-editor-btn--secondary">Volver al panel</a>
                            <button type="submit" class="customer-editor-btn customer-editor-btn--primary" id="submitUserForm">Registrar</button>
                        </div>
                    </form>

                    <div id="formButtons" class="d-none">
                        <div class="customer-editor-card" style="margin-top: 18px; margin-bottom: 0;">
                            <div class="customer-editor-card__header">
                                <div>
                                    <h2 class="customer-editor-card__title">Editar usuario</h2>
                                    <p class="customer-editor-card__subtitle">
                                        Ajusta los datos seleccionados y confirma los cambios sin abandonar el panel.
                                    </p>
                                </div>
                            </div>
                            <div class="customer-editor-card__body">
                                <div id="formselectUser"></div>
                                <div class="customer-editor-actions">
                                    <button type="button" class="customer-editor-btn customer-editor-btn--secondary" id="cancelUserManagement">Volver a crear</button>
                                    <button type="button" class="customer-editor-btn customer-editor-btn--primary" id="buttonEdit">Actualizar</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </main>
    </div>
</div>
<?php echo $this->endSection() ?>

<?php echo $this->section('scripts') ?>
    <script>
        window.userRegisterState = {
            embedded: true,
            selectedUserId: <?= json_encode($selectedUserId) ?>
        };
    </script>
    <script src="<?= base_url(PUBLIC_FOLDER . 'assets/js/users.js?v=' . $usersScriptVersion) ?>"></script>
<?php echo $this->endSection() ?>
