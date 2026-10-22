(function () {
    const root = document.documentElement
    const button = document.querySelector('[data-theme-toggle]')
    if (!button) return

    const systemTheme = () => window.matchMedia('(prefers-color-scheme: light)').matches ? 'light' : 'dark'
    const applyTheme = theme => {
        const next = theme === 'light' || theme === 'dark' ? theme : systemTheme()
        root.dataset.theme = next
        localStorage.setItem('la-barca-theme', next)
        button.setAttribute('aria-label', next === 'dark' ? 'Cambiar a modo claro' : 'Cambiar a modo oscuro')
        button.setAttribute('title', next === 'dark' ? 'Cambiar a modo claro' : 'Cambiar a modo oscuro')
    }

    button.addEventListener('click', () => applyTheme(root.dataset.theme === 'dark' ? 'light' : 'dark'))
    window.matchMedia('(prefers-color-scheme: light)').addEventListener?.('change', event => {
        if (!localStorage.getItem('la-barca-theme')) applyTheme(event.matches ? 'light' : 'dark')
    })

    document.querySelectorAll('.filters-toggle').forEach(toggle => toggle.addEventListener('click', () => {
        const filters = toggle.closest('.modern-filters')
        if (!filters) return
        const collapsed = filters.classList.toggle('is-collapsed')
        toggle.setAttribute('aria-expanded', String(!collapsed))
    }))
}())
