<div id="generalButtons" class="mt-3">
    <?php if (session()->superadmin) : ?>
        <a href="<?= base_url('auth/registerWindow') ?>" class="btn btn-success mt-2 mb-2 js-open-superadmin-user-management" id="openUserManagement" data-register-url="<?= base_url('auth/registerWindow') ?>" data-fallback-url="<?= base_url('auth/registerWindow') ?>" role="button">
            <i class="fa-solid fa-user-plus me-1"></i>Crear usuario
        </a>
        <button type="button" class="btn btn-warning mt-2 mb-2" id="openRateModal" data-bs-toggle="modal" data-bs-target="#rateModal"><i class="fa-solid fa-percent me-1"></i>Editar porcentaje de reserva</button>
        <button type="button" class="btn btn-primary mt-2 mb-2" id="openOfferRateModal" data-bs-toggle="modal" data-bs-target="#offerRateModal"><i class="fa-solid fa-percent me-1"></i>Editar porcentaje de oferta</button>
        <button type="button" class="btn btn-outline-dark mt-2 mb-2" id="toggleConfigPanel"><i class="fa-solid fa-gear me-1"></i>Configuracion</button>
        <button type="button" class="btn btn-outline-secondary mt-2 mb-2" id="toggleCancelReservations"><i class="fa-solid fa-calendar-xmark me-1"></i>Cierre de cancha</button>
    <?php endif; ?>

</div>

<?php if (session()->superadmin) : ?>
    <div class="card mt-3 d-none" id="userManagementPanel">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <h5 class="mb-1">Usuarios del panel</h5>
                    <p class="text-muted mb-0">Crea o edita usuarios desde este mismo sector sin salir de General.</p>
                </div>
                <button type="button" class="btn-close" aria-label="Close" id="closeUserManagement" onclick="(function(){if(window.resetUserManagementView){window.resetUserManagementView();}else{var p=document.getElementById('userManagementPanel'); if(!p)return; p.classList.add('d-none');}})()"></button>
            </div>

            <div class="row g-4">
                <div class="col-lg-5">
                    <div class="border rounded-3 p-3 h-100">
                        <h6 class="mb-3">Seleccionar usuario</h6>
                        <div class="form-floating mb-3">
                            <select
                                class="form-select"
                                name="users"
                                id="selectUser"
                                aria-label="Seleccionar usuario"
                                onchange="(function(select){
                                    var option = select.options[select.selectedIndex];
                                    var hasValue = !!select.value;
                                    var title = document.getElementById('userFormTitle');
                                    var form = document.getElementById('formUsers');
                                    var selectedUserId = document.getElementById('selectedUserId');
                                    var userInput = document.getElementById('userCreate');
                                    var passwordInput = document.getElementById('passwordCreate');
                                    var repeatPasswordInput = document.getElementById('repeatPasswordCreate');
                                    var nameInput = document.getElementById('nameCreate');
                                    var superadminInput = document.getElementById('superadminRadio');
                                    var submitButton = document.getElementById('submitUserForm');
                                    var deleteButton = document.getElementById('buttonDeleteUser');
                                    var deleteForm = document.getElementById('deleteUserForm');
                                    if (!form) return;
                                    if (!hasValue) {
                                        form.action = '<?= base_url('auth/register') ?>';
                                        if (selectedUserId) selectedUserId.value = '';
                                        if (userInput) userInput.value = '';
                                        if (passwordInput) passwordInput.value = '';
                                        if (repeatPasswordInput) repeatPasswordInput.value = '';
                                        if (nameInput) nameInput.value = '';
                                        if (superadminInput) superadminInput.checked = false;
                                        if (title) title.textContent = 'Crear usuario nuevo';
                                        if (submitButton) submitButton.textContent = 'Registrar';
                                        if (deleteButton) deleteButton.classList.add('d-none');
                                        if (deleteForm) {
                                            deleteForm.classList.add('d-none');
                                            deleteForm.action = '';
                                        }
                                        return;
                                    }
                                    form.action = '<?= base_url('editUser') ?>';
                                    if (selectedUserId) selectedUserId.value = select.value;
                                    if (userInput) userInput.value = option.dataset.user || '';
                                    if (passwordInput) passwordInput.value = '';
                                    if (repeatPasswordInput) repeatPasswordInput.value = '';
                                    if (nameInput) nameInput.value = option.dataset.name || '';
                                    if (superadminInput) superadminInput.checked = option.dataset.superadmin === '1';
                                    if (title) title.textContent = 'Editando: ' + (option.dataset.name || option.textContent);
                                    if (submitButton) submitButton.textContent = 'Guardar';
                                    if (deleteButton) deleteButton.classList.remove('d-none');
                                    if (deleteForm) {
                                        deleteForm.classList.remove('d-none');
                                        deleteForm.action = '<?= base_url('deleteUser') ?>/' + select.value;
                                    }
                                    if (window.populateSelectedUser) window.populateSelectedUser();
                                })(this)">
                                <option value="">Seleccionar usuario</option>
                                <?php if (isset($users)) : ?>
                                    <?php foreach ($users as $user) : ?>
                                        <option
                                            value="<?= $user['id'] ?>"
                                            data-user="<?= esc($user['user'], 'attr') ?>"
                                            data-name="<?= esc($user['name'], 'attr') ?>"
                                            data-superadmin="<?= (int) $user['superadmin'] ?>"
                                        >
                                            <?= $user['name'] ?> (<?= $user['user'] ?>)
                                        </option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                            <label for="selectUser">Usuario existente</label>
                        </div>
                    </div>
                </div>

                <div class="col-lg-7">
                    <div class="border rounded-3 p-3 h-100">
                        <h6 class="mb-3" id="userFormTitle">Crear usuario nuevo</h6>
                        <div class="d-none alert mb-3" id="userManagementMessage" role="alert"></div>
                        <form action="<?= base_url('auth/register') ?>" method="POST" id="formUsers" onsubmit="if(window.handleUserFormSubmit){window.handleUserFormSubmit(event,this);} return false;">
                            <?= csrf_field() ?>
                            <input type="hidden" name="id" id="selectedUserId">
                            <div class="form-floating mb-3">
                                <input type="text" name="user" class="form-control" id="userCreate" placeholder="Usuario">
                                <label for="userCreate">Usuario</label>
                            </div>

                            <div class="form-floating mb-3">
                                <input type="password" name="password" class="form-control" id="passwordCreate" placeholder="Nueva contrasena">
                                <label for="passwordCreate">Nueva contrasena</label>
                            </div>

                            <div class="form-floating mb-3">
                                <input type="password" name="repeat_password" class="form-control" id="repeatPasswordCreate" placeholder="Repetir contrasena">
                                <label for="repeatPasswordCreate">Repetir contrasena</label>
                            </div>

                            <div class="form-floating mb-3">
                                <input type="text" name="name" class="form-control" id="nameCreate" placeholder="Nombre visible">
                                <label for="nameCreate">Nombre visible</label>
                            </div>

                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" name="superadmin" id="superadminRadio">
                                <label class="form-check-label" for="superadminRadio">
                                    Superadmin
                                </label>
                            </div>

                            <div class="d-flex gap-2 flex-wrap">
                                <button type="submit" class="btn btn-brand" id="submitUserForm">Registrar</button>
                                <button type="button" class="btn btn-danger d-none" id="buttonDeleteUser" onclick="if(window.inlineDeleteUser){window.inlineDeleteUser(document.getElementById('deleteUserForm'));}">Eliminar</button>
                                <button
                                    type="button"
                                    class="btn btn-muted-action"
                                    id="cancelUserManagement"
                                    onclick="(function(){
                                        var form = document.getElementById('formUsers');
                                        var select = document.getElementById('selectUser');
                                        var title = document.getElementById('userFormTitle');
                                        var submitButton = document.getElementById('submitUserForm');
                                        var deleteButton = document.getElementById('buttonDeleteUser');
                                        var deleteForm = document.getElementById('deleteUserForm');
                                        var selectedUserId = document.getElementById('selectedUserId');
                                        var superadmin = document.getElementById('superadminRadio');
                                        var message = document.getElementById('userManagementMessage');
                                        if (form) {
                                            form.reset();
                                            form.action = '<?= base_url('auth/register') ?>';
                                        }
                                        if (select) select.value = '';
                                        if (selectedUserId) selectedUserId.value = '';
                                        if (superadmin) superadmin.checked = false;
                                        if (title) title.textContent = 'Crear usuario nuevo';
                                        if (submitButton) submitButton.textContent = 'Registrar';
                                        if (deleteButton) deleteButton.classList.add('d-none');
                                        if (deleteForm) {
                                            deleteForm.classList.add('d-none');
                                            deleteForm.action = '';
                                        }
                                        if (message) {
                                            message.className = 'd-none alert mb-3';
                                            message.textContent = '';
                                        }
                                        if (window.resetUserManagementView) window.resetUserManagementView();
                                    })()">Cancelar</button>
                            </div>
                        </form>
                        <form action="" method="POST" id="deleteUserForm" class="d-none" data-confirm-message="Eliminar usuario seleccionado?">
                            <?= csrf_field() ?>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php if (!session()->superadmin) : ?>
    <div class="table-responsive-sm">
        <table class="table align-middle table-striped-columns mt-2">
            <thead>
                <tr>
                    <th scope="col">Porcentaje de reserva</th>
                    <th scope="col">Porcentaje de oferta</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><?= $rate['value'] ?>%</td>
                    <td><?= $offerRate['value'] ?>%</td>
                </tr>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<!-- Modal rate -->
<div class="modal fade" id="rateModal" tabindex="-1" aria-labelledby="rateModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h1 class="modal-title fs-5" id="rateModalLabel">Porcentaje de reserva</h1>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-light border d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <div class="small text-muted">Porcentaje configurado actualmente</div>
                        <div class="fw-bold" id="currentRateValueDisplay"><?php if ($rate) : ?><?= $rate['value'] ?>%<?php else : ?>No configurado<?php endif; ?></div>
                    </div>
                </div>
                <div class="input-group mb-3">
                    <span class="input-group-text" id="basic-addon1">%</span>
                    <?php if ($rate) : ?>
                        <input type="text" class="form-control" placeholder="Ingresar porcentaje" name="rate" id="rate" aria-label="rate" aria-describedby="basic-addon1" value="<?= $rate['value'] ?>">
                    <?php else : ?>
                        <input type="text" class="form-control" placeholder="Ingresar porcentaje" name="rate" id="rate" aria-label="rate" aria-describedby="basic-addon1">
                    <?php endif; ?>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary me-auto" id="viewRateHistory">Ver historial</button>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Volver</button>
                <button type="button" class="btn btn-primary" id="saveRate">Guardar</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal historial rate -->
<div class="modal fade" id="rateHistoryModal" tabindex="-1" aria-labelledby="rateHistoryModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h1 class="modal-title fs-5" id="rateHistoryModalLabel">Historial del porcentaje de reserva</h1>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-light border">
                    <div class="small text-muted">Valor actual configurado</div>
                    <div class="fw-bold" id="rateHistoryCurrentValue">No configurado</div>
                </div>
                <div id="rateHistoryTable" class="table-responsive">
                    <div class="text-muted small">Cargando historial...</div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal offer rate -->
<div class="modal fade" id="offerRateModal" tabindex="-1" aria-labelledby="offerRateModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h1 class="modal-title fs-5" id="offerRateModalLabel">Porcentaje de oferta</h1>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="input-group mb-3">
                    <span class="input-group-text" id="basic-addon1">%</span>
                    <?php if ($offerRate) : ?>
                        <input type="text" class="form-control" placeholder="Ingresar porcentaje" name="offerRate" id="offerRate" aria-label="offerRate" aria-describedby="basic-addon1" value="<?= $offerRate['value'] ?>">
                    <?php else : ?>
                        <input type="text" class="form-control" placeholder="Ingresar porcentaje" name="offerRate" id="offerRate" aria-label="offerRate" aria-describedby="basic-addon1">
                    <?php endif; ?>
                </div>

                <div class="form-floating">
                    <textarea class="form-control" placeholder="Leave a comment here" id="descriptionOffer"></textarea>
                    <label for="descriptionOffer">Anadir una descripcion a la oferta</label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Volver</button>
                <button type="button" class="btn btn-primary" id="saveOfferRate">Guardar</button>
            </div>
        </div>
    </div>
</div>


<?php if (session()->superadmin) : ?>
    <div class="card mt-3 d-none" id="configPanel">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <h6 class="mb-0">Configuracion</h6>
                <button type="button" class="btn-close" aria-label="Close" id="closeConfigPanel"></button>
            </div>
            <ul class="nav nav-tabs" id="configTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="config-mp-tab" data-bs-toggle="tab" data-bs-target="#config-mp" type="button" role="tab">Mercado Pago</button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="config-fondo-tab" data-bs-toggle="tab" data-bs-target="#config-fondo" type="button" role="tab">Fondo</button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="config-general-tab" data-bs-toggle="tab" data-bs-target="#config-general" type="button" role="tab">General</button>
                </li>
            </ul>

            <div class="tab-content pt-3">
                <div class="tab-pane fade show active" id="config-mp" role="tabpanel">
                    <a href="<?= base_url('configMpView') ?>" type="button" class="btn btn-light">
                        <img src="<?= base_url(PUBLIC_FOLDER . 'assets/images/mercado-pago.jfif') ?>" alt="Icono Mercado Pago" width="10%" height="5%"> Configurar Mercado Pago
                    </a>
                </div>
                <div class="tab-pane fade" id="config-fondo" role="tabpanel">
                    <a href="<?= base_url('upload') ?>" type="button" class="btn btn-info"><i class="fa-solid fa-file-arrow-up me-1"></i>Cambiar fondo</a>
                </div>
                <div class="tab-pane fade" id="config-general" role="tabpanel">
                    <div class="form-floating mb-3">
                        <textarea class="form-control" id="closureTextConfig" style="height: 160px" placeholder="Texto de cierre"><?= isset($closureText) ? $closureText : '' ?></textarea>
                        <label for="closureTextConfig">Texto de cierre (usar &lt;fecha&gt;)</label>
                        <div class="form-text">Si escribis &lt;fecha&gt; se reemplaza por la fecha (dd/mm/yyyy).</div>
                    </div>
                    <div class="form-floating mb-3">
                        <input type="text" class="form-control" id="bookingEmailConfig" placeholder="Email para reservas" value="<?= isset($bookingEmail) ? $bookingEmail : '' ?>">
                        <label for="bookingEmailConfig">Emails para enviar reservas</label>
                    </div>
                    <button type="button" class="btn btn-success" id="saveConfigGeneral">Guardar configuracion</button>
                </div>
            </div>
        </div>
    </div>

    <div class="card mt-3 d-none" id="cancelReservationsPanel">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Cierre de cancha</h5>
                <button type="button" class="btn-close" aria-label="Close" id="closeCancelReservations"></button>
            </div>

            <div class="mt-3">
                <ul class="nav nav-tabs" id="cancelReservationsTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="cancel-closures-new-tab" data-bs-toggle="tab" data-bs-target="#cancel-closures-new" type="button" role="tab">
                            Nuevo cierre
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="cancel-closures-list-tab" data-bs-toggle="tab" data-bs-target="#cancel-closures-list" type="button" role="tab">
                            Cierres programados
                        </button>
                    </li>
                </ul>

                <div class="tab-content pt-3">
                    <div class="tab-pane fade show active" id="cancel-closures-new" role="tabpanel" aria-labelledby="cancel-closures-new-tab">
                        <div class="row g-2">
                            <div class="col-sm-6">
                                <div class="form-floating">
                                    <input type="date" class="form-control" id="cancelDate">
                                    <label for="cancelDate">Fecha</label>
                                </div>
                            </div>
                            <div class="col-sm-6">
                                <div class="form-floating">
                                    <select class="form-select" id="cancelField" aria-label="Cancha">
                                        <option value="all">Todas</option>
                                        <?php if (!empty($fields)) : ?>
                                            <?php foreach ($fields as $field) : ?>
                                                <option value="<?= $field['id'] ?>"><?= $field['name'] ?></option>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </select>
                                    <label for="cancelField">Cancha</label>
                                </div>
                                <div id="cancelFieldHint" class="form-text"></div>
                            </div>
                        </div>

                        <div class="mt-3 d-flex gap-2">
                            <button type="button" class="btn btn-primary" id="confirmCancelReservations">Aceptar</button>
                            <button type="button" class="btn btn-outline-secondary d-none" id="cancelEditCancelReservation">Cancelar edicion</button>
                        </div>

                        <div id="cancelReservationsResult" class="mt-3"></div>
                        <div id="existingClosures" class="mt-3"></div>
                    </div>

                    <div class="tab-pane fade" id="cancel-closures-list" role="tabpanel" aria-labelledby="cancel-closures-list-tab">
                        <?= view('superadmin/tabClosures', ['fields' => $fields]) ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="table-responsive-sm" id="tableCustomers">
        <table class="table align-middle table-striped-columns mt-2">
            <thead>
                <tr>
                    <th scope="col">Usuario</th>
                    <th scope="col">Nombre</th>
                    <th scope="col">Superadmin</th>
                    <th scope="col">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $user) : ?>
                    <tr>
                        <td><?= $user['user'] ?></td>
                        <td><?= $user['name'] ?></td>
                        <td><?= $user['superadmin'] == 1 ? 'Si' : 'No' ?></td>
                        <td>
                            <a
                                href="<?= base_url('auth/registerWindow?user_id=' . $user['id']) ?>"
                                class="btn btn-primary me-2 js-open-superadmin-user-management"
                                data-register-url="<?= base_url('auth/registerWindow') ?>"
                                data-fallback-url="<?= base_url('auth/registerWindow') ?>"
                                data-user-id="<?= $user['id'] ?>"
                                role="button"
                            >Editar</a>
                            <form action="<?= base_url('deleteUser/' . $user['id']) ?>" method="POST" style="display:inline;" data-confirm-message="Eliminar usuario seleccionado?"><?= csrf_field() ?> <button type="button" class="btn btn-danger" onclick="if(window.inlineDeleteUser){window.inlineDeleteUser(this.form);}">Eliminar</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<script>
    window.inlineDeleteUser = async function (form) {
        if (!form) return;
        const message = form.getAttribute('data-confirm-message') || 'Eliminar usuario seleccionado?';
        const confirmed = typeof window.showAppConfirm === 'function'
            ? await window.showAppConfirm({
                title: 'Aviso',
                message,
                acceptText: 'Aceptar',
                cancelText: 'Cancelar',
            })
            : window.confirm(message);

        if (!confirmed) return;

        try {
            const response = await fetch(form.action, {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: new FormData(form),
            });

            const data = await response.json();
            if (data.csrf && data.csrf.name && data.csrf.hash) {
                document.querySelectorAll(`input[name="${data.csrf.name}"]`).forEach((input) => {
                    input.value = data.csrf.hash;
                });
            }

            if (!response.ok || data.error) {
                if (window.alert) window.alert(data.message || 'No se pudo eliminar el usuario.');
                return;
            }

            const deletedId = String((data.user && data.user.id) || '');
            const select = document.getElementById('selectUser');
            if (deletedId && select) {
                const option = select.querySelector(`option[value="${deletedId}"]`);
                if (option) option.remove();
            }

            if (form.id === 'deleteUserForm') {
                const title = document.getElementById('userFormTitle');
                const userForm = document.getElementById('formUsers');
                const selectedUserId = document.getElementById('selectedUserId');
                const deleteButton = document.getElementById('buttonDeleteUser');
                if (userForm) {
                    userForm.reset();
                    userForm.action = '<?= base_url('auth/register') ?>';
                }
                if (selectedUserId) selectedUserId.value = '';
                if (select) select.value = '';
                if (title) title.textContent = 'Crear usuario nuevo';
                const submitButton = document.getElementById('submitUserForm');
                if (submitButton) submitButton.textContent = 'Registrar';
                if (deleteButton) deleteButton.classList.add('d-none');
                form.classList.add('d-none');
                form.action = '';
            } else {
                const row = form.closest('tr');
                if (row) row.remove();
            }

            if (window.alert) window.alert(data.message || 'Usuario eliminado correctamente');
        } catch (error) {
            if (window.alert) window.alert('No se pudo eliminar el usuario.');
            console.error(error);
            }
        };
</script>

<?php if (session()->superadmin) : ?>
    <?php $userManagementScriptPath = FCPATH . 'assets/js/userManagementModal.js'; ?>
    <div class="modal fade superadmin-user-modal" id="superadminUserManagementModal" tabindex="-1" aria-labelledby="superadminUserManagementModalLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="superadminUserManagementModalLabel">Usuarios del panel</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body p-0">
                    <iframe id="superadminUserManagementFrame" class="superadmin-user-frame" title="Usuarios del panel"></iframe>
                </div>
            </div>
        </div>
    </div>
    <script src="<?= base_url(PUBLIC_FOLDER . 'assets/js/userManagementModal.js?v=' . (is_file($userManagementScriptPath) ? filemtime($userManagementScriptPath) : time())) ?>"></script>
<?php endif; ?>
