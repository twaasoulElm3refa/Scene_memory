import maplibregl from "maplibre-gl";
import "maplibre-gl/dist/maplibre-gl.css";

export default class MapService {
    constructor(markerRef) {
        this.markerRef = markerRef;
        this.map = null;
        this.marker = null;
        this.fullMap = null;
        this.fullMarker = null;
        this.eventMarkers = [];

        // caches
        this.reverseGeocodeCache = new Map(); // key = `${lat},${lng}`, value = {city, state}
        this.cityEventCache = new Map(); // key = city, value = events array
    }

    initMap(mapId, zoom = 10) {
        this.map = new maplibregl.Map({
            container: mapId,
            style: this._getStyle(),
            center: [this.markerRef.value.lng, this.markerRef.value.lat],
            zoom: zoom,
        });

        this.map.addControl(new maplibregl.NavigationControl());

        this.map.on("load", () => {
            this._addDraggableMarker(this.map, false);
        });
    }

    openFullscreen(mapId, zoom = 12) {
        this.fullMap = new maplibregl.Map({
            container: mapId,
            style: this._getStyle(),
            center: [this.markerRef.value.lng, this.markerRef.value.lat],
            zoom: zoom,
        });

        this.fullMap.addControl(new maplibregl.NavigationControl());

        this.fullMap.on("load", () => {
            this._addDraggableMarker(this.fullMap, true);
        });
    }

    closeFullscreen() {
        if (this.fullMap) {
            this.fullMap.remove();
            this.fullMap = null;
            this.fullMarker = null;
        }
    }

    _getStyle() {
        const MAPTILER_KEY = "YU0yOJ7Mluv9CxBIa97r";
        const lang = localStorage.getItem("language") || "ar";
        return `https://api.maptiler.com/maps/streets-v2/style.json?key=${MAPTILER_KEY}&language=${lang}`;
    }

    _addDraggableMarker(mapInstance, isFullscreen = false) {
        const marker = new maplibregl.Marker({ draggable: true })
            .setLngLat([this.markerRef.value.lng, this.markerRef.value.lat])
            .addTo(mapInstance);

        if (isFullscreen) this.fullMarker = marker;
        else this.marker = marker;

        const updatePosition = (lngLat) => {
            this._updateLocation(lngLat.lat, lngLat.lng);
            this._reverseGeocode(lngLat.lat, lngLat.lng);
        };

        marker.on("dragend", () => updatePosition(marker.getLngLat()));
        mapInstance.on("click", (e) => updatePosition(e.lngLat));
    }

    setLocation(lat, lng) {
        this._updateLocation(lat, lng);
        this._reverseGeocode(lat, lng);
    }

    _updateLocation(lat, lng) {
        this.markerRef.value.lat = lat;
        this.markerRef.value.lng = lng;

        if (this.marker) {
            this.marker.setLngLat([lng, lat]);
            this.map.flyTo({ center: [lng, lat], zoom: 12 });
        }

        if (this.fullMarker) {
            this.fullMarker.setLngLat([lng, lat]);
            this.fullMap.flyTo({ center: [lng, lat], zoom: 12 });
        }
    }

    _reverseGeocode(lat, lng) {
        const key = `${lat.toFixed(6)},${lng.toFixed(6)}`;
        if (this.reverseGeocodeCache.has(key)) {
            const cached = this.reverseGeocodeCache.get(key);
            this._setCityState(cached.city, cached.state);
            if (cached.state) this._sendCityToBackend(cached.state);
            else this._dispatchMarkerEvent([]);
            return;
        }

        const backendLang = 'ar';
        const url = `https://nominatim.openstreetmap.org/reverse?format=jsonv2&lat=${lat}&lon=${lng}&zoom=16&addressdetails=1&accept-language=${backendLang}`;

        fetch(url, {
            headers: { "User-Agent": "SceneMemoryApp (your-contact-email@example.com)" }
        })
            .then(res => {
                if (!res.ok) throw new Error(`HTTP ${res.status}`);
                return res.json();
            })
            .then(data => {
                if (data.error) throw new Error(data.error);

                const addr = data.address || {};
                let city = addr.city || addr.town || addr.village || addr.municipality || addr.hamlet || addr.suburb || addr.neighbourhood || addr.city_district || null;
                let state = addr.state || addr.region || addr.province || addr.county || addr.state_district || null;

                if (!city && data.display_name) {
                    const parts = data.display_name.split(',').map(p => p.trim());
                    if (parts.length >= 3) city = parts[parts.length - 3];
                }

                this.reverseGeocodeCache.set(key, { city, state });
                this._setCityState(city, state);

                if (state) this._sendCityToBackend(state);
                else this._dispatchMarkerEvent([]);
            })
            .catch(err => {
                console.error("[Reverse Geocode] Error:", err);
                this._setCityState(null, null);
                this._dispatchMarkerEvent([]);
            });
    }

    _setCityState(city, state) {
        this.markerRef.value.city = city;
        this.markerRef.value.state = state;
    }

    _sendCityToBackend(city) {
        if (this.cityEventCache.has(city)) {
            this._dispatchMarkerEvent(this.cityEventCache.get(city));
            return;
        }

        const encodedCity = encodeURIComponent(city);
        fetch(`/api/v1/events/${encodedCity}/marker/search`, {
            headers: {
                Accept: "application/json",
                Authorization: `Bearer ${localStorage.getItem("auth_token") || ""}`,
                "Accept-Language": localStorage.getItem("language") || "ar",
            },
        })
            .then(res => res.json())
            .then(data => {
                const events = data?.data || [];
                this.cityEventCache.set(city, events);
                this._dispatchMarkerEvent(events);
            })
            .catch(() => {
                this._dispatchMarkerEvent([]);
            });
    }

    _dispatchMarkerEvent(eventsArray) {
        document.dispatchEvent(
            new CustomEvent("marker-events-loaded", { detail: { events: eventsArray } })
        );
    }

    addEventMarkers(events, targetMap = this.map) {
        this.eventMarkers.forEach(m => m.remove());
        this.eventMarkers = [];

        if (!events?.length) return;

        events.forEach(event => {
            const lat = parseFloat(event.lattitude);
            const lng = parseFloat(event.langitude);
            if (isNaN(lat) || isNaN(lng)) return;

            const marker = new maplibregl.Marker({ color: "#e53e3e" })
                .setLngLat([lng, lat])
                .addTo(targetMap);

            let popupHTML = `
                <div style="min-width:180px;font-family:sans-serif">
                <strong>${event.title || "Event"}</strong><br>
                ${event.start_date ? new Date(event.start_date).toLocaleDateString() : ""}
            `;
            if (event.image_url) popupHTML += `<img src="${event.image_url}" style="width:100%;border-radius:6px;margin-top:6px">`;
            if (event.slug) popupHTML += `<br><a href="/events/${event.slug}" target="_blank">View Details</a>`;
            popupHTML += `</div>`;

            marker.setPopup(new maplibregl.Popup({ offset: 25 }).setHTML(popupHTML));
            this.eventMarkers.push(marker);
        });

        if (events.length > 1) {
            const bounds = new maplibregl.LngLatBounds();
            events.forEach(event => {
                const lat = parseFloat(event.lattitude);
                const lng = parseFloat(event.langitude);
                if (!isNaN(lat) && !isNaN(lng)) bounds.extend([lng, lat]);
            });
            if (bounds.isValid()) targetMap.fitBounds(bounds, { padding: 60 });
        }
    }
}
