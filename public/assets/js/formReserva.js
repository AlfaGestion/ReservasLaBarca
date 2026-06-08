const confirmarReservabutton = document.getElementById('confirmarReserva')
const bookingForm = document.getElementById('bookingForm')
const selectCancha = document.getElementById('cancha')
const serviceTypeSelect = document.getElementById('serviceType')
let serviceTypeOptions = document.querySelectorAll('input[name="serviceTypeOption"]')
const fechaInput = document.getElementById('fecha')
const horarioDesde = document.getElementById('horarioDesde')
const horarioHasta = document.getElementById('horarioHasta')
const inputMonto = document.getElementById('inputMonto')
const divMonto = document.getElementById('div-monto')
const nombre = document.getElementById('nombre')
const telefono = document.getElementById('telefono')
const localidad = document.getElementById('localidad')
const pagoReserva = document.getElementById('inputPagoReserva')
const pagoTotal = document.getElementById('switchPagoTotal')
const divTime = document.getElementById('div-time')
const divTimeH = document.getElementById('div-time-h')
const modalConfirmarReserva = new bootstrap.Modal('#modalConfirmarReserva')
const modalSpinner = new bootstrap.Modal('#modalSpinner')
const modalIngresarPago = new bootstrap.Modal('#ingresarPago')
const modalIngresarPagoElement = document.getElementById('ingresarPago')
const modalResult = new bootstrap.Modal('#modalResult')
const uiConfirmModalElement = document.getElementById('uiConfirmModal')
const uiConfirmModal = uiConfirmModalElement ? new bootstrap.Modal(uiConfirmModalElement) : null
const uiConfirmTitle = document.getElementById('uiConfirmTitle')
const uiConfirmBody = document.getElementById('uiConfirmBody')
const uiConfirmContent = uiConfirmModalElement ? uiConfirmModalElement.querySelector('.modal-content') : null
const uiConfirmAccept = document.getElementById('uiConfirmAccept')
const uiConfirmCancel = document.getElementById('uiConfirmCancel')
const uiConfirmClose = document.getElementById('uiConfirmClose')
const contentBookingResult = document.getElementById('bookingResult')
const divSelectCancha = document.getElementById('divSelectCancha')
const powerOff = document.getElementsByName('powerOff')
const welcomeModal = new bootstrap.Modal('#welcomeModal')
const ofertaModal = new bootstrap.Modal('#ofertaModal')
const closureNotice = document.getElementById('closureNotice')
const closureWelcomeNotice = document.getElementById('closureWelcomeNotice')
const closureTopNotice = document.getElementById('closureTopNotice')
const addQuincho = document.getElementById('addQuincho')
const quinchoAdditionalBox = document.getElementById('quinchoAdditionalBox')
const quinchoAdditionalFields = document.getElementById('quinchoAdditionalFields')
const quinchoDesde = document.getElementById('quinchoDesde')
const quinchoHasta = document.getElementById('quinchoHasta')
const quinchoAvailabilityMessage = document.getElementById('quinchoAvailabilityMessage')
const allFields = Array.isArray(window.bookingFields) ? window.bookingFields : Array.from(selectCancha?.options || [])
    .filter(option => option.value)
    .map(option => ({
        id: option.value,
        name: option.textContent,
        service_type: option.dataset.serviceType || 'football',
        duration_minutes: option.dataset.durationMinutes || option.dataset.blockMinutes || 60,
        slot_interval_minutes: option.dataset.slotIntervalMinutes || option.dataset.blockMinutes || 60,
        block_minutes: option.dataset.blockMinutes || 60,
        opening_time: option.dataset.openingTime || '',
        closing_time: option.dataset.closingTime || '',
        value: option.dataset.price || 0,
        ilumination_value: option.dataset.priceLight || option.dataset.price || 0,
        disabled: 0,
    }))
const openingTime = Array.isArray(window.bookingOpeningTime) ? window.bookingOpeningTime : []
const bookingServices = Array.isArray(window.bookingServices) ? window.bookingServices : []

let data = {}
let preferencesIds = {}
let useOffer = false
let pendingMpCleanupTimer = null
let skipCancelOnHide = false
let pendingMpContext = null
let closureLoadNoticeShown = false
// let idCustomer
let closureInfo = { closed: false, scope: 'none', label: '', fecha: '', closedAll: false, closedFields: [] }
let currentOccupiedSlots = []
let timeBookingsRequestId = 0

function renderServiceTypeOptions() {
    if (!serviceTypeSelect || bookingServices.length === 0) return
    const wrapper = document.querySelector('.service-type-options')
    serviceTypeSelect.innerHTML = ''
    if (wrapper) wrapper.innerHTML = ''

    bookingServices
        .filter(service => String(service.active ?? '1') !== '0' && String(service.online_available ?? '1') !== '0')
        .forEach((service, index) => {
            const code = service.code || 'football'
            serviceTypeSelect.appendChild(new Option(service.name || code, code, index === 0, index === 0))
            if (!wrapper) return
            const id = `serviceType${String(code).replace(/[^A-Za-z0-9]/g, '')}`
            const input = document.createElement('input')
            input.className = 'btn-check'
            input.type = 'radio'
            input.name = 'serviceTypeOption'
            input.id = id
            input.value = code
            input.autocomplete = 'off'
            input.checked = index === 0

            const label = document.createElement('label')
            label.className = 'service-type-option'
            label.htmlFor = id
            label.textContent = service.name || code
            label.style.setProperty('--service-color', service.color || '#F39323')

            wrapper.appendChild(input)
            wrapper.appendChild(label)
        })

    serviceTypeOptions = document.querySelectorAll('input[name="serviceTypeOption"]')
}

renderServiceTypeOptions()

let dataOferta = {
    valor: 0,
    descripcion: '',
    fecha: 0,
}

function formatPriceAR(value) {
    return '$ ' + new Intl.NumberFormat('es-AR', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    }).format(Number(value || 0))
}

function parsePriceAR(value) {
    if (typeof value === 'number') return value
    const normalized = String(value || '0')
        .replace(/\$/g, '')
        .replace(/\s/g, '')
        .replace(/\./g, '')
        .replace(',', '.')
    return Number(normalized) || 0
}

function minutesToHuman(minutes) {
    const total = Math.max(0, Number(minutes || 0))
    const hours = Math.floor(total / 60)
    const remainder = total % 60
    return `${hours}:${String(remainder).padStart(2, '0')}`
}

function isEmptyData(data) {
    if (data === null || data === undefined) return true
    if (Array.isArray(data)) return data.length === 0
    if (typeof data === 'string') return data.trim() === ''
    return false
}

function normalizePhone(value) {
    return String(value || '').replace(/\D/g, '')
}

function normalizeText(value) {
    return (value || '')
        .toString()
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLowerCase()
}

function setupLocalityAutocomplete(inputEl, datalistId) {
    if (!inputEl) return
    const dataList = document.getElementById(datalistId)
    if (!dataList) return

    const options = Array.from(dataList.querySelectorAll('option'))
        .map(opt => opt.value)
        .filter(Boolean)

    if (options.length === 0) return

    const parent = inputEl.parentElement
    if (parent) {
        parent.style.position = 'relative'
    }

    const box = document.createElement('div')
    box.className = 'locality-suggestions'

    parent.appendChild(box)

    const render = (items) => {
        box.innerHTML = ''
        if (!items || items.length === 0) {
            box.style.display = 'none'
            return
        }
        items.slice(0, 8).forEach((name) => {
            const item = document.createElement('div')
            item.className = 'locality-suggestion-item'
            item.textContent = name
            item.addEventListener('mousedown', (e) => {
                e.preventDefault()
                inputEl.value = name
                box.style.display = 'none'
            })
            box.appendChild(item)
        })
        box.style.display = 'block'
    }

    const onInput = () => {
        const q = normalizeText(inputEl.value)
        if (!q) {
            box.style.display = 'none'
            return
        }
        const matches = options.filter((name) => normalizeText(name).includes(q))
        render(matches)
    }

    inputEl.addEventListener('input', onInput)
    inputEl.addEventListener('focus', onInput)
    inputEl.addEventListener('blur', () => {
        setTimeout(() => { box.style.display = 'none' }, 150)
    })
}

function getTodayDateValue() {
    const fechaSistema = new Date()
    const anio = fechaSistema.getFullYear()
    const mes = String(fechaSistema.getMonth() + 1).padStart(2, '0')
    const dia = String(fechaSistema.getDate()).padStart(2, '0')
    return `${anio}-${mes}-${dia}`
}

function isDateBeforeToday(dateValue) {
    if (!dateValue) return false
    return String(dateValue) < getTodayDateValue()
}

function isReservationDateTimeInPast(dateValue, timeValue) {
    if (!dateValue || !timeValue) return false
    const normalizedTime = normalizeTimeValue(timeValue)
    if (!normalizedTime) return false
    const reservationDate = new Date(`${dateValue}T${normalizedTime}:00`)
    if (Number.isNaN(reservationDate.getTime())) return false

    return reservationDate <= new Date()
}

// Fecha actual por defecto
document.addEventListener('DOMContentLoaded', async (e) => {
    if (esDomingo === '1') {
        checkSunday()
    }

    const fechaActual = getTodayDateValue()
    fechaInput.setAttribute('min', fechaActual)
    fechaInput.value = fechaActual;
    if (addQuincho) {
        addQuincho.checked = false
        quinchoAdditionalFields?.classList.add('d-none')
    }
    deleteRejected()

    await refreshFieldsFromApi()
    updateServiceOptions()
    await checkClosureStatus()

    if (closureWelcomeNotice) {
        closureWelcomeNotice.textContent = ''
        closureWelcomeNotice.classList.add('d-none')
    }
    if (closureTopNotice) {
        closureTopNotice.textContent = ''
        closureTopNotice.classList.add('d-none')
    }

    if (!closureLoadNoticeShown && closureInfo && (closureInfo.closedAll || (Array.isArray(closureInfo.closedFields) && closureInfo.closedFields.length > 0))) {
        closureLoadNoticeShown = true
        if (closureWelcomeNotice) {
            if (closureInfo.closedAll) {
                closureWelcomeNotice.textContent = 'Aviso: hoy hay un cierre informado para todas las canchas. No se pueden realizar reservas para esta fecha.'
            } else {
                closureWelcomeNotice.textContent = 'Aviso: hoy hay cierres informados para algunas canchas. Revisa la disponibilidad antes de reservar.'
            }
            closureWelcomeNotice.classList.remove('d-none')
        }
    } else {
        await loadUpcomingClosureNotice()
    }

    welcomeModal.show()

    setupLocalityAutocomplete(localidad, 'localitiesList')

    if (modalIngresarPagoElement) {
        modalIngresarPagoElement.addEventListener('hidden.bs.modal', async () => {
            if (skipCancelOnHide) {
                skipCancelOnHide = false
                return
            }
            await cancelPendingMpReservation()
        })
    }
})

document.addEventListener('change', async (e) => {
    if (e.target) {
        if (e.target.name == 'serviceTypeOption') {
            serviceTypeSelect.value = e.target.value
            serviceTypeSelect.dispatchEvent(new Event('change', { bubbles: true }))
        } else if (e.target.id == 'serviceType') {
            syncServiceTypeOptions()
            selectCancha.value = ''
            inputMonto.value = 0
            updateServiceOptions()
            await getTimeFromBookings()
        } else if (e.target.id == 'fecha') {
            if (isDateBeforeToday(fechaInput.value)) {
                alert('No se puede reservar con una fecha anterior a hoy.')
                fechaInput.value = getTodayDateValue()
                return
            }
            const day = new Date(fechaInput.value);
            const dayOfWeek = day.getDay();

            if (esDomingo === '1' && dayOfWeek === 6) {
                return alert('Ese dia permaneceran cerradas las canchas')
            }

            selectCancha.selectedIndex = 0
            horarioDesde.selectedIndex = 0
            horarioHasta.selectedIndex = 0

            await checkClosureStatus()

        } else if (e.target.id == 'horarioDesde') {
            divTime.classList.remove('d-none')
            divTimeH.style.width = '49%'
            selectCancha.classList.remove('d-none')

            ensureHorarioHastaSelected(true)
            syncQuinchoSuggestion()
            inputMonto.value = 0
            getAmount(selectCancha.value)
            getTimeFromBookings()
                .then(() => {
                    ensureHorarioHastaSelected(true)
                    syncQuinchoSuggestion()
                    getAmount(selectCancha.value)
                })
                .catch(() => { })

        } else if (e.target.id == 'cancha') {
            if (!sessionUserLogued) {
                divMonto.classList.remove('d-none')
            }

            updateTimeOptions()
            getAmount(selectCancha.value)
            await getTimeFromBookings()
            await checkClosureStatus()
            updateAdditionalQuinchoVisibility()

        } else if (e.target.id == 'horarioHasta') {
            inputMonto.value = 0
            getAmount(selectCancha.value)
            syncQuinchoSuggestion()
        } else if (e.target.id == 'addQuincho') {
            quinchoAdditionalFields?.classList.toggle('d-none', !addQuincho.checked)
            if (addQuincho.checked) {
                syncQuinchoSuggestion()
                await getTimeFromBookings()
                validateQuinchoAdditional(true)
            }
            getAmount(selectCancha.value)
        } else if (e.target.id == 'quinchoDesde') {
            if (quinchoDesde.value) quinchoHasta.value = minutesToTime(timeToMinutes(quinchoDesde.value) + 60)
            validateQuinchoAdditional(true)
            getAmount(selectCancha.value)
        } else if (e.target.id == 'quinchoHasta') {
            validateQuinchoAdditional(true)
            getAmount(selectCancha.value)

        } else if (e.target.id == 'switchPagoTotal') {
            const btnMpParcial = document.getElementById('checkout-btn-parcial')
            const btnMpTotal = document.getElementById('checkout-btn-total')

            if (pagoTotal.checked) {
                btnMpParcial.style.display = 'none'
                btnMpTotal.style.display = 'block'
            } else {
                btnMpParcial.style.display = 'block'
                btnMpTotal.style.display = 'none'
            }
        }
    }
})


document.addEventListener('click', async (e) => {
    if (e.target) {
        const rate = await getRate()
        const normalizedPhone = normalizePhone(telefono.value)

        if (sessionUserLogued) {
            data = {
                fecha: fecha.value,
                cancha: selectCancha.value,
                horarioDesde: horarioDesde.value,
                horarioHasta: horarioHasta.value,
                nombre: nombre.value,
                telefono: normalizedPhone,
                localidad: localidad ? localidad.value : '',
                additionalQuincho: buildAdditionalQuinchoPayload(),
            }
        } else {
            const totalAmount = parsePriceAR(inputMonto.value)
            data = {
                fecha: fecha.value,
                cancha: cancha.value,
                horarioDesde: horarioDesde.value,
                horarioHasta: horarioHasta.value,
                nombre: nombre.value,
                telefono: normalizedPhone,
                localidad: localidad ? localidad.value : '',
                monto: pagoReserva.value,
                total: totalAmount,
                parcial: totalAmount * rate / 100,
                diferencia: totalAmount - parsePriceAR(pagoReserva.value),
                reservacion: pagoReserva.value,
                pagoTotal: pagoTotal.checked,
                metodoDePago: 'Mercado Pago',
                oferta: useOffer,
                additionalQuincho: buildAdditionalQuinchoPayload(),
                // idCliente: idCustomer,
            }
        }

        if (e.target.id == 'confirmarReserva') {
            if (closureInfo && closureInfo.closedAll) {
                alert('No se puede reservar en una fecha con cierre informado.')
                return
            }
            if (isDateBeforeToday(fecha.value)) {
                alert('No se puede reservar con una fecha anterior a hoy.')
                return
            }
            if (isReservationDateTimeInPast(fecha.value, horarioDesde.value)) {
                alert('No se puede reservar con una fecha u horario ya pasados.')
                return
            }
            if (fecha.value == '' || cancha.value == '' || horarioDesde.value == '' || horarioHasta.value == '' || nombre.value == '' || telefono.value == '') {
                alert('Debe completar todos los campos obligatorios.')
                return;
            }
            if (!validateQuinchoAdditional(true)) {
                return
            }

            if (timeToMinutes(horarioDesde.value) >= timeToMinutes(horarioHasta.value) && timeToMinutes(horarioHasta.value) !== 0) {
                alert('El horario de inicio no puede ser mayor o igual al horario de fin.')
                return;
            }

            fetchFormInfo(data)
            modalConfirmarReserva.show()

        } else if (e.target.id == 'cancelarReserva') {
            location.reload(true)
        } else if (e.target.id == 'buttonCancelPayment') {
            const confirmCancel = await showStyledConfirm({
                title: 'Cancelar reserva',
                message: '<p class="mb-1">Atencion: se anulara la reserva pendiente y se liberara el horario.</p><p class="mb-0"><b>Deseas continuar?</b></p>',
                acceptText: 'Si, cancelar',
                cancelText: 'Volver',
                tone: 'danger'
            })
            if (!confirmCancel) {
                return
            }
            const cancelled = await cancelPendingMpReservation()
            if (cancelled) {
                skipCancelOnHide = true
                modalIngresarPago.hide()
            } else {
                alert('No se pudo cancelar la reserva. Intenta nuevamente.')
            }
        } else if (e.target.id == 'switchPagoTotal') {
            const switchPagoTotal = document.getElementById('switchPagoTotal')
            const rate = await getRate()
            const totalAmount = parsePriceAR(inputMonto.value)

            if (switchPagoTotal.checked) {
                pagoReserva.value = formatPriceAR(totalAmount)
            } else {
                pagoReserva.value = formatPriceAR(totalAmount * rate / 100)
            }
        } else if (e.target.id == 'abonarReservaBoton') { //Por defecto me va a traer el valor del porcentual
            if (sessionUserLogued) {
                const totalReserva = document.getElementById('adminBookingTotalAmount')
                const amount = document.getElementById('adminBookingAmount')

                if (totalReserva) totalReserva.value = inputMonto.value
                if (amount) amount.value = inputMonto.value

                modalIngresarPago.show()
            } else {
                const accepted = await showStyledConfirm({
                    title: 'Antes de continuar',
                    message: `
                        <p class="mb-2">Al abonar una reserva aceptas estas condiciones:</p>
                        <ul class="mb-2">
                            <li>No hay devolucion de dinero.</li>
                            <li>Los cambios dependen de la disponibilidad.</li>
                            <li>Si llegas tarde, el horario finaliza en la hora reservada.</li>
                        </ul>
                        <p class="mb-0"><b>Deseas ir a Mercado Pago?</b></p>
                    `,
                    acceptText: 'Continuar',
                    cancelText: 'Volver'
                })
                if (!accepted) {
                    return
                }

                const rate = await getRate()
                const totalAmount = parsePriceAR(inputMonto.value)
                const mpReady = await setScriptMP(totalAmount)
                if (!mpReady) {
                    return
                }
                modalIngresarPago.show()
                pagoReserva.value = formatPriceAR(totalAmount * rate / 100)
            }

        } else if (e.target.id == 'confirmBooking') {
            const amount = document.getElementById('adminBookingAmount')
            const paymentMethod = document.getElementById('adminPaymentMethod')
            const description = document.getElementById('adminBookingDescription')
            const totalReserva = document.getElementById('adminBookingTotalAmount')

            data.monto = parsePriceAR(amount.value)
            data.metodoDePago = paymentMethod.value
            data.descripcion = description.value
            data.total = parsePriceAR(totalReserva.value)
            data.additionalQuincho = buildAdditionalQuinchoPayload()

            saveAdminBooking(data)
        } else if (e.target.id == 'confirmarAdminReserva') {
            if (closureInfo && (closureInfo.closedAll || (Array.isArray(closureInfo.closedFields) && closureInfo.closedFields.length > 0))) {
                alert('Aviso: hay un cierre informado para esta fecha. Como admin, la reserva se puede guardar igual.')
            }
            if (!validateQuinchoAdditional(true)) {
                return
            }
            fetchFormInfo(data)

            modalConfirmarReserva.show()
        } else if (e.target.id == 'volverPagoModal') {
            const confirmCancel = await showStyledConfirm({
                title: 'Cancelar reserva',
                message: '<p class="mb-1">Atencion: se anulara la reserva pendiente y se liberara el horario.</p><p class="mb-0"><b>Deseas continuar?</b></p>',
                acceptText: 'Si, cancelar',
                cancelText: 'Volver',
                tone: 'danger'
            })
            if (!confirmCancel) {
                return
            }
            const cancelled = await cancelPendingMpReservation()
            if (cancelled) {
                skipCancelOnHide = true
                modalIngresarPago.hide()
            } else {
                alert('No se pudo cancelar la reserva. Intenta nuevamente.')
            }
        }
    }
})

function checkSunday() {
    const today = new Date();
    const dayOfWeek = today.getDay();
    const form = document.getElementById("formBooking")
    const container = document.getElementById("isSunday")

    if (dayOfWeek === 0) {
        form.classList.add("d-none")
        container.classList.remove("d-none")
    }
}


telefono.addEventListener('input', async () => {

    let content

    const phone = normalizePhone(telefono.value)

    if (phone.length >= 10 && phone.length <= 11) {
        modalSpinner.show()
        try {
            const offer = await getOffer()
            const customer = await getCustomer(phone)
            const amount = Number(inputMonto.value || 0)

            if (customer) {
                if (customer.offer == '1') {
                    content = `
                        <h1 class="offerTitle">${offer.value}%</h1>
                        <h6 class="offerDescription">${offer.description}</h6>
                        <button type="button" class="btn mb-2" data-bs-dismiss="modal" style="background-color: #f09424">Continuar</button>
                        `

                    if (offer.value != 0) {
                        const ofertaModalContent = document.getElementById('ofertaModalContent')

                        ofertaModalContent.innerHTML = content
                        ofertaModal.show()
                    }

                    const discount = amount * offer.value / 100
                    const discountAmount = amount - discount

                    useOffer = true
                    // idCustomer = customer.id
                    inputMonto.value = discountAmount
                    nombre.value = customer.name
                    if (localidad) {
                        localidad.value = customer.city || ''
                    }
                } else {
                    // idCustomer = customer.id
                    nombre.value = customer.name
                    if (localidad) {
                        localidad.value = customer.city || ''
                    }
                }
            } else {
                await getAmount(selectCancha.value)
            }
        } catch (error) {
            console.error('Error al validar telefono:', error)
            await getAmount(selectCancha.value)
        } finally {
            setTimeout(() => { modalSpinner.hide() }, 300);
        }
    }
})

async function deleteRejected() {
    try {
        const response = await fetch(`${baseUrl}deleteRejected`);

        const responseData = await response.json();

    } catch (error) {
        console.error('Error:', error);
        throw error;
    }
}


async function saveAdminBooking(data) {
    modalIngresarPago.hide()

    try {
        const response = await fetch(`${baseUrl}saveAdminBooking`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(data)
        });

        if (response.redirected || (response.url && response.url.includes('/auth/login'))) {
            alert('Tu sesion vencio. Volve a iniciar sesion para guardar la reserva.')
            return
        }

        let responseData = null
        try {
            responseData = await response.json();
        } catch (parseError) {
            alert('No se pudo procesar la respuesta del servidor. Si la sesion expiro, inicia sesion nuevamente.')
            return
        }

        if (!response.ok || responseData.error) {
            alert(responseData.message || 'No se pudo guardar la reserva. Puede que ya exista una reserva activa para esa cancha y horario.')
            return
        }

        if (response.ok) {

            modalResult.show()

            contentBookingResult.innerHTML = `
            <div class="modal-body modalResultPayment d-flex justify-content-center align-items-center flex-column" style="background-color: #157347; color: #fff">
                <h4 class="mb-5">Reserva confirmada!</h4>
                <i class="fa-regular fa-circle-check fa-2xl" style="margin-bottom: 20px;"></i>
            </div>`
        }

        location.reload(true)

    } catch (error) {
        console.error('Error:', error);
        throw error;
    }
}

async function setScriptMP(amount) {
    let preference = {}

    modalSpinner.show()

    preference = {
        amount: amount,
    }

    try {
        const preferences = await setPreference(`${baseUrl}setPreference`, { amount: amount, booking: data })
        const mp = new MercadoPago(publicKeyMp, {
            locale: "es-AR"
        })
        resetMercadoPagoButtons()

        mp.checkout({
            preference: {
                id: preferences.preferenceIdParcial
            },
            render: {
                container: '#checkout-btn-parcial',
                label: 'Pagar con Mercado Pago'
            }
        })

        mp.checkout({
            preference: {
                id: preferences.preferenceIdTotal
            },
            render: {
                container: '#checkout-btn-total',
                label: 'Pagar con Mercado Pago'
            }
        })

        data.preferenceIdParcial = preferences.preferenceIdParcial,
            data.preferenceIdTotal = preferences.preferenceIdTotal
        data.pendingBookingId = preferences.bookingId || null
        data.pendingSlot = {
            fecha: data.fecha || null,
            cancha: data.cancha || null,
            horarioDesde: data.horarioDesde || null,
            horarioHasta: data.horarioHasta || null,
        }
        pendingMpContext = {
            bookingId: data.pendingBookingId || null,
            preferenceIdParcial: data.preferenceIdParcial || null,
            preferenceIdTotal: data.preferenceIdTotal || null,
            telefono: data.telefono || null,
            fecha: data.pendingSlot?.fecha || null,
            cancha: data.pendingSlot?.cancha || null,
            horarioDesde: data.pendingSlot?.horarioDesde || null,
            horarioHasta: data.pendingSlot?.horarioHasta || null,
        }

        schedulePendingMpCleanup()
        return true
    } catch (error) {
        const message = String(error?.message || '')
        const isAvailabilityError = /horario|ocupad|quincho no est|tomado por otra reserva/i.test(message)
        await showStyledConfirm({
            title: isAvailabilityError ? 'Horario no disponible' : 'No se pudo iniciar el pago',
            message: isAvailabilityError
                ? `<p class="mb-1">${message || 'El horario ya fue tomado por otra reserva.'}</p><p class="mb-0"><b>Elegi otra cancha u horario para continuar.</b></p>`
                : `<p class="mb-1">${message || 'No se pudo iniciar el pago con Mercado Pago.'}</p><p class="mb-0"><b>Revisa la configuracion de Mercado Pago e intenta nuevamente.</b></p>`,
            acceptText: 'Aceptar',
            cancelText: 'Cerrar',
            tone: 'danger'
        })
        await backToMainAndRefreshAvailability()
        return false
    } finally {
        modalSpinner.hide()
    }
}

function normalizeTimeValue(value) {
    const raw = String(value || '').trim()
    if (!raw) return ''
    if (/^\d{1,2}$/.test(raw)) return `${raw.padStart(2, '0')}:00`
    const parts = raw.split(':')
    return `${String(parts[0] || '0').padStart(2, '0')}:${String(parts[1] || '0').padStart(2, '0')}`
}

function timeToMinutes(value) {
    const [h, m] = normalizeTimeValue(value).split(':').map(Number)
    return (h * 60) + m
}

function minutesToTime(minutes) {
    const normalized = ((minutes % 1440) + 1440) % 1440
    const h = Math.floor(normalized / 60)
    const m = normalized % 60
    return `${String(h).padStart(2, '0')}:${String(m).padStart(2, '0')}`
}

function rangesOverlap(fromA, untilA, fromB, untilB) {
    let aFrom = timeToMinutes(fromA)
    let aUntil = timeToMinutes(untilA)
    let bFrom = timeToMinutes(fromB)
    let bUntil = timeToMinutes(untilB)
    if (aUntil <= aFrom) aUntil += 1440
    if (bUntil <= bFrom) bUntil += 1440
    return aFrom < bUntil && aUntil > bFrom
}

function getSelectedServiceType() {
    return serviceTypeSelect?.value || 'football'
}

function syncServiceTypeOptions() {
    serviceTypeOptions.forEach(option => {
        option.checked = option.value === getSelectedServiceType()
    })
}

function getFieldById(id) {
    return allFields.find(field => String(field.id) === String(id)) || null
}

function getFieldsByService(serviceType) {
    return allFields.filter(field => String(field.disabled || '0') !== '1' && String(field.service_active ?? '1') !== '0' && String(field.online_available ?? '1') !== '0' && (field.service_type || 'football') === serviceType)
}

function getQuinchoField() {
    return getFieldsByService('quincho')[0] || null
}

async function getSelectedFieldDetails(fieldId) {
    const localField = getFieldById(fieldId)
    if (localField) return localField

    try {
        return await getField(fieldId)
    } catch (error) {
        console.error('No se pudo obtener la cancha:', error)
        return null
    }
}

function isNocturnalBooking(nocturnalTime) {
    const times = Array.isArray(nocturnalTime?.time) ? nocturnalTime.time : []
    return times.includes(horarioDesde.value) && times.includes(horarioHasta.value)
}

function getCurrentBlockMinutes() {
    const selected = getFieldById(selectCancha?.value)
    if (selected?.duration_minutes) return Number(selected.duration_minutes)
    if (selected?.block_minutes) return Number(selected.block_minutes)
    return 60
}

function getCurrentSlotIntervalMinutes() {
    const selected = getFieldById(selectCancha?.value) || getFieldsByService(getSelectedServiceType())[0]
    if (selected?.slot_interval_minutes) return Number(selected.slot_interval_minutes)
    if (selected?.booking_interval_minutes) return Number(selected.booking_interval_minutes)
    return getCurrentBlockMinutes()
}

function getOpeningStartMinutes() {
    const field = getFieldById(selectCancha?.value) || getFieldsByService(getSelectedServiceType())[0]
    return timeToMinutes(field?.opening_time || openingTime[0] || '07:00')
}

function getOpeningEndMinutes() {
    const field = getFieldById(selectCancha?.value) || getFieldsByService(getSelectedServiceType())[0]
    const end = timeToMinutes(field?.closing_time || openingTime[openingTime.length - 1] || '23:00')
    const start = getOpeningStartMinutes()
    return end <= start ? end + 1440 : end
}

function fillTimeSelect(select, values, placeholder = 'Seleccionar') {
    if (!select) return
    const previous = select.value
    select.innerHTML = ''
    select.appendChild(new Option(placeholder, ''))
    values.forEach(value => select.appendChild(new Option(value, value)))
    if (previous && values.includes(previous)) select.value = previous
}

function buildStartTimes(blockMinutes, stepMinutes = blockMinutes) {
    const start = getOpeningStartMinutes()
    const end = getOpeningEndMinutes()
    const values = []
    for (let current = start; current + blockMinutes <= end; current += stepMinutes) {
        values.push(minutesToTime(current))
    }
    return values
}

function updateTimeOptions() {
    const block = getCurrentBlockMinutes()
    const starts = buildStartTimes(block, getCurrentSlotIntervalMinutes())
    const selectedFieldId = selectCancha?.value || ''
    const availableStarts = selectedFieldId
        ? starts.filter(start => isSlotAvailable(selectedFieldId, start, minutesToTime(timeToMinutes(start) + block)))
        : starts
    fillTimeSelect(horarioDesde, availableStarts)
    fillTimeSelect(horarioHasta, availableStarts.map(t => minutesToTime(timeToMinutes(t) + block)))
    ensureHorarioHastaSelected()
    if (quinchoDesde && quinchoHasta) {
        const hourlyStarts = buildStartTimes(60)
        fillTimeSelect(quinchoDesde, hourlyStarts)
        fillTimeSelect(quinchoHasta, hourlyStarts.map(t => minutesToTime(timeToMinutes(t) + 60)))
    }
}

function ensureHorarioHastaSelected(forceDefaultFromDesde = false) {
    if (!horarioHasta) return
    const validUntilOptions = Array.from(horarioHasta.options || [])
        .map(opt => normalizeTimeValue(opt.value))
        .filter(Boolean)

    if (validUntilOptions.length === 0) {
        horarioHasta.value = ''
        return
    }

    if (!horarioDesde?.value) {
        const currentUntil = normalizeTimeValue(horarioHasta.value)
        if (currentUntil && validUntilOptions.includes(currentUntil)) {
            horarioHasta.value = currentUntil
            return
        }
        horarioHasta.value = validUntilOptions[0]
        return
    }

    const fromMinutes = timeToMinutes(horarioDesde.value)
    const blockMinutes = getCurrentBlockMinutes()
    const minUntilMinutes = fromMinutes + blockMinutes
    const eligibleUntilOptions = validUntilOptions.filter(value => timeToMinutes(value) >= minUntilMinutes)
    if (eligibleUntilOptions.length === 0) {
        horarioHasta.value = ''
        return
    }

    if (forceDefaultFromDesde) {
        horarioHasta.value = eligibleUntilOptions[0]
        return
    }

    const currentUntil = normalizeTimeValue(horarioHasta.value)
    if (currentUntil && eligibleUntilOptions.includes(currentUntil)) {
        const currentUntilMinutes = timeToMinutes(currentUntil)
        if (currentUntilMinutes > fromMinutes && currentUntilMinutes >= minUntilMinutes) {
            horarioHasta.value = currentUntil
            return
        }
    }

    const idealUntil = minutesToTime(fromMinutes + blockMinutes)
    if (eligibleUntilOptions.includes(idealUntil)) {
        horarioHasta.value = idealUntil
        return
    }

    horarioHasta.value = eligibleUntilOptions[0]
}

function updateServiceOptions() {
    if (!selectCancha) return
    const selectedService = getSelectedServiceType()
    const selected = selectCancha.value
    selectCancha.innerHTML = ''
    selectCancha.appendChild(new Option('Elegí una cancha o espacio', ''))
    getFieldsByService(selectedService).forEach((field) => {
        const option = new Option(field.name, field.id)
        option.dataset.serviceType = field.service_type || 'football'
        option.dataset.blockMinutes = field.duration_minutes || field.block_minutes || 60
        option.dataset.durationMinutes = field.duration_minutes || field.block_minutes || 60
        option.dataset.slotIntervalMinutes = field.slot_interval_minutes || field.booking_interval_minutes || field.duration_minutes || field.block_minutes || 60
        option.dataset.openingTime = field.opening_time || ''
        option.dataset.closingTime = field.closing_time || ''
        option.textContent = `${field.name} - ${minutesToHuman(field.duration_minutes || field.block_minutes || 60)}`
        selectCancha.appendChild(option)
    })
    if (selected && Array.from(selectCancha.options).some(opt => opt.value === selected)) {
        selectCancha.value = selected
    }
    if (!selectCancha.value && selectCancha.options.length === 2) {
        selectCancha.selectedIndex = 1
    }
    applyClosedFieldsToSelect()
    updateTimeOptions()
    updateAdditionalQuinchoVisibility()
}

function updateAdditionalQuinchoVisibility() {
    const quincho = getQuinchoField()
    const selected = getFieldById(selectCancha?.value)
    const canOffer = Boolean(quincho && selected && String(selected.id) !== String(quincho.id) && String(selected.allows_quincho_addon ?? '0') !== '0')
    if (!quinchoAdditionalBox) return
    quinchoAdditionalBox.classList.toggle('d-none', !canOffer)
    if (!canOffer && addQuincho) {
        addQuincho.checked = false
        quinchoAdditionalFields?.classList.add('d-none')
    }
}

function syncQuinchoSuggestion() {
    if (!quinchoDesde || !quinchoHasta || !horarioHasta.value) return
    quinchoDesde.value = normalizeTimeValue(horarioHasta.value)
    quinchoHasta.value = minutesToTime(timeToMinutes(quinchoDesde.value) + 60)
}

function isSlotAvailable(fieldId, from, until) {
    return !currentOccupiedSlots.some(slot => String(slot.id_cancha) === String(fieldId) && rangesOverlap(from, until, slot.time_from || slot.time?.[0], slot.time_until || slot.time?.[1]))
}

function validateQuinchoAdditional(showMessage = true) {
    if (!addQuincho?.checked) return true
    const quincho = getQuinchoField()
    const valid = Boolean(quincho && quinchoDesde.value && quinchoHasta.value && isSlotAvailable(quincho.id, quinchoDesde.value, quinchoHasta.value))
    if (quinchoAvailabilityMessage) {
        quinchoAvailabilityMessage.textContent = valid ? '' : 'El quincho no está disponible en el horario seleccionado.'
        quinchoAvailabilityMessage.classList.toggle('d-none', valid || !showMessage)
    }
    return valid
}

function buildAdditionalQuinchoPayload() {
    if (!addQuincho?.checked) return null
    const quincho = getQuinchoField()
    if (!quincho) return null
    return {
        enabled: true,
        fecha: fechaInput.value,
        cancha: quincho.id,
        horarioDesde: quinchoDesde.value,
        horarioHasta: quinchoHasta.value,
    }
}

function schedulePendingMpCleanup() {
    if (pendingMpCleanupTimer) {
        clearTimeout(pendingMpCleanupTimer)
    }

    // Si el cliente no avanza al checkout en un tiempo razonable, liberamos el slot.
    pendingMpCleanupTimer = setTimeout(async () => {
        await cancelPendingMpReservation()
    }, 3 * 60 * 1000)
}

function resetMercadoPagoButtons() {
    const btnParcial = document.getElementById('checkout-btn-parcial')
    const btnTotal = document.getElementById('checkout-btn-total')

    if (btnParcial) {
        btnParcial.innerHTML = ''
        btnParcial.style.display = 'block'
    }

    if (btnTotal) {
        btnTotal.innerHTML = ''
        btnTotal.style.display = 'none'
    }
}

async function backToMainAndRefreshAvailability() {
    resetMercadoPagoButtons()
    skipCancelOnHide = true
    try {
        modalSpinner.hide()
    } catch (e) {}
    try {
        modalIngresarPago.hide()
    } catch (e) {}
    try {
        modalConfirmarReserva.hide()
    } catch (e) {}

    data.pendingBookingId = null
    data.preferenceIdParcial = null
    data.preferenceIdTotal = null
    data.pendingSlot = null
    pendingMpContext = null

    await refreshFieldsFromApi()

    if (horarioDesde?.value && horarioHasta?.value) {
        await getTimeFromBookings()
    }

    if (selectCancha) {
        selectCancha.value = ''
        selectCancha.focus()
    }

    // Limpieza defensiva por si quedo algun backdrop abierto.
    document.querySelectorAll('.modal-backdrop').forEach((el) => el.remove())
    document.body.classList.remove('modal-open')
    document.body.style.removeProperty('padding-right')
}


async function savePreferenceIds(data) {
    try {
        const response = await fetch(`${baseUrl}savePreferenceIds`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(data)
        });

        const responseData = await response.json();

    } catch (error) {
        console.error('Error:', error);
        throw error;
    }
}

async function setPreference(url, data) {
    try {
        const response = await fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(data)
        });
        const raw = await response.text();
        let responseData = null;
        try {
            responseData = raw ? JSON.parse(raw) : null;
        } catch (e) {
            throw new Error('No se pudo iniciar el pago: respuesta invalida del servidor.');
        }

        if (!responseData) {
            throw new Error('No se pudo iniciar el pago: servidor sin respuesta valida.');
        }

        if (responseData.error) {
            throw new Error(responseData.message || 'No se pudo iniciar el pago con Mercado Pago.')
        }

        if (!response.ok) {
            throw new Error(responseData.message || 'No se pudo iniciar el pago con Mercado Pago.')
        }

        return responseData.data

    } catch (error) {
        console.error('Error:', error);
        throw error;
    }
}

async function cancelPendingMpReservation() {
    let cancelled = false
    try {
        const cancelPayload = {
            bookingId: pendingMpContext?.bookingId || data?.pendingBookingId || null,
            preferenceIdParcial: pendingMpContext?.preferenceIdParcial || data?.preferenceIdParcial || null,
            preferenceIdTotal: pendingMpContext?.preferenceIdTotal || data?.preferenceIdTotal || null,
            telefono: pendingMpContext?.telefono || data?.telefono || normalizePhone(telefono?.value || '') || null,
            fecha: pendingMpContext?.fecha || data?.pendingSlot?.fecha || data?.fecha || fechaInput?.value || null,
            cancha: pendingMpContext?.cancha || data?.pendingSlot?.cancha || data?.cancha || selectCancha?.value || null,
            horarioDesde: pendingMpContext?.horarioDesde || data?.pendingSlot?.horarioDesde || data?.horarioDesde || horarioDesde?.value || null,
            horarioHasta: pendingMpContext?.horarioHasta || data?.pendingSlot?.horarioHasta || data?.horarioHasta || horarioHasta?.value || null,
        }

        const hasIdentifiers = cancelPayload.bookingId || cancelPayload.preferenceIdParcial || cancelPayload.preferenceIdTotal
        const hasSlotData = cancelPayload.fecha && cancelPayload.cancha && cancelPayload.horarioDesde && cancelPayload.horarioHasta
        if (!hasIdentifiers && !hasSlotData) {
            return false
        }

        const response = await fetch(`${baseUrl}cancelPendingMpReservation`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(cancelPayload)
        })

        const responseData = await response.json().catch(() => null)
        if (!response.ok || !responseData || responseData.error) {
            throw new Error(responseData?.message || 'No se pudo cancelar la reserva. Intenta nuevamente.')
        }
        // Si el backend respondio OK, tratamos la operacion como cancelada (idempotente).
        cancelled = true
        return cancelled
    } catch (error) {
        console.error('Error al cancelar reserva pendiente:', error)
        alert(error.message || 'No se pudo cancelar la reserva. Intenta nuevamente.')
        return false
    } finally {
        if (cancelled) {
            if (pendingMpCleanupTimer) {
                clearTimeout(pendingMpCleanupTimer)
                pendingMpCleanupTimer = null
            }
            resetMercadoPagoButtons()
            // Evita reintentos sobre preferencias viejas.
            data.pendingBookingId = null
            data.preferenceIdParcial = null
            data.preferenceIdTotal = null
            data.pendingSlot = null
            pendingMpContext = null
        }
    }
}

function showStyledConfirm({
    title = 'Confirmar',
    message = '',
    acceptText = 'Aceptar',
    cancelText = 'Cancelar',
    tone = 'default'
} = {}) {
    if (!uiConfirmModal || !uiConfirmTitle || !uiConfirmBody || !uiConfirmAccept || !uiConfirmCancel || !uiConfirmClose) {
        return Promise.resolve(window.confirm(message || title))
    }

    uiConfirmTitle.textContent = title
    uiConfirmBody.innerHTML = message
    uiConfirmAccept.textContent = acceptText
    uiConfirmCancel.textContent = cancelText
    if (uiConfirmContent) {
        uiConfirmContent.classList.remove('ui-confirm-danger')
        if (tone === 'danger') {
            uiConfirmContent.classList.add('ui-confirm-danger')
        }
    }

    return new Promise((resolve) => {
        let settled = false

        const cleanup = () => {
            uiConfirmAccept.removeEventListener('click', onAccept)
            uiConfirmCancel.removeEventListener('click', onCancel)
            uiConfirmClose.removeEventListener('click', onCancel)
            uiConfirmModalElement.removeEventListener('hidden.bs.modal', onHidden)
        }

        const finish = (result) => {
            if (settled) return
            settled = true
            cleanup()
            resolve(result)
        }

        const onAccept = () => {
            finish(true)
            uiConfirmModal.hide()
        }

        const onCancel = () => {
            finish(false)
            uiConfirmModal.hide()
        }

        const onHidden = () => {
            finish(false)
        }

        uiConfirmAccept.addEventListener('click', onAccept)
        uiConfirmCancel.addEventListener('click', onCancel)
        uiConfirmClose.addEventListener('click', onCancel)
        uiConfirmModalElement.addEventListener('hidden.bs.modal', onHidden)
        uiConfirmModal.show()
    })
}


async function getAmount(field = "1") {
    try {
        if (!field || !horarioDesde.value || !horarioHasta.value) {
            inputMonto.value = 0
            return
        }
        const selectedField = await getSelectedFieldDetails(field)

        if (!selectedField) {
            alert('No se pudo obtener la información. Intenta nuevamente.')
            return
        }

        const nocturnalTime = await getNocturnalTime()
        if (isNocturnalBooking(nocturnalTime)) {
            const nightPrice = Number(selectedField.ilumination_value || 0) > 0 ? selectedField.ilumination_value : selectedField.value
            inputMonto.value = formatPriceAR(calculateAmount(horarioDesde.value, horarioHasta.value, nightPrice))
        } else {
            inputMonto.value = formatPriceAR(calculateAmount(horarioDesde.value, horarioHasta.value, selectedField.value))
        }
    } catch (error) {
        console.error('Error:', error);
        inputMonto.value = 0
    }
}

async function getRate() {
    try {
        const response = await fetch(`${baseUrl}getRate`);
        const responseData = await response.json();

        if (isEmptyData(responseData.data)) {
            console.warn('No se pudo obtener la tarifa (rate).')
            return 0
        }

        if (responseData.data != '') {

            return responseData.data.value
        } else {
            alert('No se pudo obtener la información. Intenta nuevamente.');
        }
    } catch (error) {
        console.error('Error:', error);
        throw error;
    }
}

async function getOffer() {
    try {
        const response = await fetch(`${baseUrl}getOffersRate`);
        const responseData = await response.json();

        if (isEmptyData(responseData.data)) {
            console.warn('No se pudo obtener la oferta.')
            return null
        }

        if (responseData.data != '') {

            return responseData.data
        } else {
            alert('No se pudo obtener la información. Intenta nuevamente.');
        }
    } catch (error) {
        console.error('Error:', error);
        throw error;
    }
}


async function getNocturnalTime() {
    try {
        const response = await fetch(`${baseUrl}getNocturnalTime`);
        const responseData = await response.json();

        if (isEmptyData(responseData.data)) {
            console.warn('No se pudo obtener el horario nocturno.')
            return null
        }

        if (responseData.data != '') {

            const nocturnalTime = { time: responseData.data }

            return nocturnalTime
        } else {
            alert('No se pudo obtener la información. Intenta nuevamente.');
        }
    } catch (error) {
        console.error('Error:', error);
        return null
    }
}


async function getFields() {
    try {
        const response = await fetch(`${baseUrl}getFields`);

        const responseData = await response.json();

        if (isEmptyData(responseData.data)) {
            console.warn('No se encontraron canchas.')
            return []
        }

        if (responseData.data != '') {

            return responseData.data

        } else {
            alert('No se pudo obtener la información. Intenta nuevamente.');
        }

    } catch (error) {
        console.error('Error:', error);
        throw error;
    }
}

async function refreshFieldsFromApi() {
    try {
        const fields = await getFields()

        if (!fields || fields.length === 0) {
            return
        }
        window.bookingFields = fields
        allFields.splice(0, allFields.length, ...fields)
        updateServiceOptions()
    } catch (error) {
        console.error('Error:', error)
    }
}

// Busca la cancha seleccionada para colocar valor
async function getField(id) {
    try {
        const response = await fetch(`${baseUrl}getField/${id}`);

        const responseData = await response.json();

        if (isEmptyData(responseData.data)) {
            console.warn('No se pudo obtener la cancha.')
            return null
        }

        if (responseData.data != '') {

            return responseData.data

        } else {
            alert('No se pudo obtener la información. Intenta nuevamente.');
        }

    } catch (error) {
        console.error('Error:', error);
        throw error;
    }
}


// Guarda reserva
async function saveBooking(data) {

    try {
        const response = await fetch(`${baseUrl}saveBooking`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(data)
        });

        const responseData = await response.json();
        if (!response.ok || responseData.error) {
            alert(responseData.message || 'El horario seleccionado ya no esta disponible. Elegi otro e intenta nuevamente.')
            return
        }

    } catch (error) {
        console.error('Error:', error);
        throw error;
    }
}

// Trae la informacion a mostrar en el modal
async function fetchFormInfo(data) {
    try {
        const response = await fetch(`${baseUrl}formInfo`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(data)
        });

        const responseData = await response.json();

        if (isEmptyData(responseData.data)) {
            console.warn('No se pudo obtener la informacion para el modal.')
            return
        }

        if (responseData.data != '') {
            fillModal(responseData);
        } else {
            alert('No se pudo obtener la información. Intenta nuevamente.');
        }

    } catch (error) {
        console.error('Error:', error);
        throw error;
    }
}

async function getCustomer(phone) {
    try {
        const response = await fetch(`${baseUrl}getCustomer/${phone}`);

        const responseData = await response.json();

        if (isEmptyData(responseData.data)) {
            return null
        }

        if (responseData.data != '') {

            return responseData.data

        } else {
            alert('No se pudo obtener la información. Intenta nuevamente.');
        }

    } catch (error) {
        console.error('Error:', error);
        return null
    }
}

// Rellena el modal
async function fillModal(data) {

    const modalBody = document.querySelector('.modal-resume-body')
    let amount = inputMonto.value

    if (data == '') {
        return;
    }

    let info = '';

    const fecha = convertDateFormat(data.data.fecha)
    const localidadValue = data.data.localidad ? `<li><i class="fa-solid fa-location-dot"></i> <b>Localidad:</b> ${data.data.localidad}</li>` : ''
    const additional = buildAdditionalQuinchoPayload()
    const duration = timeToMinutes(data.data.horarioHasta) > timeToMinutes(data.data.horarioDesde)
        ? timeToMinutes(data.data.horarioHasta) - timeToMinutes(data.data.horarioDesde)
        : timeToMinutes(data.data.horarioHasta) + 1440 - timeToMinutes(data.data.horarioDesde)
    const additionalDuration = additional
        ? (timeToMinutes(additional.horarioHasta) > timeToMinutes(additional.horarioDesde)
            ? timeToMinutes(additional.horarioHasta) - timeToMinutes(additional.horarioDesde)
            : timeToMinutes(additional.horarioHasta) + 1440 - timeToMinutes(additional.horarioDesde))
        : 0
    const additionalText = additional ? `<li><i class="fa-solid fa-plus"></i> <b>Adicional:</b> Quincho de ${additional.horarioDesde} a ${additional.horarioHasta} (${minutesToHuman(additionalDuration)})</li>` : ''

    if (sessionUserLogued) {
        info =
            `
        <ul id="bookingDetail">
            <li><i class="fa-solid fa-calendar-days"></i> <b>Fecha:</b> ${fecha}</li>
            <li><i class="fa-solid fa-futbol"></i> <b>Tipo de reserva:</b> ${data.data.cancha}</li>
            <li><i class="fa-solid fa-clock"></i> <b>Horario:</b> ${normalizeTimeValue(data.data.horarioDesde)} a ${normalizeTimeValue(data.data.horarioHasta)}</li>
            <li><i class="fa-regular fa-hourglass-half"></i> <b>Duración:</b> ${minutesToHuman(duration)}</li>
            ${additionalText}
            <li><i class="fa-solid fa-user"></i> <b>Nombre:</b> ${data.data.nombre}</li>
            <li><i class="fa-solid fa-phone"></i> <b>Telefono:</b> ${data.data.telefono}</li>
            ${localidadValue}
        </ul>
        `;
    } else {
        info =
            `
        <ul id="bookingDetail">
            <li><i class="fa-solid fa-calendar-days"></i> <b>Fecha:</b> ${fecha}</li>
            <li><i class="fa-solid fa-futbol"></i> <b>Tipo de reserva:</b> ${data.data.cancha}</li>
            <li><i class="fa-solid fa-clock"></i> <b>Horario:</b> ${normalizeTimeValue(data.data.horarioDesde)} a ${normalizeTimeValue(data.data.horarioHasta)}</li>
            <li><i class="fa-regular fa-hourglass-half"></i> <b>Duración:</b> ${minutesToHuman(duration)}</li>
            ${additionalText}
            <li><i class="fa-regular fa-money-bill-1"></i> <b>Monto:</b> ${amount}</li>
            <li><i class="fa-solid fa-user"></i> <b>Nombre:</b> ${data.data.nombre}</li>
            <li><i class="fa-solid fa-phone"></i> <b>Telefono:</b> ${data.data.telefono}</li>
            ${localidadValue}
        </ul>
        `;
    }



    modalBody.innerHTML = info;
}

function convertDateFormat(date) {
    return date.split("-").reverse().join("/")
}

function formatDateDdMmYyyy(dateStr) {
    if (!dateStr || typeof dateStr !== 'string' || !dateStr.includes('-')) return dateStr || ''
    const [y, m, d] = dateStr.split('-')
    if (!y || !m || !d) return dateStr
    return `${d}/${m}/${y}`
}

async function loadUpcomingClosureNotice() {
    if (!closureWelcomeNotice && !closureTopNotice) return
    try {
        const response = await fetch(`${baseUrl}getUpcomingClosure`)
        const responseData = await response.json()
        if (responseData.error || !responseData.data) return

        const upcoming = responseData.data
        const fechaLabel = formatDateDdMmYyyy(upcoming.fecha)
        const detail = upcoming.closedAll
            ? 'Proximo cierre general'
            : 'Proximo cierre informado'
        const text = `${detail}: ${fechaLabel}.`
        if (closureWelcomeNotice) {
            closureWelcomeNotice.textContent = text
            closureWelcomeNotice.classList.remove('d-none')
        }
        if (closureTopNotice) {
            closureTopNotice.textContent = text
            closureTopNotice.classList.remove('d-none')
        }
    } catch (error) {
        console.error('Error:', error)
    }
}

function showClosureNotice(message, blocking = false) {
    if (!closureNotice) return
    if (!message) {
        closureNotice.classList.add('d-none')
        closureNotice.textContent = ''
        closureNotice.classList.remove('closure-blocking-notice')
        return
    }
    closureNotice.textContent = message
    closureNotice.style.whiteSpace = 'pre-line'
    if (blocking) {
        closureNotice.classList.add('closure-blocking-notice')
    } else {
        closureNotice.classList.remove('closure-blocking-notice')
    }
    closureNotice.classList.remove('d-none')
}

function setBookingDisabled(disabled) {
    if (selectCancha) selectCancha.disabled = disabled
    if (horarioDesde) horarioDesde.disabled = disabled
    if (horarioHasta) horarioHasta.disabled = disabled
    if (inputMonto) inputMonto.disabled = true
    if (telefono) telefono.disabled = disabled
    if (localidad) localidad.disabled = disabled
    if (nombre) nombre.disabled = disabled
    const btnCancelar = document.getElementById('cancelarReserva')
    const btnConfirmar = document.getElementById('confirmarReserva')
    const btnConfirmarAdmin = document.getElementById('confirmarAdminReserva')
    if (btnCancelar) btnCancelar.disabled = disabled
    if (btnConfirmar) btnConfirmar.disabled = disabled
    if (btnConfirmarAdmin) btnConfirmarAdmin.disabled = disabled
    if (fechaInput) fechaInput.disabled = false
}

function setClosureOnlyDateMode(enabled) {
    if (!bookingForm) return
    if (enabled) {
        bookingForm.classList.add('closure-only-date')
    } else {
        bookingForm.classList.remove('closure-only-date')
    }
}

function applyClosedFieldsToSelect() {
    if (!selectCancha) return
    if (!closureInfo || !Array.isArray(closureInfo.closedFields)) return
    const closedSet = new Set(closureInfo.closedFields.map(String))
    const options = Array.from(selectCancha.options)
    options.forEach((opt) => {
        if (closedSet.has(String(opt.value))) {
            opt.remove()
        }
    })
    if (closedSet.has(String(selectCancha.value))) {
        selectCancha.value = ''
    }
}

async function checkClosureStatus() {
    const dateValue = fechaInput?.value || ''
    if (!dateValue) {
        showClosureNotice('', false)
        setClosureOnlyDateMode(false)
        setBookingDisabled(false)
        return
    }

    const fieldValue = selectCancha?.value || 'all'
    try {
        const response = await fetch(`${baseUrl}checkClosure`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ fecha: dateValue, cancha: fieldValue || 'all' })
        })
        const responseData = await response.json()
        if (responseData.error || !responseData.data) {
            closureInfo = { closed: false, scope: 'none', label: '', fecha: '', closedAll: false, closedFields: [] }
            showClosureNotice('', false)
            setClosureOnlyDateMode(false)
            setBookingDisabled(false)
            return
        }
        const data = responseData.data
        closureInfo = data
        if (!data.closedAll) {
            showClosureNotice('', false)
            setClosureOnlyDateMode(false)
            setBookingDisabled(false)
        }

        applyClosedFieldsToSelect()
        if (!data.closedAll) {
            return
        }
        const fechaLabel = formatDateDdMmYyyy(data.fecha)
        const template = data.message && data.message.trim()
            ? data.message
            : `Aviso importante\n\nQueremos informarles que el dia <fecha> las canchas permaneceran cerradas.\nPedimos disculpas por las molestias que esto pueda ocasionar.\n\nDe todas formas, ya pueden reservar normalmente las horas para fechas posteriores.\nMuchas gracias por la comprension y por seguir eligiendonos.`
        const resolved = template.replace(/<fecha>/g, fechaLabel)
        showClosureNotice(resolved, true)
        setClosureOnlyDateMode(true)
        setBookingDisabled(true)
    } catch (error) {
        console.error('Error:', error)
        closureInfo = { closed: false, scope: 'none', label: '', fecha: '', closedAll: false, closedFields: [] }
        setClosureOnlyDateMode(false)
        setBookingDisabled(false)
    }
}

// Trae los horarios de las reservas hechas
async function getTimeFromBookings() {
    const requestId = ++timeBookingsRequestId
    const fecha = document.getElementById('fecha').value


    try {
        const response = await fetch(`${baseUrl}getBookings/${fecha}`);
        const responseData = await response.json();
        if (requestId !== timeBookingsRequestId) return

        if (isEmptyData(responseData.data)) {
            getFieldForTimeBookings([])
            return
        }

        if (responseData.data != '') {


            getFieldForTimeBookings(responseData.data)
        } else {
            alert('No se pudo obtener la información. Intenta nuevamente.');
        }

    } catch (error) {
        console.error('Error:', error);
        throw error;
    }
}

// // Quita las canchas sin horario disponible seleccionado
// async function getFieldForTimeBookings(timeBookings) {
//     const currentReserva = [horarioDesde.value, horarioHasta.value]
//     const options = selectCancha.options; //canchas (4)

//     timeBookings.forEach(element => {
//         let reserva = []
//         for (let t = 0; t < element.time.length; t += 2) {
//             reserva.push(element.time.slice(t, t + 2))
//         }


//         const remove = element.time.includes(horarioDesde.value) && element.time.includes(horarioHasta.value)

//         if (remove) {
//             for (let i = 0; i < options.length; i++) {

//                 if (options[i].value == element.id_cancha) {
//                     options[i].remove()

//                     break
//                 }
//             }
//         } else {
//             let exists = false

//             for (let i = 0; i < options.length; i++) {
//                 if (options[i].value == element.id_cancha) {
//                     exists = true

//                     break
//                 }
//             }

//             if (!exists) {
//                 const newOption = new Option(element.nombre_cancha, element.id_cancha)
//                 selectCancha.appendChild(newOption)
//             }
//         }
//     })

//     const optionsArray = Array.from(selectCancha.options)

//     optionsArray.sort((a, b) => {
//         const valueA = parseFloat(a.value)
//         const valueB = parseFloat(b.value)
//         return valueA - valueB
//     });

//     selectCancha.innerHTML = ''

//     if (optionsArray.length == 1) {
//         selectCancha.setAttribute('disabled', 'true')
//         selectCancha.style.backgroundColor = '#bb2d3b'
//         optionsArray[0].innerText = 'No hay canchas disponibles en este horario'
//     } else {
//         selectCancha.removeAttribute('disabled')
//         selectCancha.style.backgroundColor = ''
//         optionsArray[0].innerText = 'Canchas disponibles'
//     }

//     optionsArray.forEach(option => {
//         selectCancha.appendChild(option)
//     })
// }


async function getFieldForTimeBookings(timeBookings) {
    currentOccupiedSlots = Array.isArray(timeBookings) ? timeBookings : []
    updateServiceOptions()

    if (horarioDesde.value && horarioHasta.value) {
        Array.from(selectCancha.options).forEach((option) => {
            if (!option.value) return
            if (!isSlotAvailable(option.value, horarioDesde.value, horarioHasta.value)) {
                option.remove()
            }
        })
    }

    if (selectCancha.options.length == 1) {
        selectCancha.setAttribute('disabled', 'true')
        selectCancha.style.backgroundColor = '#bb2d3b'
        selectCancha.options[0].innerText = 'No hay canchas o espacios disponibles'
    } else {
        selectCancha.removeAttribute('disabled')
        selectCancha.style.backgroundColor = ''
        selectCancha.options[0].innerText = 'Elegí una cancha o espacio'
        if (!selectCancha.value && selectCancha.options.length === 2) {
            selectCancha.selectedIndex = 1
        }
    }

    applyClosedFieldsToSelect()
    validateQuinchoAdditional(false)
    if (selectCancha.value && horarioDesde.value && horarioHasta.value) {
        getAmount(selectCancha.value)
    }
}




// Calcula el total $ de la reserva

function calculateAmount(from, until, amount) {
    const field = getFieldById(selectCancha?.value)
    const blockMinutes = Number(field?.block_minutes || getCurrentBlockMinutes())
    let fromMinutes = timeToMinutes(from)
    let untilMinutes = timeToMinutes(until)
    if (untilMinutes <= fromMinutes) untilMinutes += 1440
    const minutes = Math.max(0, untilMinutes - fromMinutes)
    const multiplier = minutes / Math.max(1, blockMinutes)
    let total = multiplier * Number(amount || 0)

    if (addQuincho?.checked) {
        const quincho = getQuinchoField()
        if (quincho && quinchoDesde.value && quinchoHasta.value) {
            let qFrom = timeToMinutes(quinchoDesde.value)
            let qUntil = timeToMinutes(quinchoHasta.value)
            if (qUntil <= qFrom) qUntil += 1440
            const qBlockMinutes = Number(quincho.duration_minutes || quincho.block_minutes || 60)
            total += ((qUntil - qFrom) / Math.max(1, qBlockMinutes)) * Number(quincho.value || 0)
        }
    }

    return Math.round(total)
}




