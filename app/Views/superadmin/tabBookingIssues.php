<div class="card mt-3 mb-4 border-warning" id="pendingBookingSlotsCard">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <h6 class="mb-1">Bloqueos temporales</h6>
                <small class="text-muted">Horarios reservados provisoriamente mientras se confirma el pago. Se liberan al vencer.</small>
            </div>
            <span class="badge text-bg-warning" id="pendingBookingSlotsCount">0</span>
        </div>
        <div class="table-responsive mt-3">
            <table class="table table-sm align-middle mb-0">
                <thead><tr><th>Fecha</th><th>Cancha</th><th>Horario</th><th>Reserva</th><th>Vence</th></tr></thead>
                <tbody id="pendingBookingSlotsBody"><tr><td colspan="5" class="text-muted">No hay bloqueos temporales vigentes.</td></tr></tbody>
            </table>
        </div>
    </div>
</div>

<hr class="my-4">
<h6 class="mb-2">Canceladas / pagos no aprobados</h6>
<div class="table-responsive">
    <table class="table align-middle table-striped-columns mt-2">
        <thead>
            <tr>
                <th>Fecha</th><th>Servicio</th><th>Horario</th><th>Nombre</th><th>Teléfono</th>
                <th>Creado por</th><th>Pagó total</th><th>Pagado</th><th>Total</th><th>Saldo</th>
                <th>Método de pago</th><th>Descripción</th><th>Estado de Mercado Pago</th><th>Estado</th><th>Motivo</th><th>Acciones</th>
            </tr>
        </thead>
        <tbody class="divBookingIssues"></tbody>
    </table>
</div>
