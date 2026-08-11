const checkCustomersWithOffer = document.getElementById('checkCustomersWithOffer')
const customersTabButton = document.getElementById('nav-customers-tab')

let customersTabLoaded = false

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
                <li><a type="button" href="${webBaseUrl}customers/editWindow/${customer.id}" class="btn btn-primary dropdown-item" data-id="${customer.id}">Editar cliente</a></li>
                <li><a type="button" href="${webBaseUrl}customers/deleteCustomer/${customer.id}" class="btn btn-primary dropdown-item" data-id="${customer.id}">Eliminar cliente</a></li>
            </ul>
        </div>
    `
}

async function loadInitialCustomers() {
    await searchCustomer(`${baseUrl}customers/getCustomers?limit=50`)
}

if (customersTabButton) {
    customersTabButton.addEventListener('shown.bs.tab', async () => {
        if (customersTabLoaded) return
        customersTabLoaded = true
        await loadInitialCustomers()
    })
}

checkCustomersWithOffer?.addEventListener('change', async () => {
    if (checkCustomersWithOffer.checked) {
        await getCustomersWithOffer()
    } else {
        await refreshCustomersList()
    }
})

document.addEventListener('click', async (e) => {
    if (e.target) {
        if (e.target.id == 'searchCustomerButton') {
            if (checkCustomersWithOffer) {
                checkCustomersWithOffer.checked = false
            }
            const customerPhone = document.getElementById('searchCustomerInput')

            if (customerPhone.value == '') {
                await searchCustomer(`${baseUrl}customers/getCustomers?limit=50`)
            } else {
                await searchCustomer(`${baseUrl}customers/getCustomer/${customerPhone.value}`)
            }
        } else if (e.target.id == 'setOfferTrue') {
            await setOfferTrue(true)
        } else if (e.target.id == 'setOfferFalse') {
            await setOfferFalse(false)
        }
    }
})

async function refreshCustomersList() {
    const customerPhone = document.getElementById('searchCustomerInput')
    const phoneValue = customerPhone ? customerPhone.value.trim() : ''

    if (checkCustomersWithOffer && checkCustomersWithOffer.checked) {
        await getCustomersWithOffer()
        return
    }

    if (phoneValue !== '') {
        await searchCustomer(`${baseUrl}customers/getCustomer/${phoneValue}`)
        return
    }

    await searchCustomer(`${baseUrl}customers/getCustomers?limit=50`)
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
    try {
        const response = await fetch(`${baseUrl}customers/getCustomersWithOffer`)
        const responseData = await response.json()

        fillCustomersTable(responseData.data)
    } catch (error) {
        console.error('Error:', error)
        throw error
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
            tr += `
            <tr>
                <td>${escapeHtml(customer.name)}</td>
                <td>${escapeHtml(customer.last_name)}</td>
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
        tr += `
            <tr>
                <td>${escapeHtml(data.name)}</td>
                <td>${escapeHtml(data.last_name)}</td>
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
