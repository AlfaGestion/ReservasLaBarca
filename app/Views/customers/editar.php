<?php
$customerOffer = $customerOffer ?? null;
$offerActive = ! empty($customerOffer) && (int) ($customerOffer['active'] ?? 0) === 1;
$applyAllFields = ! empty($customerOffer) && (int) ($customerOffer['apply_all_fields'] ?? 0) === 1;
$applyAllServices = ! empty($customerOffer) && (int) ($customerOffer['apply_all_services'] ?? 0) === 1;
$selectedFieldIds = array_map('intval', array_column($customerOffer['fields'] ?? [], 'field_id'));
$selectedServiceCodes = array_map(static fn ($row) => strtolower((string) ($row['service_code'] ?? '')), $customerOffer['services'] ?? []);

$fieldGroups = [];
foreach (($fields ?? []) as $field) {
    $serviceType = (string) ($field['service_type'] ?? 'general');
    if (! isset($fieldGroups[$serviceType])) {
        $fieldGroups[$serviceType] = [];
    }
    $fieldGroups[$serviceType][] = $field;
}

$serviceLabels = [];
foreach (($services ?? []) as $service) {
    $serviceLabels[(string) ($service['code'] ?? '')] = (string) ($service['name'] ?? '');
}

$formatPercent = static function (float $value): string {
    $formatted = number_format($value, 2, ',', '.');
    $formatted = rtrim(rtrim($formatted, '0'), ',');
    return $formatted === '' ? '0' : $formatted;
};

$offerValue = (float) ($customerOffer['value'] ?? 0);
$offerValueLabel = $offerActive ? $formatPercent($offerValue) . '%' : 'Sin descuento';
$fieldCount = count($selectedFieldIds);
$serviceCount = count($selectedServiceCodes);
$fieldScopeLabel = $applyAllFields ? 'Todas las canchas' : ($fieldCount > 0 ? $fieldCount . ' cancha' . ($fieldCount === 1 ? '' : 's') : 'Sin canchas');
$serviceScopeLabel = $applyAllServices ? 'Todos los servicios' : ($serviceCount > 0 ? $serviceCount . ' servicio' . ($serviceCount === 1 ? '' : 's') : 'Sin servicios');
$expirationTimestamp = ! empty($customerOffer['expiration_date']) ? strtotime((string) $customerOffer['expiration_date']) : false;
$expirationLabel = $expirationTimestamp ? date('d/m/Y', $expirationTimestamp) : 'Sin vencimiento';
$previewSummaryParts = array_filter([
    $offerActive ? $offerValueLabel . ' OFF' : 'Sin descuento activo',
    $fieldScopeLabel,
    $serviceScopeLabel,
], static fn ($value) => $value !== '');
$previewSummaryLabel = implode(' · ', $previewSummaryParts);
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar cliente</title>
    <link rel="stylesheet" href="<?= base_url(PUBLIC_FOLDER . 'assets/css/styles.css') ?>">
    <link rel="stylesheet" href="<?= base_url(PUBLIC_FOLDER . 'assets/css/customer-editor.css?v=' . (is_file(FCPATH . 'assets/css/customer-editor.css') ? filemtime(FCPATH . 'assets/css/customer-editor.css') : time())) ?>">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-rbsA2VBKQhggwzxH7PCaAqO46MgnOM80zW1RWuH61DGLwZJEdK2Kadq2F9CUG65" crossorigin="anonymous">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-kenU1KFdBIe4zVF0s0G1M5b4hcpxyD9F7jL+jjXkk+Q2h455rYXK/7HAuoJl+0I4" crossorigin="anonymous"></script>
    <script src="https://kit.fontawesome.com/9bae38f407.js" crossorigin="anonymous"></script>
    <link rel="icon" href="<?= base_url(PUBLIC_FOLDER . 'assets/images/favicon.ico') ?>" type="image/x-icon">
</head>

<body class="customer-editor-page">
    <div class="customer-editor-wrap">
        <div class="customer-editor-shell">
            <header class="customer-editor-header">
                <div class="customer-editor-header__brand">
                    <a href="<?= base_url() ?>" class="customer-editor-logo" aria-label="Ir al inicio">
                        <img src="<?= base_url(PUBLIC_FOLDER . 'assets/images/logo.png') ?>" width="200" alt="La Barca Centro Deportivo">
                    </a>
                    <div>
                        <p class="customer-editor-kicker">Administración de clientes</p>
                        <h1 class="customer-editor-title">Editar cliente</h1>
                        <p class="customer-editor-subtitle">
                            Actualizá los datos del cliente y su oferta personalizada. Esta configuración se usará luego en las reservas y en Mercado Pago.
                        </p>
                    </div>
                </div>
                <div class="customer-editor-header__meta">
                    <span class="customer-status-badge <?= $offerActive ? 'customer-status-badge--success' : 'customer-status-badge--secondary' ?>" id="customerOfferStatusBadge">
                        <?= $offerActive ? 'Descuento activo' : 'Sin descuento activo' ?>
                    </span>
                    <p class="customer-editor-note">
                        La oferta se guarda por cliente, no global, y mantiene su histórico aunque luego cambie la configuración.
                    </p>
                </div>
            </header>

            <main class="customer-editor-body">
                <form action="<?= base_url('customers/editCustomer') ?>" method="POST" id="customerOfferForm">
                    <?php if (session('msg')) : ?>
                        <div class="alert alert-<?= session('msg.type') ?> alert-dismissible fade show" role="alert">
                            <small><?= esc(session('msg.body')) ?></small>
                            <button type="button" class="customer-alert-close" data-bs-dismiss="alert" aria-label="Cerrar">&times;</button>
                        </div>
                    <?php endif; ?>

                    <input type="hidden" value="<?= esc((string) ($customer['id'] ?? '')) ?>" name="idCustomer">
                    <input type="hidden" id="customer_offer_fields_json" name="customer_offer_fields_json" value="<?= esc(json_encode($selectedFieldIds, JSON_UNESCAPED_UNICODE)) ?>">
                    <input type="hidden" id="customer_offer_services_json" name="customer_offer_services_json" value="<?= esc(json_encode($selectedServiceCodes, JSON_UNESCAPED_UNICODE)) ?>">

                    <section class="customer-editor-card">
                        <div class="customer-editor-card__header">
                            <div>
                                <h2 class="customer-editor-card__title">Datos del cliente</h2>
                                <p class="customer-editor-card__subtitle">Mantené actualizados el nombre, documento, teléfono y localidad.</p>
                            </div>
                        </div>
                        <div class="customer-editor-card__body">
                            <div class="customer-editor-grid customer-editor-grid--client">
                                <div class="customer-editor-field">
                                    <label for="customer_name">Nombre</label>
                                    <input type="text" id="customer_name" name="name" class="form-control" placeholder="Nombre" value="<?= esc((string) ($customer['name'] ?? '')) ?>">
                                </div>
                                <div class="customer-editor-field">
                                    <label for="customer_last_name">Apellido</label>
                                    <input type="text" id="customer_last_name" name="last_name" class="form-control" placeholder="Apellido" value="<?= esc((string) ($customer['last_name'] ?? '')) ?>">
                                </div>
                                <div class="customer-editor-field">
                                    <label for="customer_dni">DNI</label>
                                    <input type="text" id="customer_dni" name="dni" class="form-control" placeholder="DNI" value="<?= esc((string) ($customer['dni'] ?? '')) ?>">
                                </div>
                                <div class="customer-editor-field">
                                    <label for="customer_phone">Teléfono</label>
                                    <input type="text" id="customer_phone" name="phone" class="form-control" placeholder="Teléfono" value="<?= esc((string) ($customer['phone'] ?? '')) ?>">
                                </div>
                                <div class="customer-editor-field">
                                    <label for="customer_city">Localidad</label>
                                    <input type="text" id="customer_city" name="city" class="form-control" placeholder="Localidad" value="<?= esc((string) ($customer['city'] ?? '')) ?>">
                                </div>
                            </div>
                        </div>
                    </section>

                    <section class="customer-editor-card">
                        <div class="customer-editor-card__header">
                            <div>
                                <h2 class="customer-editor-card__title">Oferta personalizada</h2>
                                <p class="customer-editor-card__subtitle">Configurá si el cliente tiene descuento, qué porcentaje recibe y sobre qué canchas o servicios aplica.</p>
                            </div>
                            <div class="customer-offer-pill" id="customerOfferPreviewText">
                                <?= esc($previewSummaryLabel) ?>
                            </div>
                        </div>
                        <div class="customer-editor-card__body">
                            <div class="customer-summary-grid">
                                <div class="customer-summary-chip">
                                    <span>Estado</span>
                                    <strong id="customerOfferSummaryState"><?= $offerActive ? 'Activo' : 'Inactivo' ?></strong>
                                </div>
                                <div class="customer-summary-chip">
                                    <span>Descuento</span>
                                    <strong id="customerOfferSummaryValue"><?= esc($offerValueLabel) ?></strong>
                                </div>
                                <div class="customer-summary-chip">
                                    <span>Canchas</span>
                                    <strong id="customerOfferSummaryFields"><?= esc($fieldScopeLabel) ?></strong>
                                </div>
                                <div class="customer-summary-chip">
                                    <span>Servicios</span>
                                    <strong id="customerOfferSummaryServices"><?= esc($serviceScopeLabel) ?></strong>
                                </div>
                                <div class="customer-summary-chip">
                                    <span>Vencimiento</span>
                                    <strong id="customerOfferSummaryExpiration"><?= esc($expirationLabel) ?></strong>
                                </div>
                            </div>

                            <div class="customer-editor-grid customer-editor-grid--offer">
                                <div class="customer-editor-field">
                                    <div class="customer-editor-switch">
                                        <input class="form-check-input" type="checkbox" role="switch" id="customer_offer_active" name="customer_offer_active" <?= $offerActive ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="customer_offer_active">Tiene descuento</label>
                                    </div>
                                </div>
                                <div class="customer-editor-field">
                                    <label for="customer_offer_value">Porcentaje</label>
                                    <div class="customer-editor-percent-group">
                                        <input type="number" class="form-control" id="customer_offer_value" name="customer_offer_value" min="0" max="100" step="0.01" value="<?= esc((string) ($customerOffer['value'] ?? 0)) ?>">
                                        <span class="customer-offer-pill customer-editor-percent-symbol">%</span>
                                    </div>
                                </div>
                                <div class="customer-editor-field">
                                    <label for="customer_offer_expiration_date">Vencimiento</label>
                                    <input type="date" class="form-control" id="customer_offer_expiration_date" name="customer_offer_expiration_date" value="<?= esc((string) ($customerOffer['expiration_date'] ?? '')) ?>">
                                </div>
                            </div>

                            <div class="customer-editor-field" style="margin-top: 16px;">
                                <label for="customer_offer_description">Descripción de la oferta</label>
                                <textarea class="form-control" rows="3" id="customer_offer_description" name="customer_offer_description" placeholder="Ej: 20% cliente frecuente"><?= esc((string) ($customerOffer['description'] ?? '')) ?></textarea>
                            </div>

                            <div class="customer-editor-grid customer-editor-grid--scopes" style="margin-top: 18px;">
                                <div class="offer-scope-card" data-scope="fields">
                                    <div class="offer-scope-card__header">
                                        <h3>Aplicar descuento a canchas</h3>
                                        <p>Elegí una o varias canchas, o activá el alcance completo para cubrirlas todas.</p>
                                    </div>

                                    <div class="customer-editor-switch">
                                        <input class="form-check-input" type="checkbox" role="switch" id="customer_offer_apply_all_fields" name="customer_offer_apply_all_fields" <?= $applyAllFields ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="customer_offer_apply_all_fields">Todas las canchas</label>
                                    </div>

                                    <div class="offer-choice-grid">
                                        <?php foreach ($fieldGroups as $serviceType => $groupFields) : ?>
                                            <?php $serviceLabel = $serviceLabels[(string) $serviceType] ?? ucfirst((string) $serviceType); ?>
                                            <?php foreach ($groupFields as $field) : ?>
                                                <?php $fieldId = (int) ($field['id'] ?? 0); ?>
                                                <label class="offer-choice-card <?= in_array($fieldId, $selectedFieldIds, true) ? 'is-selected' : '' ?>" data-offer-card="field">
                                                    <input class="form-check-input customer-offer-field" type="checkbox" value="<?= esc((string) $fieldId) ?>" id="customer_offer_field_<?= esc((string) $fieldId) ?>" <?= in_array($fieldId, $selectedFieldIds, true) ? 'checked' : '' ?>>
                                                    <span>
                                                        <strong><?= esc((string) ($field['name'] ?? 'Cancha')) ?></strong>
                                                        <small><?= esc((string) ($field['service_name'] ?? $serviceLabel)) ?></small>
                                                    </span>
                                                </label>
                                            <?php endforeach; ?>
                                        <?php endforeach; ?>
                                    </div>
                                </div>

                                <div class="offer-scope-card" data-scope="services">
                                    <div class="offer-scope-card__header">
                                        <h3>Aplicar descuento a servicios</h3>
                                        <p>Seleccioná uno o varios tipos de servicio, o marcá todos los disponibles.</p>
                                    </div>

                                    <div class="customer-editor-switch">
                                        <input class="form-check-input" type="checkbox" role="switch" id="customer_offer_apply_all_services" name="customer_offer_apply_all_services" <?= $applyAllServices ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="customer_offer_apply_all_services">Todos los servicios</label>
                                    </div>

                                    <div class="offer-choice-grid">
                                        <?php foreach (($services ?? []) as $service) : ?>
                                            <?php $serviceCode = strtolower((string) ($service['code'] ?? '')); ?>
                                            <label class="offer-choice-card <?= in_array($serviceCode, $selectedServiceCodes, true) ? 'is-selected' : '' ?>" data-offer-card="service">
                                                <input class="form-check-input customer-offer-service" type="checkbox" value="<?= esc($serviceCode) ?>" id="customer_offer_service_<?= esc($serviceCode) ?>" <?= in_array($serviceCode, $selectedServiceCodes, true) ? 'checked' : '' ?>>
                                                <span>
                                                    <strong><?= esc((string) ($service['name'] ?? $serviceCode)) ?></strong>
                                                    <small><?= esc($serviceCode) ?></small>
                                                </span>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>

                            <div class="customer-offer-helper mt-4" id="customerOfferHelperText">
                                Si desactivás la oferta, la configuración queda guardada pero no se aplicará a nuevas reservas.
                            </div>
                        </div>
                    </section>

                    <div class="customer-editor-actions">
                        <a href="<?= base_url('abmAdmin') ?>" class="customer-editor-btn customer-editor-btn--secondary">Volver</a>
                        <button type="submit" class="customer-editor-btn customer-editor-btn--primary">Guardar</button>
                    </div>
                </form>
            </main>
        </div>
    </div>

    <?php
    $customerOfferEditorPath = FCPATH . 'assets/js/customerOfferEditor.js';
    $customerOfferEditorVersion = is_file($customerOfferEditorPath) ? filemtime($customerOfferEditorPath) : time();
    ?>
    <script>
        window.customerOfferEditorState = {
            active: <?= $offerActive ? 'true' : 'false' ?>,
            applyAllFields: <?= $applyAllFields ? 'true' : 'false' ?>,
            applyAllServices: <?= $applyAllServices ? 'true' : 'false' ?>
        };
    </script>
    <script src="<?= base_url(PUBLIC_FOLDER . 'assets/js/customerOfferEditor.js?v=' . $customerOfferEditorVersion) ?>"></script>
</body>

</html>
