(function () {
    const form = document.getElementById('customerOfferForm')
    if (!form) return

    const activeSwitch = document.getElementById('customer_offer_active')
    const applyAllFieldsSwitch = document.getElementById('customer_offer_apply_all_fields')
    const applyAllServicesSwitch = document.getElementById('customer_offer_apply_all_services')
    const hiddenFields = document.getElementById('customer_offer_fields_json')
    const hiddenServices = document.getElementById('customer_offer_services_json')
    const previewText = document.getElementById('customerOfferPreviewText')
    const statusBadge = document.getElementById('customerOfferStatusBadge')
    const helperText = document.getElementById('customerOfferHelperText')
    const fieldCheckboxes = Array.from(document.querySelectorAll('.customer-offer-field'))
    const serviceCheckboxes = Array.from(document.querySelectorAll('.customer-offer-service'))
    const valueInput = document.getElementById('customer_offer_value')
    const expirationInput = document.getElementById('customer_offer_expiration_date')

    function parseNumber(value) {
        const normalized = String(value ?? '')
            .replace(/\$/g, '')
            .replace(/\s/g, '')
            .replace(/\./g, '')
            .replace(',', '.')
        const number = Number(normalized)
        return Number.isFinite(number) ? number : 0
    }

    function getSelectedValues(inputs) {
        return inputs
            .filter(input => input.checked)
            .map(input => input.value)
            .filter(Boolean)
    }

    function buildPreviewText() {
        if (!activeSwitch?.checked) {
            return 'Sin descuento activo'
        }

        const value = parseNumber(valueInput?.value || 0)
        const fieldLabel = applyAllFieldsSwitch?.checked
            ? 'Todas las canchas'
            : `${getSelectedValues(fieldCheckboxes).length} cancha${getSelectedValues(fieldCheckboxes).length === 1 ? '' : 's'}`
        const serviceLabel = applyAllServicesSwitch?.checked
            ? 'Todos los servicios'
            : `${getSelectedValues(serviceCheckboxes).length} servicio${getSelectedValues(serviceCheckboxes).length === 1 ? '' : 's'}`
        const scope = [fieldLabel, serviceLabel].filter(Boolean).join(' | ')
        const base = value > 0 ? `${value}% OFF` : 'Oferta sin porcentaje'
        return `${base} | ${scope || 'Sin alcance'}`
    }

    function syncHiddenSelection() {
        if (hiddenFields) {
            hiddenFields.value = JSON.stringify(getSelectedValues(fieldCheckboxes).map(id => Number(id)).filter(Number.isFinite))
        }
        if (hiddenServices) {
            hiddenServices.value = JSON.stringify(getSelectedValues(serviceCheckboxes))
        }
    }

    function syncDisabledState() {
        const active = Boolean(activeSwitch?.checked)
        const allFields = Boolean(applyAllFieldsSwitch?.checked)
        const allServices = Boolean(applyAllServicesSwitch?.checked)

        fieldCheckboxes.forEach((checkbox) => {
            checkbox.disabled = !active || allFields
        })

        serviceCheckboxes.forEach((checkbox) => {
            checkbox.disabled = !active || allServices
        })

        ;[valueInput, expirationInput].forEach((input) => {
            if (!input) return
            input.disabled = false
        })

        if (helperText) {
            helperText.classList.toggle('alert-secondary', !active)
            helperText.classList.toggle('alert-info', active)
            helperText.textContent = active
                ? 'Si desactivas la oferta, la configuracion queda guardada pero no se aplicara a nuevas reservas.'
                : 'La oferta esta desactivada. La configuracion se conserva para reactivarla cuando quieras.'
        }

        if (statusBadge) {
            statusBadge.className = `badge ${active ? 'bg-success' : 'bg-secondary'} fs-6 px-3 py-2`
            statusBadge.textContent = active ? 'Descuento activo' : 'Sin descuento activo'
        }

        if (previewText) {
            previewText.textContent = buildPreviewText()
        }
    }

    function syncState() {
        syncHiddenSelection()
        syncDisabledState()
    }

    activeSwitch?.addEventListener('change', syncState)
    applyAllFieldsSwitch?.addEventListener('change', syncState)
    applyAllServicesSwitch?.addEventListener('change', syncState)
    fieldCheckboxes.forEach((checkbox) => checkbox.addEventListener('change', syncState))
    serviceCheckboxes.forEach((checkbox) => checkbox.addEventListener('change', syncState))
    valueInput?.addEventListener('input', syncState)
    expirationInput?.addEventListener('change', syncState)
    form.addEventListener('submit', syncState)

    syncState()
})()
