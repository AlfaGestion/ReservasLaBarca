const formBooking = document.getElementById('formBooking')
const selectMenuAbm = document.getElementById('selectMenuAbm')
const openingTime = document.getElementById('openingTime')
const switchCutTime = document.getElementById('switchCutTime')
const horarioNocturno = document.getElementById('horarioNocturno')
const inputCompletarPagoReserva = document.getElementById('inputCompletarPagoReserva')
const inputRate = document.getElementById('rate')
const inputOfferRate = document.getElementById('offerRate')
const descriptionOffer = document.getElementById('descriptionOffer')
const medioPagoSelect = document.getElementById('medioPagoSelect')
const changeTimeFrom = document.getElementById('changeTimeFrom')
const changeTimeUntil = document.getElementById('changeTimeUntil')
const changeTimeFromCut = document.getElementById('changeTimeFromCut')
const changeTimeUntilCut = document.getElementById('changeTimeUntilCut')
const completarPagoModalB = new bootstrap.Modal('#completarPagoModal')
const cambiarEstadoMPModal = new bootstrap.Modal('#modalCambiarEstado')
const cancelBookingModal = new bootstrap.Modal('#eliminarReservaModal')
const editBookingModal = new bootstrap.Modal('#editarReservaModal')
const completarPagoModal = document.getElementById('completarPagoModal')
const spinnerCompletarPago = new bootstrap.Modal('#spinnerCompletarPago')
const modalResultPayment = new bootstrap.Modal('#modalResultPayment')
const contentPaymentResult = document.getElementById('paymentResult')
const enterFieldsForm = document.getElementById('enterFields')
const selectEditField = document.getElementById('selectEditField')
const editFieldDiv = document.getElementById('editFieldDiv')
const selectEditFields = document.getElementById('selectEditFields')
const fieldFormModalElement = document.getElementById('fieldFormModal')
const fieldFormModal = fieldFormModalElement ? new bootstrap.Modal(fieldFormModalElement) : null
const fieldFormModalTitle = document.getElementById('fieldFormModalTitle')
const fieldFormMessage = document.getElementById('fieldFormMessage')
const serviceFieldsTableBody = document.getElementById('serviceFieldsTableBody')
const adminTabs = new bootstrap.Tab(document.getElementById('nav-tab'))
const toggleCancelReservations = document.getElementById('toggleCancelReservations')
const cancelReservationsPanel = document.getElementById('cancelReservationsPanel')
const cancelDateInput = document.getElementById('cancelDate')
const cancelFieldSelect = document.getElementById('cancelField')
const cancelFieldHint = document.getElementById('cancelFieldHint')
const confirmCancelReservationsButton = document.getElementById('confirmCancelReservations')
const cancelEditCancelReservationButton = document.getElementById('cancelEditCancelReservation')
const cancelReservationsResult = document.getElementById('cancelReservationsResult')
const existingClosures = document.getElementById('existingClosures')
const closuresTabDateRangeSelect = document.getElementById('closuresTabDateRange')
const closuresTabDateFromInput = document.getElementById('closuresTabDateFrom')
const closuresTabDateToInput = document.getElementById('closuresTabDateTo')
const closuresTabFieldSelect = document.getElementById('closuresTabField')
const closuresTabList = document.getElementById('closuresTabList')
const configPanel = document.getElementById('configPanel')
const closureTextConfig = document.getElementById('closureTextConfig')
const bookingEmailConfig = document.getElementById('bookingEmailConfig')
let idBooking
let editingCancelReservationId = null

function formatPriceAR(value) {
    return '$ ' + new Intl.NumberFormat('es-AR', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    }).format(Number(value || 0))
}

function minutesToHuman(minutes) {
    const total = Math.max(0, Number(minutes || 0))
    const hours = Math.floor(total / 60)
    const remainder = total % 60
    const parts = []
    if (hours > 0) parts.push(`${hours} hs`)
    if (remainder > 0) parts.push(`${remainder} min`)
    return `${total} min (${parts.length ? parts.join(' ') : '0 min'})`
}

function minutesToAdminHuman(totalMinutes) {
    const total = Math.max(0, Number(totalMinutes || 0))
    const hours = Math.floor(total / 60)
    const remainder = total % 60
    if (hours > 0 && remainder > 0) return `${hours} hs ${remainder} min`
    if (hours > 0) return `${hours} hs`
    return `${remainder} min`
}

function updateServiceConfigForm(form) {
    const hours = Number(form.querySelector('.service-duration-hours')?.value || 0)
    const minutes = Number(form.querySelector('.service-duration-minutes')?.value || 0)
    const total = Math.max(0, (hours * 60) + minutes)
    const durationPreview = form.querySelector('.service-duration-preview')
    if (durationPreview) {
        durationPreview.textContent = `${total} min (${minutesToAdminHuman(total)})`
    }

    const offerToggle = form.querySelector('.service-offer-toggle')
    const offerFields = form.querySelector('.service-offer-fields')
    const offerEnabled = Boolean(offerToggle?.checked)
    offerFields?.classList.toggle('d-none', !offerEnabled)
    offerFields?.querySelectorAll('input, select, textarea').forEach(input => {
        input.disabled = !offerEnabled
    })

    const preview = form.querySelector('.service-offer-preview')
    if (!preview) return
    const text = form.querySelector('.service-offer-text')?.value?.trim() || 'Oferta'
    const type = form.querySelector('.service-discount-type:checked')?.value || 'percentage'
    const rawValue = String(form.querySelector('.service-discount-value')?.value || '0').replace(/\./g, '').replace(',', '.')
    const value = Number(rawValue) || 0
    const fixedValue = formatPriceAR(value)
    const discount = type === 'fixed' ? `${fixedValue} de descuento` : `${value}% OFF`
    preview.textContent = offerEnabled && value > 0 ? `Se mostrará como: ${text} - ${discount}` : 'Se mostrará como: -'
}

function getLocalDateISO(date = new Date()) {
    const d = new Date(date)
    const year = d.getFullYear()
    const month = String(d.getMonth() + 1).padStart(2, '0')
    const day = String(d.getDate()).padStart(2, '0')
    return `${year}-${month}-${day}`
}

const fieldServiceLabels = {
    football: 'Cancha / Fútbol',
    padel: 'Pádel',
    quincho: 'Quincho',
    eventos: 'Eventos / Confitería',
}

document.querySelectorAll('#createServiceType option').forEach((option) => {
    fieldServiceLabels[option.value] = option.textContent
})

function initServiceConfigForms(scope = document) {
    scope.querySelectorAll('.service-config-form').forEach(updateServiceConfigForm)
}

initServiceConfigForms()

function getServiceTypeOptions() {
    return Array.from(document.querySelectorAll('[name="createServiceTypeOption"]')).map(option => ({
        value: option.value,
        label: fieldServiceLabels[option.value] || option.value,
        duration: option.dataset.durationMinutes || 60,
    }))
}

function escapeHtml(value) {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;')
}

function showFieldFormMessage(type, message) {
    if (!fieldFormMessage) return
    fieldFormMessage.className = `alert alert-${type}`
    fieldFormMessage.textContent = message
    fieldFormMessage.classList.remove('d-none')
}

function clearFieldFormMessage() {
    if (!fieldFormMessage) return
    fieldFormMessage.textContent = ''
    fieldFormMessage.className = 'alert d-none'
}

function setFieldModalMode(mode) {
    clearFieldFormMessage()
    if (fieldFormModalTitle) {
        fieldFormModalTitle.textContent = mode === 'create' ? 'Crear precio' : 'Editar precio'
    }
    enterFieldsForm?.classList.toggle('d-none', mode !== 'create')
    selectEditField?.classList.toggle('d-none', mode !== 'selectEdit')
    if (mode !== 'edit' && editFieldDiv) {
        editFieldDiv.innerHTML = ''
    }
    if (mode === 'create') {
        const form = enterFieldsForm?.querySelector('form')
        form?.reset()
        const serviceSelect = document.getElementById('createServiceType')
        const blockMinutes = document.getElementById('createBlockMinutes')
        const firstOption = document.querySelector('[name="createServiceTypeOption"]')
        if (serviceSelect && firstOption) serviceSelect.value = firstOption.value
        if (blockMinutes && firstOption) blockMinutes.value = firstOption.dataset.durationMinutes || 60
        if (firstOption) firstOption.checked = true
    }
}

function renderServiceFieldRow(field) {
    const serviceType = field.service_type || 'football'
    const disabled = String(field.disabled || '0') === '1'
    return `
        <tr data-field-row="${escapeHtml(field.id)}">
            <td>${escapeHtml(field.name)}</td>
            <td>${escapeHtml(fieldServiceLabels[serviceType] || serviceType)}</td>
            <td>${escapeHtml(minutesToHuman(field.duration_minutes || field.block_minutes || 60))}</td>
            <td>${formatPriceAR(field.value || 0)}</td>
            <td>${formatPriceAR(field.ilumination_value || 0)}</td>
            <td>
                <span class="badge ${disabled ? 'bg-secondary' : 'bg-success'}">
                    ${disabled ? 'Deshabilitado' : 'Activo'}
                </span>
            </td>
            <td class="text-end">
                <button type="button" class="btn btn-sm btn-outline-primary field-edit-row" data-field-id="${escapeHtml(field.id)}">
                    <i class="fa-solid fa-pen-to-square me-1"></i>Editar
                </button>
            </td>
        </tr>`
}

function upsertServiceFieldRow(field) {
    if (!serviceFieldsTableBody || !field?.id) return
    const emptyRow = document.getElementById('serviceFieldsEmptyRow')
    emptyRow?.remove()
    const existing = Array.from(serviceFieldsTableBody.querySelectorAll('[data-field-row]'))
        .find(row => String(row.dataset.fieldRow) === String(field.id))
    if (existing) {
        existing.outerHTML = renderServiceFieldRow(field)
        return
    }
    serviceFieldsTableBody.insertAdjacentHTML('beforeend', renderServiceFieldRow(field))
}

function upsertServiceFieldSelectOption(field) {
    if (!selectEditFields || !field?.id) return
    let option = Array.from(selectEditFields.options).find(opt => String(opt.value) === String(field.id))
    if (!option) {
        option = new Option(field.name, field.id)
        selectEditFields.appendChild(option)
    } else {
        option.textContent = field.name
    }
}

if (adminTabs && adminTabs._element) {
    adminTabs._element.addEventListener("shown.bs.tab", (e) => {
        if (enterFieldsForm) enterFieldsForm.classList.add('d-none')
        if (selectEditField) selectEditField.classList.add('d-none')
    })
}

if (fieldFormModalElement) {
    fieldFormModalElement.addEventListener('hidden.bs.modal', () => {
        clearFieldFormMessage()
        if (editFieldDiv) editFieldDiv.innerHTML = ''
        if (selectEditFields) selectEditFields.value = ''
    })
}

if (cancelDateInput) {
    const today = getLocalDateISO()
    cancelDateInput.value = today
    cancelDateInput.min = today
    if (cancelFieldSelect) {
        refreshExistingClosures()
        refreshCancelFieldAllAvailability()
    }
}
if (closuresTabDateRangeSelect && closuresTabDateFromInput && closuresTabDateToInput) {
    applyClosuresDateRange(closuresTabDateRangeSelect.value || 'MA')
}

if (selectEditField) {
    selectEditField.addEventListener('change', async (e) => {
        if (selectEditFields?.value) {
            clearFieldFormMessage()
            await getEditField(selectEditFields.value)
            selectEditField.classList.add('d-none')
        } else if (editFieldDiv) {
            editFieldDiv.innerHTML = ''
        }
    })
}

document.addEventListener('change', (e) => {
    const serviceForm = e.target.closest('.service-config-form')
    if (serviceForm) {
        updateServiceConfigForm(serviceForm)
    }

    const serviceTypeOption = e.target.closest('[data-service-select]')
    if (!serviceTypeOption) return

    const select = document.querySelector(serviceTypeOption.dataset.serviceSelect)
    const blockMinutes = document.querySelector(serviceTypeOption.dataset.blockMinutesTarget)
    if (select) {
        select.value = serviceTypeOption.value
    }
    if (blockMinutes) {
        blockMinutes.value = serviceTypeOption.dataset.durationMinutes || 60
    }
})

document.addEventListener('input', (e) => {
    const serviceForm = e.target.closest('.service-config-form')
    if (serviceForm) {
        updateServiceConfigForm(serviceForm)
    }
})

async function refreshServicesPanelFromServer() {
    const currentPanel = document.getElementById('services-panel')
    if (!currentPanel) return

    const response = await fetch(`${webBaseUrl}abmAdmin`, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
    })
    const html = await response.text()
    const doc = new DOMParser().parseFromString(html, 'text/html')
    const freshPanel = doc.getElementById('services-panel')
    if (!freshPanel) return

    currentPanel.innerHTML = freshPanel.innerHTML
    initServiceConfigForms(currentPanel)
    updateServicesRatesNewButton()
}

document.addEventListener('submit', async (e) => {
    const serviceForm = e.target.closest('.service-config-form')
    if (serviceForm) {
        e.preventDefault()
        const submitButton = serviceForm.querySelector('[type="submit"]')
        const originalText = submitButton ? submitButton.textContent : ''
        if (submitButton) {
            submitButton.disabled = true
            submitButton.textContent = 'Guardando...'
        }

        try {
            const response = await fetch(serviceForm.action, {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'text/html,application/json',
                },
                body: new FormData(serviceForm),
            })
            if (!response.ok) {
                throw new Error('No se pudo guardar el servicio.')
            }
            await refreshServicesPanelFromServer()
        } catch (error) {
            console.error('Error:', error)
            alert('No se pudo guardar el servicio. Intenta nuevamente.')
        } finally {
            if (submitButton) {
                submitButton.disabled = false
                submitButton.textContent = originalText
            }
        }
        return
    }

    const form = e.target.closest('.field-ajax-form')
    if (!form) return

    e.preventDefault()
    clearFieldFormMessage()

    const submitButton = form.querySelector('[type="submit"]')
    const originalText = submitButton ? submitButton.textContent : ''
    if (submitButton) {
        submitButton.disabled = true
        submitButton.textContent = 'Guardando...'
    }

    try {
        const response = await fetch(form.action, {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
            },
            body: new FormData(form),
        })
        const result = await response.json()
        if (!response.ok || result.error) {
            showFieldFormMessage('danger', result.message || 'No se pudo guardar el servicio.')
            return
        }
        if (result.data) {
            upsertServiceFieldRow(result.data)
            upsertServiceFieldSelectOption(result.data)
        }
        fieldFormModal?.hide()
        form.reset()
        if (editFieldDiv) editFieldDiv.innerHTML = ''
    } catch (error) {
        console.error('Error:', error)
        showFieldFormMessage('danger', 'No se pudo guardar el servicio. Intenta nuevamente.')
    } finally {
        if (submitButton) {
            submitButton.disabled = false
            submitButton.textContent = originalText
        }
    }
})


function updateServicesRatesNewButton() {
    const button = document.getElementById('buttonCreateField')
    if (!button) return
    const servicesPanelIsActive = document.getElementById('services-panel')?.classList.contains('active')
    button.innerHTML = servicesPanelIsActive
        ? '<i class="fa-solid fa-plus me-1"></i>Nuevo servicio'
        : '<i class="fa-solid fa-plus me-1"></i>Nuevo precio'
}

document.querySelectorAll('#servicesRatesTabs [data-bs-toggle="tab"]').forEach(tab => {
    tab.addEventListener('shown.bs.tab', updateServicesRatesNewButton)
})
updateServicesRatesNewButton()

document.addEventListener('click', async (e) => {
    if (e.target) {
        const serviceEditAction = e.target.closest('.service-edit-row')
        if (serviceEditAction) {
            const panel = document.getElementById(`serviceEditPanel${serviceEditAction.dataset.serviceId}`)
            if (!panel) return
            document.querySelectorAll('.service-edit-panel').forEach(row => {
                if (row !== panel) row.classList.add('d-none')
            })
            panel.classList.toggle('d-none')
            if (!panel.classList.contains('d-none')) {
                panel.scrollIntoView({ behavior: 'smooth', block: 'center' })
            }
            return
        }

        const fieldAction = e.target.closest('#buttonCreateField, .field-edit-row')
        if (fieldAction?.id == 'buttonCreateField') {
            const servicesPanelIsActive = document.getElementById('services-panel')?.classList.contains('active')
            if (servicesPanelIsActive) {
                const currentNewServicePanel = document.getElementById('newServicePanel')
                if (!currentNewServicePanel) return
                currentNewServicePanel.classList.toggle('d-none')
                document.querySelectorAll('.service-edit-panel').forEach(row => row.classList.add('d-none'))
                if (!currentNewServicePanel.classList.contains('d-none')) {
                    currentNewServicePanel.scrollIntoView({ behavior: 'smooth', block: 'center' })
                }
                return
            }
            setFieldModalMode('create')
            fieldFormModal?.show()
            return
        }
        if (fieldAction?.classList.contains('field-edit-row')) {
            const fieldId = fieldAction.dataset.fieldId
            if (!fieldId) return
            setFieldModalMode('edit')
            selectEditField?.classList.add('d-none')
            if (selectEditFields) selectEditFields.value = fieldId
            await getEditField(fieldId)
            fieldFormModal?.show()
            return
        }

        if (e.target.id == 'botonCompletarPago') {

            const idUser = document.getElementById('userId').dataset.id
            const botonPagar = document.getElementById('botonCompletarPago')
            const bookingId = botonPagar.dataset.id
            const booking = await getBooking(bookingId)

            if (medioPagoSelect.value == '' || inputCompletarPagoReserva.value == '') {
                return alert('Debe completar todos los campos obligatorios.')
            }

            if (Number(inputCompletarPagoReserva.value) > Number(booking.diference)) {
                return alert('El monto a abonar no puede ser mayor al saldo.')
            }

            let data = {
                pago: inputCompletarPagoReserva.value,
                idUser: idUser,
                medioPago: medioPagoSelect.value,
                idCustomer: booking.id_customer,
            }

            completePayment(`${baseUrl}completePayment/${bookingId}`, data)

        } else if (e.target.id == 'saveRate') {

            let data = {
                value: inputRate.value,
            }

            saveRate(`${baseUrl}saveRate`, data)

        } else if (e.target.id == 'saveOfferRate') {

            let data = {
                value: inputOfferRate.value,
                description: descriptionOffer.value
            }

            saveOfferRate(`${baseUrl}saveOfferRate`, data)

        } else if (e.target.id == 'modalCompletarPago') {

            const bookingId = e.target.dataset.id
            const botonPagar = document.getElementById('botonCompletarPago')
            const booking = await getBooking(bookingId)
            botonPagar.setAttribute('data-id', bookingId)

            completarPagoModalB.show()
            inputCompletarPagoReserva.value = booking.diference

        } else if (e.target.id == 'eliminarReservaModal') {
            idBooking = e.target.dataset.id

            cancelBookingModal.show()
        } else if (e.target.id == 'cancelCancelBooking') {
            cancelBookingModal.hide()

        } else if (e.target.id == 'confirmCancelBooking') {
            let dataCancel = {
                idBooking: idBooking
            }

            cancelBooking(dataCancel)
        } else if (e.target.id == 'editarReservaModal') {

            editBookingModal.show()
        } else if (e.target.id == 'toggleCancelReservations') {
            if (cancelReservationsPanel) {
                cancelReservationsPanel.classList.toggle('d-none')
                if (!cancelReservationsPanel.classList.contains('d-none')) {
                    await refreshExistingClosures()
                    await refreshCancelFieldAllAvailability()
                    cancelReservationsPanel.scrollIntoView({ behavior: 'smooth', block: 'start' })
                }
            }
        } else if (e.target.id == 'toggleConfigPanel') {
            if (configPanel) {
                configPanel.classList.toggle('d-none')
            }
        } else if (e.target.id == 'closuresTabSearch' || e.target.id == 'cancel-closures-list-tab') {
            await refreshClosuresTab()
        } else if (e.target.id == 'closeCancelReservations') {
            if (cancelReservationsPanel) {
                cancelReservationsPanel.classList.add('d-none')
            }
            resetCancelReservationEditMode()
        } else if (e.target.id == 'closeConfigPanel') {
            if (configPanel) {
                configPanel.classList.add('d-none')
            }
        } else if (e.target.id == 'cancelEditCancelReservation') {
            resetCancelReservationEditMode()
        } else if (e.target.id == 'confirmCancelReservations') {
            if (!cancelDateInput || !cancelFieldSelect) return
            const payload = {
                fecha: cancelDateInput.value,
                cancha: cancelFieldSelect.value,
            }
            const today = getLocalDateISO()
            if (payload.fecha < today) {
                alert('No se pueden informar cierres con fecha anterior a hoy.')
                return
            }
            const force = e.target.dataset.force === '1'
            if (editingCancelReservationId) {
                const updated = await updateCancelReservation({
                    id: editingCancelReservationId,
                    fecha: payload.fecha,
                    cancha: payload.cancha,
                })
                if (updated) {
                    resetCancelReservationEditMode()
                    await refreshExistingClosures()
                    await refreshClosuresTab()
                }
                return
            }
            if (!force) {
                const result = await checkCancelReservations(payload)
                if (!result) return
                const bookings = result.bookings || []
                if (bookings.length === 0) {
                    const saved = await saveCancelReservations(payload)
                    if (saved) {
                        await refreshExistingClosures()
                        await refreshClosuresTab()
                    }
                } else {
                    renderCancelReservationsResult(result)
                }
                return
            }
            const saved = await saveCancelReservations(payload)
            if (saved) {
                await refreshExistingClosures()
                await refreshClosuresTab()
            }
        } else if (e.target.id == 'saveConfigGeneral') {
            const payload = {
                textoCierre: closureTextConfig ? closureTextConfig.value : '',
                emailReservas: bookingEmailConfig ? bookingEmailConfig.value : '',
            }
            await saveConfigGeneral(payload)
            if (configPanel) {
                configPanel.classList.add('d-none')
            }
        } else if (e.target.id == 'cancelCancelReservations') {
            if (cancelReservationsResult) {
                cancelReservationsResult.innerHTML = ''
            }
        }
        const deleteBtn = e.target.closest('.delete-closure')
        if (deleteBtn) {
            const id = deleteBtn.dataset.id
            if (!id) return
            await deleteCancelReservation({ id })
            await refreshExistingClosures()
            await refreshClosuresTab()
        }
        const editBtn = e.target.closest('.edit-closure')
        if (editBtn) {
            const id = editBtn.dataset.id
            const fecha = editBtn.dataset.fecha
            const cancha = editBtn.dataset.cancha
            if (!id || !fecha || !cancha) return
            editingCancelReservationId = id
            if (cancelDateInput) cancelDateInput.value = fecha
            if (cancelFieldSelect) cancelFieldSelect.value = cancha
            if (confirmCancelReservationsButton) confirmCancelReservationsButton.textContent = 'Guardar cambios'
            if (cancelEditCancelReservationButton) cancelEditCancelReservationButton.classList.remove('d-none')
            await refreshCancelFieldAllAvailability()
            cancelReservationsPanel?.scrollIntoView({ behavior: 'smooth', block: 'start' })
        }
    }
})

if (cancelDateInput) {
    cancelDateInput.addEventListener('change', async () => {
        await refreshExistingClosures()
        await refreshCancelFieldAllAvailability()
    })
}
if (cancelFieldSelect) {
    cancelFieldSelect.addEventListener('change', async () => {
        await refreshExistingClosures()
        await refreshCancelFieldAllAvailability()
    })
}
if (closuresTabDateRangeSelect) {
    closuresTabDateRangeSelect.addEventListener('input', () => {
        applyClosuresDateRange(closuresTabDateRangeSelect.value)
        refreshClosuresTab()
    })
}
if (closuresTabDateFromInput) {
    closuresTabDateFromInput.addEventListener('change', refreshClosuresTab)
}
if (closuresTabDateToInput) {
    closuresTabDateToInput.addEventListener('change', refreshClosuresTab)
}
if (closuresTabFieldSelect) {
    closuresTabFieldSelect.addEventListener('change', refreshClosuresTab)
}

async function editBooking(data) {
    try {
        const response = await fetch(`${baseUrl}editBooking`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(data)
        });

        if (response.ok) {
            alert('Reserva actualizada correctamente.')

        } else {
            alert('No se pudo completar la operacion. Intenta nuevamente.');
        }

    } catch (error) {
        console.error('Error:', error);
        throw error;
    }
}


async function cancelBooking(data) {
    try {
        const response = await fetch(`${baseUrl}cancelBooking`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(data)
        });

        if (response.ok) {
            alert('Reserva anulada correctamente.')

            cancelBookingModal.hide()
            location.reload(true)

        } else {
            alert('No se pudo completar la operacion. Intenta nuevamente.');
        }

    } catch (error) {
        console.error('Error:', error);
        throw error;
    }
}

async function checkCancelReservations(data) {
    try {
        const response = await fetch(`${baseUrl}checkCancelReservations`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(data)
        });

        const responseData = await response.json();
        if (responseData.error) {
            alert(responseData.message || 'No se pudo completar la operacion. Intenta nuevamente.')
            return null
        }
        return responseData.data
    } catch (error) {
        console.error('Error:', error);
        throw error;
    }
}

async function saveCancelReservations(data) {
    try {
        const response = await fetch(`${baseUrl}saveCancelReservations`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(data)
        });

        const responseData = await response.json();
        if (!response.ok || responseData.error) {
            alert(responseData.message || 'No se pudo completar la operacion. Intenta nuevamente.')
            return false
        }

        alert('Cierre de cancha informado correctamente.')
        if (cancelReservationsResult) {
            cancelReservationsResult.innerHTML = ''
        }
        return true
    } catch (error) {
        console.error('Error:', error);
        throw error;
    }
}

async function getCancelReservations(data) {
    try {
        const response = await fetch(`${baseUrl}getCancelReservations`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(data)
        });

        const responseData = await response.json();
        if (responseData.error) {
            return []
        }
        return responseData.data || []
    } catch (error) {
        console.error('Error:', error);
        return []
    }
}

async function deleteCancelReservation(data) {
    try {
        const response = await fetch(`${baseUrl}deleteCancelReservation`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(data)
        });

        const responseData = await response.json();
        if (!response.ok || responseData.error) {
            alert(responseData.message || 'No se pudo completar la operacion. Intenta nuevamente.')
            return
        }
    } catch (error) {
        console.error('Error:', error);
    }
}

async function updateCancelReservation(data) {
    try {
        const response = await fetch(`${baseUrl}updateCancelReservation`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(data)
        });

        const responseData = await response.json();
        if (!response.ok || responseData.error) {
            alert(responseData.message || 'No se pudo completar la operacion. Intenta nuevamente.')
            return false
        }
        alert('Cierre actualizado correctamente.')
        return true
    } catch (error) {
        console.error('Error:', error);
        return false
    }
}

async function refreshExistingClosures() {
    if (!cancelDateInput || !existingClosures) return
    const payload = {
        fecha: cancelDateInput.value,
        cancha: 'all',
    }
    const rows = await getCancelReservations(payload)
    if (!rows || rows.length === 0) {
        existingClosures.innerHTML = ''
        return
    }
    const formatDate = (dateStr) => {
        if (!dateStr || typeof dateStr !== 'string' || !dateStr.includes('-')) return dateStr || ''
        const [y, m, d] = dateStr.split('-')
        if (!y || !m || !d) return dateStr
        return `${d}/${m}/${y}`
    }
    const isPastDate = (dateStr) => {
        if (!dateStr) return false
        const today = new Date()
        today.setHours(0, 0, 0, 0)
        const d = new Date(`${dateStr}T00:00:00`)
        return d < today
    }
    existingClosures.innerHTML = `
        <div class="alert alert-info mb-2">Cierres informados para esta fecha</div>
        <ul class="list-group">
            ${rows.map(r => `
                <li class="list-group-item d-flex justify-content-between align-items-center">
                    <span>${formatDate(r.cancel_date)} - ${r.field_label} (por ${r.user_name})</span>
                    ${isPastDate(r.cancel_date)
            ? '<span class="badge text-bg-secondary">Sin acciones</span>'
            : `<div class="d-flex gap-2">
                    <button type="button" class="btn btn-sm btn-outline-primary edit-closure" data-id="${r.id}" data-fecha="${r.cancel_date}" data-cancha="${r.field_id ?? 'all'}">Editar</button>
                    <button type="button" class="btn btn-sm btn-outline-danger delete-closure" data-id="${r.id}">Cancelar</button>
               </div>`}
                </li>
            `).join('')}
        </ul>
    `
}

async function refreshClosuresTab() {
    if (!closuresTabList) return

    const payload = {
        fechaDesde: closuresTabDateFromInput ? closuresTabDateFromInput.value : '',
        fechaHasta: closuresTabDateToInput ? closuresTabDateToInput.value : '',
        cancha: closuresTabFieldSelect ? closuresTabFieldSelect.value : 'all',
    }

    const rows = await getCancelReservations(payload)
    if (!rows || rows.length === 0) {
        closuresTabList.innerHTML = '<div class="alert alert-light border">No hay cierres programados para el filtro seleccionado.</div>'
        return
    }

    const today = new Date()
    today.setHours(0, 0, 0, 0)
    const parseDate = (dateStr) => {
        if (!dateStr) return null
        const d = new Date(`${dateStr}T00:00:00`)
        return Number.isNaN(d.getTime()) ? null : d
    }
    const orderedRows = [...rows].sort((a, b) => {
        const dateA = parseDate(a.cancel_date)
        const dateB = parseDate(b.cancel_date)
        if (!dateA && !dateB) return 0
        if (!dateA) return 1
        if (!dateB) return -1

        const aIsPast = dateA < today
        const bIsPast = dateB < today
        if (aIsPast !== bIsPast) {
            return aIsPast ? 1 : -1
        }

        if (!aIsPast) {
            // Hoy y futuras: de la mas cercana a la mas lejana.
            return dateA - dateB
        }

        // Pasadas al final: de la mas reciente a la mas antigua.
        return dateB - dateA
    })

    const formatDate = (dateStr) => {
        if (!dateStr || typeof dateStr !== 'string' || !dateStr.includes('-')) return dateStr || ''
        const [y, m, d] = dateStr.split('-')
        if (!y || !m || !d) return dateStr
        return `${d}/${m}/${y}`
    }
    const isPastDate = (dateStr) => {
        if (!dateStr) return false
        const today = new Date()
        today.setHours(0, 0, 0, 0)
        const d = new Date(`${dateStr}T00:00:00`)
        return d < today
    }

    const groups = orderedRows.reduce((acc, row) => {
        const key = row.cancel_date || ''
        if (!acc[key]) acc[key] = []
        acc[key].push(row)
        return acc
    }, {})
    const groupDates = Object.keys(groups)

    closuresTabList.innerHTML = `
        <div class="accordion" id="closuresByDateAccordion">
            ${groupDates.map((date, index) => {
            const groupRows = groups[date]
            const uniqueFields = [...new Set(groupRows.map(r => r.field_label).filter(Boolean))]
            const fieldsText = uniqueFields.join(', ')
            const collapseId = `closureDateCollapse${index}`
            const headingId = `closureDateHeading${index}`
            const isPast = isPastDate(date)
            return `
                    <div class="accordion-item">
                        <h2 class="accordion-header" id="${headingId}">
                            <button class="accordion-button ${index === 0 ? '' : 'collapsed'}" type="button" data-bs-toggle="collapse" data-bs-target="#${collapseId}" aria-expanded="${index === 0 ? 'true' : 'false'}" aria-controls="${collapseId}">
                                <div class="d-flex w-100 justify-content-between align-items-center pe-2">
                                    <span><strong>${formatDate(date)}</strong> - ${fieldsText || 'Sin cancha'}</span>
                                    <span class="badge text-bg-secondary">${groupRows.length} cierre(s)</span>
                                </div>
                            </button>
                        </h2>
                        <div id="${collapseId}" class="accordion-collapse collapse ${index === 0 ? 'show' : ''}" aria-labelledby="${headingId}" data-bs-parent="#closuresByDateAccordion">
                            <div class="accordion-body p-0">
                                <div class="table-responsive">
                                    <table class="table align-middle table-striped mb-0">
                                        <thead>
                                            <tr>
                                                <th>Cancha</th>
                                                <th>Informado por</th>
                                                <th>Accion</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            ${groupRows.map(r => `
                                                <tr>
                                                    <td>${r.field_label}</td>
                                                    <td>${r.user_name}</td>
                                                    <td>
                                                        ${isPast
                    ? '<span class="badge text-bg-secondary">Sin acciones</span>'
                    : `<div class="d-flex gap-2">
                                                                <button type="button" class="btn btn-sm btn-outline-primary edit-closure" data-id="${r.id}" data-fecha="${r.cancel_date}" data-cancha="${r.field_id ?? 'all'}">Editar</button>
                                                                <button type="button" class="btn btn-sm btn-outline-danger delete-closure" data-id="${r.id}">Cancelar</button>
                                                           </div>`}
                                                    </td>
                                                </tr>
                                            `).join('')}
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                `
        }).join('')}
        </div>
    `
}

function resetCancelReservationEditMode() {
    editingCancelReservationId = null
    if (confirmCancelReservationsButton) {
        confirmCancelReservationsButton.textContent = 'Aceptar'
        delete confirmCancelReservationsButton.dataset.force
    }
    if (cancelEditCancelReservationButton) {
        cancelEditCancelReservationButton.classList.add('d-none')
    }
    refreshCancelFieldAllAvailability()
}

async function refreshCancelFieldAllAvailability() {
    if (!cancelDateInput || !cancelFieldSelect) return
    const allOption = cancelFieldSelect.querySelector('option[value="all"]')
    if (!allOption) return

    const selectedDate = cancelDateInput.value
    if (!selectedDate) {
        allOption.disabled = false
        if (cancelFieldHint) cancelFieldHint.textContent = ''
        return
    }

    const rows = await getCancelReservations({
        fecha: selectedDate,
        cancha: 'all',
    })

    let allowAll = true
    let hint = ''

    if (editingCancelReservationId) {
        if (rows.length > 0) {
            const firstId = rows.reduce((min, r) => {
                const rowId = Number(r.id || 0)
                return min === null || rowId < min ? rowId : min
            }, null)
            allowAll = firstId === null || Number(editingCancelReservationId) === firstId
            if (!allowAll) {
                hint = 'Solo el primer cierre de la fecha puede cambiarse a "Todas".'
            }
        }
    } else {
        if (rows.length > 0) {
            allowAll = false
            const hasAllClosure = rows.some(r => r.field_id === null || r.field_id === '' || String(r.field_id).toLowerCase() === 'all')
            if (hasAllClosure) {
                hint = 'Ya existe un cierre para "Todas" en esta fecha.'
            } else {
                hint = 'Ya existen cierres puntuales en esta fecha. Edite el primer registro si desea pasar a "Todas".'
            }
        }
    }

    allOption.disabled = !allowAll
    if (!allowAll && cancelFieldSelect.value === 'all') {
        const firstEnabledOption = Array.from(cancelFieldSelect.options).find(opt => !opt.disabled && opt.value !== 'all')
        if (firstEnabledOption) {
            cancelFieldSelect.value = firstEnabledOption.value
        }
    }
    if (cancelFieldHint) {
        cancelFieldHint.textContent = hint
    }
}

function applyClosuresDateRange(rangeValue) {
    if (!closuresTabDateFromInput || !closuresTabDateToInput) return

    const fechaActual = new Date()
    const toISO = (d) => getLocalDateISO(d)

    if (rangeValue === 'FD') {
        const hoy = toISO(fechaActual)
        closuresTabDateFromInput.value = hoy
        closuresTabDateToInput.value = hoy
        return
    }

    if (rangeValue === 'MA') {
        const primerDiaDelMes = new Date(fechaActual.getFullYear(), fechaActual.getMonth(), 1)
        const ultimoDiaDelMes = new Date(fechaActual.getFullYear(), fechaActual.getMonth() + 1, 0)
        closuresTabDateFromInput.value = toISO(primerDiaDelMes)
        closuresTabDateToInput.value = toISO(ultimoDiaDelMes)
        return
    }

    if (rangeValue === 'MP') {
        const fechaMesPasado = new Date(fechaActual)
        fechaMesPasado.setMonth(fechaMesPasado.getMonth() - 1)
        const primerDiaDelMesPasado = new Date(fechaMesPasado.getFullYear(), fechaMesPasado.getMonth(), 1)
        const ultimoDiaDelMesPasado = new Date(fechaMesPasado.getFullYear(), fechaMesPasado.getMonth() + 1, 0)
        closuresTabDateFromInput.value = toISO(primerDiaDelMesPasado)
        closuresTabDateToInput.value = toISO(ultimoDiaDelMesPasado)
        return
    }

    if (rangeValue === 'SA') {
        const fechaInicioSemanaActual = new Date(fechaActual)
        const diaSemanaActual = fechaActual.getDay()
        fechaInicioSemanaActual.setDate(fechaActual.getDate() - diaSemanaActual + 1)
        const fechaFinSemanaActual = new Date(fechaInicioSemanaActual)
        fechaFinSemanaActual.setDate(fechaInicioSemanaActual.getDate() + 6)
        closuresTabDateFromInput.value = toISO(fechaInicioSemanaActual)
        closuresTabDateToInput.value = toISO(fechaFinSemanaActual)
        return
    }

    if (rangeValue === 'SP') {
        const fechaInicioSemanaPasada = new Date(fechaActual)
        const diaSemanaActual = fechaActual.getDay()
        fechaInicioSemanaPasada.setDate(fechaActual.getDate() - diaSemanaActual - 6)
        const fechaFinSemanaPasada = new Date(fechaInicioSemanaPasada)
        fechaFinSemanaPasada.setDate(fechaInicioSemanaPasada.getDate() + 6)
        closuresTabDateFromInput.value = toISO(fechaInicioSemanaPasada)
        closuresTabDateToInput.value = toISO(fechaFinSemanaPasada)
    }
}

async function saveConfigGeneral(data) {
    try {
        const response = await fetch(`${baseUrl}saveConfigGeneral`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(data)
        });

        const responseData = await response.json();
        if (!response.ok || responseData.error) {
            alert(responseData.message || 'No se pudo completar la operacion. Intenta nuevamente.')
            return
        }

        alert('Configuracion guardada correctamente.')
    } catch (error) {
        console.error('Error:', error);
        throw error;
    }
}

function renderCancelReservationsResult(result, payload) {
    if (!cancelReservationsResult) return
    if (!result) {
        cancelReservationsResult.innerHTML = ''
        return
    }

    const bookings = result.bookings || []
    let html = ''
    const formatDate = (dateStr) => {
        if (!dateStr || typeof dateStr !== 'string' || !dateStr.includes('-')) return dateStr || ''
        const [y, m, d] = dateStr.split('-')
        if (!y || !m || !d) return dateStr
        return `${d}/${m}/${y}`
    }
    const fechaLabel = formatDate(result.fecha)

    if (bookings.length > 0) {
        html += `
            <div class="alert alert-warning">
                Se encontraron reservas para ${fechaLabel} (${result.canchaLabel}). Revisa antes de informar el cierre.
            </div>
            <div class="table-responsive">
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>Nombre</th>
                            <th>Telefono</th>
                            <th>Cancha</th>
                            <th>Horario</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${bookings.map(b => `
                            <tr>
                                <td>${b.nombre}</td>
                                <td>${b.telefono}</td>
                                <td>${b.cancha}</td>
                                <td>${b.horario}</td>
                            </tr>
                        `).join('')}
                    </tbody>
                </table>
            </div>
        `
    } else {
        html += `
            <div class="alert alert-success">
                No hay reservas para ${fechaLabel} (${result.canchaLabel}).
            </div>
        `
    }

    html += `
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-primary" id="confirmCancelReservations" data-force="1">Aceptar igual</button>
            <button type="button" class="btn btn-secondary" id="cancelCancelReservations">Cancelar</button>
        </div>
    `

    cancelReservationsResult.innerHTML = html
}

async function completePayment(url, data) {
    try {
        const response = await fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(data)
        });

        if (response.ok) {
            if (response.ok) {

                setTimeout(() => { spinnerCompletarPago.show() }, 500)

                completarPagoModalB.hide()

                contentPaymentResult.innerHTML = `
                <div class="modal-body modalResultPayment d-flex justify-content-center align-items-center flex-column" style="background-color: #157347;">
                    <h4 class="mb-5">Pago confirmado!</h4>
                    <i class="fa-regular fa-circle-check fa-2xl" style="margin-bottom: 20px;"></i>
                </div>`

                setTimeout(() => { modalResultPayment.show() }, 1000)
                setTimeout(() => { spinnerCompletarPago.hide() }, 1200)
                setTimeout(() => { modalResultPayment.hide() }, 2500)

                if (typeof getActiveBookings === 'function' && typeof bookingData !== 'undefined') {
                    getActiveBookings(bookingData)
                }

            } else {
                setTimeout(() => { spinnerCompletarPago.show() }, 500)
                completarPagoModalB.hide()

                contentPaymentResult.innerHTML = `
                <div class="modal-body modalResultPayment d-flex justify-content-center align-items-center flex-column" style="background-color: #bb2d3b;">
                    <h4 class="mb-5">No se pudo guardar el pago. Vuelva a intentar</h4>
                    <i class="fa-regular fa-circle-xmark fa-2xl" style="margin-bottom: 20px;"></i>
                </div>`

                setTimeout(() => { modalResultPayment.show() }, 2000)
                setTimeout(() => { spinnerCompletarPago.hide() }, 2000)
            }

        } else {
            alert('No se pudo completar la operacion. Intenta nuevamente.');
        }

    } catch (error) {
        console.error('Error:', error);
        throw error;
    }
}

async function saveRate(url, data) {
    try {
        const response = await fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(data)
        });

        if (response.ok) {
            alert('Porcentaje actualizado correctamente.')
            location.reload(true)

        } else {
            alert('No se pudo completar la operacion. Intenta nuevamente.');
        }

    } catch (error) {
        console.error('Error:', error);
        throw error;
    }
}

async function saveOfferRate(url, data) {
    try {
        const response = await fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(data)
        });

        if (response.ok) {
            alert('Oferta actualizada correctamente.')
            location.reload(true)

        } else {
            alert('No se pudo completar la operacion. Intenta nuevamente.');
        }

    } catch (error) {
        console.error('Error:', error);
        throw error;
    }
}


async function getBooking(id) {
    try {
        const response = await fetch(`${baseUrl}getBooking/${id}`);

        const responseData = await response.json();

        if (responseData.data != '') {

            return responseData.data

        } else {
            alert('No se pudo obtener la informacion. Intenta nuevamente.');
        }

    } catch (error) {
        console.error('Error:', error);
        throw error;
    }
}

async function getEditField(id) {
    try {
        clearFieldFormMessage()
        const response = await fetch(`${baseUrl}getField/${id}`);

        const responseData = await response.json();

        if (responseData.data != '') {
            if (fieldFormModalTitle) fieldFormModalTitle.textContent = 'Editar precio'
            fillDiv(responseData.data)

        } else {
            alert('No se pudo obtener la informacion. Intenta nuevamente.');
        }

    } catch (error) {
        console.error('Error:', error);
        throw error;
    }
}

function fillDiv(field) {
    let div = ''

    let disabledCheck = ''
    if (field.disabled == 1) { disabledCheck = 'checked' }
    let roofedCheck = ''
    if (field.roofed == 1) { roofedCheck = 'checked' }
    const serviceType = field.service_type || 'football'
    const blockMinutes = field.duration_minutes || field.block_minutes || 60
    const serviceOptionName = `editServiceTypeOption${field.id}`
    const serviceSelectId = `editServiceType${field.id}`
    const blockMinutesId = `editBlockMinutes${field.id}`
    const serviceOptions = getServiceTypeOptions()
    const serviceSelectOptions = serviceOptions.map(option => `
                        <option value="${escapeHtml(option.value)}" ${serviceType === option.value ? 'selected' : ''}>${escapeHtml(option.label)}</option>`).join('')
    const serviceRadioOptions = serviceOptions.map(option => {
        const optionId = `${serviceSelectId}${String(option.value).replace(/[^A-Za-z0-9]/g, '')}`
        return `
                        <input class="btn-check" type="radio" name="${serviceOptionName}" id="${optionId}" value="${escapeHtml(option.value)}" data-service-select="#${serviceSelectId}" data-block-minutes-target="#${blockMinutesId}" data-duration-minutes="${escapeHtml(option.duration)}" autocomplete="off" ${serviceType === option.value ? 'checked' : ''}>
                        <label class="service-type-option" for="${optionId}">${escapeHtml(option.label)}</label>`
    }).join('')

    div = `
        <div class="editFields" id="editFields">
            <form action="${webBaseUrl}editField/${field.id}" method="POST" class="field-ajax-form">

                <div class="form-check form-switch mt-4">
                    <input class="form-check-input" type="checkbox" role="switch" name="disabled" id="disableField" ${disabledCheck}>
                    <label class="form-check-label" for="disableField">Deshabilitar servicio</label>
                </div>

                <div class="input-group mt-3 mb-3">
                    <span class="input-group-text" id="basic-addon1">Nombre servicio</span>
                    <input type="text" class="form-control" value="${escapeHtml(field.name)}" name="nombre" placeholder="Ingrese el nombre del servicio" aria-label="Nombre servicio" aria-describedby="basic-addon1">
                </div>

                <div class="mb-3">
                    <label class="form-label d-block mb-2">Tipo de reserva</label>
                    <select class="form-select visually-hidden" name="serviceType" id="${serviceSelectId}" aria-hidden="true" tabindex="-1">
${serviceSelectOptions}
                    </select>
                    <div class="service-type-options">
${serviceRadioOptions}
                    </div>
                </div>

                <div class="input-group mb-3">
                    <span class="input-group-text">Duración del bloque</span>
                    <input type="number" class="form-control" id="${blockMinutesId}" value="${blockMinutes}" name="blockMinutes" min="30" step="30" aria-label="Duracion del bloque">
                </div>

                <div class="input-group mb-3">
                    <span class="input-group-text" id="basic-addon3">Medidas</span>
                    <input type="text" class="form-control" value="${escapeHtml(field.sizes || '')}" name="medidas" placeholder="Ingrese las medidas de la cancha" aria-label="Medidas" aria-describedby="basic-addon3">
                </div>

                <div class="input-group mb-3">
                    <span class="input-group-text" id="basic-addon2">Tipo de piso</span>
                    <input type="text" class="form-control" value="${escapeHtml(field.floor_type || '')}" name="tipoPiso" placeholder="Ingrese el tipo de piso de la cancha" aria-label="Tipo piso" aria-describedby="basic-addon2">
                </div>

                <div class="input-group mb-3">
                    <span class="input-group-text" id="basic-addon4">Detalle</span>
                    <input type="text" class="form-control" value="${escapeHtml(field.field_type || '')}" name="tipoCancha" placeholder="Detalle opcional" aria-label="Detalle" aria-describedby="basic-addon4">
                </div>

                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" name="tipoTecho" role="switch" id="tipoTechoEdit${field.id}" ${roofedCheck}>
                    <label class="form-check-label" for="tipoTechoEdit${field.id}">Es techada</label>
                </div>

                <div class="input-group mb-3">
                    <span class="input-group-text">Precio base</span>
                    <input type="text" class="form-control" value="${escapeHtml(field.value || '')}" name="valor" placeholder="Precio por hora o bloque" aria-label="Valor">
                </div>

                <div class="input-group mb-3">
                    <span class="input-group-text">Precio nocturno</span>
                    <input type="text" class="form-control" value="${escapeHtml(field.ilumination_value || '')}" name="valorIluminacion" placeholder="Si no aplica, repetir precio base" aria-label="Valor nocturno">
                </div>

                <button type="submit" class="btn btn-success">Guardar</button>
                <button type="button" class="btn btn-danger" data-bs-dismiss="modal">Cancelar</button>
            </form>
        </div>
        `

    editFieldDiv.innerHTML = div
}

