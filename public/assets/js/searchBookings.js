const searchBookingButton = document.getElementById('searchBooking')
const inputDesdeBooking = document.getElementById('fechaDesdeBooking')
const inputHastaBooking = document.getElementById('fechaHastaBooking')
let bookingData = {}
let bookingId = ''

function formatPriceAR(value) {
    return '$ ' + new Intl.NumberFormat('es-AR', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    }).format(Number(value || 0))
}

function formatDateTime(dateTime) {
    if (!dateTime) return ''
    const parts = dateTime.split(' ')
    const datePart = parts[0] || ''
    const timePart = parts[1] || ''
    const dateBits = datePart.split('-')
    if (dateBits.length !== 3) return dateTime
    const formattedDate = `${dateBits[2]}/${dateBits[1]}/${dateBits[0]}`
    return timePart ? `${formattedDate} ${timePart}` : formattedDate
}

function normalizeHexColor(color) {
    const value = String(color || '').trim().toUpperCase()
    return /^#[0-9A-F]{6}$/.test(value) ? value : '#F39323'
}

function renderServiceBadge(serviceName, serviceColor) {
    const safeName = String(serviceName || 'N/D')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#39;')
    const color = normalizeHexColor(serviceColor)
    return `<span class="badge" style="background-color:${color};color:#fff;font-weight:700;">${safeName}</span>`
}

document.addEventListener('DOMContentLoaded', async (e) => {
    const fechaActual = new Date().toISOString().split('T')[0]
    inputDesdeBooking.value = fechaActual
    inputHastaBooking.value = fechaActual

    bookingData = {
        fechaDesde: inputDesdeBooking.value,
        fechaHasta: inputHastaBooking.value
    }

    getActiveBookings(bookingData)
})


document.addEventListener('click', async (e) => {
    if (e.target) {
        if (e.target.id == 'searchBooking') {
            bookingData = {
                fechaDesde: inputDesdeBooking.value,
                fechaHasta: inputHastaBooking.value
            }

            getActiveBookings(bookingData)
        } else if (e.target.id == 'searchAnnulledBooking') {
            bookingData = {
                fechaDesde: inputDesdeBooking.value,
                fechaHasta: inputHastaBooking.value
            }

            getAnnulledBookings(bookingData)
        } else if (e.target.id == 'modalCompletarPago') {

            const bookingId = e.target.dataset.id
            const botonPagar = document.getElementById('botonCompletarPago')
            const booking = await getBooking(bookingId)
            botonPagar.setAttribute('data-id', bookingId)

            completarPagoModalB.show()
            inputCompletarPagoReserva.value = booking.diference
        } else if (e.target.id == 'modalCambiarEstado') {
            cambiarEstadoMPModal.show()
            bookingId = e.target.dataset.id

        } else if (e.target.id == 'confirmarMP') {
            const check = document.getElementById('confirmarMPCheck')

            let dataState = {
                confirm: check.checked,
                bookingId: bookingId
            }
            
            confirmMP(dataState)

        }
    }
})

async function confirmMP(data) {

    try {
        const response = await fetch(`${baseUrl}/confirmMP`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(data)
        });
        
        location.reload(true)

    } catch (error) {
        console.error('Error:', error);
        throw error;
    }
}

async function getActiveBookings(data) {
    try {
        const response = await fetch(`${baseUrl}getActiveBookings`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(data)
        });

        const responseData = await response.json();

        fillTableBookings(responseData.data)

    } catch (error) {
        console.error('Error:', error);
        throw error;
    }
}

async function getAnnulledBookings(data) {
    try {
        const response = await fetch(`${baseUrl}getAnnulledBookings`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(data)
        });

        const responseData = await response.json();

        fillTableBookings(responseData.data)

    } catch (error) {
        console.error('Error:', error);
        throw error;
    }
}

async function fillTableBookings(data) {
    const divBookings = document.querySelector('.divBookings')

    let existPending = false
    let stateMP = ''
    let tr = ''
    let actions = ''
    let edit = ''
    let anular = ''
    let state = ''

    const pendientes = []
    const finalizadas = []

    data.forEach(reserva => {
        if (reserva.anulada == 0 && reserva.pago_total === 'Si') {
            finalizadas.push(reserva)
        } else {
            pendientes.push(reserva)
        }
    })

    const ordered = pendientes.concat(finalizadas)

    ordered.forEach(reserva => {

        if (reserva.mp == 0) {
            if (existPending == false) {
                existPending = true
                alert('Hay pagos pendientes de Mercado Pago para confirmar.')
            }
        }

        reserva.anulada == 1 ? state = 'Anulada' : state = 'Activa'
        reserva.mp == 0 ? stateMP = 'Pendiente' : stateMP = 'Confirmado'

        if (sessionUserSuperadmin == 1) {
            edit = `
            <li><button type="button" class="btn btn-primary dropdown-item" id="editarReservaBtn" data-id="${reserva.id}" data-bs-toggle="modal" data-bs-target="#editarReservaModal">Editar reserva</button></li>
            `
            if (reserva.anulada == 0) {
                anular = `
                <li><button type="button" class="btn btn-primary dropdown-item" id="eliminarReservaModal" data-id="${reserva.id}">Anular reserva</button></li>
                `
            }
        }

        if (reserva.pago_total === 'Si') {
            if (sessionUserSuperadmin == 1) {
                if (reserva.anulada == 1) {
                    actions = `
                    <div class="btn-group" role="group">
                        <button type="button" class="btn btn-success" disabled>
                            Sin acciones
                        </button>
                    </div>
                `
                } else {
                    actions = `
                    <div class="btn-group dropstart" role="group">
                        <button type="button" class="btn btn-danger dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                            Acciones
                        </button>
                        <ul class="dropdown-menu">
                            <input type="text" id="userId" data-id="${sessionUserId}" hidden>                        
                            ${anular}
    
                            ${edit}
                        </ul>
                    </div>
                `;
                }


            } else {
                actions = `
                <div class="btn-group" role="group">
                    <button type="button" class="btn btn-success" disabled>
                        Sin acciones
                    </button>
                </div>
            `
            }

        } else {
            if (reserva.anulada == 1) {
                actions = `
                <div class="btn-group" role="group">
                    <button type="button" class="btn btn-success" disabled>
                        Sin acciones
                    </button>
                </div>
            `
            } else {
                actions = `
            <div class="btn-group dropstart" role="group">
                <button type="button" class="btn btn-danger dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                    Acciones
                </button>
                <ul class="dropdown-menu">
                    <input type="text" id="userId" data-id="${sessionUserId}" hidden>
                    <li><button type="button" class="btn btn-primary dropdown-item" id="modalCambiarEstado" data-id="${reserva.id}">Cambiar estado de pago</button></li>
                    <li><button type="button" class="btn btn-primary dropdown-item" id="modalCompletarPago" data-id="${reserva.id}">Completar pago</button></li>

                    ${anular}

                    ${edit}
                </ul>
            </div>
        `;
            }

        }


        let descripcion = ''
        descripcion = reserva.descripcion == '' || reserva.descripcion == null ? 'Reserva' : reserva.descripcion

        // console.log(typeof reserva.descripcion)

        const editInfo = reserva.editado_por ? `<br><small>Editado por: ${reserva.editado_por}${reserva.editado_en ? ' (' + formatDateTime(reserva.editado_en) + ')' : ''}</small>` : ''

        const rowClass = (reserva.anulada == 0 && reserva.pago_total === 'Si') ? 'booking-finalizada' : ''

        tr += `
        <tr class="${rowClass}">
            <td>${reserva.fecha}</th>
            <td>${renderServiceBadge(reserva.cancha, reserva.service_color || reserva.color || reserva.field_color)}</td>
            <td>${reserva.horario}</td>
            <td>${reserva.nombre}</td>
            <td>${reserva.telefono}</td>
            <td>${reserva.creado_por || 'N/D'}${editInfo}</td>
            <td>${reserva.pago_total}</td>
            <td>${formatPriceAR(reserva.monto_reserva)}</td>
            <td>${formatPriceAR(reserva.total_reserva)}</td>
            <td>${formatPriceAR(reserva.diferencia)}</td>
            <td>${reserva.metodo_pago}</td>
            <td>${descripcion}</td>
            <td>${stateMP}</td>
            <td>${state}</td>
            <td>${actions}</td>
        </tr>
    `
    });

    divBookings.innerHTML = tr
}

