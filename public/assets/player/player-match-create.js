(function () {
    const form = document.getElementById('match-create-form')
    if (!form) return
    const bookings = window.playerMatchBookings || []
    const booking = document.getElementById('match-booking')
    const max = document.getElementById('match-max')
    const confirmed = document.getElementById('match-confirmed')
    const mode = document.getElementById('match-mode')
    const level = document.getElementById('match-level')
    const description = document.getElementById('match-description')
    const screens = [...form.querySelectorAll('[data-match-step]')]
    const progressLabel = document.getElementById('match-step-label')
    let currentStep = 1

    const escapeHtml = value => String(value ?? '').replace(/[&<>"']/g, char => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[char]))
    const selected = () => bookings.find(item => String(item.id) === String(booking.value)) || bookings[0]
    const updateValues = () => {
        const item = selected()
        const total = Math.max(0, Number(max.value) || 0)
        const initial = Math.max(0, Number(confirmed.value) || 0)
        const available = Math.max(0, total - initial)
        if (item) document.getElementById('selected-booking-card').innerHTML = `<strong>${escapeHtml(item.sport)} · ${escapeHtml(item.field)}</strong><span>${escapeHtml(item.date)} · ${escapeHtml(item.time)}</span>`
        document.getElementById('places-summary').textContent = `Quedan ${available} ${available === 1 ? 'lugar disponible' : 'lugares disponibles'}.`
        document.getElementById('mode-help').textContent = mode?.value === 'auto' ? 'Los jugadores podrán sumarse directamente mientras haya lugares.' : 'Vas a revisar las solicitudes antes de aceptar jugadores.'
        document.getElementById('description-counter').textContent = `${description.value.length}/500`
    }
    const updateReview = () => {
        const item = selected()
        const total = Number(max.value) || 0
        const initial = Number(confirmed.value) || 0
        if (item) {
            document.getElementById('review-booking').textContent = `${item.sport} · ${item.field}`
            document.getElementById('review-date').textContent = `${item.date} · ${item.time}`
        }
        document.getElementById('review-capacity').textContent = `${total} totales · ${initial} confirmados · ${Math.max(0, total - initial)} disponibles`
        document.getElementById('review-mode').textContent = mode?.value === 'auto' ? 'Ingreso automático' : 'Con aprobación'
        document.getElementById('review-level').textContent = level?.value || 'Sin especificar'
        document.getElementById('review-description').textContent = description.value.trim() || 'Sin descripción'
    }
    const showStep = step => {
        currentStep = step
        screens.forEach(screen => { screen.hidden = Number(screen.dataset.matchStep) !== step })
        progressLabel.textContent = step === 1 ? 'Paso 1 de 2' : step === 2 ? 'Paso 2 de 2' : 'Revisión'
        document.querySelectorAll('[data-match-progress]').forEach(item => item.classList.toggle('is-active', Number(item.dataset.matchProgress) <= Math.min(step, 2)))
        window.requestAnimationFrame(() => form.scrollIntoView({ behavior: 'smooth', block: 'start' }))
    }
    const validPlayers = () => {
        const total = Number(max.value) || 0
        const initial = Number(confirmed.value) || 0
        if (total < 2 || initial < 1 || initial > total) {
            document.getElementById('places-summary').textContent = 'Revisá la cantidad de jugadores: los confirmados no pueden superar el total.'
            document.getElementById('places-summary').classList.add('is-error')
            return false
        }
        document.getElementById('places-summary').classList.remove('is-error')
        return true
    }
    form.querySelectorAll('select, input, textarea').forEach(field => field.addEventListener('input', () => { updateValues(); if (currentStep === 3) updateReview() }))
    form.querySelector('[data-match-next="2"]').addEventListener('click', () => { if (validPlayers()) showStep(2) })
    form.querySelector('[data-match-review]').addEventListener('click', () => { if (validPlayers()) { updateReview(); showStep(3) } })
    form.querySelectorAll('[data-match-back]').forEach(button => button.addEventListener('click', () => showStep(Number(button.dataset.matchBack))))
    form.addEventListener('submit', event => {
        if (!validPlayers()) { event.preventDefault(); showStep(1); return }
        const button = document.getElementById('publish-match-button')
        button.disabled = true
        button.innerHTML = 'Publicando partido…'
    })
    updateValues()
    showStep(1)
}())
