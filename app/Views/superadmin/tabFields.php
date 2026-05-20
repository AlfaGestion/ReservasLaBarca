<div class="fieldsButtons mt-3">
    <button type="button" id="buttonCreateField" class="btn btn-success"><i class="fa-solid fa-plus me-1"></i>Nuevo</button>
</div>

<?php
$services = $services ?? [];
$fields = $fields ?? [];
$serviceLabels = [];
foreach ($services as $serviceForLabel) {
    $serviceLabels[$serviceForLabel['code']] = $serviceForLabel['name'];
}
$defaultService = $services[0] ?? [
    'code' => 'football',
    'name' => 'Cancha / Futbol',
    'duration_minutes' => 60,
];
$splitMinutes = static function ($minutes): array {
    $minutes = max(0, (int) $minutes);
    return [intdiv($minutes, 60), $minutes % 60];
};
$renderServiceForm = static function (array $service, bool $isCreate = false) use ($splitMinutes): void {
    $duration = (int)($service['duration_minutes'] ?? $service['minimum_duration_minutes'] ?? 60);
    $interval = (int)($service['slot_interval_minutes'] ?? $service['booking_interval_minutes'] ?? $duration);
    [$durationHours, $durationMinutesRemainder] = $splitMinutes($duration);
    [$intervalHours, $intervalMinutesRemainder] = $splitMinutes($interval);
    $action = $isCreate ? base_url('saveService') : base_url('editService/' . $service['id']);
    ?>
    <form action="<?= $action ?>" method="POST" class="row g-2 align-items-end">
        <div class="col-lg-2">
            <label class="form-label small mb-1">Servicio</label>
            <input class="form-control form-control-sm" name="name" value="<?= esc($service['name'] ?? '') ?>" required>
            <?php if ($isCreate) : ?>
                <input class="form-control form-control-sm mt-1" name="code" placeholder="codigo_sin_espacios" required>
            <?php else : ?>
                <div class="small text-muted"><code><?= esc($service['code'] ?? '') ?></code></div>
            <?php endif; ?>
        </div>
        <div class="col-lg-2">
            <label class="form-label small mb-1">Horario</label>
            <div class="d-flex gap-1">
                <input type="time" class="form-control form-control-sm" name="opening_time" value="<?= esc(substr((string)($service['opening_time'] ?? '07:00'), 0, 5)) ?>">
                <input type="time" class="form-control form-control-sm" name="closing_time" value="<?= esc(substr((string)($service['closing_time'] ?? '23:00'), 0, 5)) ?>">
            </div>
        </div>
        <div class="col-lg-2">
            <label class="form-label small mb-1">Duraci&oacute;n <?= esc(minutesToHuman($duration)) ?></label>
            <div class="d-flex gap-1">
                <input type="number" class="form-control form-control-sm" name="duration_hours" min="0" step="1" value="<?= esc($durationHours) ?>" aria-label="Horas de duracion">
                <input type="number" class="form-control form-control-sm" name="duration_minutes_remainder" min="0" max="59" step="15" value="<?= esc($durationMinutesRemainder) ?>" aria-label="Minutos de duracion">
            </div>
        </div>
        <div class="col-lg-2">
            <label class="form-label small mb-1">Intervalo <?= esc(minutesToHuman($interval)) ?></label>
            <div class="d-flex gap-1">
                <input type="number" class="form-control form-control-sm" name="slot_interval_hours" min="0" step="1" value="<?= esc($intervalHours) ?>" aria-label="Horas de intervalo">
                <input type="number" class="form-control form-control-sm" name="slot_interval_minutes_remainder" min="0" max="59" step="15" value="<?= esc($intervalMinutesRemainder) ?>" aria-label="Minutos de intervalo">
            </div>
        </div>
        <div class="col-lg-2">
            <label class="form-label small mb-1">Estados</label>
            <div class="d-flex flex-wrap gap-2 small">
                <label><input type="checkbox" name="active" <?= !array_key_exists('active', $service) || !empty($service['active']) ? 'checked' : '' ?>> Activo</label>
                <label><input type="checkbox" name="online_available" <?= !array_key_exists('online_available', $service) || !empty($service['online_available']) ? 'checked' : '' ?>> Online</label>
                <label><input type="checkbox" name="allows_quincho_addon" <?= !array_key_exists('allows_quincho_addon', $service) || !empty($service['allows_quincho_addon']) ? 'checked' : '' ?>> Quincho</label>
            </div>
        </div>
        <div class="col-lg-3">
            <label class="form-label small mb-1">Oferta</label>
            <div class="d-flex gap-1 mb-1">
                <select class="form-select form-select-sm" name="discount_type">
                    <option value="percentage" <?= ($service['discount_type'] ?? '') !== 'fixed' ? 'selected' : '' ?>>%</option>
                    <option value="fixed" <?= ($service['discount_type'] ?? '') === 'fixed' ? 'selected' : '' ?>>$</option>
                </select>
                <input class="form-control form-control-sm" name="discount_value" value="<?= esc($service['discount_value'] ?? 0) ?>">
                <input type="date" class="form-control form-control-sm" name="offer_start_date" value="<?= esc($service['offer_start_date'] ?? '') ?>">
                <input type="date" class="form-control form-control-sm" name="offer_end_date" value="<?= esc($service['offer_end_date'] ?? '') ?>">
            </div>
            <div class="d-flex gap-2">
                <label class="small"><input type="checkbox" name="offer_active" <?= !empty($service['offer_active']) ? 'checked' : '' ?>> Activa</label>
                <input class="form-control form-control-sm" name="offer_text" placeholder="Texto de oferta" value="<?= esc($service['offer_text'] ?? '') ?>">
            </div>
        </div>
        <div class="col-lg-1 text-end">
            <button type="submit" class="btn btn-sm btn-primary">Guardar</button>
        </div>
    </form>
    <?php
};
?>

<ul class="nav nav-tabs mt-3" id="servicesRatesTabs" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link active" id="services-subtab" data-bs-toggle="tab" data-bs-target="#services-panel" type="button" role="tab">Servicios</button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="prices-subtab" data-bs-toggle="tab" data-bs-target="#prices-panel" type="button" role="tab">Precios</button>
    </li>
</ul>

<div class="tab-content" id="servicesRatesTabsContent">
<div class="tab-pane fade show active" id="services-panel" role="tabpanel" aria-labelledby="services-subtab">

<div class="table-responsive mt-3 d-none" id="newServicePanel">
    <table class="table table-striped table-hover align-middle">
        <tbody>
            <tr>
                <td>
                    <?php $renderServiceForm([
                        'opening_time' => '07:00',
                        'closing_time' => '23:00',
                        'duration_minutes' => 60,
                        'slot_interval_minutes' => 60,
                        'active' => 1,
                        'online_available' => 1,
                        'allows_quincho_addon' => 1,
                    ], true); ?>
                </td>
            </tr>
        </tbody>
    </table>
</div>

<?php if (!empty($services)) : ?>
    <div class="table-responsive mt-3">
        <table class="table table-striped table-hover align-middle">
            <thead>
                <tr>
                    <th>Servicio</th>
                    <th>C&oacute;digo</th>
                    <th>Horario</th>
                    <th>Duraci&oacute;n</th>
                    <th>Intervalo</th>
                    <th>Online</th>
                    <th>Oferta</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($services as $service) : ?>
                    <tr>
                        <td colspan="7">
                            <?php $renderServiceForm($service); ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php else : ?>
    <div class="text-center text-muted py-4">No hay servicios cargados.</div>
<?php endif; ?>

</div>
<div class="tab-pane fade" id="prices-panel" role="tabpanel" aria-labelledby="prices-subtab">

<div class="table-responsive mt-3">
    <table class="table table-striped table-hover align-middle">
        <thead>
            <tr>
                <th>Servicio</th>
                <th>Tipo</th>
                <th>Duraci&oacute;n</th>
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
                        <td><?= esc(minutesToHuman($field['duration_minutes'] ?? $field['block_minutes'] ?? 60)) ?></td>
                        <td><?= format_price_ar($field['value'] ?? 0) ?></td>
                        <td><?= format_price_ar($field['ilumination_value'] ?? 0) ?></td>
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

</div>
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
                                <?php foreach ($services as $index => $service) : ?>
                                    <option value="<?= esc($service['code']) ?>" <?= $index === 0 ? 'selected' : '' ?>><?= esc($service['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div class="service-type-options">
                                <?php foreach ($services as $index => $service) : ?>
                                    <?php $optionId = 'createServiceType' . preg_replace('/[^A-Za-z0-9]/', '', (string)$service['code']); ?>
                                    <input class="btn-check" type="radio" name="createServiceTypeOption" id="<?= esc($optionId) ?>" value="<?= esc($service['code']) ?>" data-service-select="#createServiceType" data-block-minutes-target="#createBlockMinutes" data-duration-minutes="<?= esc($service['duration_minutes'] ?? $service['minimum_duration_minutes'] ?? 60) ?>" autocomplete="off" <?= $index === 0 ? 'checked' : '' ?>>
                                    <label class="service-type-option" for="<?= esc($optionId) ?>"><?= esc($service['name']) ?></label>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div class="input-group mb-3">
                            <span class="input-group-text">Duraci&oacute;n del bloque</span>
                            <input type="number" class="form-control" id="createBlockMinutes" name="blockMinutes" value="<?= esc($defaultService['duration_minutes'] ?? 60) ?>" min="30" step="30" aria-label="Duracion del bloque">
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

