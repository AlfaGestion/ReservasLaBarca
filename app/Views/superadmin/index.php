<?php echo $this->extend('templates/dashboard_panel') ?>

<?php echo $this->section('title') ?>
<title>Panel</title>
<?php echo $this->endSection() ?>


<?php echo $this->section('content') ?>

<style>
    .superadmin-reservas-shell {
        width: min(98vw, 1760px);
        max-width: 1760px;
        margin: 0 auto;
        padding-left: 10px;
        padding-right: 10px;
    }
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
    .superadmin-reservas .table-responsive {
        width: 100%;
    }
    .superadmin-reservas .card,
    .superadmin-reservas .modal-content {
        border-color: #d8e6f4;
    }
    .service-config-grid {
        display: grid;
        gap: 1rem;
    }
    .service-config-card {
        display: grid;
        gap: 1rem;
        padding: 1rem;
        border: 1px solid #d8e6f4;
        border-radius: 8px;
        background: #ffffff;
    }
    .service-config-header {
        display: flex;
        justify-content: space-between;
        gap: 1rem;
        align-items: flex-start;
    }
    .service-config-section {
        padding: 1rem;
        border: 1px solid #e3edf7;
        border-radius: 8px;
        background: #f8fbfe;
    }
    .service-config-section h6 {
        margin-bottom: .85rem;
        color: #1f4467;
        font-weight: 700;
    }
    .prices-table-modern {
        border-radius: 12px;
        overflow: hidden;
        box-shadow: 0 8px 24px rgba(15, 54, 97, .08);
    }
    .prices-table-modern thead th {
        background: linear-gradient(180deg, #f7fbff 0%, #edf5fd 100%);
        border-bottom: 1px solid #d8e6f4;
        font-weight: 700;
    }
    #fieldFormModal .modal-content {
        border-radius: 14px;
        box-shadow: 0 16px 36px rgba(12, 46, 84, .18);
    }
    #fieldFormModal .modal-header {
        background: linear-gradient(90deg, #f8fbff 0%, #eef5fc 100%);
    }
    .service-duration-preview,
    .service-offer-preview {
        padding: .55rem .75rem;
        border-radius: 6px;
        background: #eef6ff;
        color: #1f4467;
        font-weight: 600;
        min-height: 38px;
        display: flex;
        align-items: center;
    }
    .edit-field-frame {
        border: 1px solid #2f5d86;
        border-radius: 10px;
        padding: 12px;
        background: rgba(19, 52, 84, .25);
    }
    .edit-field-frame-title {
        text-transform: uppercase;
        letter-spacing: .08em;
        font-size: .78rem;
        font-weight: 700;
        color: #9fc6ea;
        margin-bottom: 10px;
    }
    #fieldFormModal .field-editor-dialog {
        width: min(760px, calc(100vw - 2rem));
        max-width: none;
        margin: 1rem auto;
    }
    #fieldFormModal .field-editor-content {
        max-height: calc(100vh - 2rem);
        border: 0;
        overflow: hidden;
    }
    #fieldFormModal .field-editor-body {
        padding: 1.25rem;
    }
    .field-editor-form {
        display: grid;
        gap: 1rem;
    }
    .field-editor-section {
        padding: 1rem;
        border: 1px solid #d8e6f4;
        border-radius: 12px;
        background: #f8fbfe;
    }
    .field-editor-section--main {
        background: #ffffff;
    }
    .field-editor-section--prices {
        background: #fffaf2;
        border-color: #f2d5a6;
    }
    .field-editor-section-heading {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 1rem;
        margin-bottom: .9rem;
    }
    .field-editor-section-heading h6 {
        margin: .15rem 0 0;
        color: #1f4467;
        font-size: 1rem;
    }
    .field-editor-eyebrow {
        color: #6b8aa5;
        font-size: .7rem;
        font-weight: 800;
        letter-spacing: .1em;
        text-transform: uppercase;
    }
    .field-editor-helper {
        color: #6b7f91;
        font-size: .8rem;
        text-align: right;
    }
    .field-editor-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: .85rem;
    }
    .field-editor-grid--identity {
        grid-template-columns: minmax(0, 1fr);
    }
    .field-editor-field,
    .field-editor-price {
        display: grid;
        gap: .35rem;
        margin: 0;
        color: #385773;
        font-size: .82rem;
        font-weight: 700;
    }
    .field-editor-field small,
    .field-editor-price small {
        font-weight: 400;
        color: #71879a;
    }
    .field-editor-field .form-control,
    .field-editor-price .form-control {
        min-height: 42px;
    }
    .field-editor-service-types {
        grid-template-columns: repeat(4, minmax(0, 1fr));
    }
    .field-editor-service-types .service-type-option {
        min-height: 42px;
        padding: 8px 10px;
        font-size: .85rem;
    }
    .field-editor-switch,
    .field-editor-check {
        display: inline-flex;
        align-items: center;
        gap: .55rem;
        margin: 0;
        color: #385773;
        font-size: .85rem;
        font-weight: 700;
    }
    .field-editor-switch .form-check-input,
    .field-editor-check .form-check-input {
        margin: 0;
    }
    .field-editor-price-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: .85rem;
    }
    .field-editor-price {
        padding: .85rem;
        border: 1px solid #efc77f;
        border-radius: 10px;
        background: #ffffff;
        color: #6b4818;
    }
    .field-editor-price-input {
        display: flex;
        align-items: center;
        overflow: hidden;
        border: 1px solid #ced4da;
        border-radius: 7px;
        background: #ffffff;
    }
    .field-editor-price-input b {
        padding: 0 .75rem;
        color: #a36815;
    }
    .field-editor-price-input .form-control {
        border: 0;
        box-shadow: none;
    }
    .field-editor-actions {
        position: sticky;
        top: -1.25rem;
        order: -1;
        z-index: 2;
        display: flex;
        justify-content: flex-end;
        gap: .65rem;
        padding: .15rem 0 1rem;
        border-bottom: 1px solid #d8e6f4;
        background: #ffffff;
    }
    #fieldFormModal #fieldFormMessage {
        position: sticky;
        top: -1.25rem;
        z-index: 3;
        margin: 0 0 1rem;
    }
    .service-config-form .service-form-message {
        position: sticky;
        top: -1.25rem;
        z-index: 3;
        margin: 0;
    }
    .service-config-form {
        width: 100%;
        min-width: 0;
        padding: 0;
        border: 0;
        border-radius: 0;
        background: transparent;
        box-shadow: none;
    }
    .service-config-form > .service-editor-main {
        display: grid;
        grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
        align-items: start;
        gap: 1rem;
    }
    .service-config-form > .service-editor-main > .col-lg-5:first-child {
        grid-column: 1;
        width: auto !important;
        padding: 0 !important;
    }
    .service-config-form > .service-editor-main > .col-lg-2 {
        grid-column: 2;
        width: auto !important;
        padding: 0 !important;
    }
    .service-config-form > .service-editor-main > .col-lg-5:last-child {
        grid-column: 1 / -1;
        width: auto !important;
        padding: 0 !important;
    }
    .service-config-form > .service-editor-main .service-config-section {
        height: auto !important;
    }
    .service-config-form > .service-editor-main .row {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: .75rem;
    }
    .service-config-form > .service-editor-main .row > * {
        width: auto;
        padding: 0;
    }
    .service-config-form .service-config-section,
    .service-config-form .field-editor-section {
        min-width: 0;
    }
    .service-state-option {
        min-height: 112px;
        padding: .75rem;
        border: 1px solid #d8e6f4;
        border-radius: 10px;
        background: #ffffff;
    }
    .service-state-option .form-check-input {
        float: none;
        margin: 0 .45rem 0 0;
        vertical-align: middle;
    }
    .service-state-option .form-check-label {
        display: inline;
        font-weight: 700;
    }
    .service-state-option .small {
        display: block;
        margin-top: .45rem;
        line-height: 1.35;
    }
    .admin-history-list {
        display: grid;
        gap: 8px;
    }
    .admin-history-item {
        border: 1px solid #2f5d86;
        border-radius: 8px;
        padding: 8px 10px;
        background: rgba(19, 52, 84, .2);
    }
    .admin-history-summary {
        cursor: pointer;
        list-style: none;
        margin-bottom: 0;
    }
    .admin-history-summary::-webkit-details-marker {
        display: none;
    }
    .admin-history-item[open] .admin-history-summary {
        margin-bottom: 8px;
    }
    .admin-history-head {
        display: flex;
        justify-content: space-between;
        gap: 10px;
        align-items: center;
        margin-bottom: 6px;
        flex-wrap: wrap;
    }
    .history-change-item {
        font-size: .88rem;
        line-height: 1.35;
    }
    .history-table th {
        font-size: .78rem;
        text-transform: uppercase;
        letter-spacing: .05em;
        color: #9fc6ea;
    }
    .history-table td {
        font-size: .9rem;
        vertical-align: top;
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
    body.theme-dark .service-config-card,
    body.theme-dark .service-config-section {
        background: #182d42;
        border-color: #33506e;
    }
    body.theme-dark .prices-table-modern {
        box-shadow: 0 8px 24px rgba(0, 0, 0, .26);
    }
    body.theme-dark .prices-table-modern thead th {
        background: linear-gradient(180deg, #203a54 0%, #1a3148 100%);
        border-bottom-color: #33506e;
    }
    body.theme-dark #fieldFormModal .modal-header {
        background: #1a3148 !important;
    }
    body.theme-dark .service-config-section h6,
    body.theme-dark .service-duration-preview,
    body.theme-dark .service-offer-preview {
        color: #dbe9f8;
    }
    body.theme-dark .service-duration-preview,
    body.theme-dark .service-offer-preview {
        background: #21374d;
    }
    body.theme-dark .edit-field-frame {
        border-color: #3a6d95;
        background: rgba(22, 48, 74, .45);
    }
    body.theme-dark #fieldFormModal .field-editor-section,
    body.theme-dark #fieldFormModal .field-editor-section--main {
        background: #1b344b;
        border-color: #3a6d95;
    }
    body.theme-dark #serviceFormModal .service-config-form .field-editor-actions {
        color: #dbe9f8;
        border-bottom-color: #33506e;
        background: #182d42;
    }
    body.theme-dark #serviceFormModal .service-config-form .field-editor-section,
    body.theme-dark #serviceFormModal .service-config-form .service-config-section {
        background: #1b344b;
        border-color: #3a6d95;
        color: #dbe9f8;
    }
    body.theme-dark #serviceFormModal .service-config-form .form-label,
    body.theme-dark #serviceFormModal .service-config-form .form-check-label,
    body.theme-dark #serviceFormModal .service-config-form h5,
    body.theme-dark #serviceFormModal .service-config-form h6,
    body.theme-dark #serviceFormModal .service-config-form .text-muted,
    body.theme-dark #serviceFormModal .service-config-form .small {
        color: #dbe9f8 !important;
    }
    body.theme-dark #serviceFormModal .service-config-form .service-duration-preview,
    body.theme-dark #serviceFormModal .service-config-form .service-offer-preview {
        background: #21374d;
        color: #dbe9f8;
    }
    body.theme-dark #serviceFormModal .service-state-option {
        background: #21374d;
        border-color: #3a6d95;
    }
    body.theme-dark #fieldFormModal .field-editor-section--prices {
        background: #3a3021;
        border-color: #8c6834;
    }
    body.theme-dark #fieldFormModal .field-editor-section-heading h6,
    body.theme-dark #fieldFormModal .field-editor-field,
    body.theme-dark #fieldFormModal .field-editor-switch,
    body.theme-dark #fieldFormModal .field-editor-check {
        color: #dbe9f8;
    }
    body.theme-dark #fieldFormModal .field-editor-eyebrow,
    body.theme-dark #fieldFormModal .field-editor-helper,
    body.theme-dark #fieldFormModal .field-editor-field small,
    body.theme-dark #fieldFormModal .field-editor-price small {
        color: #a9c3da;
    }
    body.theme-dark #fieldFormModal .field-editor-price {
        background: #263e53;
        border-color: #8c6834;
        color: #ffe2a8;
    }
    body.theme-dark #fieldFormModal .field-editor-price-input {
        background: #193047;
        border-color: #356fbf;
    }
    body.theme-dark #fieldFormModal .field-editor-price-input b {
        color: #ffd98b;
    }
    body.theme-dark #fieldFormModal .field-editor-actions {
        border-bottom-color: #33506e;
        background: #182d42;
    }
    body.theme-dark #fieldFormModal #fieldFormMessage {
        box-shadow: 0 8px 16px rgba(0, 0, 0, .18);
    }
    body.theme-dark .service-config-form .service-form-message {
        box-shadow: 0 8px 16px rgba(0, 0, 0, .18);
    }

    @media (max-width: 640px) {
        #fieldFormModal .field-editor-dialog {
            width: 100%;
            margin: 0;
        }
        #fieldFormModal .field-editor-content {
            max-height: 100vh;
            border-radius: 0;
        }
        #fieldFormModal .field-editor-body {
            padding: .85rem;
        }
        .field-editor-section-heading,
        .field-editor-price-grid {
            grid-template-columns: 1fr;
            display: grid;
        }
        .field-editor-helper {
            text-align: left;
        }
        .field-editor-grid,
        .field-editor-service-types {
            grid-template-columns: 1fr;
        }
        .field-editor-actions {
            justify-content: stretch;
        }
        .field-editor-actions .btn {
            flex: 1;
        }
        .service-config-form > .service-editor-main {
            grid-template-columns: 1fr;
        }
        .service-config-form > .service-editor-main > .col-lg-5:first-child,
        .service-config-form > .service-editor-main > .col-lg-2,
        .service-config-form > .service-editor-main > .col-lg-5:last-child {
            grid-column: 1;
        }
        .service-config-form > .service-editor-main .row {
            grid-template-columns: 1fr;
        }
    }
    body.theme-dark .edit-field-frame-title {
        color: #b5d7f5;
    }
    body.theme-dark .admin-history-item {
        border-color: #3a6d95;
        background: rgba(22, 48, 74, .45);
    }
    body.theme-dark .history-table th,
    body.theme-dark .history-table td {
        color: #dbe9f8;
    }
    body.theme-dark #bookingAlertsStatus.text-success {
        color: #7ee0a1 !important;
    }
    body.theme-dark #bookingAlertsStatus.text-muted {
        color: #a9c3da !important;
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

<div class="container-fluid superadmin-reservas-shell superadmin-reservas">
    <div class="row">
        <div class="col-12">
            <nav>
                <div class="nav nav-tabs mt-3" id="nav-tab" role="tablist">
                    <button class="nav-link active" id="nav-bookings-tab" data-bs-toggle="tab" data-bs-target="#nav-bookings" type="button" role="tab" aria-controls="nav-bookings" aria-selected="false"><i class="fa-regular fa-calendar-days"></i> Reservas</button>
                    <button class="nav-link" id="nav-booking-issues-tab" data-bs-toggle="tab" data-bs-target="#nav-booking-issues" type="button" role="tab" aria-controls="nav-booking-issues" aria-selected="false"><i class="fa-solid fa-triangle-exclamation"></i> Pendientes y bloqueos</button>
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

                <div class="tab-pane fade" id="nav-booking-issues" role="tabpanel" aria-labelledby="nav-booking-issues-tab" tabindex="0">
                    <?= view('superadmin/tabBookingIssues') ?>
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
