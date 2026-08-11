(function () {
    const form = document.getElementById('customerRegisterForm')
    if (!form) return

    const editorState = window.customerRegisterEditorState || {}
    const frameMode = Boolean(editorState.frameMode || window.top !== window.self)
    const feedbackBox = document.getElementById('customerRegisterFeedback')
    const statusBadge = document.getElementById('customerRegisterStatusBadge')
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

    async function handleSubmit(event) {
        if (!frameMode) {
            return
        }

        event.preventDefault()
        showFeedback('info', 'Guardando cliente...')

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
                showFeedback('danger', (payload && payload.message) ? payload.message : 'No se pudo registrar el cliente')
                if (statusBadge) {
                    statusBadge.textContent = 'Error al guardar'
                }
                return
            }

            showFeedback('success', payload.message || 'Cliente registrado exitosamente')

            if (statusBadge) {
                statusBadge.textContent = 'Cliente registrado'
            }

            window.setTimeout(() => {
                if (window.parent && window.parent !== window) {
                    window.parent.postMessage({
                        type: 'customer-register-saved',
                        customerId: payload?.data?.customer_id ?? null,
                        message: payload.message || 'Cliente registrado exitosamente',
                    }, window.location.origin)
                }
            }, 250)
        } catch (error) {
            console.error('Error saving customer register:', error)
            showFeedback('danger', 'No se pudo registrar el cliente')
            if (statusBadge) {
                statusBadge.textContent = 'Error al guardar'
            }
        } finally {
            if (submitButton) {
                submitButton.disabled = false
            }
        }
    }

    form.addEventListener('submit', handleSubmit)
})()
