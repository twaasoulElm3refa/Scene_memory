// @vitest-environment jsdom
import { describe, expect, it, vi } from "vitest";
import MapService from "../../resources/js/services/MapService/MapService";

vi.mock("../../resources/js/services/mapLibre", () => ({
    default: {
        getRTLTextPluginStatus: () => "loaded",
        LngLatBounds: class {
            extend() {}
        },
        Popup: class {
            setHTML() { return this; }
            on() { return this; }
        },
        Marker: class {
            setLngLat() { return this; }
            setPopup() { return this; }
            addTo() { return this; }
        },
    },
}));
vi.mock("../../resources/js/services/ApiClient", () => ({ default: {} }));

const event = (lat, lng) => ({ lattitude: lat, langitude: lng, title: "Event" });

function mapService(preserveMainMapViewport) {
    const service = Object.create(MapService.prototype);
    service.preserveMainMapViewport = preserveMainMapViewport;
    service.map = { fitBounds: vi.fn(), flyTo: vi.fn() };
    service.markerRef = { value: { lat: 0, lng: 0 } };
    service.marker = { setLngLat: vi.fn() };
    service.fullMarker = null;
    service.eventMarkers = [];
    service.fullEventMarkers = [];
    service._activePopup = null;
    service._currentLang = "en";
    service._createEventMarkerEl = () => ({ addEventListener: vi.fn() });
    service._buildPopupHTML = () => "";
    return service;
}

describe("homepage map camera", () => {
    it("keeps the mobile camera stable while adding event markers or moving its pin", () => {
        const service = mapService(true);

        service.addEventMarkers([event(30, 31), event(40, 42)], service.map);
        service._updateLocation(48.8566, 2.3522);

        expect(service.eventMarkers).toHaveLength(2);
        expect(service.marker.setLngLat).toHaveBeenCalledWith([2.3522, 48.8566]);
        expect(service.map.fitBounds).not.toHaveBeenCalled();
        expect(service.map.flyTo).not.toHaveBeenCalled();
    });

    it("retains the existing desktop camera behavior", () => {
        const service = mapService(false);

        service.addEventMarkers([event(30, 31), event(40, 42)], service.map);
        service._updateLocation(48.8566, 2.3522);

        expect(service.map.fitBounds).toHaveBeenCalledOnce();
        expect(service.map.flyTo).toHaveBeenCalledWith({ center: [2.3522, 48.8566], zoom: 12 });
    });
});
