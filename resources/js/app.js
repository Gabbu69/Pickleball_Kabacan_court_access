import './bootstrap';

import Alpine from 'alpinejs';

document.documentElement.classList.add('js');

window.Alpine = Alpine;

Alpine.data('siteShell', () => ({
    open: false,
    motionPaused: window.localStorage.getItem('kpp-motion-paused') === 'true',

    init() {
        document.documentElement.dataset.motionPaused = this.motionPaused ? 'true' : 'false';
    },

    toggleMotion() {
        this.motionPaused = !this.motionPaused;
        window.localStorage.setItem('kpp-motion-paused', this.motionPaused ? 'true' : 'false');
        document.documentElement.dataset.motionPaused = this.motionPaused ? 'true' : 'false';
        document.dispatchEvent(new CustomEvent('kpp:motion-preference', {
            detail: { paused: this.motionPaused },
        }));
    },
}));

Alpine.data('availabilityPicker', (courtSlug, initialDate) => ({
    courtSlug,
    date: initialDate,
    slots: [],
    selected: null,
    waitlistSelection: null,
    loading: false,
    loaded: false,
    error: '',
    requestController: null,

    init() {
        this.load();
    },

    async load() {
        this.requestController?.abort();
        this.requestController = new AbortController();
        this.loading = true;
        this.loaded = false;
        this.selected = null;
        this.waitlistSelection = null;
        this.error = '';

        try {
            const response = await fetch(
                `/courts/${encodeURIComponent(this.courtSlug)}/availability?date=${encodeURIComponent(this.date)}`,
                {
                    headers: { Accept: 'application/json' },
                    signal: this.requestController.signal,
                },
            );

            if (!response.ok) {
                throw new Error(response.status === 422
                    ? 'Choose a date within the next 60 days.'
                    : 'The live schedule could not be loaded.');
            }

            const payload = await response.json();
            this.slots = Array.isArray(payload.slots) ? payload.slots : [];
            this.loaded = true;
        } catch (error) {
            if (error.name !== 'AbortError') {
                this.error = error.message;
                this.slots = [];
                this.loaded = true;
            }
        } finally {
            if (!this.requestController?.signal.aborted) {
                this.loading = false;
            }
        }
    },
}));

Alpine.start();

const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
const motionIsPaused = () => prefersReducedMotion || document.documentElement.dataset.motionPaused === 'true';

const initialiseReveals = () => {
    const elements = document.querySelectorAll('.reveal');

    if (motionIsPaused() || !('IntersectionObserver' in window)) {
        elements.forEach((element) => element.classList.add('is-visible'));
        return;
    }

    const observer = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
            if (!entry.isIntersecting) {
                return;
            }

            entry.target.classList.add('is-visible');
            observer.unobserve(entry.target);
        });
    }, {
        rootMargin: '0px 0px -8% 0px',
        threshold: 0.12,
    });

    elements.forEach((element, index) => {
        element.style.setProperty('--reveal-delay', `${Math.min(index % 4, 3) * 55}ms`);
        observer.observe(element);
    });
};

const initialiseHeroMotion = () => {
    const section = document.querySelector('[data-hero-section]');
    const stage = document.querySelector('[data-hero-motion]');

    if (!section || !stage || motionIsPaused()) {
        return;
    }

    let ticking = false;

    const update = () => {
        const rect = section.getBoundingClientRect();
        const distance = Math.max(section.offsetHeight - window.innerHeight * 0.2, 1);
        const progress = Math.min(1, Math.max(0, -rect.top / distance));
        const compact = window.innerWidth < 720;

        stage.style.setProperty('--scene-y', `${progress * (compact ? 8 : 18)}px`);
        stage.style.setProperty('--scene-tilt', `${-5 + progress * (compact ? 2 : 4)}deg`);
        stage.style.setProperty('--scene-scale', `${1 + progress * 0.025}`);
        ticking = false;
    };

    const schedule = () => {
        if (ticking) {
            return;
        }

        ticking = true;
        window.requestAnimationFrame(update);
    };

    update();
    window.addEventListener('scroll', schedule, { passive: true });
    window.addEventListener('resize', schedule);
};

const initialiseMotionLoops = () => {
    const loops = document.querySelectorAll('[data-motion-loop]');

    if (!loops.length || motionIsPaused() || !('IntersectionObserver' in window)) {
        return;
    }

    const applyState = (element, inView) => {
        element.dataset.motionInView = inView ? 'true' : 'false';
        element.classList.toggle('is-motion-paused', document.hidden || !inView);
    };

    const observer = new IntersectionObserver((entries) => {
        entries.forEach((entry) => applyState(entry.target, entry.isIntersecting));
    }, {
        rootMargin: '120px 0px',
        threshold: 0.02,
    });

    loops.forEach((element) => {
        element.classList.add('is-motion-paused');
        observer.observe(element);
    });

    document.addEventListener('visibilitychange', () => {
        loops.forEach((element) => {
            const inView = element.dataset.motionInView === 'true';
            element.classList.toggle('is-motion-paused', document.hidden || !inView);
        });
    });
};

let leafletPromise;

const loadLeaflet = async () => {
    leafletPromise ??= Promise.all([
        import('leaflet'),
        import('leaflet/dist/leaflet.css'),
    ]).then(([module]) => module.default);

    return leafletPromise;
};

const markerIcon = (L, label = 'K') => L.divIcon({
    className: '',
    html: `<div class="map-marker"><span>${label.replace(/[<>&"']/g, '')}</span></div>`,
    iconSize: [40, 40],
    iconAnchor: [20, 38],
    popupAnchor: [0, -37],
});

const tileLayer = (L) => L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
    maxZoom: 19,
});

const initialiseDirectoryMap = async () => {
    const element = document.querySelector('[data-court-map]');
    const dataElement = document.getElementById('court-map-data');

    if (!element || !dataElement) {
        return;
    }

    let courts = [];

    try {
        courts = JSON.parse(dataElement.textContent || '[]');
    } catch {
        courts = [];
    }

    if (!courts.length) {
        return;
    }

    const L = await loadLeaflet();
    element.replaceChildren();
    const map = L.map(element, {
        scrollWheelZoom: false,
        zoomControl: true,
    });
    tileLayer(L).addTo(map);

    const bounds = [];

    courts.forEach((court, index) => {
        const coordinates = [Number(court.lat), Number(court.lng)];

        if (!coordinates.every(Number.isFinite)) {
            return;
        }

        bounds.push(coordinates);
        const safeName = String(court.name || 'Kabacan court');
        const safeMeta = [court.environment, court.barangay].filter(Boolean).join(' · ');
        const popup = document.createElement('div');
        popup.className = 'map-popup';

        const title = document.createElement('strong');
        title.textContent = safeName;
        const meta = document.createElement('span');
        meta.textContent = safeMeta;
        const link = document.createElement('a');
        link.href = court.url;
        link.textContent = 'View court';
        popup.append(title, meta, link);

        L.marker(coordinates, {
            icon: markerIcon(L, safeName.slice(0, 1).toUpperCase()),
            riseOnHover: true,
        })
            .addTo(map)
            .bindPopup(popup)
            .getElement()
            ?.style.setProperty('animation-delay', `${index * 90}ms`);
    });

    if (bounds.length === 1) {
        map.setView(bounds[0], 16);
    } else {
        map.fitBounds(bounds, { padding: [42, 42], maxZoom: 16 });
    }

    const viewObserver = new MutationObserver(() => {
        if (document.body.dataset.directoryView === 'map') {
            window.setTimeout(() => map.invalidateSize(), 40);
        }
    });
    viewObserver.observe(document.body, { attributes: true, attributeFilter: ['data-directory-view'] });
};

const initialiseSingleMap = async () => {
    const element = document.querySelector('[data-single-map]');

    if (!element) {
        return;
    }

    const hasCoordinates = element.dataset.lat?.trim() && element.dataset.lng?.trim();
    const coordinates = [Number(element.dataset.lat), Number(element.dataset.lng)];

    if (!hasCoordinates || !coordinates.every(Number.isFinite)) {
        element.innerHTML = '<div class="map-empty-state">A verified map pin is not available.</div>';
        return;
    }

    const L = await loadLeaflet();
    const name = element.dataset.name || 'Kabacan court';
    const map = L.map(element, {
        center: coordinates,
        zoom: 17,
        scrollWheelZoom: false,
    });

    tileLayer(L).addTo(map);
    L.marker(coordinates, {
        icon: markerIcon(L, name.slice(0, 1).toUpperCase()),
    }).addTo(map).bindPopup(name).openPopup();
};

const initialiseMapPicker = async () => {
    const element = document.querySelector('[data-map-picker]');

    if (!element) {
        return;
    }

    const latitudeInput = document.querySelector('[name="latitude"]');
    const longitudeInput = document.querySelector('[name="longitude"]');

    if (!latitudeInput || !longitudeInput) {
        return;
    }

    const L = await loadLeaflet();
    const initial = [Number(element.dataset.lat), Number(element.dataset.lng)];
    const hasInitial = element.dataset.lat?.trim()
        && element.dataset.lng?.trim()
        && initial.every(Number.isFinite);
    const kabacanCenter = [7.1061, 124.8292];
    element.replaceChildren();

    const map = L.map(element, {
        center: hasInitial ? initial : kabacanCenter,
        zoom: hasInitial ? 17 : 14,
        scrollWheelZoom: false,
    });
    tileLayer(L).addTo(map);

    let marker = null;
    const setLocation = (coordinates) => {
        latitudeInput.value = coordinates.lat.toFixed(7);
        longitudeInput.value = coordinates.lng.toFixed(7);

        if (!marker) {
            marker = L.marker(coordinates, {
                icon: markerIcon(L, 'K'),
                draggable: true,
            }).addTo(map);
            marker.on('dragend', () => setLocation(marker.getLatLng()));
        } else {
            marker.setLatLng(coordinates);
        }
    };

    if (hasInitial) {
        setLocation(L.latLng(initial[0], initial[1]));
    }

    map.on('click', (event) => setLocation(event.latlng));

    const syncFromInputs = () => {
        const coordinates = [Number(latitudeInput.value), Number(longitudeInput.value)];
        if (latitudeInput.value.trim() && longitudeInput.value.trim() && coordinates.every(Number.isFinite)) {
            const latLng = L.latLng(coordinates[0], coordinates[1]);
            setLocation(latLng);
            map.panTo(latLng);
        }
    };

    latitudeInput.addEventListener('change', syncFromInputs);
    longitudeInput.addEventListener('change', syncFromInputs);
};

const initialiseCardMotion = () => {
    if (motionIsPaused() || !window.matchMedia('(pointer: fine)').matches) {
        return;
    }

    document.querySelectorAll('[data-court-card]').forEach((card) => {
        card.addEventListener('pointermove', (event) => {
            const bounds = card.getBoundingClientRect();
            const x = (event.clientX - bounds.left) / bounds.width - 0.5;
            const y = (event.clientY - bounds.top) / bounds.height - 0.5;
            card.style.setProperty('--card-rx', `${y * -3.5}deg`);
            card.style.setProperty('--card-ry', `${x * 4.5}deg`);
        });

        card.addEventListener('pointerleave', () => {
            card.style.removeProperty('--card-rx');
            card.style.removeProperty('--card-ry');
        });
    });
};

const initialiseBookingPass = async () => {
    const canvas = document.querySelector('[data-booking-qr]');

    if (!canvas?.dataset.payload) {
        return;
    }

    const module = await import('qrcode');
    const QRCode = module.default ?? module;
    await QRCode.toCanvas(canvas, canvas.dataset.payload, {
        width: Math.min(320, Math.max(240, window.innerWidth - 96)),
        margin: 2,
        color: {
            dark: '#071e2a',
            light: '#ffffff',
        },
        errorCorrectionLevel: 'H',
    });
};

const initialiseCheckInScanner = () => {
    const root = document.querySelector('[data-check-in-scanner]');

    if (!root) {
        return;
    }

    const form = root.querySelector('form');
    const input = root.querySelector('[name="token"]');
    const video = root.querySelector('video');
    const startButton = root.querySelector('[data-start-camera]');
    const stopButton = root.querySelector('[data-stop-camera]');
    const resultPanel = root.querySelector('[data-scan-result]');
    let controls;
    let submitting = false;

    const stopCamera = () => {
        controls?.stop();
        controls = null;
        video.srcObject = null;
        startButton.hidden = false;
        stopButton.hidden = true;
    };

    const showResult = (message, state = 'neutral', booking = null) => {
        resultPanel.dataset.state = state;
        resultPanel.innerHTML = '';
        const title = document.createElement('strong');
        title.textContent = message;
        resultPanel.append(title);

        if (booking) {
            const details = document.createElement('p');
            details.textContent = `${booking.player} · ${booking.court} / ${booking.unit} · ${booking.time} · Payment ${booking.payment_status}`;
            resultPanel.append(details);
        }
    };

    const submitToken = async (token) => {
        if (!token || submitting) {
            return;
        }

        submitting = true;
        showResult('Checking reservation…');

        try {
            const response = await fetch(form.action, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                },
                body: JSON.stringify({ token }),
            });
            const payload = await response.json();

            if (!response.ok) {
                const validation = payload.errors ? Object.values(payload.errors).flat()[0] : null;
                throw new Error(validation || payload.message || 'This pass could not be checked in.');
            }

            showResult(payload.message, 'success', payload.booking);
            input.value = '';
            stopCamera();
        } catch (error) {
            showResult(error.message, 'error');
        } finally {
            submitting = false;
        }
    };

    form.addEventListener('submit', (event) => {
        event.preventDefault();
        submitToken(input.value.trim());
    });

    startButton.addEventListener('click', async () => {
        if (!navigator.mediaDevices?.getUserMedia) {
            showResult('Camera scanning is unavailable in this browser. Enter the pass code manually.', 'error');
            return;
        }

        try {
            showResult('Starting camera…');
            const { BrowserQRCodeReader } = await import('@zxing/browser');
            const reader = new BrowserQRCodeReader();
            controls = await reader.decodeFromVideoDevice(undefined, video, (result) => {
                if (result) {
                    submitToken(result.getText());
                }
            });
            startButton.hidden = true;
            stopButton.hidden = false;
            showResult('Point the camera at the player’s Kabacan PicklePlay pass.');
        } catch {
            showResult('Camera permission was not granted. Enter the pass code manually.', 'error');
            stopCamera();
        }
    });

    stopButton.addEventListener('click', stopCamera);
    window.addEventListener('pagehide', stopCamera);
};

document.addEventListener('DOMContentLoaded', () => {
    document.body.dataset.directoryView = 'list';
    initialiseReveals();
    initialiseHeroMotion();
    initialiseMotionLoops();
    initialiseDirectoryMap();
    initialiseSingleMap();
    initialiseMapPicker();
    initialiseCardMotion();
    initialiseBookingPass();
    initialiseCheckInScanner();
});
