<div id="generalButtons" class="mt-3">
    <button type="button" class="btn btn-success mt-2 mb-2 js-open-customer-register" data-register-url="<?= base_url('customers/registerWindow') ?>">
        <i class="fa-solid fa-user-plus me-1"></i>Ingresar cliente
    </button>
    <button type="button" id="setOfferTrue" class="btn btn-warning mt-2 mb-2" id=""><i class="fa-solid fa-tags me-1"></i>Oferta global legacy</button>
    <button type="button" id="setOfferFalse" class="btn btn-danger mt-2 mb-2" id=""><i class="fa-solid fa-tag me-1"></i>Quitar legacy</button>


    <div class="form-check form-switch mt-3">
        <input class="form-check-input" type="checkbox" role="switch" id="checkCustomersWithOffer">
        <label class="form-check-label" for="checkCustomersWithOffer">Ver clientes con oferta</label>
    </div>

    <div class="d-flex justify-content-center align-items-center flex-row">
        <div class="form-floating mb-3">
            <input type="search" class="form-control" id="searchCustomerInput" placeholder="">
            <label for="searchCustomerInput">Télefono</label>
        </div>
        <button class="btn btn-primary ms-2" id="searchCustomerButton">Buscar</button>
    </div>

</div>

<div class="table-responsive-sm" id="tableCustomers">
    <table class="table align-middle table-striped-columns mt-2">
        <thead>
            <tr>
                <th scope="col">Nombre</th>
                <th scope="col">Apellido</th>
                <th scope="col">DNI</th>
                <th scope="col">Teléfono</th>
                <th scope="col">Localidad</th>
                <th scope="col">Oferta</th>
                <th scope="col">Alcance</th>
                <th scope="col">Reservas</th>
                <th scope="col">Acciones</th>
            </tr>
        </thead>
        <tbody id="customersDiv">

        </tbody>
    </table>
</div>

<style>
    .customer-edit-modal .modal-dialog {
        width: min(96vw, 1120px);
        max-width: none;
    }

    .customer-edit-modal .modal-content {
        overflow: hidden;
        border: 1px solid #d8e6f4;
        border-radius: 16px;
        box-shadow: 0 24px 60px rgba(15, 54, 97, 0.22);
        background: #f8fbff;
    }

    .customer-edit-modal .modal-header {
        background: linear-gradient(135deg, #f4f8fd 0%, #eaf2fb 100%);
        border-bottom: 1px solid #d6e2ef;
        padding: 1rem 1.25rem;
    }

    .customer-edit-modal .modal-title {
        font-weight: 700;
        color: #17324d;
        letter-spacing: 0.01em;
    }

    .customer-edit-modal .modal-body {
        background: #f8fbff;
    }

    .customer-edit-frame {
        display: block;
        width: 100%;
        height: min(82vh, 920px);
        border: 0;
        background: #ffffff;
    }

    body.theme-dark .customer-edit-modal .modal-content {
        background: linear-gradient(180deg, rgba(16, 33, 52, 0.98) 0%, rgba(13, 25, 38, 0.98) 100%);
        border-color: rgba(177, 212, 240, 0.16);
        box-shadow: 0 28px 70px rgba(0, 0, 0, 0.50);
    }

    body.theme-dark .customer-edit-modal .modal-header {
        background: linear-gradient(135deg, rgba(14, 28, 44, 0.98) 0%, rgba(20, 38, 58, 0.96) 100%);
        border-bottom-color: rgba(177, 212, 240, 0.10);
    }

    body.theme-dark .customer-edit-modal .modal-title {
        color: #eff7ff;
    }

    body.theme-dark .customer-edit-modal .modal-body {
        background: #0f1d2c;
    }

    body.theme-dark .customer-edit-frame {
        background: #0f1d2c;
    }

    body.theme-dark .customer-edit-modal .btn-close {
        filter: invert(1) grayscale(100%) brightness(200%);
        opacity: 0.92;
    }
</style>

<div class="modal fade customer-edit-modal" id="customerEditModal" tabindex="-1" aria-labelledby="customerEditModalLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="customerEditModalLabel">Editar cliente</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body p-0">
                <iframe id="customerEditFrame" class="customer-edit-frame" title="Editar cliente"></iframe>
            </div>
        </div>
    </div>
</div>
