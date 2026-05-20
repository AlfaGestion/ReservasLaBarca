<div class="fieldsButtons mt-3">
    <button type="submit" id="buttonCreateField" class="btn btn-success"><i class="fa-solid fa-plus me-1"></i>Crear</button>
    <button type="submit" id="buttonEditField" class="btn btn-warning"><i class="fa-solid fa-pen-to-square me-1"></i>Editar</button>
</div>

<?php
$serviceLabels = [
    'football' => 'Cancha / Fútbol',
    'padel' => 'Pádel',
    'quincho' => 'Quincho',
    'eventos' => 'Eventos / Confitería',
];
?>

<div class="table-responsive mt-3">
    <table class="table table-striped table-hover align-middle">
        <thead>
            <tr>
                <th>Servicio</th>
                <th>Tipo</th>
                <th>Duración</th>
                <th>Precio base</th>
                <th>Precio nocturno</th>
                <th>Estado</th>
                <th class="text-end">Acciones</th>
            </tr>
        </thead>
        <tbody id="serviceFieldsTableBody">
            <?php if (!empty($fields)) : ?>
                <?php foreach ($fields as $field) : ?>
                    <?php
                    $serviceType = $field['service_type'] ?? 'football';
                    $disabled = (string)($field['disabled'] ?? '0') === '1';
                    ?>
                    <tr data-field-row="<?= esc($field['id']) ?>">
                        <td><?= esc($field['name'] ?? '') ?></td>
                        <td><?= esc($serviceLabels[$serviceType] ?? $serviceType) ?></td>
                        <td><?= esc($field['block_minutes'] ?? 60) ?> min</td>
                        <td>$<?= esc($field['value'] ?? 0) ?></td>
                        <td>$<?= esc($field['ilumination_value'] ?? 0) ?></td>
                        <td>
                            <span class="badge <?= $disabled ? 'bg-secondary' : 'bg-success' ?>">
                                <?= $disabled ? 'Deshabilitado' : 'Activo' ?>
                            </span>
                        </td>
                        <td class="text-end">
                            <button type="button" class="btn btn-sm btn-outline-primary field-edit-row" data-field-id="<?= esc($field['id']) ?>">
                                <i class="fa-solid fa-pen-to-square me-1"></i>Editar
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else : ?>
                <tr id="serviceFieldsEmptyRow">
                    <td colspan="7" class="text-center text-muted py-4">No hay servicios cargados.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<div class="modal fade" id="fieldFormModal" tabindex="-1" aria-labelledby="fieldFormModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="fieldFormModalTitle">Servicio</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <div id="fieldFormMessage" class="alert d-none"></div>

                <div class="enterFields d-none" id="enterFields">
                    <form action="<?= base_url('saveField') ?>" method="POST" class="field-ajax-form">
                        <div class="input-group mt-3 mb-3">
                            <span class="input-group-text">Nombre servicio</span>
                            <input type="text" class="form-control" name="nombre" placeholder="Ingrese el nombre del servicio" aria-label="Nombre servicio">
                        </div>

                        <div class="mb-3">
                            <label class="form-label d-block mb-2">Tipo de reserva</label>
                            <select class="form-select visually-hidden" name="serviceType" id="createServiceType" aria-hidden="true" tabindex="-1">
                                <option value="football">Cancha / Fútbol</option>
                                <option value="padel">Pádel</option>
                                <option value="quincho">Quincho</option>
                                <option value="eventos">Eventos / Confitería</option>
                            </select>
                            <div class="service-type-options">
                                <input class="btn-check" type="radio" name="createServiceTypeOption" id="createServiceTypeFootball" value="football" data-service-select="#createServiceType" data-block-minutes-target="#createBlockMinutes" autocomplete="off" checked>
                                <label class="service-type-option" for="createServiceTypeFootball">Cancha / Fútbol</label>

                                <input class="btn-check" type="radio" name="createServiceTypeOption" id="createServiceTypePadel" value="padel" data-service-select="#createServiceType" data-block-minutes-target="#createBlockMinutes" autocomplete="off">
                                <label class="service-type-option" for="createServiceTypePadel">Pádel</label>

                                <input class="btn-check" type="radio" name="createServiceTypeOption" id="createServiceTypeQuincho" value="quincho" data-service-select="#createServiceType" data-block-minutes-target="#createBlockMinutes" autocomplete="off">
                                <label class="service-type-option" for="createServiceTypeQuincho">Quincho</label>

                                <input class="btn-check" type="radio" name="createServiceTypeOption" id="createServiceTypeEventos" value="eventos" data-service-select="#createServiceType" data-block-minutes-target="#createBlockMinutes" autocomplete="off">
                                <label class="service-type-option" for="createServiceTypeEventos">Eventos / Confitería</label>
                            </div>
                        </div>

                        <div class="input-group mb-3">
                            <span class="input-group-text">Duración del bloque</span>
                            <input type="number" class="form-control" id="createBlockMinutes" name="blockMinutes" value="60" min="30" step="30" aria-label="Duracion del bloque">
                        </div>

                        <div class="input-group mb-3">
                            <span class="input-group-text">Medidas</span>
                            <input type="text" class="form-control" name="medidas" placeholder="Opcional" aria-label="Medidas">
                        </div>

                        <div class="input-group mb-3">
                            <span class="input-group-text">Tipo de piso</span>
                            <input type="text" class="form-control" name="tipoPiso" placeholder="Opcional" aria-label="Tipo piso">
                        </div>

                        <div class="input-group mb-3">
                            <span class="input-group-text">Detalle</span>
                            <input type="text" class="form-control" name="tipoCancha" placeholder="Detalle opcional" aria-label="Detalle">
                        </div>

                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="tipoTecho" role="switch" id="tipoTecho">
                            <label class="form-check-label" for="tipoTecho">Es techada</label>
                        </div>

                        <div class="input-group mb-3">
                            <span class="input-group-text">Precio base</span>
                            <input type="text" class="form-control" name="valor" placeholder="Precio por hora o bloque" aria-label="Valor">
                        </div>

                        <div class="input-group mb-3">
                            <span class="input-group-text">Precio nocturno</span>
                            <input type="text" class="form-control" name="valorIluminacion" placeholder="Si no aplica, repetir precio base" aria-label="Valor nocturno">
                        </div>

                        <button type="submit" class="btn btn-success">Guardar</button>
                        <button type="button" class="btn btn-danger" data-bs-dismiss="modal">Cancelar</button>
                    </form>
                </div>

                <div class="form-floating d-none mt-3" id="selectEditField">
                    <select class="form-select" id="selectEditFields" aria-label="Editar servicio">
                        <option value="">Seleccionar</option>
                        <?php foreach ($fields as $field) : ?>
                            <option value="<?= $field['id'] ?>"><?= $field['name'] ?></option>
                        <?php endforeach; ?>
                    </select>
                    <label for="selectEditFields">Editar servicio</label>
                </div>

                <div id="editFieldDiv"></div>
            </div>
        </div>
    </div>
</div>
