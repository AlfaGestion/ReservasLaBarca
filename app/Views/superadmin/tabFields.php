<div class="fieldsButtons mt-3">
    <button type="button" id="buttonCreateField" class="btn btn-success"><i class="fa-solid fa-plus me-1"></i>Nuevo</button>
</div>

<?php
$services = $services ?? [];
$fields = $fields ?? [];
$serviceLabels = [];
$serviceColors = [];
foreach ($services as $serviceForLabel) {
    $serviceLabels[$serviceForLabel['code']] = $serviceForLabel['name'];
    $serviceColors[$serviceForLabel['code']] = $serviceForLabel['color'] ?? '#F39323';
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
    [$durationHours, $durationMinutesRemainder] = $splitMinutes($duration);
    $action = $isCreate ? base_url('saveService') : base_url('editService/' . $service['id']);
    $code = (string)($service['code'] ?? '');
    $offerActive = !empty($service['offer_active']);
    ?>
    <form action="<?= $action ?>" method="POST" class="service-config-card service-config-form">
        <div class="service-config-header">
            <div>
                <div class="text-uppercase small text-muted fw-semibold">Servicio</div>
                <h5 class="mb-1"><?= esc($service['name'] ?? 'Nuevo servicio') ?></h5>
            </div>
            <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk me-1"></i>Guardar</button>
        </div>

        <input type="hidden" name="code" value="<?= esc($code) ?>">

        <div class="row g-3">
            <div class="col-lg-5">
                <label class="form-label">Nombre visible</label>
                <input class="form-control" name="name" value="<?= esc($service['name'] ?? '') ?>" required>
            </div>
            <div class="col-lg-2">
                <label class="form-label">Color</label>
                <input type="color" class="form-control form-control-color" name="color" value="<?= esc($service['color'] ?? '#F39323') ?>" title="Color del botón de este tipo de reserva">
            </div>
            <div class="col-lg-5">
                <div class="service-config-section h-100">
                    <h6>Estados</h6>
                    <div class="row g-2">
                        <div class="col-md-4">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="active<?= esc($code) ?>" name="active" <?= !array_key_exists('active', $service) || !empty($service['active']) ? 'checked' : '' ?>>
                                <label class="form-check-label fw-semibold" for="active<?= esc($code) ?>">Activo</label>
                                <div class="small text-muted">Permite usar este servicio en el sistema.</div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="online<?= esc($code) ?>" name="online_available" <?= !array_key_exists('online_available', $service) || !empty($service['online_available']) ? 'checked' : '' ?>>
                                <label class="form-check-label fw-semibold" for="online<?= esc($code) ?>">Visible online</label>
                                <div class="small text-muted">Lo muestra en la web para que los clientes lo puedan reservar.</div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="quinchoAddon<?= esc($code) ?>" name="allows_quincho_addon" <?= !array_key_exists('allows_quincho_addon', $service) || !empty($service['allows_quincho_addon']) ? 'checked' : '' ?>>
                                <label class="form-check-label fw-semibold" for="quinchoAddon<?= esc($code) ?>">Permite quincho adicional</label>
                                <div class="small text-muted">Después de reservar este servicio, ofrece agregar quincho si está disponible.</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="service-config-section">
            <h6>Horarios y duración</h6>
            <div class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label">Desde</label>
                    <input type="time" class="form-control" name="opening_time" value="<?= esc(substr((string)($service['opening_time'] ?? '07:00'), 0, 5)) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Hasta</label>
                    <input type="time" class="form-control" name="closing_time" value="<?= esc(substr((string)($service['closing_time'] ?? '23:00'), 0, 5)) ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Horas</label>
                    <input type="number" class="form-control service-duration-hours" name="duration_hours" min="0" step="1" value="<?= esc($durationHours) ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Minutos</label>
                    <input type="number" class="form-control service-duration-minutes" name="duration_minutes_remainder" min="0" max="59" step="15" value="<?= esc($durationMinutesRemainder) ?>">
                </div>
                <div class="col-md-2">
                    <div class="service-duration-preview"><?= esc($duration) ?> min (<?= esc(minutesToHuman($duration)) ?>)</div>
                </div>
            </div>
        </div>

        <div class="service-config-section service-offer-section">
            <h6>Oferta</h6>
            <div class="form-check form-switch mb-3">
                <input class="form-check-input service-offer-toggle" type="checkbox" role="switch" id="offer<?= esc($code) ?>" name="offer_active" <?= $offerActive ? 'checked' : '' ?>>
                <label class="form-check-label fw-semibold" for="offer<?= esc($code) ?>">Activar oferta</label>
            </div>
            <div class="service-offer-fields <?= $offerActive ? '' : 'd-none' ?>">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label d-block">Tipo de descuento</label>
                        <div class="btn-group" role="group">
                            <input type="radio" class="btn-check service-discount-type" name="discount_type" id="discountPct<?= esc($code) ?>" value="percentage" <?= ($service['discount_type'] ?? '') !== 'fixed' ? 'checked' : '' ?>>
                            <label class="btn btn-outline-secondary" for="discountPct<?= esc($code) ?>">Porcentaje %</label>
                            <input type="radio" class="btn-check service-discount-type" name="discount_type" id="discountFixed<?= esc($code) ?>" value="fixed" <?= ($service['discount_type'] ?? '') === 'fixed' ? 'checked' : '' ?>>
                            <label class="btn btn-outline-secondary" for="discountFixed<?= esc($code) ?>">Monto fijo $</label>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Valor</label>
                        <input class="form-control service-discount-value" name="discount_value" value="<?= esc($service['discount_value'] ?? 0) ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Texto para mostrar</label>
                        <input class="form-control service-offer-text" name="offer_text" placeholder="Promo verano" value="<?= esc($service['offer_text'] ?? '') ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Desde</label>
                        <input type="date" class="form-control" name="offer_start_date" value="<?= esc($service['offer_start_date'] ?? '') ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Hasta</label>
                        <input type="date" class="form-control" name="offer_end_date" value="<?= esc($service['offer_end_date'] ?? '') ?>">
                    </div>
                </div>
                <div class="service-offer-preview mt-3">Se mostrará como: -</div>
            </div>
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

<div class="d-none mt-3" id="newServicePanel">
    <?php $renderServiceForm([
        'opening_time' => '07:00',
        'closing_time' => '23:00',
        'duration_minutes' => 60,
        'active' => 1,
        'online_available' => 1,
        'allows_quincho_addon' => 1,
        'discount_type' => 'percentage',
    ], true); ?>
</div>

<div class="table-responsive mt-3">
    <table class="table table-striped table-hover align-middle">
        <thead>
            <tr>
                <th>Servicio</th>
                <th>Horario</th>
                <th>Duraci&oacute;n</th>
                <th>Estado</th>
                <th>Online</th>
                <th>Oferta</th>
                <th class="text-end">Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($services as $service) : ?>
                <?php
                $serviceId = (int)($service['id'] ?? 0);
                $duration = (int)($service['duration_minutes'] ?? $service['minimum_duration_minutes'] ?? 60);
                $active = !array_key_exists('active', $service) || !empty($service['active']);
                $online = !array_key_exists('online_available', $service) || !empty($service['online_available']);
                $offer = !empty($service['offer_active']);
                $serviceBadgeColor = strtoupper((string)($service['color'] ?? '#F39323'));
                if (!preg_match('/^#[0-9A-F]{6}$/', $serviceBadgeColor)) {
                    $serviceBadgeColor = '#F39323';
                }
                ?>
                <tr>
                    <td>
                        <span class="badge" style="background-color: <?= esc($serviceBadgeColor) ?>; color:#fff; font-weight:700;"><?= esc($service['name'] ?? '') ?></span>
                    </td>
                    <td><?= esc(substr((string)($service['opening_time'] ?? '07:00'), 0, 5)) ?> a <?= esc(substr((string)($service['closing_time'] ?? '23:00'), 0, 5)) ?></td>
                    <td><?= esc(minutesToHuman($duration)) ?></td>
                    <td><span class="badge <?= $active ? 'bg-success' : 'bg-secondary' ?>"><?= $active ? 'Activo' : 'Inactivo' ?></span></td>
                    <td><span class="badge <?= $online ? 'bg-primary' : 'bg-secondary' ?>"><?= $online ? 'Visible' : 'Oculto' ?></span></td>
                    <td>
                        <?php if ($offer) : ?>
                            <span class="badge bg-warning text-dark"><?= esc($service['offer_text'] ?: 'Oferta activa') ?></span>
                        <?php else : ?>
                            <span class="text-muted">Sin oferta</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-end">
                        <button type="button" class="btn btn-sm btn-outline-primary service-edit-row" data-service-id="<?= esc($serviceId) ?>">
                            <i class="fa-solid fa-pen-to-square me-1"></i>Editar
                        </button>
                    </td>
                </tr>
                <tr class="service-edit-panel d-none" id="serviceEditPanel<?= esc($serviceId) ?>">
                    <td colspan="7">
                        <?php $renderServiceForm($service); ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

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
                    $serviceColor = strtoupper((string)($serviceColors[$serviceType] ?? '#F39323'));
                    if (!preg_match('/^#[0-9A-F]{6}$/', $serviceColor)) {
                        $serviceColor = '#F39323';
                    }
                    ?>
                    <tr data-field-row="<?= esc($field['id']) ?>">
                        <td><?= esc($field['name'] ?? '') ?></td>
                        <td><span class="badge" style="background-color: <?= esc($serviceColor) ?>; color:#fff; font-weight:700;"><?= esc($serviceLabels[$serviceType] ?? $serviceType) ?></span></td>
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
                    <td colspan="7" class="text-center text-muted py-4">No hay precios o espacios cargados.</td>
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
                <h5 class="modal-title" id="fieldFormModalTitle">Precio</h5>
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
                                    <input class="btn-check" type="radio" name="createServiceTypeOption" id="<?= esc($optionId) ?>" value="<?= esc($service['code']) ?>" data-service-select="#createServiceType" data-block-minutes-target="#createBlockMinutes" data-duration-minutes="<?= esc($service['duration_minutes'] ?? $service['minimum_duration_minutes'] ?? 60) ?>" data-color="<?= esc($service['color'] ?? '#F39323') ?>" autocomplete="off" <?= $index === 0 ? 'checked' : '' ?>>
                                    <label class="service-type-option" style="--service-color: <?= esc($service['color'] ?? '#F39323') ?>;" for="<?= esc($optionId) ?>"><?= esc($service['name']) ?></label>
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
                    <select class="form-select" id="selectEditFields" aria-label="Editar precio">
                        <option value="">Seleccionar</option>
                        <?php foreach ($fields as $field) : ?>
                            <option value="<?= $field['id'] ?>"><?= $field['name'] ?></option>
                        <?php endforeach; ?>
                    </select>
                    <label for="selectEditFields">Editar precio</label>
                </div>

                <div id="editFieldDiv"></div>
            </div>
        </div>
    </div>
</div>

