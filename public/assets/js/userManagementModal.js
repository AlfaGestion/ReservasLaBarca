(function () {
    const modalElement = document.getElementById('superadminUserManagementModal');
    const frameElement = document.getElementById('superadminUserManagementFrame');
    const titleElement = document.getElementById('superadminUserManagementModalLabel');
    const modal = modalElement && window.bootstrap?.Modal
        ? window.bootstrap.Modal.getOrCreateInstance(modalElement)
        : null;

    function clearFrame() {
        if (frameElement) {
            frameElement.src = 'about:blank';
        }
    }

    function setTitle(title) {
        if (titleElement && title) {
            titleElement.textContent = title;
        }
    }

    function buildUrl(baseUrl, userId) {
        if (!baseUrl) {
            return '';
        }

        const separator = baseUrl.includes('?') ? '&' : '?';
        const params = ['iframe=1'];
        if (userId) {
            params.push(`user_id=${encodeURIComponent(userId)}`);
        }

        return `${baseUrl}${separator}${params.join('&')}`;
    }

    function openUserManagement(trigger) {
        const baseUrl = trigger?.dataset?.registerUrl || trigger?.href || '';
        const fallbackUrl = trigger?.dataset?.fallbackUrl || trigger?.href || '';
        const userId = trigger?.dataset?.userId || '';
        const targetUrl = buildUrl(baseUrl, userId);

        if (!targetUrl || !modal || !frameElement) {
            window.location.href = userId ? buildUrl(fallbackUrl, userId) : fallbackUrl;
            return;
        }

        setTitle(userId ? 'Editar usuario' : 'Crear usuario');
        frameElement.src = targetUrl;
        modal.show();
    }

    function handleMessage(event) {
        if (event.origin !== window.location.origin) {
            return;
        }

        const payload = event.data || {};
        if (payload.type !== 'user-management-saved') {
            return;
        }

        if (modal) {
            modal.hide();
        }

        clearFrame();

        window.setTimeout(() => {
            window.location.reload();
        }, 250);
    }

    if (modalElement) {
        modalElement.addEventListener('hidden.bs.modal', clearFrame);
    }

    document.addEventListener('click', (event) => {
        const trigger = event.target.closest?.('.js-open-superadmin-user-management');
        if (!trigger) {
            return;
        }

        event.preventDefault();
        openUserManagement(trigger);
    });

    window.addEventListener('message', handleMessage);
})();
