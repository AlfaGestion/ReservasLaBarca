(function () {
    const input = document.getElementById('matches-date')
    const trigger = document.getElementById('matches-date-trigger')
    const calendar = document.getElementById('matches-calendar')
    if (!input || !trigger || !calendar) return

    const title = calendar.querySelector('[data-calendar-title]')
    const days = calendar.querySelector('[data-calendar-days]')
    let month = new Date(`${input.value}T12:00:00`)
    const format = value => value ? value.split('-').reverse().join('/') : 'Seleccioná una fecha'
    const render = () => {
        const year = month.getFullYear()
        const currentMonth = month.getMonth()
        title.textContent = new Intl.DateTimeFormat('es-AR', { month: 'long', year: 'numeric' }).format(month)
        days.innerHTML = ''
        const offset = (new Date(year, currentMonth, 1).getDay() + 6) % 7
        const total = new Date(year, currentMonth + 1, 0).getDate()
        for (let index = 0; index < offset; index++) days.appendChild(document.createElement('span'))
        for (let day = 1; day <= total; day++) {
            const value = `${year}-${String(currentMonth + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`
            const button = document.createElement('button')
            button.type = 'button'; button.textContent = day; button.disabled = value < input.min
            if (value === input.value) button.classList.add('is-selected')
            button.addEventListener('click', () => { input.value = value; trigger.querySelector('span').textContent = format(value); calendar.hidden = true; trigger.setAttribute('aria-expanded', 'false') })
            days.appendChild(button)
        }
    }
    trigger.querySelector('span').textContent = format(input.value)
    trigger.addEventListener('click', () => { calendar.hidden = !calendar.hidden; trigger.setAttribute('aria-expanded', String(!calendar.hidden)); if (!calendar.hidden) render() })
    calendar.querySelector('[data-calendar-prev]')?.addEventListener('click', () => { month.setMonth(month.getMonth() - 1); render() })
    calendar.querySelector('[data-calendar-next]')?.addEventListener('click', () => { month.setMonth(month.getMonth() + 1); render() })
}())
