const fechaDesde = document.getElementById('buscarFechaDesde')
const fechaHasta = document.getElementById('buscarFechaHasta')
const selectUser = document.getElementById('selectUserReport')
const generateReportButton = document.getElementById('generateReport')
const downloadReportButton = document.getElementById('downloadReport')
const downloadPaymentsReportButton = document.getElementById('downloadPaymentsReport')
const rateModal = new bootstrap.Modal('#rateModal')
const generateReportModal = new bootstrap.Modal('#generateReportModal')

function formatPriceAR(value) {
    return '$ ' + new Intl.NumberFormat('es-AR', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    }).format(Number(value || 0))
}
const switchPaymentsMp = document.getElementById('checkPaymetsMp')
const reservePaymentsButton = document.getElementById('reservePayments')
const searchReportsButton = document.getElementById('searchReports')
const selectDateRange = document.getElementById('selectDateRange')
const printReportButton = document.getElementById('printReport')

document.addEventListener('DOMContentLoaded', (e) => {
    const fechaActual = new Date()
    const hoy = fechaActual.toISOString().split('T')[0]

    fechaDesde.value = hoy
    fechaHasta.value = hoy
})

selectDateRange.addEventListener('input', () => {
    const fechaActual = new Date();
    const primerDiaDelMesActual = new Date(fechaActual.getFullYear(), fechaActual.getMonth(), 1);

    if (selectDateRange.value === 'FD') {
        const fechaAnterior = new Date(fechaActual)
        fechaAnterior.setDate(fechaActual.getDate());

        fechaDesde.value = fechaAnterior.toISOString().split('T')[0];
        fechaHasta.value = fechaActual.toISOString().split('T')[0];
    } else if (selectDateRange.value === 'MA') {
        const primerDiaDelMes = new Date(fechaActual.getFullYear(), fechaActual.getMonth(), 1);
        const ultimoDiaDelMes = new Date(fechaActual.getFullYear(), fechaActual.getMonth() + 1, 0);

        fechaDesde.value = primerDiaDelMes.toISOString().split('T')[0];
        fechaHasta.value = ultimoDiaDelMes.toISOString().split('T')[0];
    } else if (selectDateRange.value === 'MP') {
        const fechaMesPasado = new Date(fechaActual);
        fechaMesPasado.setMonth(fechaMesPasado.getMonth() - 1);

        const primerDiaDelMesPasado = new Date(fechaMesPasado.getFullYear(), fechaMesPasado.getMonth(), 1);
        const ultimoDiaDelMesPasado = new Date(fechaMesPasado.getFullYear(), fechaMesPasado.getMonth() + 1, 0);

        fechaDesde.value = primerDiaDelMesPasado.toISOString().split('T')[0];
        fechaHasta.value = ultimoDiaDelMesPasado.toISOString().split('T')[0];
    } else if (selectDateRange.value === 'SA') {
        const fechaInicioSemanaActual = new Date(fechaActual);
        const diaSemanaActual = fechaActual.getDay(); 

        fechaInicioSemanaActual.setDate(fechaActual.getDate() - diaSemanaActual + 1);
        const fechaFinSemanaActual = new Date(fechaInicioSemanaActual);
        fechaFinSemanaActual.setDate(fechaInicioSemanaActual.getDate() + 6);

        fechaDesde.value = fechaInicioSemanaActual.toISOString().split('T')[0];
        fechaHasta.value = fechaFinSemanaActual.toISOString().split('T')[0];
    } else if (selectDateRange.value === 'SP') {
        const fechaInicioSemanaPasada = new Date(fechaActual);
        const diaSemanaActual = fechaActual.getDay();

        fechaInicioSemanaPasada.setDate(fechaActual.getDate() - diaSemanaActual - 6);
        const fechaFinSemanaPasada = new Date(fechaInicioSemanaPasada);
        fechaFinSemanaPasada.setDate(fechaInicioSemanaPasada.getDate() + 6);

        fechaDesde.value = fechaInicioSemanaPasada.toISOString().split('T')[0];
        fechaHasta.value = fechaFinSemanaPasada.toISOString().split('T')[0];
    }
});

switchPaymentsMp.addEventListener('change', (e) => {
    if (switchPaymentsMp.checked) {
        reservePaymentsButton.classList.remove('d-none')
        searchReportsButton.classList.add('d-none')
        generateReportButton.classList.add('d-none')
        downloadReportButton.classList.add('d-none')
        downloadPaymentsReportButton.classList.add('d-none')
        selectUser.classList.add('d-none')

    } else {
        searchReportsButton.classList.remove('d-none')
        reservePaymentsButton.classList.add('d-none')
        generateReportButton.classList.add('d-none')
        downloadReportButton.classList.add('d-none')
        downloadPaymentsReportButton.classList.add('d-none')
        selectUser.classList.remove('d-none')


    }
})


document.addEventListener('change', (e) => {
    if (e.target) {
        if (e.target.id == 'selectUserReport') {
            generateReportButton.classList.add('d-none')
            downloadReportButton.classList.add('d-none')
            reservePaymentsButton.classList.add('d-none')
            searchReportsButton.classList.remove('d-none')
            switchPaymentsMp.checked = false
        }
    }
})


document.addEventListener('click', async (e) => {
    if (e.target) {
        if (e.target.id == 'searchReports') {

            let data = {
                fechaDesde: fechaDesde.value,
                fechaHasta: fechaHasta.value,
                user: selectUser.value,
            }
            const tableReports = document.getElementById('tableReports')
            generateReportButton.classList.remove('d-none')
            downloadReportButton.classList.remove('d-none')
            tableReports.classList.remove('d-none')
            tableReservations.classList.add('d-none')

            getReports(data)
        } else if (e.target.id == 'nav-reports-tab') {
            const tableReports = document.getElementById('tableReports')
            const tableReservations = document.getElementById('tableReservations')

            tableReservations.classList.add('d-none')
            tableReports.classList.add('d-none')
            generateReportButton.classList.add('d-none')
            downloadReportButton.classList.add('d-none')


        } else if (e.target.id == 'openRateModal') {
            rateModal.show()

        } else if (e.target.id == 'generateReport') {
            generateReportModal.show()

        } else if (e.target.id == 'reservePayments') {
            const tableReservations = document.getElementById('tableReservations')

            let data = {
                fechaDesde: fechaDesde.value,
                fechaHasta: fechaHasta.value,
            }

            tableReports.classList.add('d-none')
            tableReservations.classList.remove('d-none')
            generateReportButton.classList.remove('d-none')
            downloadPaymentsReportButton.classList.remove('d-none')

            getMpPayments(data)
        }
        else if (e.target.id == 'downloadReport') {
            const buscarFechaDesde = fechaDesde.value
            const buscarFechaHasta = fechaHasta.value
            const idUser = selectUser.value == '' ? 'all' : selectUser.value

            const a = document.createElement("a")
            a.href = `${webBaseUrl}generateReportPdf/${idUser}/${buscarFechaDesde}/${buscarFechaHasta}`
            a.target = "_blank"
            a.click()

        } else if (e.target.id == 'downloadPaymentsReport') {
            const buscarFechaDesde = fechaDesde.value
            const buscarFechaHasta = fechaHasta.value
            const idUser = selectUser.value == '' ? 'all' : selectUser.value

            const a = document.createElement("a")
            a.href = `${webBaseUrl}generatePaymentsReportPdf/${buscarFechaDesde}/${buscarFechaHasta}`
            a.target = "_blank"
            a.click()
        } else if (e.target.id == 'printReport') {
            const resumePayments = document.querySelector('.paymentsMethodsResume')
            if (!resumePayments) return

            const resumenHtml = resumePayments.innerHTML.trim()
            if (!resumenHtml) return

            const formatDate = (iso) => {
                if (!iso || typeof iso !== 'string' || !iso.includes('-')) return iso || ''
                const [y, m, d] = iso.split('-')
                if (!y || !m || !d) return iso
                return `${d}/${m}/${y}`
            }

            const buscarFechaDesde = fechaDesde?.value || ''
            const buscarFechaHasta = fechaHasta?.value || ''
            const desdeLabel = formatDate(buscarFechaDesde)
            const hastaLabel = formatDate(buscarFechaHasta)
            const rangoLabel = buscarFechaDesde && buscarFechaHasta
                ? `Desde ${desdeLabel} hasta ${hastaLabel}`
                : ''

            const selectedUser = selectUser?.value || ''
            const selectedUserLabel = selectedUser
                ? `Usuario: ${selectUser.options[selectUser.selectedIndex]?.text || selectedUser}`
                : 'Usuario: Todos'

            const printWindow = window.open('', '_blank', 'width=900,height=700')
            if (!printWindow) return

            const html = `
                <!doctype html>
                <html>
                <head>
                    <meta charset="utf-8" />
                    <title>Resumen</title>
                    <style>
                        * { box-sizing: border-box; }
                        @page { size: 80mm auto; margin: 3mm; }
                        html, body { width: 74mm; margin: 0; padding: 0; }
                        body { font-family: Arial, sans-serif; color: #111; }
                        h1 { font-size: 16px; margin: 0 0 8px 0; }
                        .meta { font-size: 11px; color: #444; margin-bottom: 10px; }
                        .resume { border: 1px solid #d0d0d0; padding: 8px; border-radius: 4px; width: 100%; }
                        .resume p { margin: 4px 0; font-size: 12px; }
                        .resume hr { border: none; border-top: 1px solid #ddd; margin: 8px 0; }
                    </style>
                </head>
                <body>
                    <h1>Resumen</h1>
                    <div class="meta">${selectedUserLabel}${rangoLabel ? ' | ' + rangoLabel : ''}</div>
                    <div class="resume">${resumenHtml}</div>
                    <script>
                        window.onload = function() {
                            window.print();
                            window.close();
                        };
                    </script>
                </body>
                </html>
            `
            printWindow.document.open()
            printWindow.document.write(html)
            printWindow.document.close()
        }
    }
})

async function getMpPayments(data) {
    try {
        const response = await fetch(`${baseUrl}getMpPayments`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(data)
        });

        const responseData = await response.json();

        fillReservations(responseData.data)

    } catch (error) {
        console.error('Error:', error);
        throw error;
    }
}


async function fillReservations(data) {

    const divReservas = document.querySelector('.divReservas')
    const resumePayments = document.querySelector('.paymentsMethodsResume')

    let tr = ''
    let resume = ''
    let totalReservations = 0

    data.forEach(pago => {
        tr += `
        <tr >
            <td>${pago.fecha}</th>
            <td>${formatPriceAR(pago.reserva)}</td>
        </tr>
    `

        totalReservations += Number(pago.reserva)
    })

    resume = `
        <p>Total: <b>${formatPriceAR(totalReservations)}</b></p>
    `

    divReservas.innerHTML = tr
    resumePayments.innerHTML = resume
}


async function getReports(data) {
    try {
        const response = await fetch(`${baseUrl}getReports`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(data)
        });

        const responseData = await response.json();

        fillTable(responseData.data)

    } catch (error) {
        console.error('Error:', error);
        throw error;
    }
}

async function getReportsForPdf(data) {
    try {
        const response = await fetch(`${baseUrl}getReports`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(data)
        });

        const responseData = await response.json();

        return responseData.data

    } catch (error) {
        console.error('Error:', error);
        throw error;
    }
}

async function fillTable(data) {
    const divTr = document.querySelector('.divTr')
    const resumePayments = document.querySelector('.paymentsMethodsResume')
    const reportsCount = document.getElementById('reportsCount')

    let tr = ''
    let resume = ''
    let efectivo = 0
    let transferencia = 0
    let mercadoPago = 0
    let reservasCount = 0

    const formatMetodo = (m) => {
        if (m === 'mercado_pago' || m === 'Mercado Pago') return 'Mercado Pago'
        if (m === 'efectivo') return 'Efectivo'
        if (m === 'transferencia') return 'Transferencia'
        return m || 'N/D'
    }

    const groups = new Map()
    data.forEach((pago, index) => {
        const fallbackKey = `${pago.fecha || ''}-${pago.cliente || ''}-${pago.telefonoCliente || ''}-${pago.totalReserva || ''}`
        const key = pago.bookingId ? String(pago.bookingId) : (fallbackKey || `no-booking-${index}`)
        if (!groups.has(key)) {
            groups.set(key, {
                bookingId: pago.bookingId || null,
                fecha: pago.fecha,
                usuario: pago.usuario,
                cliente: pago.cliente,
                telefono: pago.telefonoCliente,
                totalReserva: pago.totalReserva ? Number(pago.totalReserva) : null,
                pagos: [],
            })
        }
        const group = groups.get(key)
        group.pagos.push({
            metodo: pago.metodoPago,
            monto: Number(pago.pago),
        })
    })

    groups.forEach((g) => {
        reservasCount += 1
        const totalPagado = g.pagos.reduce((acc, p) => acc + Number(p.monto || 0), 0)
        const totalReserva = g.totalReserva ?? totalPagado
        const saldo = totalReserva - totalPagado
        const methods = Array.from(new Set(g.pagos.map(p => formatMetodo(p.metodo))))
        const methodSummary = methods.length > 1 ? methods.join(' + ') : (methods[0] || 'N/D')

        tr += `
        <tr class="report-summary" data-booking="${g.bookingId ?? ''}">
            <td>${g.fecha}</th>
            <td>${g.usuario}</td>
            <td>${formatPriceAR(totalReserva)}</td>
            <td>${methodSummary}</td>
            <td>${g.cliente}</td>
            <td>${g.telefono}</td>
        </tr>
        <tr class="report-detail d-none" data-booking="${g.bookingId ?? ''}">
            <td colspan="6">
                <div class="report-detail-box">
                    <div><strong>Pagado:</strong> ${formatPriceAR(totalPagado)}</div>
                    <div><strong>Saldo:</strong> ${formatPriceAR(saldo)}</div>
                    <div class="report-detail-list">
                        ${g.pagos.map(p => `<div>${formatMetodo(p.metodo)}: ${formatPriceAR(p.monto)}</div>`).join('')}
                    </div>
                </div>
            </td>
        </tr>
        `

        g.pagos.forEach((pago) => {
            if (pago.metodo === "efectivo") {
                efectivo += Number(pago.monto)
            } else if (pago.metodo === "transferencia") {
                transferencia += Number(pago.monto)
            } else if (pago.metodo === "mercado_pago" || pago.metodo === "Mercado Pago") {
                mercadoPago += Number(pago.monto)
            }
        })
    })

    resume = `
        <p>Reservas: <b>${reservasCount}</b></p>
        <p>Efectivo: <b>${formatPriceAR(efectivo)}</b></p>
        <p>Mercado Pago: <b>${formatPriceAR(mercadoPago)}</b></p>
        <p>Transferencia: <b>${formatPriceAR(transferencia)}</b></p>
        <hr>
        <p>Total: <b>${formatPriceAR(efectivo + mercadoPago + transferencia)}</b></p>
    `

    divTr.innerHTML = tr
    resumePayments.innerHTML = resume
    if (reportsCount) {
        reportsCount.textContent = `Reservas: ${reservasCount}`
    }
}

document.addEventListener('click', (e) => {
    const row = e.target.closest('.report-summary')
    if (!row) return
    const bookingId = row.dataset.booking
    const detailRow = document.querySelector(`.report-detail[data-booking="${bookingId}"]`)
    if (detailRow) {
        detailRow.classList.toggle('d-none')
    }
})
