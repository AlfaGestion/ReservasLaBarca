<?php

use App\Models\MercadoPagoKeysModel;
use App\Models\UploadModel;

$modelUploads = new UploadModel();
$userBackground = $modelUploads->first();

$mpKeysModel = new MercadoPagoKeysModel();
$mpKeys = $mpKeysModel->first();


?>

<?php echo $this->extend('templates/dashboard') ?>

<?php echo $this->section('title') ?>
<title>Reserva</title>
<?php echo $this->endSection() ?>

<?php echo $this->section('content') ?>

<div class="container booking-page-container">


    <!-- Modal de bienvenida -->
    <div class="modal fade" data-bs-backdrop="static" id="welcomeModal" tabindex="-1" aria-labelledby="welcomeModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <?php if ($userBackground) : ?>

                <div class="modal-content d-flex justify-content-center align-items-center flex-column text-center" style="background: url(<?= base_url(PUBLIC_FOLDER . "assets/images/uploads/" . $userBackground['name']) ?>);">

                    <div class="modal-body d-flex justify-content-center align-items-center">
                        <img src="<?= base_url(PUBLIC_FOLDER . "assets/images/wlogo.png") ?>" class="mainModalImg" width="450px" alt="Logo">
                    </div>
                <?php else : ?>
                    <div class="modal-content d-flex justify-content-center align-items-center flex-column text-center">

                        <div class="modal-body d-flex justify-content-center align-items-center">
                            <img src="<?= base_url(PUBLIC_FOLDER . "assets/images/logo.png") ?>" class="mainModalImg" width="450px" alt="Logo">
                        </div>
                    <?php endif; ?>


                    <!-- <p style="color: red; font-weight: bold">CUIDADO! LOS PAGOS EN ESTA VERSIÓN SERÁN PAGOS REALES</p> -->
                    <div id="closureWelcomeNotice" class="alert alert-warning mx-3 d-none booking-preline"></div>
                    <div class="d-flex justify-content-center align-items-center mb-3">
                        <button type="button" class="btn btn-brand" data-bs-dismiss="modal">Comenzar reserva</button>
                    </div>

                    </div>
                </div>
        </div>
        <!-- Modal de bienvenida -->

        <!-- Modal de oferta -->
        <div class="modal fade" data-bs-backdrop="static" id="ofertaModal" tabindex="-1" aria-labelledby="ofertaModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content d-flex justify-content-center align-items-center flex-column text-center" id="ofertaModalContent">

                </div>
            </div>
        </div>
        <!-- Modal de oferta -->

        <input type="text" name="publicKeyMp" id="publicKeyMp" class="form-control" value="<?= isset($mpKeys) ? $mpKeys['public_key'] : '' ?>" aria-label="date" hidden>

        <div id="isSunday" class="d-flex justify-content-center align-items-center mt-5 d-none">
            <span style="color: #fff; font-weight: bold; background-color: red; padding: 10px 10px; border-radius: 30px">Hoy las canchas permanecerán cerradas</span>
        </div>

        <div id="closureTopNotice" class="alert alert-warning d-none mt-3 booking-preline"></div>
        <div id="formBooking" class="booking-shell">
            <form action="" id="bookingForm">

                <?php if (session('msg')) : ?>
                    <div class="alert alert-<?= session('msg.type') ?> alert-dismissible fade show" role="alert">
                        <small> <?= session('msg.body') ?> </small>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <div class="form-floating mb-3 mt-3">
                    <input type="date" name="fecha" id="fecha" class="form-control" value="" aria-label="date">
                    <label for="fecha">Fecha</label>
                </div>
                <div id="closureNotice" class="alert alert-warning d-none"></div>

                <div class="mb-3 service-type-field">
                    <label class="form-label d-block mb-2" id="serviceTypeLabel">Tipo de reserva</label>
                    <select class="form-select visually-hidden" name="serviceType" id="serviceType" aria-hidden="true" tabindex="-1">
                        <?php
                        $activeOnlineServices = array_values(array_filter(($services ?? []), static function ($service) {
                            return (int)($service['active'] ?? 1) === 1 && (int)($service['online_available'] ?? 1) === 1;
                        }));
                        ?>
                        <?php foreach ($activeOnlineServices as $index => $service) : ?>
                            <option value="<?= esc($service['code'] ?? 'football') ?>" <?= $index === 0 ? 'selected' : '' ?>><?= esc($service['name'] ?? ($service['code'] ?? 'Servicio')) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="service-type-options" role="radiogroup" aria-labelledby="serviceTypeLabel">
                        <?php foreach ($activeOnlineServices as $index => $service) : ?>
                            <?php $optionId = 'serviceType' . preg_replace('/[^A-Za-z0-9]/', '', (string)($service['code'] ?? 'service')); ?>
                            <input class="btn-check" type="radio" name="serviceTypeOption" id="<?= esc($optionId) ?>" value="<?= esc($service['code'] ?? 'football') ?>" autocomplete="off" <?= $index === 0 ? 'checked' : '' ?>>
                            <label class="service-type-option" style="--service-color: <?= esc($service['color'] ?? '#F39323') ?>;" for="<?= esc($optionId) ?>"><?= esc($service['name'] ?? ($service['code'] ?? 'Servicio')) ?></label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="horario d-flex flex-row">
                    <div class="form-floating" id="div-time-h" style="width: 100%;">
                        <select class="form-select mb-3" name="horarioDesde" id="horarioDesde" aria-label="l">
                            <option value="">Seleccionar</option>

                            <?php

                            $totalHours = count($time);
                            foreach ($time as $key => $hour) :
                                if ($key !== $totalHours - 1) :

                            ?>

                                    <option value="<?= str_pad($hour, 2, '0', STR_PAD_LEFT) . ':00' ?>"><?= str_pad($hour, 2, '0', STR_PAD_LEFT) . ':00' ?></option>

                            <?php
                                endif;
                            endforeach;

                            ?>
                        </select>
                        <label for="horarioDesde">Horario desde</label>
                    </div>


                    <div class="form-floating  ms-4 d-none" id="div-time" style="width: 49%;">
                        <select class="form-select mb-3" name="horarioHasta" id="horarioHasta" aria-label="">
                            <option value="">Seleccionar</option>
                            <?php foreach ($time as $hour) : ?>
                                <option value="<?= str_pad($hour, 2, '0', STR_PAD_LEFT) . ':00' ?>"><?= str_pad($hour, 2, '0', STR_PAD_LEFT) . ':00' ?></option>
                            <?php endforeach; ?>
                        </select>
                        <label for="horarioHasta">Horario hasta</label>
                    </div>
                </div>

                <div class="form-floating" id="divSelectCancha">
                    <select class="form-select mb-3 d-none" name="cancha" id="cancha" aria-label="Default floating label">
                        <option value="">Elegí una cancha o espacio</option>
                        <?php foreach ($fields as $field) : ?>
                            <option value="<?= $field['id'] ?>" data-service-type="<?= esc($field['service_type'] ?? 'football') ?>" data-duration-minutes="<?= esc($field['duration_minutes'] ?? $field['block_minutes'] ?? 60) ?>" data-slot-interval-minutes="<?= esc($field['slot_interval_minutes'] ?? $field['booking_interval_minutes'] ?? $field['block_minutes'] ?? 60) ?>" data-block-minutes="<?= esc($field['block_minutes'] ?? 60) ?>" data-opening-time="<?= esc($field['opening_time'] ?? '') ?>" data-closing-time="<?= esc($field['closing_time'] ?? '') ?>" data-price="<?= esc($field['value'] ?? 0) ?>" data-price-light="<?= esc($field['ilumination_value'] ?? $field['value'] ?? 0) ?>"><?= esc($field['name']) ?> - <?= esc(minutesToHuman($field['duration_minutes'] ?? $field['block_minutes'] ?? 60)) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <label for="cancha">Cancha o espacio</label>
                </div>

                <div id="quinchoAdditionalBox" class="border rounded p-3 mb-3 d-none">
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" role="switch" id="addQuincho" autocomplete="off">
                        <label class="form-check-label" for="addQuincho">Agregar quincho como adicional</label>
                    </div>
                    <div id="quinchoAdditionalFields" class="d-none">
                        <div class="horario d-flex flex-row">
                            <div class="form-floating" style="width: 49%;">
                                <select class="form-select mb-3" id="quinchoDesde" aria-label="Quincho desde"></select>
                                <label for="quinchoDesde">Quincho desde</label>
                            </div>
                            <div class="form-floating ms-4" style="width: 49%;">
                                <select class="form-select mb-3" id="quinchoHasta" aria-label="Quincho hasta"></select>
                                <label for="quinchoHasta">Quincho hasta</label>
                            </div>
                        </div>
                        <div id="quinchoAvailabilityMessage" class="alert alert-warning d-none"></div>
                    </div>
                </div>

                <div class="form-floating flex-nowrap mb-3 d-none" id="div-monto">
                    <input type="text" class="form-control" name="inputMonto" id="inputMonto" value="0" aria-label="name" disabled>
                    <label for="inputMonto">Monto</label>
                </div>

                <div class="form-floating flex-nowrap mb-3">
                    <input type="tel" inputmode="numeric" class="form-control" name="telefono" id="telefono" placeholder="Ingrese el telefono completo" aria-label="name" required>
                    <label for="telefono">Telefono</label>
                </div>

                <div class="form-floating flex-nowrap mb-3">
                    <input type="text" class="form-control" name="localidad" id="localidad" placeholder="Ingrese la localidad" aria-label="localidad" autocomplete="off" spellcheck="false">
                    <label for="localidad">Localidad</label>
                </div>

                <div class="form-floating flex-nowrap mb-3">
                    <input type="text" class="form-control" name="nombre" id="nombre" placeholder="Ingrese el nombre y apellido" aria-label="name" required>
                    <label for="nombre">Nombre y apellido</label>
                </div>

                <datalist id="localitiesList">
                    <?php if (!empty($localities)) : ?>
                        <?php foreach ($localities as $loc) : ?>
                            <option value="<?= $loc['name'] ?>"></option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </datalist>

                <?php if (session()->logueado) : ?>
                    <button type="button" class="btn btn-brand" id="confirmarAdminReserva">Confirmar reserva</button>
                <?php else : ?>
                    <button type="button" class="btn btn-brand" id="confirmarReserva">Confirmar reserva</button>
                <?php endif; ?>

                <button type="button" class="btn btn-muted-action" id="cancelarReserva">Cancelar reserva</button>

            </form>
        </div>
        <div>
            <!-- First modal -->
            <div class="modal fade" id="modalConfirmarReserva" data-bs-backdrop="static" aria-hidden="true" aria-labelledby="confirmarReservaLabel" tabindex="-1">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h1 class="modal-title fs-5" id="confirmarReservaLabel">Resumen reserva</h1>
                            <button type="button" id="buttonCancelSummary" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-resume-body">

                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-brand" id="abonarReservaBoton" data-bs-toggle="modal">Abonar reserva</button>
                            <button type="button" class="btn btn-muted-action" data-bs-dismiss="modal">Volver</button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Second Modal -->
            <div class="modal fade" id="ingresarPago" aria-hidden="true" data-bs-backdrop="static" aria-labelledby="ingresarPagoLabel" tabindex="-1">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h1 class="modal-title fs-5" id="ingresarPagoLabel">Ingresar pago</h1>
                            <button type="button" id="buttonCancelPayment" class="btn-close" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">

                            <?php if (session()->logueado) : ?>
                                <div class="mb-3">
                                    <div class="form-floating flex-nowrap mb-3">
                                        <input type="text" class="form-control" name="adminBookingTotalAmount" id="adminBookingTotalAmount" placeholder="Ingrese el monto" aria-label="Amount" required>
                                        <label for="adminBookingTotalAmount">Ingresar total de la reserva</label>
                                    </div>

                                    <div class="form-floating flex-nowrap mb-3">
                                        <input type="text" class="form-control" name="adminBookingAmount" id="adminBookingAmount" placeholder="Ingrese el monto" aria-label="Amount" required>
                                        <label for="adminBookingAmount">Ingresar monto a abonar de la reserva</label>
                                    </div>

                                    <div class="form-floating mb-3">
                                        <select class="form-select" id="adminPaymentMethod" aria-label="Floating label select example">
                                            <option>Seleccionar medio de pago</option>
                                            <option value="Efectivo">Efectivo</option>
                                            <option value="Transferencia">Transferencia</option>
                                            <option value="Mercado Pago">Mercado Pago</option>
                                        </select>
                                        <label for="adminPaymentMethod">Medio de pago</label>
                                    </div>

                                    <div class="form-floating">
                                        <textarea class="form-control" placeholder="Ingrese el motivo de la reserva" id="adminBookingDescription"></textarea>
                                        <label for="adminBookingDescription">Descripción</label>
                                    </div>
                                </div>

                            <?php else : ?>
                                <div class="mb-3">
                                    <div class="form-check form-switch mb-2">
                                        <input class="form-check-input" type="checkbox" role="switch" name="switchPagoTotal" id="switchPagoTotal">
                                        <label class="form-check-label" for="switchPagoTotal">Pagar el total</label>
                                    </div>
                                    <label for="inputPagoReserva" class="form-label">A abonar</label>
                                    <input type="text" class="form-control" id="inputPagoReserva" name="inputPagoReserva" placeholder="" disabled>
                                </div>

                                <div class="alert alert-warning alert-dismissible fade show" role="alert">
                                    <small> <b>UNA VEZ EFECTUADO EL PAGO, AGUARDE EL TIEMPO ESTIPULADO POR MERCADO PAGO PARA SER REDIRECCIONADO AL SITIO. DE OTRA FORMA, EL PAGO NO SERÁ CONFIRMADO.</b></small>
                                </div>
                            <?php endif; ?>

                        </div>
                        <div class="modal-footer d-flex justify-contente-center align-items-center">
                            <div id="checkout-btn-parcial"></div>
                            <div id="checkout-btn-total"></div>
                            <?php if (session()->logueado) : ?>
                                <button type="button" class="btn btn-primary" id="confirmBooking">Reservar</button>
                            <?php endif; ?>
                            <button type="button" class="btn btn-muted-action" id="volverPagoModal">Volver</button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Modal result -->
            <div class="modal fade" id="modalResult" tabindex="-1" data-bs-backdrop="static" aria-labelledby="modalResultLabel" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content" id="bookingResult">

                    </div>
                </div>
            </div>

            <!-- modal spinner -->
            <div class="modal fade" id="modalSpinner" tabindex="-1" data-bs-backdrop="static" aria-labelledby="modalSpinnerLabel" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered d-flex justify-content-center">

                    <div class="d-flex justify-content-center align-items-center">
                        <div class="spinner-border" style="width: 4rem; height: 4rem; color: #f39323" role="status">
                            <span class="visually-hidden">Procesando reserva...</span>
                        </div>
                    </div>

                </div>
            </div>

            <!-- modal confirmacion estilizada -->
            <div class="modal fade" id="uiConfirmModal" tabindex="-1" aria-labelledby="uiConfirmTitle" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="uiConfirmTitle">Confirmar</h5>
                            <button type="button" class="btn-close" id="uiConfirmClose" aria-label="Close"></button>
                        </div>
                        <div class="modal-body" id="uiConfirmBody"></div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-muted-action" id="uiConfirmCancel">Cancelar</button>
                            <button type="button" class="btn btn-brand" id="uiConfirmAccept">Aceptar</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>


    <?php echo $this->endSection() ?>

    <?php echo $this->section('footer') ?>
    <?php echo $this->endSection() ?>

    <?php echo $this->section('scripts') ?>
    <script src="https://sdk.mercadopago.com/js/v2"></script>

    <?php
    $formReservaRelativePath = "assets/js/formReserva.js";
    $formReservaPath = FCPATH . $formReservaRelativePath;
    $formReservaVersion = is_file($formReservaPath) ? filemtime($formReservaPath) : time();
    ?>
    <script>
        let esDomingo = <?php echo json_encode($esDomingo); ?>;
        window.bookingFields = <?= json_encode($fields) ?>;
        window.bookingServices = <?= json_encode($services ?? []) ?>;
        window.bookingOpeningTime = <?= json_encode($time) ?>;
    </script>
    <script src="<?= base_url(PUBLIC_FOLDER . $formReservaRelativePath . "?v=" . $formReservaVersion) ?>"></script>

    <?php echo $this->endSection() ?>
