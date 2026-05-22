(function () {
    const STORAGE_KEY = 'reservas_theme';
    const THEME_DARK = 'dark';
    const THEME_LIGHT = 'light';

    function applyTheme(mode) {
        document.body.classList.toggle('theme-dark', mode === THEME_DARK);
    }

    function createToggle() {
        const existing = document.querySelector('.theme-toggle-global');
        if (existing) existing.remove();

        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'theme-toggle-global';
        button.setAttribute('aria-label', 'Cambiar tema');
        button.innerHTML = `
            <span class="theme-toggle-switch">
                <span class="theme-toggle-knob">
                    <i class="fa-solid fa-sun theme-toggle-active-icon"></i>
                </span>
            </span>
        `;

        const setMode = (mode) => {
            const normalized = mode === THEME_DARK ? THEME_DARK : THEME_LIGHT;
            localStorage.setItem(STORAGE_KEY, normalized);
            applyTheme(normalized);
            button.dataset.mode = normalized;
            const icon = button.querySelector('.theme-toggle-active-icon');
            if (icon) {
                icon.className = normalized === THEME_DARK
                    ? 'fa-solid fa-moon theme-toggle-active-icon'
                    : 'fa-solid fa-sun theme-toggle-active-icon';
            }
        };

        const initial = localStorage.getItem(STORAGE_KEY) === THEME_DARK ? THEME_DARK : THEME_LIGHT;
        setMode(initial);
        button.addEventListener('click', () => {
            const next = button.dataset.mode === THEME_DARK ? THEME_LIGHT : THEME_DARK;
            setMode(next);
        });

        document.body.appendChild(button);
    }

    document.addEventListener('DOMContentLoaded', function () {
        const saved = localStorage.getItem(STORAGE_KEY) === THEME_DARK ? THEME_DARK : THEME_LIGHT;
        applyTheme(saved);
        createToggle();
    });
})();
