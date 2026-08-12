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
const bookingAuditModalElement = document.getElementById('bookingAuditModal')
const bookingAuditModal = bookingAuditModalElement ? new bootstrap.Modal(bookingAuditModalElement) : null
const bookingAuditContent = document.getElementById('bookingAuditContent')
const bookingAuditSubtitle = document.getElementById('bookingAuditSubtitle')
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

function escapeHtml(value) {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;')
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
        const auditButton = e.target.closest('.view-booking-audit')
        if (auditButton) {
            const id = auditButton.dataset.id
            if (!id) return
            if (bookingAuditModal && bookingAuditContent) {
                bookingAuditSubtitle.textContent = `Reserva #${id} · cargando detalle...`
                bookingAuditContent.innerHTML = '<div class="text-muted small">Cargando auditoría...</div>'
                bookingAuditModal.show()
            }
            try {
                const audit = await fetchBookingAudit(id)
                if (bookingAuditSubtitle) {
                    bookingAuditSubtitle.textContent = `Reserva #${audit.booking_id || id}`
                }
                if (bookingAuditContent) {
                    bookingAuditContent.innerHTML = renderBookingAuditModal(audit)
                }
            } catch (error) {
                if (bookingAuditContent) {
                    bookingAuditContent.innerHTML = '<div class="alert alert-danger mb-0">No se pudo cargar el detalle de la reserva.</div>'
                }
            }
            return
        }
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

async function fetchBookingAudit(bookingId) {
    const response = await fetch(`${baseUrl}getBookingAudit/${bookingId}`)
    const json = await response.json()
    if (!response.ok || json.error) throw new Error(json.message || 'No se pudo obtener el detalle de la reserva')
    return json.data || {}
}

function formatAuditMoney(value) {
    if (value === null || value === undefined || value === '') return 'No registrado'
    return formatPriceAR(value)
}

function formatAuditDateTime(value) {
    if (!value) return 'No registrado'
    const text = String(value)
    if (text.includes('T')) {
        return new Date(text).toLocaleString('es-AR', { hour12: false })
    }
    if (text.includes(' ')) {
        const [datePart, timePart] = text.split(' ')
        const [y, m, d] = datePart.split('-')
        if (y && m && d) {
            return `${d}/${m}/${y} ${timePart || ''}`.trim()
        }
    }
    if (text.includes('-')) {
        const [y, m, d] = text.split('-')
        if (y && m && d) return `${d}/${m}/${y}`
    }
    return text
}

function renderAuditBadge(label, type = 'secondary') {
    return `<span class="badge bg-${type}">${escapeHtml(label)}</span>`
}

function renderBookingAuditSection(title, body) {
    return `
        <section class="mb-4">
            <h6 class="mb-2">${escapeHtml(title)}</h6>
            <div class="border rounded-3 p-3 bg-light-subtle">
                ${body}
            </div>
        </section>
    `
}

function renderBookingAuditTable(rows, emptyMessage) {
    if (!Array.isArray(rows) || rows.length === 0) {
        return `<div class="text-muted small">${escapeHtml(emptyMessage)}</div>`
    }

    return `
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Método</th>
                        <th>Importe</th>
                        <th>Usuario</th>
                        <th>MP ID</th>
                        <th>Interno</th>
                    </tr>
                </thead>
                <tbody>
                    ${rows.map((row) => `
                        <tr>
                            <td>${escapeHtml(formatAuditDateTime(row.created_at || row.date))}</td>
                            <td>${escapeHtml(row.payment_method || 'N/D')}</td>
                            <td>${escapeHtml(formatAuditMoney(row.amount))}</td>
                            <td>${escapeHtml(row.user_name || 'N/D')}</td>
                            <td>${escapeHtml(row.mercado_pago_id || 'N/D')}</td>
                            <td>${escapeHtml(String(row.id || 'N/D'))}</td>
                        </tr>
                    `).join('')}
                </tbody>
            </table>
        </div>
    `
}

function renderBookingAuditModal(data) {
    const booking = data.booking || {}
    const customer = data.customer || {}
    const creator = data.creator || {}
    const field = data.field || {}
    const service = data.service || {}
    const payments = Array.isArray(data.payments) ? data.payments : []
    const mp = data.mercado_pago || {}
    const mpLive = data.mercado_pago_live || {}
    const firstMpPayment = data.first_mp_payment || null
    const warnings = Array.isArray(data.warnings) ? data.warnings : []

    const reservationRate = data.reservation_rate === null || data.reservation_rate === undefined
        ? 'No registrado'
        : `${Number(data.reservation_rate).toFixed(2).replace(/\.00$/, '')}%`
    const expectedPartial = data.expected_partial === null || data.expected_partial === undefined
        ? 'No registrado'
        : formatPriceAR(data.expected_partial)
    const storedPartial = data.stored_partial === null || data.stored_partial === undefined
        ? 'No registrado'
        : formatPriceAR(data.stored_partial)
    const total = formatPriceAR(booking.total || 0)
    const totalPaid = Number(data.payments_total_unique ?? data.payments_total ?? 0)
    const totalPaidRaw = Number(data.payments_total_raw ?? data.payments_total ?? 0)
    const totalValue = Number(booking.total || 0)
    const paid = formatPriceAR(totalPaid)
    const saldo = formatPriceAR(data.saldo || 0)
    const paymentMethod = booking.payment_method || data.payment_method || 'N/D'
    const isMismatch = warnings.length > 0
    const paymentStatus = (totalPaid >= totalValue && totalValue > 0) ? 'Sí' : 'No'

    const summaryCards = `
        <div class="row g-3 mb-3">
            <div class="col-md-4">
                <div class="border rounded-3 p-3 h-100 bg-white">
                    <div class="small text-muted">Total reserva</div>
                    <div class="fs-5 fw-bold">${escapeHtml(total)}</div>
                    <div class="small text-muted">Pagado: ${escapeHtml(paid)}</div>
                    <div class="small text-muted">Saldo: ${escapeHtml(saldo)}</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="border rounded-3 p-3 h-100 bg-white">
                    <div class="small text-muted">Porcentaje de seña</div>
                    <div class="fs-5 fw-bold">${escapeHtml(reservationRate)}</div>
                    <div class="small text-muted">Seña esperada: ${escapeHtml(expectedPartial)}</div>
                    <div class="small text-muted">Parcial guardado: ${escapeHtml(storedPartial)}</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="border rounded-3 p-3 h-100 bg-white">
                    <div class="small text-muted">Estado auditivo</div>
                    <div class="fs-5 fw-bold">${paymentStatus === 'Sí' ? 'Pagó total' : 'No pagó total'}</div>
                    <div class="small text-muted">Método: ${escapeHtml(paymentMethod)}</div>
                    <div class="small text-muted">Pagos registrados: ${escapeHtml(formatPriceAR(totalPaid))}</div>
                    <div class="small text-muted">Suma cruda: ${escapeHtml(formatPriceAR(totalPaidRaw))}</div>
                </div>
            </div>
        </div>
    `

    const warningHtml = warnings.length > 0
        ? `
            <div class="alert alert-warning">
                <div class="fw-bold mb-1">Advertencias detectadas</div>
                <div class="mb-2">
                    ${warnings.some((warning) => warning.includes('parcial almacenado') || warning.includes('Mercado Pago registró un importe diferente'))
                        ? renderAuditBadge('Importe distinto a la seña calculada', 'warning text-dark')
                        : ''}
                </div>
                <ul class="mb-0">
                    ${warnings.map((warning) => `<li>${escapeHtml(warning)}</li>`).join('')}
                </ul>
            </div>
        `
        : '<div class="alert alert-success">No se detectaron diferencias visibles entre el snapshot y los pagos guardados.</div>'

    const mpLiveRows = []
    if (mp.payment_id || mpLive.payment_id) {
        mpLiveRows.push(['Payment ID', mp.payment_id || mpLive.payment_id])
        mpLiveRows.push(['Preference parcial', booking.id_preference_parcial || 'N/D'])
        mpLiveRows.push(['Preference total', booking.id_preference_total || 'N/D'])
        mpLiveRows.push(['Estado MP', mp.status || mpLive.status || 'N/D'])
        mpLiveRows.push(['Status confirmed', (mp.status || mpLive.status || '') === 'approved' ? 'Sí' : 'No'])
        mpLiveRows.push(['MP transaction_amount', mpLive.transaction_amount ?? 'No registrado'])
        mpLiveRows.push(['Merchant order ID', mpLive.merchant_order_id || mp.merchant_order_id || 'N/D'])
        mpLiveRows.push(['Fecha MP', mpLive.date_approved || mpLive.date_created || 'N/D'])
    }
    if (firstMpPayment) {
        mpLiveRows.push(['Primer pago MP', formatPriceAR(firstMpPayment.amount || 0)])
        mpLiveRows.push(['Primer pago MP fecha', formatAuditDateTime(firstMpPayment.created_at || firstMpPayment.date)])
        mpLiveRows.push(['Primer pago MP usuario', firstMpPayment.user_name || 'N/D'])
    }

    const mpHtml = mpLiveRows.length > 0
        ? `<dl class="row mb-0">${mpLiveRows.map(([label, value]) => `
            <dt class="col-sm-4">${escapeHtml(label)}</dt>
            <dd class="col-sm-8">${escapeHtml(String(value))}</dd>
        `).join('')}</dl>`
        : '<div class="text-muted small">No hay información de Mercado Pago asociada.</div>'

    const creatorHtml = `
        <dl class="row mb-0">
            <dt class="col-sm-4">Creado por tipo</dt>
            <dd class="col-sm-8">${escapeHtml(creator.type || 'N/D')}</dd>
            <dt class="col-sm-4">Creado por nombre</dt>
            <dd class="col-sm-8">${escapeHtml(creator.name || 'N/D')}</dd>
            <dt class="col-sm-4">Usuario ID</dt>
            <dd class="col-sm-8">${escapeHtml(creator.user_id || 'N/D')}</dd>
            <dt class="col-sm-4">Editado por</dt>
            <dd class="col-sm-8">${escapeHtml(booking.edited_by_name || 'N/D')}</dd>
            <dt class="col-sm-4">Editado el</dt>
            <dd class="col-sm-8">${escapeHtml(formatAuditDateTime(booking.edited_at))}</dd>
        </dl>
    `

    const bookingHtml = `
        <dl class="row mb-0">
            <dt class="col-sm-4">ID de reserva</dt>
            <dd class="col-sm-8">${escapeHtml(booking.id || data.booking_id || 'N/D')}</dd>
            <dt class="col-sm-4">Estado</dt>
            <dd class="col-sm-8">${escapeHtml(booking.annulled == 1 ? 'Anulada' : (booking.approved == 1 ? 'Activa / aprobada' : 'Pendiente'))}</dd>
            <dt class="col-sm-4">Fecha de creación</dt>
            <dd class="col-sm-8">${escapeHtml(formatAuditDateTime(booking.booking_time || booking.created_at))}</dd>
            <dt class="col-sm-4">Fecha reservada</dt>
            <dd class="col-sm-8">${escapeHtml(booking.date || 'N/D')}</dd>
            <dt class="col-sm-4">Horario</dt>
            <dd class="col-sm-8">${escapeHtml((booking.time_from || 'N/D') + ' a ' + (booking.time_until || 'N/D'))}</dd>
            <dt class="col-sm-4">Cancha / servicio</dt>
            <dd class="col-sm-8">${escapeHtml(field.name || 'N/D')} ${service.color ? `<span class="badge ms-2" style="background:${escapeHtml(normalizeHexColor(service.color))};color:#fff;">${escapeHtml(service.name || field.service_type || '')}</span>` : ''}</dd>
            <dt class="col-sm-4">Descripción</dt>
            <dd class="col-sm-8">${escapeHtml(booking.description || 'N/D')}</dd>
        </dl>
    `

    const customerHtml = `
        <dl class="row mb-0">
            <dt class="col-sm-4">Nombre</dt>
            <dd class="col-sm-8">${escapeHtml(customer.name || booking.name || 'N/D')}</dd>
            <dt class="col-sm-4">Teléfono</dt>
            <dd class="col-sm-8">${escapeHtml(customer.phone || booking.phone || 'N/D')}</dd>
            <dt class="col-sm-4">Localidad</dt>
            <dd class="col-sm-8">${escapeHtml(customer.city || booking.locality || 'N/D')}</dd>
            <dt class="col-sm-4">Customer ID</dt>
            <dd class="col-sm-8">${escapeHtml(booking.id_customer || 'N/D')}</dd>
        </dl>
    `

    const econHtml = `
        <dl class="row mb-0">
            <dt class="col-sm-4">Precio original</dt>
            <dd class="col-sm-8">${escapeHtml(formatAuditMoney(booking.original_total))}</dd>
            <dt class="col-sm-4">Descuento aplicado</dt>
            <dd class="col-sm-8">${escapeHtml(formatAuditMoney(booking.discount_amount))} (${escapeHtml(booking.discount_percentage ?? '0')}%)</dd>
            <dt class="col-sm-4">Total final</dt>
            <dd class="col-sm-8">${escapeHtml(total)}</dd>
            <dt class="col-sm-4">Booking.payment</dt>
            <dd class="col-sm-8">${escapeHtml(formatAuditMoney(booking.payment ?? data.booking_payment_snapshot ?? 0))}</dd>
            <dt class="col-sm-4">Booking.reservation</dt>
            <dd class="col-sm-8">${escapeHtml(booking.reservation === null || booking.reservation === undefined ? 'No registrado' : formatAuditMoney(booking.reservation))}</dd>
            <dt class="col-sm-4">Seña calculada</dt>
            <dd class="col-sm-8">${escapeHtml(expectedPartial)}</dd>
            <dt class="col-sm-4">Seña guardada</dt>
            <dd class="col-sm-8">${escapeHtml(storedPartial)}</dd>
            <dt class="col-sm-4">Suma pagos única</dt>
            <dd class="col-sm-8">${escapeHtml(formatAuditMoney(totalPaid))}</dd>
            <dt class="col-sm-4">Suma pagos cruda</dt>
            <dd class="col-sm-8">${escapeHtml(formatAuditMoney(totalPaidRaw))}</dd>
            <dt class="col-sm-4">Total pagado</dt>
            <dd class="col-sm-8">${escapeHtml(formatAuditMoney(totalPaid))}</dd>
            <dt class="col-sm-4">Saldo</dt>
            <dd class="col-sm-8">${escapeHtml(saldo)}</dd>
            <dt class="col-sm-4">Pagó total</dt>
            <dd class="col-sm-8">${renderAuditBadge(paymentStatus === 'Sí' ? 'Sí' : 'No', paymentStatus === 'Sí' ? 'success' : 'warning')}</dd>
            <dt class="col-sm-4">Método de pago</dt>
            <dd class="col-sm-8">${escapeHtml(paymentMethod)}</dd>
        </dl>
    `

    const paymentsHtml = renderBookingAuditTable(payments, 'No hay pagos registrados para esta reserva.')
    const duplicatePaymentsHtml = Array.isArray(data.duplicate_payments) && data.duplicate_payments.length > 0
        ? `
            <div class="alert alert-warning">
                <div class="fw-bold mb-2">Pagos duplicados detectados</div>
                <div class="small text-muted mb-2">Estos registros comparten el mismo identificador de Mercado Pago o una misma referencia interna.</div>
                <ul class="mb-0">
                    ${data.duplicate_payments.map((payment) => `
                        <li>${escapeHtml(`${formatAuditDateTime(payment.created_at || payment.date)} | ${payment.payment_method || 'N/D'} | ${formatAuditMoney(payment.amount || 0)} | MP ID: ${payment.mercado_pago_id || 'N/D'} | ID interno: ${payment.id || 'N/D'}`)}</li>
                    `).join('')}
                </ul>
            </div>
        `
        : ''

    return `
        ${summaryCards}
        ${warningHtml}
        ${renderBookingAuditSection('Reserva', bookingHtml)}
        ${renderBookingAuditSection('Cliente', customerHtml)}
        ${renderBookingAuditSection('Creación', creatorHtml)}
        ${renderBookingAuditSection('Detalle económico', econHtml)}
        ${renderBookingAuditSection('Historial de pagos', paymentsHtml)}
        ${duplicatePaymentsHtml}
        ${renderBookingAuditSection('Mercado Pago', mpHtml)}
        ${isMismatch ? '' : ''}
    `
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
    const viewAction = (reservaId) => `<li><button type="button" class="btn btn-primary dropdown-item view-booking-audit" data-id="${reservaId}">Ver</button></li>`

    data.forEach(reserva => {
        if (reserva.anulada == 0 && reserva.pago_total === 'Si') {
            finalizadas.push(reserva)
        } else {
            pendientes.push(reserva)
        }
    })

    const ordered = pendientes.concat(finalizadas)

    ordered.forEach(reserva => {
        actions = ''
        edit = ''
        anular = ''
        state = ''

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
                    <div class="btn-group dropstart" role="group">
                        <button type="button" class="btn btn-secondary dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                            Acciones
                        </button>
                        <ul class="dropdown-menu">
                            <input type="text" id="userId" data-id="${sessionUserId}" hidden>
                            ${viewAction(reserva.id)}
                        </ul>
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
                            ${viewAction(reserva.id)}
                            ${anular}
    
                            ${edit}
                        </ul>
                    </div>
                `;
                }


            } else {
                actions = `
                <div class="btn-group dropstart" role="group">
                    <button type="button" class="btn btn-secondary dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                        Acciones
                    </button>
                    <ul class="dropdown-menu">
                        <input type="text" id="userId" data-id="${sessionUserId}" hidden>
                        ${viewAction(reserva.id)}
                    </ul>
                </div>
            `
            }

        } else {
            if (reserva.anulada == 1) {
                actions = `
                <div class="btn-group dropstart" role="group">
                    <button type="button" class="btn btn-secondary dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                        Acciones
                    </button>
                    <ul class="dropdown-menu">
                        <input type="text" id="userId" data-id="${sessionUserId}" hidden>
                        ${viewAction(reserva.id)}
                    </ul>
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
                    ${viewAction(reserva.id)}
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

