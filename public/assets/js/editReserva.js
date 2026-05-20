if (!window.__editReservaLoaded) {
  window.__editReservaLoaded = true;

const editModalEl = document.getElementById('editarReservaModal')
const modalSpinner = new bootstrap.Modal('#modalSpinner')
const modalResult = new bootstrap.Modal('#modalResult')
const contentEditBookingResult = document.getElementById('bookingEditResult')
const editBookingModal = new bootstrap.Modal('#editarReservaModal')

function getEditEls() {
    const els = {
        fecha: editModalEl.querySelector('#fecha'),
        horarioDesde: editModalEl.querySelector('#horarioDesde'),
        horarioHasta: editModalEl.querySelector('#horarioHasta'),
        cancha: editModalEl.querySelector('#cancha'),
        inputMonto: editModalEl.querySelector('#inputMonto'),
        telefono: editModalEl.querySelector('#telefono'),
        nombre: editModalEl.querySelector('#nombre'),
        localidad: editModalEl.querySelector('#localidad'),
    }
    return els
}

function normalizeText(value) {
    return (value || '')
        .toString()
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLowerCase()
}

function setupLocalityAutocomplete(inputEl, datalistId) {
    if (!inputEl) return
    const dataList = document.getElementById(datalistId)
    if (!dataList) return

    const options = Array.from(dataList.querySelectorAll('option'))
        .map(opt => opt.value)
        .filter(Boolean)

    if (options.length === 0) return

    const parent = inputEl.parentElement
    if (parent) {
        parent.style.position = 'relative'
    }

    const box = document.createElement('div')
    box.className = 'locality-suggestions'
    box.style.position = 'absolute'
    box.style.top = '100%'
    box.style.left = '0'
    box.style.right = '0'
    box.style.zIndex = '50'
    box.style.background = '#fff'
    box.style.border = '1px solid #cfd4da'
    box.style.borderTop = 'none'
    box.style.maxHeight = '200px'
    box.style.overflowY = 'auto'
    box.style.display = 'none'
    box.style.boxShadow = '0 4px 12px rgba(0,0,0,0.08)'

    parent.appendChild(box)

    const render = (items) => {
        box.innerHTML = ''
        if (!items || items.length === 0) {
            box.style.display = 'none'
            return
        }
        items.slice(0, 8).forEach((name) => {
            const item = document.createElement('div')
            item.textContent = name
            item.style.padding = '8px 12px'
            item.style.cursor = 'pointer'
            item.addEventListener('mousedown', (e) => {
                e.preventDefault()
                inputEl.value = name
                box.style.display = 'none'
            })
            item.addEventListener('mouseenter', () => {
                item.style.background = '#f1f3f5'
            })
            item.addEventListener('mouseleave', () => {
                item.style.background = '#fff'
            })
            box.appendChild(item)
        })
        box.style.display = 'block'
    }

    const onInput = () => {
        const q = normalizeText(inputEl.value)
        if (!q) {
            box.style.display = 'none'
            return
        }
        const matches = options.filter((name) => normalizeText(name).includes(q))
        render(matches)
    }

    inputEl.addEventListener('input', onInput)
    inputEl.addEventListener('focus', onInput)
    inputEl.addEventListener('blur', () => {
        setTimeout(() => { box.style.display = 'none' }, 150)
    })
}

function normalizeTimeValue(value) {
    const raw = String(value || '').trim()
    if (!raw) return ''
    if (/^\d{1,2}$/.test(raw)) return `${raw.padStart(2, '0')}:00`
    const parts = raw.split(':')
    return `${String(parts[0] || '0').padStart(2, '0')}:${String(parts[1] || '0').padStart(2, '0')}`
}

function timeToMinutes(value) {
    const [h, m] = normalizeTimeValue(value).split(':').map(Number)
    return (h * 60) + m
}

function minutesToTime(minutes) {
    const normalized = ((minutes % 1440) + 1440) % 1440
    const h = Math.floor(normalized / 60)
    const m = normalized % 60
    return `${String(h).padStart(2, '0')}:${String(m).padStart(2, '0')}`
}

function formatPriceAR(value) {
    return '$ ' + new Intl.NumberFormat('es-AR', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    }).format(Number(value || 0))
}

function parsePriceAR(value) {
    if (typeof value === 'number') return value
    const normalized = String(value || '0')
        .replace(/\$/g, '')
        .replace(/\s/g, '')
        .replace(/\./g, '')
        .replace(',', '.')
    return Number(normalized) || 0
}

let bookingId
let updateData


document.addEventListener('DOMContentLoaded', (e) => {
    const { fecha } = getEditEls()
    const fechaActual = new Date().toISOString().split('T')[0]
    fecha.setAttribute('min', fechaActual)
    fecha.value = fechaActual;

    const { localidad } = getEditEls()
    setupLocalityAutocomplete(localidad, 'localitiesListAdmin')
})

document.addEventListener('click', async (e) => {
    if (e.target) {
        const editBtn = e.target.closest('#editarReservaBtn')
        const updateBtn = e.target.closest('#actualizarReserva')
        const cancelBtn = e.target.closest('#cancelarReservaEdit')

        const { fecha, horarioDesde, horarioHasta, cancha, inputMonto, telefono, nombre, localidad } = getEditEls()
        const rate = await getRate()

        const totalAmount = parsePriceAR(inputMonto.value)
        updateData = {
            fecha: fecha.value,
            cancha: cancha.value,
            horarioDesde: horarioDesde.value,
            horarioHasta: horarioHasta.value,
            total: totalAmount,
            parcial: totalAmount * rate / 100,
            localidad: localidad ? localidad.value : '',
        }

        if (editBtn) {
            bookingId = editBtn.dataset.id
            if (!bookingId) {
                alert('No se pudo obtener la informacion de la reserva. Intenta nuevamente.')
                return
            }

            const currentBooking = await getBooking(bookingId)
            if (!currentBooking) {
                alert('No se pudo obtener la informacion de la reserva. Intenta nuevamente.')
                return
            }

            fecha.value = currentBooking.date
            horarioDesde.value = currentBooking.time_from
            horarioHasta.value = currentBooking.time_until
            cancha.value = currentBooking.id_field
            telefono.value = currentBooking.phone
            nombre.value = currentBooking.name
            inputMonto.value = formatPriceAR(currentBooking.total)
            if (localidad) {
                localidad.value = currentBooking.locality || ''
            }

            editBookingModal.show()

        } else if (updateBtn) {
            const currentBooking = await getBooking(bookingId)

            updateData.bookingId = bookingId
            const totalAmount = parsePriceAR(inputMonto.value)
            updateData.total = totalAmount
            updateData.parcial = totalAmount * rate / 100
            updateData.diferencia = totalAmount - Number(currentBooking.payment || 0)
            updateData.pagoTotal = (totalAmount - Number(currentBooking.payment || 0)) == 0 ? 1 : 0


            if (fecha.value == '' || cancha.value == '' || horarioDesde.value == '' || horarioHasta.value == '' || nombre.value == '' || telefono.value == '') {
                alert('Debe completar todos los campos obligatorios.')
                return;
            }

            if (timeToMinutes(horarioDesde.value) >= timeToMinutes(horarioHasta.value) && timeToMinutes(horarioHasta.value) !== 0) {
                alert('El horario de inicio no puede ser mayor o igual al horario de fin.')
                return;
            }

            updateBooking(updateData)
        } else if (cancelBtn) {
            editBookingModal.hide()
        }
    }
})

document.addEventListener('change', async (e) => {
    if (e.target) {
        const { fecha, horarioDesde, horarioHasta, cancha, inputMonto } = getEditEls()
        if (e.target.id == 'horarioDesde') {

            const selectedField = await getField(cancha.value)
            const duration = Number(selectedField?.duration_minutes || selectedField?.block_minutes || 60)
            horarioHasta.value = minutesToTime(timeToMinutes(horarioDesde.value) + duration)

            inputMonto.value = 0

            await getAmount(cancha.value)

            await getTimeFromBookings()


        } else if (e.target.id == 'fecha') {
            horarioDesde.selectedIndex = 0
            horarioHasta.selectedIndex = 0
            cancha.selectedIndex = 0
            inputMonto.value = 0

        } else if (e.target.id == 'cancha') {

            await getAmount(cancha.value)

        }
    }
})

async function updateBooking(data) {
    editBookingModal.hide()

    modalSpinner.show()

    try {
        const response = await fetch(`${baseUrl}editBooking`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(data)
        });

        const responseData = await response.json();

        if (!response.ok || responseData.error) {
            alert(responseData.message || 'El horario seleccionado ya no esta disponible. Elegi otro e intenta nuevamente.')
            modalSpinner.hide()
            return
        }

        if (response.ok) {

            contentEditBookingResult.innerHTML = `
            <div class="modal-body modalResultPayment d-flex justify-content-center align-items-center flex-column" style="background-color: #157347; color: #fff">
                <h4 class="mb-5">Reserva editada!</h4>
                <i class="fa-regular fa-circle-check fa-2xl" style="margin-bottom: 20px;"></i>
            </div>`


            modalResult.show()

            setTimeout(() => { location.reload(true) }, 1000)


        }

    } catch (error) {
        console.error('Error:', error);
        throw error;
    }
}


async function getBooking(id) {
    try {
        const response = await fetch(`${baseUrl}getBooking/${id}`);

        const responseData = await response.json();

        if (responseData.data != '') {

            return responseData.data

        } else {
            alert('No se pudo obtener la información. Intenta nuevamente.');
        }

    } catch (error) {
        console.error('Error:', error);
        throw error;
    }
}


// Trae los horarios de las reservas hechas
async function getTimeFromBookings() {
    const { fecha } = getEditEls()
    const fechaValue = fecha.value

    try {
        const response = await fetch(`${baseUrl}getBookings/${fechaValue}`);
        const responseData = await response.json();

        if (responseData.data != '') {

            getFieldForTimeBookings(responseData.data)
        } else {
            alert('No se pudo obtener la información. Intenta nuevamente.');
        }

    } catch (error) {
        console.error('Error:', error);
        throw error;
    }
}

// Quita las canchas sin horario disponible seleccionado
async function getFieldForTimeBookings(timeBookings) {

    const { cancha, horarioDesde, horarioHasta } = getEditEls()
    const options = cancha.options;

    timeBookings.forEach(element => {
        const remove = element.time.includes(horarioDesde.value) && element.time.includes(horarioHasta.value);

        if (remove) {
            for (let i = 0; i < options.length; i++) {
                if (options[i].value == element.id_cancha) {
                    options[i].remove();

                    break;
                }
            }
        } else {
            let exists = false;

            for (let i = 0; i < options.length; i++) {
                if (options[i].value == element.id_cancha) {
                    exists = true;

                    break;
                }
            }

            if (!exists) {
                const newOption = new Option(element.nombre_cancha, element.id_cancha);
                cancha.appendChild(newOption);

            }

        }
    });

    const optionsArray = Array.from(cancha.options);

    optionsArray.sort((a, b) => {
        const valueA = parseFloat(a.value);
        const valueB = parseFloat(b.value);
        return valueA - valueB;
    });

    cancha.innerHTML = '';

    if (optionsArray.length == 1) {
        cancha.setAttribute('disabled', 'true')
        cancha.style.backgroundColor = '#bb2d3b'
        optionsArray[0].innerText = 'No hay canchas disponibles en este horario'
    } else {
        cancha.removeAttribute('disabled')
        cancha.style.backgroundColor = ''
        optionsArray[0].innerText = 'Canchas disponibles'
    }

    optionsArray.forEach(option => {
        cancha.appendChild(option);
    });
}

// Busca la cancha seleccionada para colocar valor
async function getField(id) {
    try {
        const response = await fetch(`${baseUrl}getField/${id}`);

        const responseData = await response.json();

        if (responseData.data != '') {

            return responseData.data

        } else {
            alert('No se pudo obtener la información. Intenta nuevamente.');
        }

    } catch (error) {
        console.error('Error:', error);
        throw error;
    }
}

async function getNocturnalTime() {
    try {
        const response = await fetch(`${baseUrl}getNocturnalTime`);
        const responseData = await response.json();

        if (responseData.data != '') {

            const nocturnalTime = { time: responseData.data }

            return nocturnalTime
        } else {
            alert('No se pudo obtener la información. Intenta nuevamente.');
        }
    } catch (error) {
        console.error('Error:', error);
        throw error;
    }
}

async function getAmount(field = "1") {
    try {
        const { horarioDesde, horarioHasta, inputMonto } = getEditEls()
        const nocturnalTime = await getNocturnalTime()
        const selectedField = await getField(field)

        if (nocturnalTime.time.includes(horarioDesde.value) && nocturnalTime.time.includes(horarioHasta.value)) {
            inputMonto.value = formatPriceAR(calculateAmount(horarioDesde.value, horarioHasta.value, selectedField.ilumination_value, selectedField))
        } else {
            inputMonto.value = formatPriceAR(calculateAmount(horarioDesde.value, horarioHasta.value, selectedField.value, selectedField))
        }
    } catch (error) {
        console.error('Error:', error);
        throw error;
    }
}

// Calcula el total $ de la reserva
function calculateAmount(from, until, amount, field = null) {
    let fromMinutes = timeToMinutes(from)
    let untilMinutes = timeToMinutes(until)
    if (untilMinutes <= fromMinutes) untilMinutes += 1440
    const minutes = untilMinutes - fromMinutes
    const duration = Number(field?.duration_minutes || field?.block_minutes || 60)
    const multiplier = (field?.service_type || '') === 'padel' ? Math.ceil(minutes / duration) : minutes / 60
    return Math.round(multiplier * Number(amount || 0))
}

async function getRate() {
    try {
        const response = await fetch(`${baseUrl}getRate`);
        const responseData = await response.json();


        if (responseData.data != '') {

            return responseData.data.value
        } else {
            alert('No se pudo obtener la información. Intenta nuevamente.');
        }
    } catch (error) {
        console.error('Error:', error);
        throw error;
    }
}

}
