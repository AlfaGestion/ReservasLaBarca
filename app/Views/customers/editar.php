<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar cliente</title>
    <link rel="stylesheet" href="<?= base_url(PUBLIC_FOLDER . 'assets/css/styles.css') ?>">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-rbsA2VBKQhggwzxH7PCaAqO46MgnOM80zW1RWuH61DGLwZJEdK2Kadq2F9CUG65" crossorigin="anonymous">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-kenU1KFdBIe4zVF0s0G1M5b4hcpxyD9F7jL+jjXkk+Q2h455rYXK/7HAuoJl+0I4" crossorigin="anonymous"></script>
    <script src="https://kit.fontawesome.com/9bae38f407.js" crossorigin="anonymous"></script>
    <link rel="icon" href="<?= base_url(PUBLIC_FOLDER . 'assets/images/favicon.ico') ?>" type="image/x-icon">
</head>

<?php
$customerOffer = $customerOffer ?? null;
$offerActive = !empty($customerOffer) && (int) ($customerOffer['active'] ?? 0) === 1;
$applyAllFields = !empty($customerOffer) && (int) ($customerOffer['apply_all_fields'] ?? 0) === 1;
$applyAllServices = !empty($customerOffer) && (int) ($customerOffer['apply_all_services'] ?? 0) === 1;
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
?>

<body style="background-color: #f8f9fa;">
    <div class="container login-page d-flex justify-content-center align-items-center py-4">
        <div class="login-box w-100" style="max-width: 1100px;">
            <div class="login-box-body d-flex flex-column justify-content-center align-items-stretch">
                <div class="login-logo text-center mb-4">
                    <a href="<?= base_url() ?>"><img src="<?= base_url(PUBLIC_FOLDER . 'assets/images/logo.png') ?>" width="200px" alt=""></a>
                </div>

                <form action="<?= base_url('customers/editCustomer') ?>" method="POST" id="customerOfferForm">
                    <?php if (session('msg')) : ?>
                        <div class="alert alert-<?= session('msg.type') ?> alert-dismissible fade show" role="alert">
                            <small><?= session('msg.body') ?></small>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>

                    <h1 class="text-center mb-4" style="font-family:'Franklin Gothic Medium','Arial Narrow',Arial,sans-serif;color:#595959">Editar cliente</h1>

                    <input type="hidden" value="<?= esc($customer['id'] ?? '') ?>" name="idCustomer">
                    <input type="hidden" id="customer_offer_fields_json" name="customer_offer_fields_json" value="<?= esc(json_encode($selectedFieldIds, JSON_UNESCAPED_UNICODE)) ?>">
                    <input type="hidden" id="customer_offer_services_json" name="customer_offer_services_json" value="<?= esc(json_encode($selectedServiceCodes, JSON_UNESCAPED_UNICODE)) ?>">

                    <div class="row g-3 mb-4">
                        <div class="col-12 col-lg-6">
                            <div class="form-group">
                                <input type="text" name="name" class="form-control form-control-lg" placeholder="Nombre" value="<?= esc($customer['name'] ?? '') ?>">
                            </div>
                        </div>
                        <div class="col-12 col-lg-6">
                            <div class="form-group">
                                <input type="text" name="last_name" class="form-control form-control-lg" placeholder="Apellido" value="<?= esc($customer['last_name'] ?? '') ?>">
                            </div>
                        </div>
                        <div class="col-12 col-lg-4">
                            <div class="form-group">
                                <input type="text" name="dni" class="form-control" placeholder="DNI" value="<?= esc($customer['dni'] ?? '') ?>">
                            </div>
                        </div>
                        <div class="col-12 col-lg-4">
                            <div class="form-group">
                                <input type="text" name="phone" class="form-control" placeholder="Teléfono" value="<?= esc($customer['phone'] ?? '') ?>">
                            </div>
                        </div>
                        <div class="col-12 col-lg-4">
                            <div class="form-group">
                                <input type="text" name="city" class="form-control" placeholder="Localidad" value="<?= esc($customer['city'] ?? '') ?>">
                            </div>
                        </div>
                    </div>

                    <div class="card shadow-sm border-0 mb-4">
                        <div class="card-header bg-white border-0 d-flex flex-column flex-lg-row justify-content-between align-items-start align-items-lg-center gap-2">
                            <div>
                                <h2 class="h4 mb-1">Oferta personalizada</h2>
                                <p class="text-muted mb-0">La configuración se guarda por cliente y se usa luego en reservas y Mercado Pago.</p>
                            </div>
                            <span class="badge <?= $offerActive ? 'bg-success' : 'bg-secondary' ?> fs-6 px-3 py-2" id="customerOfferStatusBadge">
                                <?= $offerActive ? 'Descuento activo' : 'Sin descuento activo' ?>
                            </span>
                        </div>
                        <div class="card-body">
                            <div class="row g-3 align-items-end mb-3">
                                <div class="col-12 col-lg-3">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" role="switch" id="customer_offer_active" name="customer_offer_active" <?= $offerActive ? 'checked' : '' ?>>
                                        <label class="form-check-label fw-semibold" for="customer_offer_active">Tiene descuento</label>
                                    </div>
                                </div>
                                <div class="col-6 col-lg-3">
                                    <label class="form-label" for="customer_offer_value">Porcentaje</label>
                                    <div class="input-group">
                                        <input type="number" class="form-control" id="customer_offer_value" name="customer_offer_value" min="0" max="100" step="0.01" value="<?= esc((string) ($customerOffer['value'] ?? 0)) ?>">
                                        <span class="input-group-text">%</span>
                                    </div>
                                </div>
                                <div class="col-6 col-lg-3">
                                    <label class="form-label" for="customer_offer_expiration_date">Vencimiento</label>
                                    <input type="date" class="form-control" id="customer_offer_expiration_date" name="customer_offer_expiration_date" value="<?= esc($customerOffer['expiration_date'] ?? '') ?>">
                                </div>
                                <div class="col-12 col-lg-3">
                                    <div class="p-3 rounded bg-light border">
                                        <div class="small text-muted">Estado actual</div>
                                        <div class="fw-semibold" id="customerOfferPreviewText">
                                            <?= $offerActive ? esc($customerOffer['scope_label'] ?? 'Oferta activa') : 'Sin descuento configurado' ?>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label" for="customer_offer_description">Descripción de la oferta</label>
                                <textarea class="form-control" rows="3" id="customer_offer_description" name="customer_offer_description" placeholder="Ej: 20% cliente frecuente"><?= esc($customerOffer['description'] ?? '') ?></textarea>
                            </div>

                            <div class="row g-4">
                                <div class="col-12 col-xl-6">
                                    <div class="border rounded-3 p-3 h-100 offer-scope-box" data-scope="fields">
                                        <div class="d-flex justify-content-between align-items-center mb-3">
                                            <div>
                                                <h3 class="h5 mb-1">Aplicar descuento a canchas</h3>
                                                <p class="text-muted mb-0">Elegí una o varias canchas, o marcá todas para cubrirlas completas.</p>
                                            </div>
                                        </div>

                                        <div class="form-check form-switch mb-3">
                                            <input class="form-check-input" type="checkbox" role="switch" id="customer_offer_apply_all_fields" name="customer_offer_apply_all_fields" <?= $applyAllFields ? 'checked' : '' ?>>
                                            <label class="form-check-label fw-semibold" for="customer_offer_apply_all_fields">Todas las canchas</label>
                                        </div>

                                        <div class="row g-2">
                                            <?php foreach ($fieldGroups as $serviceType => $groupFields) : ?>
                                                <?php $serviceLabel = $serviceLabels[$serviceType] ?? ucfirst($serviceType); ?>
                                                <div class="col-12">
                                                    <div class="small text-uppercase text-muted mb-2"><?= esc($serviceLabel) ?></div>
                                                    <div class="row g-2">
                                                        <?php foreach ($groupFields as $field) : ?>
                                                            <?php $fieldId = (int) ($field['id'] ?? 0); ?>
                                                            <div class="col-12 col-md-6">
                                                                <div class="form-check border rounded px-3 py-2">
                                                                    <input class="form-check-input customer-offer-field" type="checkbox" value="<?= esc((string) $fieldId) ?>" id="customer_offer_field_<?= esc((string) $fieldId) ?>" <?= in_array($fieldId, $selectedFieldIds, true) ? 'checked' : '' ?>>
                                                                    <label class="form-check-label ms-2" for="customer_offer_field_<?= esc((string) $fieldId) ?>">
                                                                        <span class="fw-semibold d-block"><?= esc($field['name'] ?? 'Cancha') ?></span>
                                                                        <small class="text-muted"><?= esc($field['service_name'] ?? $serviceLabel) ?></small>
                                                                    </label>
                                                                </div>
                                                            </div>
                                                        <?php endforeach; ?>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-12 col-xl-6">
                                    <div class="border rounded-3 p-3 h-100 offer-scope-box" data-scope="services">
                                        <div class="d-flex justify-content-between align-items-center mb-3">
                                            <div>
                                                <h3 class="h5 mb-1">Aplicar descuento a servicios</h3>
                                                <p class="text-muted mb-0">Seleccioná un tipo de servicio o marcá todos los servicios disponibles.</p>
                                            </div>
                                        </div>

                                        <div class="form-check form-switch mb-3">
                                            <input class="form-check-input" type="checkbox" role="switch" id="customer_offer_apply_all_services" name="customer_offer_apply_all_services" <?= $applyAllServices ? 'checked' : '' ?>>
                                            <label class="form-check-label fw-semibold" for="customer_offer_apply_all_services">Todos los servicios</label>
                                        </div>

                                        <div class="row g-2">
                                            <?php foreach (($services ?? []) as $service) : ?>
                                                <?php $serviceCode = strtolower((string) ($service['code'] ?? '')); ?>
                                                <div class="col-12 col-md-6">
                                                    <div class="form-check border rounded px-3 py-2">
                                                        <input class="form-check-input customer-offer-service" type="checkbox" value="<?= esc($serviceCode) ?>" id="customer_offer_service_<?= esc($serviceCode) ?>" <?= in_array($serviceCode, $selectedServiceCodes, true) ? 'checked' : '' ?>>
                                                        <label class="form-check-label ms-2" for="customer_offer_service_<?= esc($serviceCode) ?>">
                                                            <span class="fw-semibold d-block"><?= esc($service['name'] ?? $serviceCode) ?></span>
                                                            <small class="text-muted"><?= esc($serviceCode) ?></small>
                                                        </label>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="alert alert-info mt-4 mb-0" id="customerOfferHelperText">
                                Si desactivás la oferta, la configuración queda guardada pero no se aplicará a nuevas reservas.
                            </div>
                        </div>
                    </div>

                    <div class="row d-flex align-items-center justify-content-center flex-nowrap flex-row">
                        <div class="col d-flex align-items-end justify-content-end">
                            <a href="<?= base_url('abmAdmin') ?>" class="btn btn-block btn-flat me-2" style="background-color: #595959; color: #fff">Volver</a>
                            <button type="submit" class="btn btn-block btn-flat" style="background-color: #f39323;">Guardar</button>
                        </div>
                    </div>
                </form>
            </div>
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
