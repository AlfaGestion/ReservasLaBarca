<div id="selectDateBooking" class="d-flex flex-column justify-content-center align-items-center">
    <div class="d-flex justify-content-center align-items-center flex-row">
        <div class="form-floating mb-3 mt-3 me-2">
            <input type="date" name="fechaDesdeBooking" id="fechaDesdeBooking" class="form-control" value="" aria-label="date">
            <label for="fechaDesdeBooking">Desde</label>
        </div>

        <div class="form-floating mb-3 mt-3 me-2">
            <input type="date" name="fechaHastaBooking" id="fechaHastaBooking" class="form-control" value="" aria-label="date">
            <label for="fechaHastaBooking">Hasta</label>
        </div>
    </div>

    <div>
        <button type="button" id="searchBooking" class="btn btn-success">Buscar activas</button>
        <button type="button" id="searchAnnulledBooking" class="btn btn-danger">Buscar anuladas</button>
        <button type="button" id="searchBookingIssues" class="btn btn-warning text-dark">Buscar incidencias</button>
        <button type="button" id="toggleBookingAlerts" class="btn btn-outline-info" title="Activar alertas">
            <i class="fa-solid fa-bell"></i>
        </button>
        <span id="bookingAlertsStatus" class="ms-2 small text-muted">Alertas: desactivadas</span>
    </div>
</div>

<!-- Modal -->
<div class="modal fade" data-bs-backdrop="static" id="modalCambiarEstado" tabindex="-1" aria-labelledby="modalCambiarEstadoLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h1 class="modal-title fs-5" id="modalCambiarEstadoLabel">Cambiar estado</h1>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" value="" id="confirmarMPCheck">
                    <label class="form-check-label" for="confirmarMPCheck">
                        Confirmar pago
                    </label>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Volver</button>
                <button type="button" id="confirmarMP" class="btn btn-success">Confirmar</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal -->
<div class="modal fade" data-bs-backdrop="static" id="completarPagoModal" tabindex="-1" aria-labelledby="completarPagoModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h1 class="modal-title fs-5" id="completarPagoModalLabel">Completar pago</h1>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label for="inputCompletarPagoReserva" class="form-label">Ingresar monto</label>
                    <input type="text" class="form-control" id="inputCompletarPagoReserva" name="inputCompletarPagoReserva" placeholder="">
                </div>

                <div class="form-floating">
                    <select class="form-select" id="medioPagoSelect" aria-label="Floating label select example">
                        <option value="">Seleccionar</option>
                        <option value="efectivo">Efectivo</option>
                        <option value="transferencia">Transferencia</option>
                        <option value="mercado_pago">Mercado Pago</option>
                    </select>
                    <label for="medioPagoSelect">Medio de pago</label>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Volver</button>
                <button type="button" id="botonCompletarPago" class="btn btn-primary">Pagar</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal eliminar reserva-->
<div class="modal fade" data-bs-backdrop="static" id="eliminarReservaModal" tabindex="-1" aria-labelledby="eliminarReservaModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-body">
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                <div class="d-flex justify-content-center align-items-center flex-column text-center">
                    <h6>¿Está seguro que desea anular la reserva?</h6>
                    <div class="d-flex justify-content-center align-items-center">
                        <button type="button" id="confirmCancelBooking" class="btn btn-success me-3">Confirmar</button>
                        <button type="button" id="cancelCancelBooking" class="btn btn-danger">Cancelar</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal editar reserva-->
<div class="modal fade" data-bs-backdrop="static" id="editarReservaModal" tabindex="-1" aria-labelledby="editarReservaModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-body">
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>

                <div class="form-floating mb-3 mt-3">
                    <input type="date" name="fecha" id="fecha" class="form-control" value="" aria-label="date">
                    <label for="fecha">Fecha</label>
                </div>

                <div class="horario d-flex flex-row">
                    <div class="form-floating" id="div-time-h" style="width: 50%;">
                        <select class="form-select mb-3" name="horarioDesde" id="horarioDesde" aria-label="l">
                            <option value="">Seleccionar</option>

                            <?php

                            $totalHours = count($openingTime);
                            foreach ($openingTime as $key => $hour) :
                                if ($key !== $totalHours - 1) :

                            ?>

                                    <option value="<?= $hour ?>"><?= $hour . ':00' ?></option>

                            <?php
                                endif;
                            endforeach;

                            ?>
                        </select>
                        <label for="horarioDesde">Horario desde</label>
                    </div>


                    <div class="form-floating  ms-4" id="div-time" style="width: 50%;">
                        <select class="form-select mb-3" name="horarioHasta" id="horarioHasta" aria-label="">
                            <option value="">Seleccionar</option>
                            <?php foreach ($openingTime as $hour) : ?>
                                <option value="<?= $hour ?>"><?= $hour . ':00' ?></option>
                            <?php endforeach; ?>
                        </select>
                        <label for="horarioHasta">Horario hasta</label>
                    </div>
                </div>

                <div class="form-floating" id="divSelectCancha">
                    <select class="form-select mb-3" name="cancha" id="cancha" aria-label="Default floating label">
                        <option value="">Servicios disponibles</option>
                        <?php foreach ($fields as $field) : ?>
                            <option value="<?= $field['id'] ?>"><?= $field['name'] ?></option>
                        <?php endforeach; ?>
                    </select>
                    <label for="cancha">Seleccionar servicio</label>
                </div>

                <div class="form-floating flex-nowrap mb-3" id="div-monto">
                    <input type="text" class="form-control" name="inputMonto" id="inputMonto" value="0" aria-label="name">
                    <label for="inputMonto">Monto</label>
                </div>

                <div class="form-floating flex-nowrap mb-3 d-flex align-items-center justify-content-center flex-row">
                    <input type="number" class="form-control" name="telefono" id="telefono" placeholder="Ingrese el teléfono" aria-label="name">
                    <label for="telefono">Teléfono</label>
                </div>

                <div class="form-floating flex-nowrap mb-3">
                    <input type="text" class="form-control" name="localidad" id="localidad" placeholder="Ingrese la localidad" aria-label="localidad" autocomplete="off" spellcheck="false">
                    <label for="localidad">Localidad</label>
                </div>

                <div class="form-floating flex-nowrap mb-3">
                    <input type="text" class="form-control" name="nombre" id="nombre" placeholder="Ingrese el nombre y apellido" aria-label="name" disabled>
                    <label for="nombre">Nombre y apellido</label>
                </div>

                <datalist id="localitiesListAdmin">
                    <?php if (!empty($localities)) : ?>
                        <?php foreach ($localities as $loc) : ?>
                            <option value="<?= $loc['name'] ?>"></option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </datalist>


                <button type="button" class="btn btn-success" id="actualizarReserva">Actualizar reserva</button>
                <button type="button" class="btn btn-danger" id="cancelarReservaEdit">Cancelar</button>
            </div>

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


<div class="table-responsive">
    <table class="table align-middle table-striped-columns mt-2">
        <thead>
            <tr>
                <th scope="col">Fecha</th>
                <th scope="col">Servicio</th>
                <th scope="col">Horario</th>
                <th scope="col">Nombre</th>
                <th scope="col">Teléfono</th>
                <th scope="col">Creado por</th>
                <th scope="col">Pagó total</th>
                <th scope="col">Pagado</th>
                <th scope="col">Total</th>
                <th scope="col">Saldo</th>
                <th scope="col">Método de pago</th>
                <th scope="col">Descripción</th>
                <th scope="col">Estado de Mercado Pago</th>
                <th scope="col">Estado</th>
                <th scope="col">Acciones</th>
            </tr>
        </thead>


        <tbody class="divBookings">

        </tbody>
    </table>
</div>

<hr class="my-4">
<h6 class="mb-2">Canceladas / pagos no aprobados</h6>
<div class="table-responsive">
    <table class="table align-middle table-striped-columns mt-2">
        <thead>
            <tr>
                <th scope="col">Fecha</th>
                <th scope="col">Servicio</th>
                <th scope="col">Horario</th>
                <th scope="col">Nombre</th>
                <th scope="col">Teléfono</th>
                <th scope="col">Creado por</th>
                <th scope="col">Pagó total</th>
                <th scope="col">Pagado</th>
                <th scope="col">Total</th>
                <th scope="col">Saldo</th>
                <th scope="col">Método de pago</th>
                <th scope="col">Descripción</th>
                <th scope="col">Estado de Mercado Pago</th>
                <th scope="col">Estado</th>
                <th scope="col">Motivo</th>
                <th scope="col">Acciones</th>
            </tr>
        </thead>
        <tbody class="divBookingIssues"></tbody>
    </table>
</div>

<div class="modal fade" id="bookingHistoryModal" tabindex="-1" aria-labelledby="bookingHistoryModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="bookingHistoryModalLabel">Historial de reserva</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <div id="bookingHistoryInfo" class="small text-muted mb-2"></div>
                <div id="bookingHistoryList" class="admin-history-list">
                    <div class="small text-muted">Cargando historial...</div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>
