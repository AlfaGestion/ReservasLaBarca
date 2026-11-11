const checkCustomersWithOffer = document.getElementById('checkCustomersWithOffer')
const customersTabButton = document.getElementById('nav-customers-tab')
const customerEditModalElement = document.getElementById('customerEditModal')
const customerEditFrame = document.getElementById('customerEditFrame')
const customerEditModalLabel = document.getElementById('customerEditModalLabel')
const customerEditModal = customerEditModalElement && window.bootstrap?.Modal
    ? window.bootstrap.Modal.getOrCreateInstance(customerEditModalElement)
    : null

let customersTabLoaded = false
let customerEditModalMessageListenerAttached = false
const customersPerPage = 25
const customersPaginationSummary = document.getElementById('customersPaginationSummary')
const customersPreviousPage = document.getElementById('customersPreviousPage')
const customersNextPage = document.getElementById('customersNextPage')
const exportCustomersButton = document.getElementById('exportCustomersButton')
const customerSearchLabel = document.querySelector('label[for="searchCustomerInput"]')
if (customerSearchLabel) customerSearchLabel.textContent = 'Buscar por nombre, DNI, teléfono o localidad'
const customerSearchInput = document.getElementById('searchCustomerInput')
const customerSearchButton = document.getElementById('searchCustomerButton')
const customersState = {
    page: 1,
    totalPages: 1,
    total: 0,
    search: '',
    offersOnly: false,
}
const customerModalSavedMessageTypes = new Set(['customer-edit-saved', 'customer-register-saved'])

function escapeHtml(value) {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;')
}

function buildOfferCell(customer) {
    const badge = customer.offer_badge || (Number(customer.offer) === 1 ? 'Oferta activa' : 'Sin descuento')
    const badgeClass = Number(customer.customer_offer_active) === 1 || /OFF|Legacy/.test(badge)
        ? 'bg-warning text-dark'
        : 'bg-secondary'

    return `
        <div class="d-flex flex-column gap-1">
            <span class="badge ${badgeClass} align-self-start">${escapeHtml(badge)}</span>
        </div>
    `
}

function buildActions(customer) {
    return `
        <div class="btn-group" role="group" aria-label="Button group with nested dropdown">
            <button type="button" class="btn btn-danger dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                Acciones
            </button>
            <ul class="dropdown-menu">
                <li><button type="button" class="btn btn-primary dropdown-item js-open-customer-edit" data-id="${customer.id}" data-edit-url="${webBaseUrl}admin/clientes/${customer.id}/editar">Editar cliente</button></li>
                <li><a type="button" href="${webBaseUrl}customers/deleteCustomer/${customer.id}" class="btn btn-primary dropdown-item" data-id="${customer.id}">Eliminar cliente</a></li>
            </ul>
        </div>
    `
}

function setCustomerModalTitle(title) {
    if (customerEditModalLabel && title) {
        customerEditModalLabel.textContent = title
    }
}

function openCustomerFrame(url, title) {
    if (!url) return false

    const targetUrl = url.includes('?') ? `${url}&iframe=1` : `${url}?iframe=1`

    if (!customerEditFrame || !customerEditModal) {
        console.warn('Customer modal is not available. Frame was not opened.', targetUrl)
        return false
    }

    setCustomerModalTitle(title)
    customerEditFrame.src = targetUrl
    customerEditModal.show()
    return true
}

function openCustomerEditFrame(url) {
    return openCustomerFrame(url, 'Editar cliente')
}

function openCustomerRegisterFrame(url) {
    return openCustomerFrame(url, 'Nuevo cliente')
}

function clearCustomerEditFrame() {
    if (customerEditFrame) {
        customerEditFrame.src = 'about:blank'
    }
}

function attachCustomerEditModalListeners() {
    if (customerEditModalElement && !customerEditModalElement.dataset.customerEditModalInit) {
        customerEditModalElement.dataset.customerEditModalInit = '1'

        customerEditModalElement.addEventListener('hidden.bs.modal', () => {
            clearCustomerEditFrame()
        })
    }

    if (!customerEditModalMessageListenerAttached) {
        customerEditModalMessageListenerAttached = true
        window.addEventListener('message', async (event) => {
            if (event.origin !== window.location.origin) {
                return
            }

            const message = event.data || {}
            if (message.type === 'customer-frame-close') {
                if (customerEditModal) {
                    customerEditModal.hide()
                }
                clearCustomerEditFrame()
                return
            }

            if (!customerModalSavedMessageTypes.has(message.type)) {
                return
            }

            if (customerEditModal) {
                customerEditModal.hide()
            }

            clearCustomerEditFrame()
            await refreshCustomersList()
        })
    }
}

attachCustomerEditModalListeners()

async function loadInitialCustomers() {
    await loadCustomers()
}

if (customersTabButton) {
    customersTabButton.addEventListener('shown.bs.tab', async () => {
        if (customersTabLoaded) return
        customersTabLoaded = true
        await loadInitialCustomers()
    })
}

checkCustomersWithOffer?.addEventListener('change', async () => {
    customersState.offersOnly = checkCustomersWithOffer.checked
    customersState.page = 1
    await loadCustomers()
})

document.addEventListener('click', async (e) => {
    if (e.target) {
        const registerLink = e.target.closest?.('.js-open-customer-register')
        if (registerLink) {
            e.preventDefault()
            openCustomerRegisterFrame(registerLink.dataset.registerUrl || registerLink.href)
            return
        }

        const editLink = e.target.closest?.('.js-open-customer-edit')
        if (editLink) {
            e.preventDefault()
            openCustomerEditFrame(editLink.dataset.editUrl || editLink.href)
            return
        }

        if (e.target.id == 'searchCustomerButton') {
            const customerSearch = document.getElementById('searchCustomerInput')
            customersState.search = customerSearch?.value.trim() || ''
            customersState.page = 1
            await loadCustomers()
        } else if (e.target.id == 'setOfferTrue') {
            await setOfferTrue(true)
        } else if (e.target.id == 'setOfferFalse') {
            await setOfferFalse(false)
        }
    }
})

customersPreviousPage?.addEventListener('click', async () => {
    if (customersState.page <= 1) return
    customersState.page -= 1
    await loadCustomers()
})

customersNextPage?.addEventListener('click', async () => {
    if (customersState.page >= customersState.totalPages) return
    customersState.page += 1
    await loadCustomers()
})

exportCustomersButton?.addEventListener('click', async () => {
    const endpoint = customersState.offersOnly ? 'customers/getCustomersWithOffer' : 'customers/getCustomers'
    const params = new URLSearchParams({ search: customersState.search, export: '1' })

    try {
        const response = await fetch(`${baseUrl}${endpoint}?${params.toString()}`)
        const payload = await response.json()
        const customers = Array.isArray(payload.data) ? payload.data : []
        const rows = [
            ['Nombre', 'DNI', 'Teléfono', 'Localidad', 'Oferta', 'Alcance', 'Reservas'],
            ...customers.map((customer) => [
                [customer.name, customer.last_name].filter(Boolean).join(' '),
                customer.dni,
                customer.phone,
                customer.city,
                customer.offer_badge || 'Sin descuento',
                customer.offer_scope || customer.offer_summary || 'Sin alcance',
                customer.quantity ?? 0,
            ]),
        ]
        const csv = '\uFEFF' + rows.map((row) => row.map((value) => `"${String(value ?? '').replace(/"/g, '""')}"`).join(';')).join('\r\n')
        const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' })
        const link = document.createElement('a')
        link.href = URL.createObjectURL(blob)
        link.download = 'clientes.csv'
        link.click()
        URL.revokeObjectURL(link.href)
    } catch (error) {
        console.error('Error exportando clientes:', error)
        alert('No se pudo exportar el listado de clientes.')
    }
})

customerSearchInput?.addEventListener('keydown', (event) => {
    if (event.key !== 'Enter') return
    event.preventDefault()
    customerSearchButton?.click()
})

async function refreshCustomersList() {
    const customerSearch = document.getElementById('searchCustomerInput')
    customersState.search = customerSearch?.value.trim() || customersState.search
    await loadCustomers()
}

async function setOfferTrue(data) {
    try {
        const response = await fetch(`${baseUrl}customers/setOfferTrue`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(data)
        })

        if (response.ok) {
            alert('Oferta legacy asignada correctamente.')
            await refreshCustomersList()
        } else {
            alert('No se pudo completar la operación. Intenta nuevamente.')
        }
    } catch (error) {
        console.error('Error:', error)
        throw error
    }
}

async function setOfferFalse(data) {
    try {
        const response = await fetch(`${baseUrl}customers/setOfferFalse`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(data)
        })

        if (response.ok) {
            alert('Oferta legacy quitada correctamente.')
            await refreshCustomersList()
        } else {
            alert('No se pudo completar la operación. Intenta nuevamente.')
        }
    } catch (error) {
        console.error('Error:', error)
        throw error
    }
}

async function getCustomersWithOffer() {
    customersState.offersOnly = true
    await loadCustomers()
}

function updateCustomersPagination(pagination = {}) {
    customersState.page = Number(pagination.page || 1)
    customersState.totalPages = Number(pagination.total_pages || 1)
    customersState.total = Number(pagination.total || 0)

    if (customersPaginationSummary) {
        customersPaginationSummary.textContent = customersState.total === 0
            ? 'No hay clientes para mostrar'
            : `Página ${customersState.page} de ${customersState.totalPages} · ${customersState.total} cliente${customersState.total === 1 ? '' : 's'}`
    }

    if (customersPreviousPage) customersPreviousPage.disabled = customersState.page <= 1
    if (customersNextPage) customersNextPage.disabled = customersState.page >= customersState.totalPages
}

async function loadCustomers() {
    const endpoint = customersState.offersOnly ? 'customers/getCustomersWithOffer' : 'customers/getCustomers'
    const params = new URLSearchParams({
        page: String(customersState.page),
        per_page: String(customersPerPage),
        search: customersState.search,
    })

    try {
        const response = await fetch(`${baseUrl}${endpoint}?${params.toString()}`)
        const payload = await response.json()
        if (!response.ok || payload.error) throw new Error(payload.message || 'No se pudo obtener el listado')
        fillCustomersTable(payload.data || [])
        updateCustomersPagination(payload.pagination)
    } catch (error) {
        console.error('Error cargando clientes:', error)
        alert('No se pudo cargar el listado de clientes.')
    }
}

async function searchCustomer(url) {
    try {
        const response = await fetch(url)
        const responseData = await response.json()

        if (responseData.data != '') {
            fillCustomersTable(responseData.data)
        } else {
            alert('No se pudo obtener la información. Intenta nuevamente.')
        }
    } catch (error) {
        console.error('Error:', error)
        throw error
    }
}

async function fillCustomersTable(data) {
    const customersDiv = document.getElementById('customersDiv')
    let tr = ''

    if (Array.isArray(data)) {
        data.forEach(customer => {
            const fullName = [customer.name, customer.last_name]
                .map((value) => String(value ?? '').trim())
                .filter(Boolean)
                .join(' ')

            tr += `
            <tr>
                <td>${escapeHtml(fullName)}</td>
                <td>${escapeHtml(customer.dni)}</td>
                <td>${escapeHtml(customer.phone)}</td>
                <td>${escapeHtml(customer.city)}</td>
                <td>${buildOfferCell(customer)}</td>
                <td>${escapeHtml(customer.offer_scope || customer.offer_summary || 'Sin alcance')}</td>
                <td>${escapeHtml(customer.quantity ?? 0)}</td>
                <td>${buildActions(customer)}</td>
            </tr>
            `
        })
    } else if (typeof data === 'object' && data !== null) {
        const fullName = [data.name, data.last_name]
            .map((value) => String(value ?? '').trim())
            .filter(Boolean)
            .join(' ')

        tr += `
            <tr>
                <td>${escapeHtml(fullName)}</td>
                <td>${escapeHtml(data.dni)}</td>
                <td>${escapeHtml(data.phone)}</td>
                <td>${escapeHtml(data.city)}</td>
                <td>${buildOfferCell(data)}</td>
                <td>${escapeHtml(data.offer_scope || data.offer_summary || 'Sin alcance')}</td>
                <td>${escapeHtml(data.quantity ?? 0)}</td>
                <td>${buildActions(data)}</td>
            </tr>
        `
    } else {
        console.error('El parametro data no es un formato valido.')
        return
    }

    customersDiv.innerHTML = tr
}
