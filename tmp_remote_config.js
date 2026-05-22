ï»¿const isLocalhost = window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1'
const apiBaseUrlMeta = document.querySelector('meta[name="app-base-url"]')
const webBaseUrlMeta = document.querySelector('meta[name="app-web-base-url"]')
//const apiBaseUrl = apiBaseUrlMeta?.content ? apiBaseUrlMeta.content : (isLocalhost ? 'http://localhost:8080/' : 'https://alfagestion.com.ar/cancha-test/')
//const apiBaseUrl = apiBaseUrlMeta?.content ? apiBaseUrlMeta.content : (isLocalhost ? 'https://audrina-unexpectable-swaggeringly.ngrok-free.dev/' : 'https://alfagestion.com.ar/cancha-test/')
const apiBaseUrl = apiBaseUrlMeta?.content ? apiBaseUrlMeta.content : (isLocalhost ? 'https://audrina-unexpectable-swaggeringly.ngrok-free.dev/' : 'https://alfagestion.com.ar/cancha_pruebas/')

const webBaseUrl = webBaseUrlMeta?.content ? webBaseUrlMeta.content : `${window.location.origin}/`
const baseUrl = apiBaseUrl
//const publicKeyMP = "APP_USR-aac9eac0-3383-456a-b41d-a591b19d4962"
const publicKeyMpEl = document.getElementById('publicKeyMp')
const publicKeyMp = publicKeyMpEl ? publicKeyMpEl.value : ''

// Fallback anti-cache: si algun JS viejo muestra mensaje generico,
// lo reemplazamos por uno especifico segun la accion ejecutada.
let lastClickedActionId = ''
document.addEventListener('click', (e) => {
    const t = e.target
    if (!t || !t.id) return
    lastClickedActionId = t.id
})

const originalAlert = window.alert.bind(window)
let appAlertModal = null
let appAlertModalBody = null

function ensureAppAlertModal() {
    if (appAlertModal && appAlertModalBody) return true
    if (typeof bootstrap === 'undefined') return false

    const wrapper = document.createElement('div')
    wrapper.innerHTML = `
        <div class="modal fade" id="appAlertModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Aviso</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                    </div>
                    <div class="modal-body" id="appAlertModalBody"></div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Aceptar</button>
                    </div>
                </div>
            </div>
        </div>
    `
    document.body.appendChild(wrapper.firstElementChild)

    const modalEl = document.getElementById('appAlertModal')
    appAlertModalBody = document.getElementById('appAlertModalBody')
    appAlertModal = new bootstrap.Modal(modalEl)
    return true
}

function showAppAlert(text) {
    if (!ensureAppAlertModal()) {
        originalAlert(text)
        return
    }
    appAlertModalBody.textContent = text
    appAlertModal.show()
}

window.alert = function patchedAlert(message) {
    let text = String(message || '')
    const isGenericSuccess = text === 'Operacion realizada correctamente.' || text === 'OperaciÃ³n realizada correctamente.' || text === 'OperaciÃÂ³n realizada correctamente.'

    if (isGenericSuccess) {
        if (lastClickedActionId === 'confirmCancelReservations') {
            text = 'Cierre de cancha informado correctamente.'
        } else if (lastClickedActionId === 'setOfferTrue') {
            text = 'Oferta asignada correctamente.'
        } else if (lastClickedActionId === 'setOfferFalse') {
            text = 'Oferta removida correctamente.'
        } else if (lastClickedActionId === 'saveOfferRate') {
            text = 'Oferta actualizada correctamente.'
        } else if (lastClickedActionId === 'saveRate') {
            text = 'Porcentaje actualizado correctamente.'
        } else {
            text = 'Operacion completada correctamente.'
        }
    }

    return showAppAlert(text)
}

// Remove third-party floating chat/WhatsApp widgets injected at runtime.
;(function blockFloatingWidgets() {
    const SELECTORS = [
        '#wh-widget-send-button',
        '#chat-widget-container',
        '#chat-application',
        '#launcher',
        '.joinchat',
        '.chaty-widget',
        '.chaty-whatsapp',
        '.floating-wpp',
        '.wpp-widget',
        '.whatsapp-widget',
        '.whatsapp-float',
        '[id*="whatsapp-chat"]',
        '[class*="whatsapp"][class*="float"]',
        '[class*="chat"][class*="floating"]',
        'iframe[src*="tawk.to"]',
        'iframe[src*="tidio"]',
        'iframe[src*="smartsupp"]',
        'iframe[src*="zendesk"]',
        'iframe[src*="intercom"]',
        'iframe[src*="crisp.chat"]',
        'iframe[src*="jivo"]',
        'iframe[src*="drift.com"]',
        'iframe[src*="donweb"]',
        '[id*="donweb"][style*="position: fixed"]',
        '[class*="donweb"][style*="position: fixed"]',
    ]

    function looksLikeFloatingWaAnchor(el) {
        if (!el || el.tagName !== 'A') return false
        const href = (el.getAttribute('href') || '').toLowerCase()
        if (!(href.includes('wa.me') || href.includes('api.whatsapp.com') || href.includes('whatsapp'))) return false
        const style = window.getComputedStyle(el)
        const isFixed = style.position === 'fixed'
        const bottom = parseInt(style.bottom || '0', 10)
        const right = parseInt(style.right || '0', 10)
        return isFixed && bottom >= 0 && right >= 0
    }

    function removeWidgetNodes(root = document) {
        SELECTORS.forEach((selector) => {
            root.querySelectorAll(selector).forEach((el) => el.remove())
        })
        root.querySelectorAll('a[href]').forEach((el) => {
            if (looksLikeFloatingWaAnchor(el)) el.remove()
        })
    }

    function run() {
        removeWidgetNodes(document)
        const observer = new MutationObserver((mutations) => {
            for (const mutation of mutations) {
                mutation.addedNodes.forEach((node) => {
                    if (!(node instanceof Element)) return
                    if (node.matches && SELECTORS.some((s) => node.matches(s))) {
                        node.remove()
                        return
                    }
                    removeWidgetNodes(node)
                })
            }
        })
        observer.observe(document.documentElement, { childList: true, subtree: true })
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', run)
    } else {
        run()
    }
})()

