(function () {
    const modalElement = document.getElementById('publicCustomerRegisterModal')
    const frameElement = document.getElementById('publicCustomerRegisterFrame')
    const titleElement = document.getElementById('publicCustomerRegisterModalLabel')
    const modal = modalElement && window.bootstrap?.Modal
        ? window.bootstrap.Modal.getOrCreateInstance(modalElement)
        : null

    let messageListenerAttached = false

    function setTitle(title) {
        if (titleElement && title) {
            titleElement.textContent = title
        }
    }

    function clearFrame() {
        if (frameElement) {
            frameElement.src = 'about:blank'
        }
    }

    function openRegisterFrame(iframeUrl, fallbackUrl) {
        if (!iframeUrl) return false

        const targetUrl = iframeUrl.includes('?') ? `${iframeUrl}&iframe=1` : `${iframeUrl}?iframe=1`

        if (!modal || !frameElement) {
            window.location.href = fallbackUrl || targetUrl
            return false
        }

        setTitle('Registro de clientes')
        frameElement.src = targetUrl
        modal.show()
        return true
    }

    function attachListeners() {
        if (modalElement && !modalElement.dataset.publicCustomerRegisterModalInit) {
            modalElement.dataset.publicCustomerRegisterModalInit = '1'
            modalElement.addEventListener('hidden.bs.modal', clearFrame)
        }

        if (!messageListenerAttached) {
            messageListenerAttached = true
            window.addEventListener('message', (event) => {
                if (event.origin !== window.location.origin) {
                    return
                }

                const message = event.data || {}
                if (message.type !== 'customer-register-saved') {
                    return
                }

                if (modal) {
                    modal.hide()
                }

                clearFrame()
            })
        }
    }

    attachListeners()

    document.addEventListener('click', (event) => {
        const registerLink = event.target.closest?.('.js-open-public-customer-register')
        if (!registerLink) {
            return
        }

        event.preventDefault()
        openRegisterFrame(registerLink.dataset.registerUrl || registerLink.href, registerLink.href)
    })
})()
