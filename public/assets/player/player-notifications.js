(function () {
    const page = document.body;
    if (!page || page.dataset.playerNotifications !== 'true') return;

    const menu = document.querySelector('[data-notification-menu]');
    const toggle = menu && menu.querySelector('[data-notification-toggle]');
    const dropdown = menu && menu.querySelector('[data-notification-dropdown]');
    const soundToggle = menu && menu.querySelector('[data-notification-sound-toggle]');
    const toast = document.querySelector('[data-notification-toast]');
    const endpoint = `${window.location.origin}/app/notificaciones/nuevas`;
    let lastId = Number(page.dataset.notificationLastId || 0);
    let audioContext = null;
    let audioUnlocked = false;

    const close = () => {
        if (!dropdown || !toggle) return;
        dropdown.hidden = true;
        toggle.setAttribute('aria-expanded', 'false');
    };

    if (toggle && dropdown) {
        toggle.addEventListener('click', function (event) {
            event.stopPropagation();
            dropdown.hidden = !dropdown.hidden;
            toggle.setAttribute('aria-expanded', dropdown.hidden ? 'false' : 'true');
        });
        document.addEventListener('click', function (event) {
            if (!menu.contains(event.target)) close();
        });
        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') close();
        });
    }

    const unlockAudio = () => {
        if (audioUnlocked || (!window.AudioContext && !window.webkitAudioContext)) return;
        const AudioCtor = window.AudioContext || window.webkitAudioContext;
        audioContext = audioContext || new AudioCtor();
        if (audioContext.state === 'suspended') audioContext.resume();
        audioUnlocked = true;
        updateSoundToggle();
    };
    document.addEventListener('pointerdown', unlockAudio, { once: true, passive: true });
    document.addEventListener('keydown', unlockAudio, { once: true });

    const updateSoundToggle = () => {
        if (!soundToggle) return;
        soundToggle.innerHTML = audioUnlocked
            ? '<i class="fa-solid fa-volume-high" aria-hidden="true"></i> Sonidos activados'
            : '<i class="fa-solid fa-volume-xmark" aria-hidden="true"></i> Activar sonidos';
        soundToggle.setAttribute('aria-pressed', audioUnlocked ? 'true' : 'false');
    };
    updateSoundToggle();
    if (soundToggle) {
        soundToggle.addEventListener('click', function (event) {
            event.stopPropagation();
            unlockAudio();
            if (audioContext) {
                const oscillator = audioContext.createOscillator();
                const gain = audioContext.createGain();
                const now = audioContext.currentTime;
                oscillator.frequency.value = 660;
                gain.gain.setValueAtTime(.0001, now);
                gain.gain.exponentialRampToValueAtTime(.08, now + .01);
                gain.gain.exponentialRampToValueAtTime(.0001, now + .16);
                oscillator.connect(gain).connect(audioContext.destination);
                oscillator.start(now);
                oscillator.stop(now + .18);
            }
        });
    }

    const playTone = (notification) => {
        if (!audioUnlocked || !audioContext) return;
        const message = `${notification.title || ''} ${notification.message || ''}`.toLowerCase();
        const now = audioContext.currentTime;
        if (message.includes('pádel') || message.includes('padel')) {
            const oscillator = audioContext.createOscillator();
            const gain = audioContext.createGain();
            oscillator.type = 'triangle';
            oscillator.frequency.setValueAtTime(720, now);
            oscillator.frequency.exponentialRampToValueAtTime(260, now + .12);
            gain.gain.setValueAtTime(.0001, now);
            gain.gain.exponentialRampToValueAtTime(.16, now + .008);
            gain.gain.exponentialRampToValueAtTime(.0001, now + .18);
            oscillator.connect(gain).connect(audioContext.destination);
            oscillator.start(now);
            oscillator.stop(now + .2);
            return;
        }
        const isFootball = message.includes('fútbol') || message.includes('futbol');
        if (isFootball) {
            const notes = [392, 523, 659, 784];
            notes.forEach((frequency, index) => {
                const oscillator = audioContext.createOscillator();
                const gain = audioContext.createGain();
                const start = now + index * .105;
                oscillator.type = index === 0 ? 'sawtooth' : 'triangle';
                oscillator.frequency.setValueAtTime(frequency, start);
                gain.gain.setValueAtTime(.0001, start);
                gain.gain.exponentialRampToValueAtTime(index === 0 ? .18 : .22, start + .018);
                gain.gain.exponentialRampToValueAtTime(.0001, start + .42);
                oscillator.connect(gain).connect(audioContext.destination);
                oscillator.start(start);
                oscillator.stop(start + .45);
            });
            return;
        }
        const oscillator = audioContext.createOscillator();
        const gain = audioContext.createGain();
        oscillator.type = 'sine';
        oscillator.frequency.setValueAtTime(520, now);
        gain.gain.setValueAtTime(.0001, now);
        gain.gain.exponentialRampToValueAtTime(.12, now + .02);
        gain.gain.exponentialRampToValueAtTime(.0001, now + .32);
        oscillator.connect(gain).connect(audioContext.destination);
        oscillator.start(now);
        oscillator.stop(now + .35);
    };

    const addNotification = (notification) => {
        if (!dropdown || !notification || !notification.id) return;
        const empty = dropdown.querySelector('.notification-empty');
        if (empty) empty.remove();
        let list = dropdown.querySelector('.notification-list');
        if (!list) { list = document.createElement('div'); list.className = 'notification-list'; dropdown.appendChild(list); }
        const link = document.createElement('a');
        link.className = 'notification-item is-unread';
        link.href = `${window.location.origin}/app/notificaciones/leer/${Number(notification.id)}`;
        link.innerHTML = '<span class="notification-item-icon"><i class="fa-solid fa-bell" aria-hidden="true"></i></span><span class="notification-item-content"><strong></strong><span></span><small></small></span>';
        link.querySelector('strong').textContent = notification.title || 'Nueva notificación';
        link.querySelector('.notification-item-content span').textContent = notification.message || '';
        link.querySelector('small').textContent = new Date(String(notification.created_at).replace(' ', 'T')).toLocaleString('es-AR', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' });
        list.prepend(link);
        const headerCount = dropdown.querySelector('.notification-dropdown-head span');
        if (headerCount) headerCount.textContent = 'Nuevas notificaciones';
        if (toggle) {
            let badge = toggle.querySelector('.notification-badge');
            if (!badge) { badge = document.createElement('span'); badge.className = 'notification-badge'; toggle.appendChild(badge); }
            const current = Number(badge.textContent.replace('+', '')) || 0;
            badge.textContent = current + 1 > 99 ? '99+' : String(current + 1);
        }
    };

    const showToast = (notification) => {
        if (!toast) return;
        toast.innerHTML = '<i class="fa-solid fa-bell" aria-hidden="true"></i><span><strong></strong><small></small></span>';
        toast.querySelector('strong').textContent = notification.title || 'Nueva notificación';
        toast.querySelector('small').textContent = notification.message || '';
        toast.hidden = false;
        window.clearTimeout(showToast.timer);
        showToast.timer = window.setTimeout(() => { toast.hidden = true; }, 5200);
    };

    const poll = () => {
        fetch(`${endpoint}?desde=${lastId}`, { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
            .then((response) => response.ok ? response.json() : null)
            .then((data) => {
                if (!data || !Array.isArray(data.notifications)) return;
                data.notifications.forEach((notification) => {
                    lastId = Math.max(lastId, Number(notification.id) || 0);
                    addNotification(notification); showToast(notification); playTone(notification);
                });
            }).catch(() => {});
    };
    window.setInterval(poll, 12000);
}());
