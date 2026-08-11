(function () {
    const form = document.getElementById('customerOfferForm')
    if (!form) return

    const editorState = window.customerOfferEditorState || {}
    const frameMode = Boolean(editorState.frameMode || window.top !== window.self)

    const activeSwitch = document.getElementById('customer_offer_active')
    const applyAllFieldsSwitch = document.getElementById('customer_offer_apply_all_fields')
    const applyAllServicesSwitch = document.getElementById('customer_offer_apply_all_services')
    const hiddenFields = document.getElementById('customer_offer_fields_json')
    const hiddenServices = document.getElementById('customer_offer_services_json')
    const feedbackBox = document.getElementById('customerOfferFeedback')
    const previewText = document.getElementById('customerOfferPreviewText')
    const statusBadge = document.getElementById('customerOfferStatusBadge')
    const helperText = document.getElementById('customerOfferHelperText')
    const summaryState = document.getElementById('customerOfferSummaryState')
    const summaryValue = document.getElementById('customerOfferSummaryValue')
    const summaryFields = document.getElementById('customerOfferSummaryFields')
    const summaryServices = document.getElementById('customerOfferSummaryServices')
    const summaryExpiration = document.getElementById('customerOfferSummaryExpiration')
    const fieldScopeBox = document.querySelector('[data-scope="fields"]')
    const serviceScopeBox = document.querySelector('[data-scope="services"]')
    const fieldCheckboxes = Array.from(document.querySelectorAll('.customer-offer-field'))
    const serviceCheckboxes = Array.from(document.querySelectorAll('.customer-offer-service'))
    const choiceCards = Array.from(document.querySelectorAll('[data-offer-card]'))
    const valueInput = document.getElementById('customer_offer_value')
    const expirationInput = document.getElementById('customer_offer_expiration_date')
    const submitButton = form.querySelector('button[type="submit"]')

    function showFeedback(type, message) {
        if (!feedbackBox) return

        feedbackBox.innerHTML = ''

        const alert = document.createElement('div')
        alert.className = `alert alert-${type} alert-dismissible fade show`
        alert.setAttribute('role', 'alert')

        const small = document.createElement('small')
        small.textContent = message

        const closeButton = document.createElement('button')
        closeButton.type = 'button'
        closeButton.className = 'btn-close'
        closeButton.setAttribute('data-bs-dismiss', 'alert')
        closeButton.setAttribute('aria-label', 'Cerrar')

        alert.appendChild(small)
        alert.appendChild(closeButton)
        feedbackBox.appendChild(alert)
    }

    function parseNumber(value) {
        const normalized = String(value ?? '')
            .replace(/\$/g, '')
            .replace(/\s/g, '')
            .replace(/\./g, '')
            .replace(',', '.')
        const number = Number(normalized)
        return Number.isFinite(number) ? number : 0
    }

    function formatPercent(value) {
        const numeric = Number.isFinite(value) ? value : 0
        const formatted = numeric.toFixed(2).replace(/\.?0+$/, '')
        return formatted.replace('.', ',')
    }

    function formatDate(value) {
        const parts = String(value ?? '').split('-')
        if (parts.length !== 3) {
            return value || 'Sin vencimiento'
        }

        return `${parts[2]}/${parts[1]}/${parts[0]}`
    }

    function getSelectedValues(inputs) {
        return inputs
            .filter((input) => input.checked)
            .map((input) => input.value)
            .filter(Boolean)
    }

    function getPluralLabel(count, singular, plural) {
        return `${count} ${count === 1 ? singular : plural}`
    }

    function buildPreviewText() {
        if (!activeSwitch?.checked) {
            return 'Sin descuento activo'
        }

        const value = parseNumber(valueInput?.value || 0)
        const fieldCount = getSelectedValues(fieldCheckboxes).length
        const serviceCount = getSelectedValues(serviceCheckboxes).length
        const fieldLabel = applyAllFieldsSwitch?.checked
            ? 'Todas las canchas'
            : (fieldCount > 0 ? getPluralLabel(fieldCount, 'cancha', 'canchas') : 'Sin canchas')
        const serviceLabel = applyAllServicesSwitch?.checked
            ? 'Todos los servicios'
            : (serviceCount > 0 ? getPluralLabel(serviceCount, 'servicio', 'servicios') : 'Sin servicios')

        return [`${formatPercent(value)}% OFF`, fieldLabel, serviceLabel].filter(Boolean).join(' · ')
    }

    function syncHiddenSelection() {
        if (hiddenFields) {
            hiddenFields.value = JSON.stringify(getSelectedValues(fieldCheckboxes).map((id) => Number(id)).filter(Number.isFinite))
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

        if (fieldScopeBox) {
            fieldScopeBox.classList.toggle('customer-offer-scope-muted', !active || allFields)
        }

        if (serviceScopeBox) {
            serviceScopeBox.classList.toggle('customer-offer-scope-muted', !active || allServices)
        }

        ;[valueInput, expirationInput].forEach((input) => {
            if (!input) return
            input.disabled = false
        })

        if (helperText) {
            helperText.classList.toggle('alert-secondary', !active)
            helperText.classList.toggle('alert-info', active)
            helperText.textContent = active
                ? 'La oferta queda guardada por cliente y se aplicara en reservas y en Mercado Pago.'
                : 'La oferta esta desactivada. La configuracion se conserva para reactivarla cuando quieras.'
        }

        if (statusBadge) {
            statusBadge.classList.remove('customer-status-badge--success', 'customer-status-badge--secondary')
            statusBadge.classList.add(active ? 'customer-status-badge--success' : 'customer-status-badge--secondary')
            statusBadge.textContent = active ? 'Descuento activo' : 'Sin descuento activo'
        }
    }

    function syncChoiceCards() {
        choiceCards.forEach((card) => {
            const input = card.querySelector('input[type="checkbox"]')
            if (!input) return

            card.classList.toggle('is-selected', input.checked)
            card.classList.toggle('is-disabled', input.disabled)
        })
    }

    function syncSummary() {
        const active = Boolean(activeSwitch?.checked)
        const value = parseNumber(valueInput?.value || 0)
        const fieldCount = getSelectedValues(fieldCheckboxes).length
        const serviceCount = getSelectedValues(serviceCheckboxes).length
        const expirationValue = expirationInput?.value ? formatDate(expirationInput.value) : 'Sin vencimiento'

        if (summaryState) {
            summaryState.textContent = active ? 'Activo' : 'Inactivo'
        }

        if (summaryValue) {
            summaryValue.textContent = `${formatPercent(value)}%`
        }

        if (summaryFields) {
            summaryFields.textContent = applyAllFieldsSwitch?.checked
                ? 'Todas las canchas'
                : (fieldCount > 0 ? getPluralLabel(fieldCount, 'cancha', 'canchas') : 'Sin canchas')
        }

        if (summaryServices) {
            summaryServices.textContent = applyAllServicesSwitch?.checked
                ? 'Todos los servicios'
                : (serviceCount > 0 ? getPluralLabel(serviceCount, 'servicio', 'servicios') : 'Sin servicios')
        }

        if (summaryExpiration) {
            summaryExpiration.textContent = expirationValue
        }

        if (previewText) {
            previewText.textContent = buildPreviewText()
        }
    }

    function syncState() {
        syncHiddenSelection()
        syncDisabledState()
        syncChoiceCards()
        syncSummary()
    }

    async function handleSubmit(event) {
        syncState()

        if (!frameMode) {
            return
        }

        event.preventDefault()
        showFeedback('info', 'Guardando cambios...')
        if (submitButton) {
            submitButton.disabled = true
        }

        try {
            const response = await fetch(form.action, {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    Accept: 'application/json',
                },
                body: new FormData(form),
            })

            const payload = await response.json().catch(() => null)

            if (!response.ok || !payload || payload.error) {
                showFeedback('danger', (payload && payload.message) ? payload.message : 'No se pudo guardar la oferta personalizada')
                return
            }

            showFeedback('success', payload.message || 'Cliente editado exitosamente')

            window.setTimeout(() => {
                if (window.parent && window.parent !== window) {
                    window.parent.postMessage({
                        type: 'customer-edit-saved',
                        customerId: payload?.data?.customer_id ?? null,
                        message: payload.message || 'Cliente editado exitosamente',
                    }, window.location.origin)
                }
            }, 250)
        } catch (error) {
            console.error('Error saving customer offer:', error)
            showFeedback('danger', 'No se pudo guardar la oferta personalizada')
        } finally {
            if (submitButton) {
                submitButton.disabled = false
            }
        }
    }

    activeSwitch?.addEventListener('change', syncState)
    applyAllFieldsSwitch?.addEventListener('change', syncState)
    applyAllServicesSwitch?.addEventListener('change', syncState)
    fieldCheckboxes.forEach((checkbox) => checkbox.addEventListener('change', syncState))
    serviceCheckboxes.forEach((checkbox) => checkbox.addEventListener('change', syncState))
    valueInput?.addEventListener('input', syncState)
    expirationInput?.addEventListener('change', syncState)
    form.addEventListener('submit', handleSubmit)

    syncState()
})()
