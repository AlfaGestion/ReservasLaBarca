<?php echo $this->extend('templates/dashboard_panel') ?>

<?php echo $this->section('title') ?>
<title>Panel</title>
<?php echo $this->endSection() ?>


<?php echo $this->section('content') ?>

<style>
    .superadmin-reservas .nav-tabs {
        border-bottom-color: #cfe0f1;
    }
    .superadmin-reservas .nav-tabs .nav-link {
        color: #2a5378;
        border-color: transparent transparent #cfe0f1 transparent;
    }
    .superadmin-reservas .nav-tabs .nav-link.active {
        color: #0b63b6;
        background: #ffffff;
        border-color: #cfe0f1 #cfe0f1 #ffffff #cfe0f1;
        font-weight: 600;
    }
    .superadmin-reservas .table {
        --bs-table-bg: #ffffff;
        --bs-table-striped-bg: #f5f9fd;
        --bs-table-striped-color: #17324d;
        --bs-table-color: #17324d;
        --bs-table-border-color: #d8e6f4;
    }
    .superadmin-reservas .table thead th {
        color: #1f4467;
    }
    .superadmin-reservas .table td,
    .superadmin-reservas .table th {
        vertical-align: middle;
    }
    .superadmin-reservas .card,
    .superadmin-reservas .modal-content {
        border-color: #d8e6f4;
    }

    body.theme-dark .superadmin-reservas {
        color: #dbe9f8;
    }
    body.theme-dark .superadmin-reservas .nav-tabs {
        border-bottom-color: #345672;
    }
    body.theme-dark .superadmin-reservas .nav-tabs .nav-link {
        color: #b7d4ee;
        border-color: transparent transparent #345672 transparent;
    }
    body.theme-dark .superadmin-reservas .nav-tabs .nav-link.active {
        color: #dff0ff;
        background: #182d42;
        border-color: #345672 #345672 #182d42 #345672;
    }
    body.theme-dark .superadmin-reservas .card,
    body.theme-dark .superadmin-reservas .modal-content,
    body.theme-dark .superadmin-reservas .nav-tabs,
    body.theme-dark .superadmin-reservas .tab-content > .tab-pane,
    body.theme-dark .superadmin-reservas .accordion-item,
    body.theme-dark .superadmin-reservas .list-group-item {
        background: #182d42 !important;
        border-color: #33506e !important;
        color: #dbe9f8 !important;
    }
    body.theme-dark .superadmin-reservas .accordion-button {
        background: #21374d !important;
        color: #dbe9f8 !important;
        border-color: #33506e !important;
        box-shadow: none;
    }
    body.theme-dark .superadmin-reservas .accordion-button:not(.collapsed) {
        background: #1d344c !important;
        color: #e7f2ff !important;
    }
    body.theme-dark .superadmin-reservas .table,
    body.theme-dark .superadmin-reservas .table thead th,
    body.theme-dark .superadmin-reservas .table tbody td,
    body.theme-dark .superadmin-reservas .table tbody th {
        color: #dbe9f8;
    }
    body.theme-dark .superadmin-reservas .table {
        --bs-table-bg: #182d42;
        --bs-table-striped-bg: #21374d;
        --bs-table-striped-color: #dbe9f8;
        --bs-table-color: #dbe9f8;
        --bs-table-border-color: #33506e;
    }
    body.theme-dark .superadmin-reservas .form-control,
    body.theme-dark .superadmin-reservas .form-select,
    body.theme-dark .superadmin-reservas .form-floating > .form-control,
    body.theme-dark .superadmin-reservas .form-floating > .form-select,
    body.theme-dark .superadmin-reservas textarea.form-control,
    body.theme-dark .superadmin-reservas .input-group-text {
        background: #193047 !important;
        border-color: #356fbf !important;
        color: #e6edf3 !important;
    }
    body.theme-dark .superadmin-reservas .form-control:focus,
    body.theme-dark .superadmin-reservas .form-select:focus,
    body.theme-dark .superadmin-reservas .form-floating > .form-control:focus,
    body.theme-dark .superadmin-reservas .form-floating > .form-select:focus,
    body.theme-dark .superadmin-reservas textarea.form-control:focus {
        background: #223c56 !important;
        border-color: #72a7ea !important;
        box-shadow: 0 0 0 .2rem rgba(114, 167, 234, .18);
    }
    body.theme-dark .superadmin-reservas .form-floating > label,
    body.theme-dark .superadmin-reservas .form-label,
    body.theme-dark .superadmin-reservas .text-muted,
    body.theme-dark .superadmin-reservas .small.text-muted,
    body.theme-dark .superadmin-reservas h1,
    body.theme-dark .superadmin-reservas h2,
    body.theme-dark .superadmin-reservas h3,
    body.theme-dark .superadmin-reservas h4,
    body.theme-dark .superadmin-reservas h5,
    body.theme-dark .superadmin-reservas h6,
    body.theme-dark .superadmin-reservas p,
    body.theme-dark .superadmin-reservas span,
    body.theme-dark .superadmin-reservas label,
    body.theme-dark .superadmin-reservas strong,
    body.theme-dark .superadmin-reservas li,
    body.theme-dark .superadmin-reservas a:not(.btn) {
        color: #dbe9f8 !important;
    }
    body.theme-dark .superadmin-reservas .btn-outline-primary {
        color: #c7dff5;
        border-color: #4b77a0;
    }
    body.theme-dark .superadmin-reservas .btn-outline-primary:hover {
        color: #ffffff;
        background: #165ecc;
        border-color: #165ecc;
    }
    body.theme-dark .superadmin-reservas .btn-outline-dark,
    body.theme-dark .superadmin-reservas .btn-outline-secondary {
        color: #c7dff5;
        border-color: #4b77a0;
        background: transparent;
    }
    body.theme-dark .superadmin-reservas .btn-outline-dark:hover,
    body.theme-dark .superadmin-reservas .btn-outline-dark:focus,
    body.theme-dark .superadmin-reservas .btn-outline-secondary:hover,
    body.theme-dark .superadmin-reservas .btn-outline-secondary:focus {
        color: #ffffff;
        background: #21374d;
        border-color: #72a7ea;
    }
    body.theme-dark .superadmin-reservas .btn-outline-danger {
        color: #f1b5bb;
        border-color: #8d4b56;
    }
    body.theme-dark .superadmin-reservas .btn-outline-danger:hover {
        color: #ffffff;
        background: #bb2d3b;
        border-color: #bb2d3b;
    }
</style>

<?php if (session('msg')) : ?>
    <div class="alert alert-<?= session('msg.type') ?> alert-dismissible fade show" role="alert">
        <small> <?= session('msg.body') ?> </small>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<div class="container superadmin-reservas">
    <div class="row">
        <div class="col-12">
            <nav>
                <div class="nav nav-tabs mt-3" id="nav-tab" role="tablist">
                    <button class="nav-link active" id="nav-bookings-tab" data-bs-toggle="tab" data-bs-target="#nav-bookings" type="button" role="tab" aria-controls="nav-bookings" aria-selected="false"><i class="fa-regular fa-calendar-days"></i> Reservas</button>
                    <button class="nav-link" id="nav-general-tab" data-bs-toggle="tab" data-bs-target="#nav-general" type="button" role="tab" aria-controls="nav-general" aria-selected="false"><i class="fa-solid fa-gear"></i> General</button>
                    <button class="nav-link" id="nav-reports-tab" data-bs-toggle="tab" data-bs-target="#nav-reports" type="button" role="tab" aria-controls="nav-reports" aria-selected="true"><i class="fa-solid fa-file-lines"></i> Reportes de cobro</button>

                    <?php if (session()->superadmin) : ?>
                        <button class="nav-link" id="nav-fields-tab" data-bs-toggle="tab" data-bs-target="#nav-fields" type="button" role="tab" aria-controls="nav-fields" aria-selected="false"><i class="fa-solid fa-futbol"></i> Servicios y Tarifas</button>
                        <button class="nav-link" id="nav-time-tab" data-bs-toggle="tab" data-bs-target="#nav-time" type="button" role="tab" aria-controls="nav-time" aria-selected="false"><i class="fa-regular fa-clock"></i> Horarios</button>
                        <button class="nav-link" id="nav-customers-tab" data-bs-toggle="tab" data-bs-target="#nav-customers" type="button" role="tab" aria-controls="nav-customers" aria-selected="false"><i class="fa-solid fa-user"></i> Clientes</button>
                    <?php endif; ?>

                </div>
            </nav>

            <div class="tab-content" id="nav-tabContent">

                <div class="tab-pane fade  show active" id="nav-bookings" role="tabpanel" aria-labelledby="nav-bookings-tab" tabindex="0">
                    <?= view('superadmin/tabBookings', ['bookings' => $bookings, 'localities' => $localities]) ?>
                </div>

                <div class="tab-pane fade" id="nav-general" role="tabpanel" aria-labelledby="nav-general-tab" tabindex="0">
                    <?= view('superadmin/tabGeneral', ['users' => $users, 'fields' => $fields]) ?>
                </div>

                <?php if (session()->superadmin) : ?>
                    <div class="tab-pane fade" id="nav-fields" role="tabpanel" aria-labelledby="nav-fields-tab" tabindex="0">
                        <?= view('superadmin/tabFields', ['fields' => $fields, 'services' => $services ?? []]) ?>
                    </div>

                    <div class="tab-pane fade" id="nav-time" role="tabpanel" aria-labelledby="nav-time-tab" tabindex="0">
                        <?= view('superadmin/tabTime') ?>
                    </div>

                    <div class="tab-pane fade" id="nav-customers" role="tabpanel" aria-labelledby="nav-customers-tab" tabindex="0">
                        <?= view('superadmin/tabCustomers') ?>
                    </div>

                <?php endif; ?>

                <div class="tab-pane fade" id="nav-reports" role="tabpanel" aria-labelledby="nav-reports-tab" tabindex="0">
                    <?= view('superadmin/tabReports', ['users' => $users]) ?>
                </div>

            </div>
        </div>
    </div>
</div>

<!-- modal spinner -->
<div class="modal fade" id="spinnerCompletarPago" tabindex="-1" data-bs-backdrop="static" aria-labelledby="spinnerCompletarPagoLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered d-flex justify-content-center">

        <div class="d-flex justify-content-center align-items-center">
            <div class="spinner-border" style="width: 4rem; height: 4rem; color: #f39323" role="status">
                <span class="visually-hidden">Guardando pago...</span>
            </div>
        </div>
    </div>
</div>

<!-- Modal result payment-->
<div class="modal fade" id="modalResultPayment" tabindex="-1" data-bs-backdrop="static" aria-labelledby="modalResultPaymentLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" id="paymentResult">

        </div>
    </div>
</div>

<!-- Modal generar reporte-->
<div class="modal fade" id="generateReportModal" data-bs-backdrop="static" tabindex="-1" aria-labelledby="generateReportModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h1 class="modal-title fs-5" id="generateReportModalLabel">Resumen</h1>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="paymentsMethodsResume">

                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-primary" id="printReport">Imprimir</button>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Volver</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal result -->
<div class="modal fade" id="modalResult" tabindex="-1" data-bs-backdrop="static" aria-labelledby="modalResultLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" id="bookingEditResult">

        </div>
    </div>
</div>

<?php echo $this->endSection() ?>

<?php echo $this->section('footer') ?>
<?php echo $this->endSection() ?>

<?php echo $this->section('scripts') ?>
<script src="<?= base_url(PUBLIC_FOLDER . "assets/js/abmSuperadmin.js?v=" . time()) ?>"></script>
<script src="<?= base_url(PUBLIC_FOLDER . "assets/js/searchReports.js?v=" . time()) ?>"></script>
<script src="<?= base_url(PUBLIC_FOLDER . "assets/js/searchBookings.js?v=" . time()) ?>"></script>
<script src="<?= base_url(PUBLIC_FOLDER . "assets/js/customers.js?v=" . time()) ?>"></script>
<script src="<?= base_url(PUBLIC_FOLDER . "assets/js/editReserva.js?v=" . time()) ?>"></script>
<script src="<?= base_url(PUBLIC_FOLDER . "assets/js/users.js?v=" . time()) ?>"></script>


<?php echo $this->endSection() ?>
