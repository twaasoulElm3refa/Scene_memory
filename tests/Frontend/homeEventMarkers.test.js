// @vitest-environment jsdom
import { describe, expect, it, vi } from 'vitest';
import MapService from '../../resources/js/services/MapService/MapService';

vi.mock('../../resources/js/services/mapLibre', () => ({
    default: {
        getRTLTextPluginStatus: () => 'loaded',
        LngLatBounds: class { extend() {} },
        Popup: class {
            handlers = {};
            open = false;
            element = document.createElement('div');
            setHTML() {
                this.element.innerHTML = '<button class="popup-details-btn">Details</button>';
                return this;
            }
            on(name, handler) { this.handlers[name] = handler; return this; }
            getElement() { return this.element; }
            isOpen() { return this.open; }
            addTo() { this.open = true; this.handlers.open?.(); return this; }
            remove() { this.open = false; this.handlers.close?.(); return this; }
        },
        Marker: class {
            constructor(options) { this.options = options; }
            setLngLat(coordinates) { this.coordinates = coordinates; return this; }
            setPopup(popup) { this.popup = popup; return this; }
            addTo() { return this; }
            getPopup() { return this.popup; }
            togglePopup() { this.popup.isOpen() ? this.popup.remove() : this.popup.addTo(); }
            remove() {}
        },
    },
}));
vi.mock('../../resources/js/services/ApiClient', () => ({ default: {} }));

function service() {
    const map = Object.create(MapService.prototype);
    map.map = { fitBounds: vi.fn(), flyTo: vi.fn() };
    map.eventMarkers = [];
    map.fullEventMarkers = [];
    map._activePopup = null;
    map._currentLang = 'en';
    map._buildPopupHTML = () => '';
    return map;
}

const event = (title, city, slug) => ({
    title, city, slug, lattitude: '30', langitude: '31',
});

describe('homepage event markers', () => {
    it('renders safe translated city labels beside blue dot marker elements', () => {
        const map = service();
        const first = map._createEventMarkerEl(event('Museum', { translation: { name: 'القاهرة' } }), 'Museum');
        const second = map._createEventMarkerEl(event('Gallery', null), 'Gallery');
        const unsafe = map._createEventMarkerEl(event('Festival', { name: '<img src=x onerror=alert(1)>' }), 'Festival');

        expect(first.querySelectorAll('.map-event-marker__pulse, .map-event-marker__halo, .map-event-marker__dot')).toHaveLength(3);
        expect(first.querySelector('.map-event-marker__city').textContent).toBe('القاهرة');
        expect(first.querySelector('.map-event-marker__city').dir).toBe('auto');
        expect(first.getAttribute('role')).toBe('button');
        expect(first.getAttribute('aria-label')).toContain('القاهرة');
        expect(second.querySelector('.map-event-marker__city')).toBeNull();
        expect(unsafe.querySelector('img')).toBeNull();
    });

    it('keeps click, selected popup state, and details navigation for multiple events', () => {
        const map = service();
        const clicked = vi.fn();
        document.addEventListener('event-marker-clicked', clicked);
        try {
            map.addEventMarkers([
                event('First', { translation: { name: 'Cairo' } }, 'first'),
                event('Second', { translation: { name: 'Cairo' } }, 'second'),
            ]);

            const [first, second] = map.eventMarkers;
            expect(first.options.anchor).toBe('center');
            expect(first.options.element.querySelector('.map-event-marker__city').textContent).toBe('Cairo');
            expect(second.options.element.querySelector('.map-event-marker__city').textContent).toBe('Cairo');

            first.options.element.click();
            expect(first.popup.isOpen()).toBe(true);
            expect(first.options.element.classList.contains('is-active')).toBe(true);

            second.options.element.click();
            expect(first.popup.isOpen()).toBe(false);
            expect(second.popup.isOpen()).toBe(true);
            expect(second.options.element.classList.contains('is-active')).toBe(true);

            second.popup.getElement().querySelector('.popup-details-btn').click();
            expect(clicked).toHaveBeenCalledOnce();
            expect(clicked.mock.calls[0][0].detail.slug).toBe('second');
            expect(second.options.element.classList.contains('is-active')).toBe(false);
        } finally {
            document.removeEventListener('event-marker-clicked', clicked);
        }
    });
});
