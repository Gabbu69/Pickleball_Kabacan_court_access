import './bootstrap';
import 'leaflet/dist/leaflet.css';

import Alpine from 'alpinejs';
import L from 'leaflet';

window.Alpine = Alpine;

Alpine.data('siteShell', () => ({
    open: false,
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

const initialiseReveals = () => {
    const elements = document.querySelectorAll('.reveal');

    if (prefersReducedMotion || !('IntersectionObserver' in window)) {
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

    if (!section || !stage || prefersReducedMotion) {
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

const markerIcon = (label = 'K') => L.divIcon({
    className: '',
    html: `<div class="map-marker"><span>${label.replace(/[<>&"']/g, '')}</span></div>`,
    iconSize: [40, 40],
    iconAnchor: [20, 38],
    popupAnchor: [0, -37],
});

const tileLayer = () => L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
    maxZoom: 19,
});

const initialiseDirectoryMap = () => {
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

    element.replaceChildren();
    const map = L.map(element, {
        scrollWheelZoom: false,
        zoomControl: true,
    });
    tileLayer().addTo(map);

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
            icon: markerIcon(safeName.slice(0, 1).toUpperCase()),
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

const initialiseSingleMap = () => {
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

    const name = element.dataset.name || 'Kabacan court';
    const map = L.map(element, {
        center: coordinates,
        zoom: 17,
        scrollWheelZoom: false,
    });

    tileLayer().addTo(map);
    L.marker(coordinates, {
        icon: markerIcon(name.slice(0, 1).toUpperCase()),
    }).addTo(map).bindPopup(name).openPopup();
};

const initialiseCardMotion = () => {
    if (prefersReducedMotion || !window.matchMedia('(pointer: fine)').matches) {
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

document.addEventListener('DOMContentLoaded', () => {
    document.body.dataset.directoryView = 'list';
    initialiseReveals();
    initialiseHeroMotion();
    initialiseDirectoryMap();
    initialiseSingleMap();
    initialiseCardMotion();
});
