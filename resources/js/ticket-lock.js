import { io } from 'socket.io-client';
let socket = null;
let currentUser = null;
let beat = null;
const states = new Map();

const log = (...args) => console.debug('[ticket-lock]', ...args);

function resetAll() {
    if (beat) clearInterval(beat);
    beat = null;
    socket?.disconnect();
    socket = null;
    currentUser = null;
    states.clear();
}

function ensureSocket(cfg) {
    if (socket && currentUser !== String(cfg.me)) {
        log('changement d\'utilisateur : nouvelle connexion');
        resetAll();
    }

    if (socket) {
        socket.auth = { token: cfg.token };
        return socket;
    }

    currentUser = String(cfg.me);
    socket = io(cfg.url, {
        auth: { token: cfg.token },
        transports: ['websocket'],
    });

    socket.on('connect', () => {
        log('connecté', socket.id);
        for (const [ticketId, state] of states) {
            socket.emit('ticket:join', { ticketId }, (res) => {
                state.lock = res?.lock ?? null;
                if (state.holding) acquireLock(ticketId);
            });
        }
    });

    socket.on('disconnect', (reason) => log('déconnecté', reason));

    socket.on('connect_error', (err) => {
        console.warn('[ticket-lock] connexion impossible :', err.message);
        if (!socket.active) setTimeout(() => socket?.connect(), 3000);
    });

    socket.on('lock:state', (payload) => {
        const state = states.get(String(payload.ticketId));
        if (state) state.lock = payload.lock;
    });

    beat = setInterval(() => {
        for (const [ticketId, state] of states) {
            if (state.holding) socket?.emit('lock:heartbeat', { ticketId });
        }
    }, 15000);

    return socket;
}

function joinTicket(ticketId) {
    let state = states.get(ticketId);
    if (state) return state;

    state = window.Alpine.reactive({ lock: null, holding: false });
    states.set(ticketId, state);

    if (socket?.connected) {
        socket.emit('ticket:join', { ticketId }, (res) => {
            state.lock = res?.lock ?? null;
        });
    }

    return state;
}

function acquireLock(ticketId) {
    return new Promise((resolve) => {
        if (!socket) return resolve(true);

        socket.timeout(3000).emit('lock:acquire', { ticketId }, (err, res) => {
            // Serveur injoignable : on ne bloque pas l'utilisateur (fail-open)
            if (err) {
                console.warn('[ticket-lock] pas de réponse du serveur, verrou non pris');
                return resolve(true);
            }

            const state = states.get(ticketId);

            if (res?.ok) {
                if (state) state.holding = true;
                return resolve(true);
            }

            if (state) state.lock = res?.lock ?? state.lock;
            window.dispatchEvent(new CustomEvent('compose-denied'));
            resolve(false);
        });
    });
}

function releaseLock(ticketId) {
    const state = states.get(ticketId);
    if (state) state.holding = false;
    socket?.emit('lock:release', { ticketId });
}

function ticketLock(cfg) {
    const ticketId = String(cfg.ticketId);

    return {
        state: null,

        get me() {
            return String(cfg.me);
        },

        get lockedByOther() {
            const lock = this.state?.lock;
            return !!lock && lock.userId !== this.me;
        },

        get lockedBy() {
            return this.state?.lock?.name ?? '';
        },

        init() {
            ensureSocket(cfg);
            this.state = joinTicket(ticketId);
        },

        destroy() {},

        acquire() {
            return acquireLock(ticketId);
        },

        release() {
            releaseLock(ticketId);
        },
    };
}

document.addEventListener('livewire:navigating', () => {
    for (const ticketId of states.keys()) {
        socket?.emit('ticket:leave', { ticketId });
    }
    states.clear();
});

document.addEventListener('visibilitychange', () => {
    if (!document.hidden && socket && !socket.connected) {
        log('onglet visible, reconnexion');
        socket.connect();
    }
});

window.ticketLockDebug = () => ({
    connected: socket?.connected ?? false,
    id: socket?.id ?? null,
    user: currentUser,
    tickets: Object.fromEntries([...states].map(([id, s]) => [id, { lock: s.lock, holding: s.holding }])),
});

window.ticketLock = ticketLock;

document.addEventListener('alpine:init', () => {
    window.Alpine.data('ticketLock', ticketLock);
});
