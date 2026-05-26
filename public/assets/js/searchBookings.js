const searchBookingButton = document.getElementById('searchBooking')
const inputDesdeBooking = document.getElementById('fechaDesdeBooking')
const inputHastaBooking = document.getElementById('fechaHastaBooking')
let bookingData = {}
let bookingId = ''
let knownActiveBookingIds = new Set()
let bookingsPollTimer = null
const BOOKING_ALERTS_STORAGE_KEY = 'booking_alerts_enabled'
const bookingHistoryModalElement = document.getElementById('bookingHistoryModal')
const bookingHistoryModal = bookingHistoryModalElement ? new bootstrap.Modal(bookingHistoryModalElement) : null
const bookingHistoryList = document.getElementById('bookingHistoryList')
const bookingHistoryInfo = document.getElementById('bookingHistoryInfo')
const toggleBookingAlertsButton = document.getElementById('toggleBookingAlerts')
const bookingAlertsStatus = document.getElementById('bookingAlertsStatus')

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

    getActiveBookings(bookingData, { notifyNew: false })
    startBookingsPolling()
    refreshAlertsUi()
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
        } else if (e.target.id == 'searchBookingIssues') {
            bookingData = {
                fechaDesde: inputDesdeBooking.value,
                fechaHasta: inputHastaBooking.value
            }
            getBookingIssues(bookingData)
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

        fillTableBookings(responseData.data, '.divBookings')
        notifyIfNewBookings(responseData.data || [])

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

        fillTableBookings(responseData.data, '.divBookingIssues')

    } catch (error) {
        console.error('Error:', error);
        throw error;
    }
}

async function fetchBookingHistory(bookingId, limit = 50) {
    const response = await fetch(`${baseUrl}getAdminLogs`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ entityType: 'booking', entityId: Number(bookingId), limit })
    })
    const json = await response.json()
    if (!response.ok || json.error) throw new Error(json.message || 'No se pudo obtener historial')
    return Array.isArray(json.data) ? json.data : []
}

function showReservationToast(message) {
    if (!areAlertsEnabled()) return
    if (document.hidden && 'Notification' in window && Notification.permission === 'granted') {
        try {
            new Notification('Nueva reserva', {
                body: message,
                tag: 'new-booking',
            })
            return
        } catch (error) {
            // fallback a toast en página
        }
    }
    const id = 'booking-live-toast-container'
    let container = document.getElementById(id)
    if (!container) {
        container = document.createElement('div')
        container.id = id
        container.style.position = 'fixed'
        container.style.top = '16px'
        container.style.right = '16px'
        container.style.zIndex = '2000'
        document.body.appendChild(container)
    }
    const toast = document.createElement('div')
    toast.className = 'alert alert-success shadow-sm mb-2'
    toast.style.minWidth = '320px'
    toast.innerHTML = `<strong>Nueva reserva</strong><br>${message}`
    container.appendChild(toast)
    setTimeout(() => toast.remove(), 5000)
}

function playReservationSound() {
    if (!areAlertsEnabled()) return
    try {
        const AudioContextClass = window.AudioContext || window.webkitAudioContext
        if (!AudioContextClass) return
        const ctx = new AudioContextClass()
        const osc = ctx.createOscillator()
        const gain = ctx.createGain()
        osc.type = 'sine'
        osc.frequency.value = 880
        gain.gain.value = 0.0001
        osc.connect(gain)
        gain.connect(ctx.destination)
        const now = ctx.currentTime
        gain.gain.exponentialRampToValueAtTime(0.18, now + 0.02)
        gain.gain.exponentialRampToValueAtTime(0.0001, now + 0.28)
        osc.start(now)
        osc.stop(now + 0.3)
    } catch (error) {
        // ignore audio errors
    }
}

function notifyIfNewBookings(data) {
    const incomingIds = new Set((data || []).map(r => Number(r.id)).filter(Boolean))
    if (knownActiveBookingIds.size === 0) {
        knownActiveBookingIds = incomingIds
        return
    }
    const fresh = (data || []).filter(r => {
        const id = Number(r.id)
        return id > 0 && !knownActiveBookingIds.has(id)
    })
    if (fresh.length > 0) {
        if (document.hidden) {
            document.title = `(${fresh.length}) Nueva reserva - La Barca`
            setTimeout(() => {
                document.title = 'La Barca'
            }, 7000)
        }
        fresh.forEach(r => {
            showReservationToast(`${r.fecha} · ${r.horario} · ${r.nombre} (${r.cancha})`)
        })
        playReservationSound()
    }
    knownActiveBookingIds = incomingIds
}

function areAlertsEnabled() {
    return localStorage.getItem(BOOKING_ALERTS_STORAGE_KEY) === '1'
}

function setAlertsEnabled(enabled) {
    localStorage.setItem(BOOKING_ALERTS_STORAGE_KEY, enabled ? '1' : '0')
    refreshAlertsUi()
}

function refreshAlertsUi() {
    if (!toggleBookingAlertsButton || !bookingAlertsStatus) return
    const enabled = areAlertsEnabled()
    toggleBookingAlertsButton.innerHTML = enabled
        ? '<i class="fa-solid fa-bell"></i>'
        : '<i class="fa-regular fa-bell"></i>'
    toggleBookingAlertsButton.title = enabled ? 'Desactivar alertas' : 'Activar alertas'
    toggleBookingAlertsButton.classList.toggle('btn-outline-info', !enabled)
    toggleBookingAlertsButton.classList.toggle('btn-info', enabled)
    if (enabled) {
        bookingAlertsStatus.textContent = 'Alertas: activadas'
        bookingAlertsStatus.className = 'ms-2 small text-success'
    } else {
        bookingAlertsStatus.textContent = 'Alertas: desactivadas'
        bookingAlertsStatus.className = 'ms-2 small text-muted'
    }
}

async function requestAlertPermissions() {
    // Intento breve de audio para desbloquear contexto por gesto de usuario
    playReservationSound()
    if (!('Notification' in window)) return true
    if (Notification.permission === 'granted') return true
    if (Notification.permission === 'denied') return false
    try {
        const permission = await Notification.requestPermission()
        return permission === 'granted'
    } catch (error) {
        return false
    }
}

function startBookingsPolling() {
    if (bookingsPollTimer) clearInterval(bookingsPollTimer)
    bookingsPollTimer = setInterval(() => {
        if (!bookingData?.fechaDesde || !bookingData?.fechaHasta) return
        getActiveBookings(bookingData).catch(() => {})
    }, 20000)
}

async function getBookingIssues(data) {
    try {
        const response = await fetch(`${baseUrl}getBookingIssues`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(data)
        });

        const responseData = await response.json();

        fillTableBookings(responseData.data, '.divBookingIssues')

    } catch (error) {
        console.error('Error:', error);
        throw error;
    }
}

async function fillTableBookings(data, targetSelector = '.divBookings') {
    const divBookings = document.querySelector(targetSelector)
    if (!divBookings) return

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
        <tr class="${rowClass}" data-booking-id="${reserva.id}" data-booking-name="${reserva.nombre || ''}" data-booking-date="${reserva.fecha || ''}" data-booking-time="${reserva.horario || ''}">
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
            ${targetSelector === '.divBookingIssues' ? `<td>${reserva.issue_reason || '-'}</td>` : ''}
            <td>${actions}</td>
        </tr>
    `
    });

    divBookings.innerHTML = tr
}

document.addEventListener('dblclick', async (e) => {
    const row = e.target.closest('tr[data-booking-id]')
    if (!row || !bookingHistoryModal) return
    const bookingId = Number(row.dataset.bookingId || 0)
    if (!bookingId) return
    if (bookingHistoryInfo) {
        bookingHistoryInfo.textContent = `Reserva #${bookingId} · ${row.dataset.bookingDate || ''} · ${row.dataset.bookingTime || ''} · ${row.dataset.bookingName || ''}`
    }
    if (bookingHistoryList) {
        bookingHistoryList.innerHTML = '<div class="small text-muted">Cargando historial...</div>'
    }
    bookingHistoryModal.show()
    try {
        const rows = await fetchBookingHistory(bookingId, 60)
        if (bookingHistoryList) {
            if (typeof renderHistoryRows === 'function') {
                bookingHistoryList.innerHTML = renderHistoryRows(rows)
                if (typeof bindHistoryFilters === 'function') bindHistoryFilters(bookingHistoryList)
            } else {
                bookingHistoryList.innerHTML = rows.length === 0
                    ? '<div class="small text-muted">Sin cambios registrados.</div>'
                    : rows.map(r => `<div class="small mb-2"><strong>${r.action_label || r.action}</strong> - ${r.created_at || ''}</div>`).join('')
            }
        }
    } catch (error) {
        if (bookingHistoryList) {
            bookingHistoryList.innerHTML = '<div class="small text-danger">No se pudo cargar el historial.</div>'
        }
    }
})

document.addEventListener('click', async (e) => {
    if (e.target?.id !== 'toggleBookingAlerts') return
    if (areAlertsEnabled()) {
        setAlertsEnabled(false)
        return
    }
    const granted = await requestAlertPermissions()
    if (!granted) {
        alert('No se pudieron activar las alertas. Revisá permisos de notificación/sonido del navegador.')
        setAlertsEnabled(false)
        return
    }
    setAlertsEnabled(true)
    alert('Alertas activadas. Te vamos a avisar con sonido y mensaje cuando entre una reserva nueva.')
})

