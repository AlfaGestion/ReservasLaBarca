(function () {
    const config = window.playerBookingConfig || {}
    const form = document.getElementById('player-booking-form')
    if (!form) return

    const screens = [...document.querySelectorAll('.wizard-screen[data-step]')]
    const progress = [...document.querySelectorAll('[data-progress-step]')]
    const serviceButtons = [...document.querySelectorAll('.service-choice')]
    const date = document.getElementById('booking-date')
    const fieldPicker = document.getElementById('field-picker')
    const fieldEmpty = document.getElementById('field-empty')
    const slots = document.getElementById('booking-slots')
    const from = document.getElementById('booking-from')
    const until = document.getElementById('booking-until')
    const loading = document.getElementById('booking-loading')
    const timeMessage = document.getElementById('booking-time-message')
    const formMessage = document.getElementById('booking-form-message')
    const dateMessage = document.getElementById('booking-date-message')
    const fieldNext = document.querySelector('[data-step="3"] .wizard-next')
    const timeNext = document.querySelector('[data-step="4"] .wizard-next')
    const quoteButton = document.getElementById('booking-quote')
    const summaryScreen = document.getElementById('booking-summary-screen')
    const resultScreen = document.getElementById('booking-result-screen')
    const formScreen = document.querySelector('.booking-wizard-form')
    const aside = document.getElementById('reservation-aside')
    const myBookings = document.getElementById('mis-reservas')
    const summary = document.getElementById('booking-summary')
    const summaryError = document.getElementById('booking-summary-error')
    const confirmButton = document.getElementById('booking-confirm')
    const payment = document.getElementById('booking-payment')
    let csrf = document.querySelector('meta[name="csrf-token"]')?.content || ''
    const addonSection = document.getElementById('addon-section')
    const addonBox = document.getElementById('addon-fields')
    const addQuincho = document.getElementById('booking-add-quincho')
    const addonField = document.getElementById('booking-addon-field')
    const addonFrom = document.getElementById('booking-addon-from')
    const addonUntil = document.getElementById('booking-addon-until')
    let step = 1
    let selectedService = ''
    let selectedField = ''
    let quote = null
    let requestId = 0
    let calendarMonth = new Date(`${date.value}T12:00:00`)

    const money = value => '$ ' + Number(value || 0).toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
    const message = (element, text, error = false) => { if (!element) return; element.textContent = text || ''; element.hidden = !text; element.classList.toggle('booking-alert-error', error) }
    const serviceFields = () => config.fieldsByService?.[selectedService] || []
    const resetSelect = (select, label) => { if (!select) return; select.innerHTML = ''; select.add(new Option(label, '')); select.disabled = true }

    const dateTrigger = document.getElementById('booking-date-trigger')
    const calendar = document.getElementById('booking-calendar')
    const calendarTitle = calendar?.querySelector('[data-calendar-title]')
    const calendarDays = calendar?.querySelector('[data-calendar-days]')
    const formatDate = value => value ? value.split('-').reverse().join('/') : 'Seleccioná una fecha'
    const renderCalendar = () => {
        if (!calendarDays || !calendarTitle) return
        const year = calendarMonth.getFullYear()
        const month = calendarMonth.getMonth()
        calendarTitle.textContent = new Intl.DateTimeFormat('es-AR', { month: 'long', year: 'numeric' }).format(calendarMonth)
        calendarDays.innerHTML = ''
        const firstDay = (new Date(year, month, 1).getDay() + 6) % 7
        const daysInMonth = new Date(year, month + 1, 0).getDate()
        const today = date.min
        for (let index = 0; index < firstDay; index++) calendarDays.appendChild(document.createElement('span'))
        for (let day = 1; day <= daysInMonth; day++) {
            const value = `${year}-${String(month + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`
            const button = document.createElement('button')
            button.type = 'button'; button.textContent = day; button.dataset.date = value
            button.disabled = value < today
            if (value === date.value) button.classList.add('is-selected')
            button.addEventListener('click', () => { date.value = value; dateTrigger.querySelector('span').textContent = formatDate(value); calendar.hidden = true; dateTrigger.setAttribute('aria-expanded', 'false'); date.dispatchEvent(new Event('change', { bubbles: true })) })
            button.addEventListener('dblclick', () => { if (!button.disabled) { date.value = value; dateTrigger.querySelector('span').textContent = formatDate(value); calendar.hidden = true; dateTrigger.setAttribute('aria-expanded', 'false'); date.dispatchEvent(new Event('change', { bubbles: true })); showStep(3) } })
            calendarDays.appendChild(button)
        }
    }
    if (dateTrigger && calendar) {
        dateTrigger.querySelector('span').textContent = formatDate(date.value)
        dateTrigger.addEventListener('click', () => { calendar.hidden = !calendar.hidden; dateTrigger.setAttribute('aria-expanded', String(!calendar.hidden)); if (!calendar.hidden) renderCalendar() })
        dateTrigger.addEventListener('dblclick', () => { if (date.value) showStep(3) })
        calendar.querySelector('[data-calendar-prev]')?.addEventListener('click', () => { calendarMonth.setMonth(calendarMonth.getMonth() - 1); renderCalendar() })
        calendar.querySelector('[data-calendar-next]')?.addEventListener('click', () => { calendarMonth.setMonth(calendarMonth.getMonth() + 1); renderCalendar() })
    }
    const cookieValue = name => {
        const prefix = `${name}=`
        const item = document.cookie.split('; ').find(value => value.startsWith(prefix))
        return item ? decodeURIComponent(item.slice(prefix.length)) : ''
    }

    const syncCsrfToken = () => {
        // CodeIgniter regenera la cookie después de cada POST protegido.
        const cookieToken = cookieValue('csrf_cookie_name')
        if (cookieToken) csrf = cookieToken
        const meta = document.querySelector('meta[name="csrf-token"]')
        if (meta?.content && !cookieToken) csrf = meta.content
    }

    const postJson = async (url, body) => {
        syncCsrfToken()
        const response = await fetch(url, { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf }, body: JSON.stringify(body) })
        const refreshedToken = cookieValue('csrf_cookie_name')
        if (refreshedToken) {
            csrf = refreshedToken
            document.querySelector('meta[name="csrf-token"]')?.setAttribute('content', refreshedToken)
        }
        let data = {}
        try { data = await response.json() } catch (error) { data = {} }
        if (data?.data?.csrfToken) {
            csrf = data.data.csrfToken
            document.querySelector('meta[name="csrf-token"]')?.setAttribute('content', csrf)
        }
        if (!response.ok || data.error) {
            const fallback = response.status === 403
                ? 'Tu sesión de seguridad venció. Actualizá la página e intentá nuevamente.'
                : 'No pudimos completar la operación. Intentá nuevamente.'
            throw new Error(data.message || fallback)
        }
        return data
    }

    function updateProgress() {
        progress.forEach(item => { const number = Number(item.dataset.progressStep); item.classList.toggle('is-active', number === step); item.classList.toggle('is-complete', number < step) })
    }

    function enforceActiveScreen() {
        screens.forEach(screen => { screen.hidden = Number(screen.dataset.step) !== step })
        updateProgress()
    }

    function showStep(nextStep) {
        step = Math.max(1, Math.min(5, nextStep))
        screens.forEach(screen => { screen.hidden = Number(screen.dataset.step) !== step })
        updateProgress()
        if (step === 3) renderFields()
        if (step === 4) loadPrimarySlots()
        if (step === 5) updateAddonVisibility()
        const heading = screens.find(screen => Number(screen.dataset.step) === step)?.querySelector('h2')
        if (heading) { heading.tabIndex = -1; heading.focus({ preventScroll: true }) }
    }

    function renderFields() {
        const fields = serviceFields()
        fieldPicker.innerHTML = ''
        selectedField = fields.some(item => String(item.id) === String(selectedField)) ? selectedField : ''
        fields.forEach(item => {
            const button = document.createElement('button')
            button.type = 'button'; button.className = 'wizard-choice field-choice'; button.dataset.field = item.id; button.setAttribute('role', 'radio'); button.setAttribute('aria-checked', String(String(item.id) === String(selectedField)))
            button.innerHTML = `<span class="field-choice-icon"><i class="fa-solid fa-location-dot" aria-hidden="true"></i></span><strong>${escapeHtml(item.name || 'Espacio')}</strong><small>${item.description ? escapeHtml(item.description) : 'Instalación disponible'}</small>`
            if (String(item.id) === String(selectedField)) button.classList.add('selected')
            button.addEventListener('click', () => { selectedField = String(item.id); selectedSlot('', ''); renderFields(); fieldNext.disabled = false })
            button.addEventListener('dblclick', () => { if (selectedField === String(item.id)) showStep(4) })
            fieldPicker.appendChild(button)
        })
        fieldEmpty.hidden = fields.length > 0
        fieldNext.disabled = !selectedField
    }

    function selectedSlot(slotFrom, slotUntil) {
        from.value = slotFrom; until.value = slotUntil; timeNext.disabled = !(slotFrom && slotUntil)
        slots?.querySelectorAll('.slot-choice').forEach(button => { const active = button.dataset.from === slotFrom && button.dataset.until === slotUntil; button.classList.toggle('selected', active); button.setAttribute('aria-checked', String(active)) })
    }

    async function loadSlots(fieldId, target, isAddon = false) {
        if (!fieldId || !date.value) return []
        const response = await fetch(`${config.availabilityUrl}?field_id=${encodeURIComponent(fieldId)}&date=${encodeURIComponent(date.value)}`)
        const result = await response.json()
        if (!response.ok || result.error) throw new Error(result.message || 'No pudimos cargar los horarios disponibles. Intentá nuevamente.')
        return result.data.slots || []
    }

    async function loadPrimarySlots() {
        if (!selectedField) return
        const currentRequest = ++requestId
        loading.hidden = false; slots.innerHTML = ''; message(timeMessage, ''); selectedSlot('', '')
        try {
            const available = await loadSlots(selectedField, slots)
            if (currentRequest !== requestId) return
            available.forEach(slot => {
                const button = document.createElement('button'); button.type = 'button'; button.className = 'slot-choice'; button.dataset.from = slot.from; button.dataset.until = slot.until; button.setAttribute('role', 'radio'); button.setAttribute('aria-checked', 'false'); button.disabled = !slot.available
                button.innerHTML = `<strong>${escapeHtml(slot.from)}</strong><small>${escapeHtml(slot.until)}${slot.available ? '' : ' · No disponible'}</small>`
                if (slot.available) {
                    button.addEventListener('click', () => selectedSlot(slot.from, slot.until))
                    button.addEventListener('dblclick', () => { selectedSlot(slot.from, slot.until); showStep(5) })
                }
                slots.appendChild(button)
            })
            if (!available.some(slot => slot.available)) message(timeMessage, 'No hay horarios disponibles para esta fecha. Elegí otra fecha.', false)
        } catch (error) { message(timeMessage, error.message || 'No pudimos cargar los horarios disponibles. Intentá nuevamente.', true) }
        loading.hidden = true
    }

    async function loadAddonSlots() {
        if (!addonField?.value) return
        try {
            const available = await loadSlots(addonField.value, addonFrom, true)
            resetSelect(addonFrom, 'Desde'); resetSelect(addonUntil, 'Hasta')
            available.filter(slot => slot.available).forEach(slot => addonFrom.add(new Option(slot.from, slot.from)))
            addonFrom.disabled = available.filter(slot => slot.available).length === 0
            addonFrom._slots = available
        } catch (error) { message(document.getElementById('addon-message'), error.message || 'No pudimos cargar los horarios del adicional.', true) }
    }

    function updateAddonVisibility() {
        const field = serviceFields().find(item => String(item.id) === String(selectedField))
        const allowed = Boolean(field && Number(field.allows_quincho_addon || 0) === 1 && (config.fieldsByService?.quincho || []).length)
        if (addonSection) addonSection.hidden = !allowed
        if (!allowed && addQuincho) { addQuincho.checked = false; addonBox.hidden = true }
    }

    function splitName(fullName) { const parts = fullName.trim().split(/\s+/).filter(Boolean); return { first_name: parts.shift() || '', last_name: parts.join(' ') } }
    function escapeHtml(value) { return String(value ?? '').replace(/[&<>'"]/g, character => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#039;', '"': '&quot;' }[character])) }

    serviceButtons.forEach(button => button.addEventListener('click', () => {
        serviceButtons.forEach(item => { item.classList.remove('selected'); item.setAttribute('aria-checked', 'false') })
        button.classList.add('selected'); button.setAttribute('aria-checked', 'true'); selectedService = button.dataset.service; selectedField = ''; quote = null
        button.closest('[data-step="1"]').querySelector('.wizard-next').disabled = false
    }))
    serviceButtons.forEach(button => button.addEventListener('dblclick', () => { if (selectedService === button.dataset.service) showStep(2) }))
    document.querySelectorAll('.wizard-next').forEach(button => button.addEventListener('click', () => {
        if (Number(button.dataset.next) === 2 && !selectedService) return
        if (Number(button.dataset.next) === 3 && !date.value) { message(dateMessage, 'Seleccioná una fecha.', true); return }
        if (Number(button.dataset.next) === 4 && !selectedField) return
        if (Number(button.dataset.next) === 5 && (!from.value || !until.value)) return
        showStep(Number(button.dataset.next))
    }))
    document.querySelectorAll('.wizard-back').forEach(button => button.addEventListener('click', () => showStep(Number(button.dataset.back))))
    date.addEventListener('change', () => { if (dateTrigger) dateTrigger.querySelector('span').textContent = formatDate(date.value); selectedSlot('', ''); selectedField = ''; quote = null; message(dateMessage, '') })
    addQuincho?.addEventListener('change', () => { addonBox.hidden = !addQuincho.checked; if (addQuincho.checked) loadAddonSlots() })
    addonField?.addEventListener('change', loadAddonSlots)
    addonFrom?.addEventListener('change', () => { const slot = (addonFrom._slots || []).find(item => item.from === addonFrom.value); resetSelect(addonUntil, 'Hasta'); if (slot) { addonUntil.add(new Option(slot.until, slot.until)); addonUntil.value = slot.until; addonUntil.disabled = false } })

    const requestedService = new URLSearchParams(window.location.search).get('servicio')
    if (requestedService) {
        const requestedButton = serviceButtons.find(button => button.dataset.service === requestedService)
        if (requestedButton) {
            requestedButton.click()
            showStep(2)
        }
    }

    quoteButton.addEventListener('click', async () => {
        if (!date.value || !selectedService || !selectedField || !from.value || !until.value || !document.getElementById('booking-name').value.trim() || !document.getElementById('booking-phone').value.trim()) { message(formMessage, 'Completá todos los datos obligatorios.', true); return }
        if (addQuincho?.checked && (!addonField.value || !addonFrom.value || !addonUntil.value)) { message(document.getElementById('addon-message'), 'Completá el horario del quincho adicional.', true); return }
        quoteButton.disabled = true; quoteButton.textContent = 'Calculando…'
        try {
            const additional = addQuincho?.checked ? { enabled: true, field_id: addonField.value, from: addonFrom.value, until: addonUntil.value } : null
            const result = await postJson(config.quoteUrl, { date: date.value, field_id: selectedField, from: from.value, until: until.value, additional })
            quote = result.data
            step = 5; updateProgress()
            const name = document.getElementById('booking-name').value
            const extra = additional ? `<div><dt>Quincho adicional</dt><dd>${escapeHtml(addonFrom.value)} a ${escapeHtml(addonUntil.value)}</dd></div>` : ''
            summary.innerHTML = `<dl><div><dt>Servicio</dt><dd>${escapeHtml(quote.field.service_name || selectedService)}</dd></div><div><dt>Cancha o espacio</dt><dd>${escapeHtml(quote.field.name)}</dd></div><div><dt>Fecha</dt><dd>${date.value.split('-').reverse().join('/')}</dd></div><div><dt>Horario</dt><dd>${from.value} a ${until.value}</dd></div>${extra}<div><dt>Jugador</dt><dd>${escapeHtml(name)}</dd></div><div><dt>Total</dt><dd>${money(quote.total)}</dd></div>${quote.deposit !== null ? `<div><dt>Seña (${quote.deposit_rate}%)</dt><dd>${money(quote.deposit)}</dd></div>` : ''}</dl>`
            formScreen.hidden = true; summaryScreen.hidden = false; if (aside) aside.hidden = true; if (myBookings) myBookings.hidden = true; summaryError.hidden = true
        } catch (error) { message(formMessage, error.message || 'No pudimos obtener el resumen. Intentá nuevamente.', true) }
        quoteButton.disabled = false; quoteButton.innerHTML = 'Ver resumen <span aria-hidden="true">→</span>'
    })

    confirmButton.addEventListener('click', async () => {
        if (!quote) return
        if (!config.testMode && (!config.publicKeyMp || !window.MercadoPago)) { message(summaryError, 'Mercado Pago todavía no está configurado. La reserva no fue creada.', true); return }
        confirmButton.disabled = true; confirmButton.textContent = 'Preparando el pago…'
        try {
            const name = splitName(document.getElementById('booking-name').value)
            const additional = addQuincho?.checked ? { enabled: true, fecha: date.value, cancha: addonField.value, horarioDesde: addonFrom.value, horarioHasta: addonUntil.value } : null
            const result = await postJson(config.createUrl, { booking: { fecha: date.value, cancha: selectedField, horarioDesde: from.value, horarioHasta: until.value, nombre: document.getElementById('booking-name').value.trim(), first_name: name.first_name, last_name: name.last_name, telefono: document.getElementById('booking-phone').value.trim(), localidad: document.getElementById('booking-locality').value.trim(), metodoDePago: 'Mercado Pago', additionalQuincho: additional } })
            const prefs = result.data || {}
            summaryScreen.hidden = true; resultScreen.hidden = false; resultScreen.querySelector('#booking-result-title').textContent = 'Reserva pendiente de pago'; resultScreen.querySelector('#booking-result-message').textContent = 'La reserva fue creada y quedó pendiente de confirmación del pago.'; if (aside) aside.hidden = true; if (myBookings) myBookings.hidden = true
            if (prefs.testMode) {
                resultScreen.querySelector('#booking-result-title').textContent = 'Reserva confirmada';
                resultScreen.querySelector('#booking-result-message').textContent = 'La reserva se confirmó correctamente en modo prueba. No se inició ningún pago.';
                payment.hidden = true;
                return;
            }
            payment.hidden = false; payment.innerHTML = '<p>Elegí una modalidad de pago:</p><div id="player-mp-partial"></div><div id="player-mp-total"></div>'
            const mp = new MercadoPago(config.publicKeyMp, { locale: 'es-AR' }); mp.checkout({ preference: { id: prefs.preferenceIdParcial }, render: { container: '#player-mp-partial', label: 'Pagar seña' } }); mp.checkout({ preference: { id: prefs.preferenceIdTotal }, render: { container: '#player-mp-total', label: 'Pagar total' } })
        } catch (error) { confirmButton.disabled = false; confirmButton.textContent = 'Confirmar reserva →'; message(summaryError, error.message || 'No pudimos procesar la reserva. Intentá nuevamente.', true) }
    })

    function showForm() { resultScreen.hidden = true; summaryScreen.hidden = true; formScreen.hidden = false; if (aside) aside.hidden = false; if (myBookings) myBookings.hidden = false }
    function showBookingsOnly() {
        if (!myBookings || window.location.hash !== '#mis-reservas') return
        formScreen.hidden = true; summaryScreen.hidden = true; resultScreen.hidden = true
        if (aside) aside.hidden = true
        myBookings.hidden = false
        window.requestAnimationFrame(() => myBookings.scrollIntoView({ behavior: 'smooth', block: 'start' }))
    }
    document.getElementById('booking-back').addEventListener('click', showForm)
    document.getElementById('booking-result-edit').addEventListener('click', showForm)
    window.addEventListener('hashchange', showBookingsOnly)
    enforceActiveScreen()
    showBookingsOnly()
    if (window.location.hash !== '#mis-reservas') serviceButtons[0]?.focus()
}())
