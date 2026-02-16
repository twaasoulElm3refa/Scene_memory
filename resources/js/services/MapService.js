// src/services/MapService.js
import L from "leaflet";
import "leaflet/dist/leaflet.css";

export default class MapService {
    constructor(markerRef) {
        this.markerRef = markerRef;
        this.map = null;
        this.marker = null;
        this.fullMap = null;
        this.fullMarker = null;
    }

    initMap(mapId, zoom = 10) {
        this.map = L.map(mapId).setView(
            [this.markerRef.value.lat, this.markerRef.value.lng],
            zoom
        );

        this._addTiles(this.map);
        this.marker = L.marker(
            [this.markerRef.value.lat, this.markerRef.value.lng],
            { draggable: true }
        ).addTo(this.map);

        this._setupEvents(this.map, this.marker);
    }

    openFullscreen(mapId, zoom = 12) {
        this.fullMap = L.map(mapId).setView(
            [this.markerRef.value.lat, this.markerRef.value.lng],
            zoom
        );

        this._addTiles(this.fullMap);

        this.fullMarker = L.marker(
            [this.markerRef.value.lat, this.markerRef.value.lng],
            { draggable: true }
        ).addTo(this.fullMap);

        this._setupEvents(this.fullMap, this.fullMarker, true);
    }

    closeFullscreen() {
        if (this.fullMap) {
            this.fullMap.remove();
            this.fullMap = null;
            this.fullMarker = null;
        }
    }

    _addTiles(map) {
        L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
            maxZoom: 19,
            attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
        }).addTo(map);
    }

    _setupEvents(map, marker, isFullscreen = false) {
        const updatePosition = (lat, lng) => {
            this._updateLocation(lat, lng, isFullscreen);
            this._reverseGeocode(lat, lng);
        };

        marker.on("dragend", (e) => {
            const pos = e.target.getLatLng();
            updatePosition(pos.lat, pos.lng);
        });

        map.on("click", (e) => {
            const pos = e.latlng;
            updatePosition(pos.lat, pos.lng);
        });
    }

    setLocation(lat, lng) {
        this._updateLocation(lat, lng);
        this._reverseGeocode(lat, lng);
    }

    _updateLocation(lat, lng, fromFullscreen = false) {
        this.markerRef.value.lat = lat;
        this.markerRef.value.lng = lng;

        if (this.marker) {
            this.marker.setLatLng([lat, lng]);
            this.map.setView([lat, lng], 12);
        }

        if (this.fullMarker) {
            this.fullMarker.setLatLng([lat, lng]);
            this.fullMap.setView([lat, lng], 12);
        }
    }

    _reverseGeocode(lat, lng) {
        // استخدم proxy مجاني لتجاوز CORS
        const baseUrl = "https://nominatim.openstreetmap.org/reverse";
        const url = `https://corsproxy.io/?${encodeURIComponent(baseUrl +
            `?format=jsonv2&lat=${lat}&lon=${lng}&zoom=16&addressdetails=1&accept-language=ar,en`)}`;

        fetch(url, {
            headers: {
                'User-Agent': 'SceneMemoryApp/1.0 (your-email@example.com)',
            }
        })
            .then(res => {
                if (!res.ok) throw new Error(`Nominatim HTTP error: ${res.status}`);
                return res.json();
            })
            .then(data => {
                if (data.error) {
                    console.warn("Nominatim returned error:", data.error);
                    this._setCityState(null, null);
                    this._dispatchMarkerEvent([]);
                    return;
                }

                const addr = data.address || {};

                let city =
                    addr.city ||
                    addr.town ||
                    addr.village ||
                    addr.municipality ||
                    addr.hamlet ||
                    addr.suburb ||
                    addr.neighbourhood ||
                    addr.city_district ||
                    null;

                let state =
                    addr.state ||
                    addr.region ||
                    addr.province ||
                    addr.county ||
                    addr.state_district ||
                    null;

                if (city === state && addr.suburb) {
                    city = addr.suburb;
                }

                if (!city && data.display_name) {
                    const parts = data.display_name.split(',').map(p => p.trim()).filter(Boolean);
                    if (parts.length >= 4) city = parts[3];
                }

                this._setCityState(city, state);

                if (state) {
                    this._sendCityToBackend(state);
                } else {
                    this._dispatchMarkerEvent([]);
                }
            })
            .catch(err => {
                console.error("Nominatim fetch failed:", err);
                this._setCityState(null, null);
                this._dispatchMarkerEvent([]);
            });
    }

    _setCityState(city, state) {
        this.markerRef.value.city = city;
        this.markerRef.value.state = state;
    }

    _sendCityToBackend(city) {
        const encodedCity = encodeURIComponent(city);
        const url = `/api/v1/events/${encodedCity}/marker/search`;

        fetch(url, {
            method: "GET",
            headers: {
                Accept: "application/json",
                Authorization: `Bearer ${localStorage.getItem("auth_token") || ""}`,
            },
        })
            .then(res => {
                if (!res.ok) throw new Error(`Backend HTTP error: ${res.status}`);
                return res.json();
            })
            .then(data => {
                console.log("Backend MarkerSearch response:", data);
                const events = data?.data || [];
                this._dispatchMarkerEvent(events);
            })
            .catch(err => {
                console.error("MarkerSearch fetch failed:", err);
                this._dispatchMarkerEvent([]);
            });
    }

    // ── جديد ── إرسال custom event للـ component
    _dispatchMarkerEvent(eventsArray) {
        const customEvent = new CustomEvent("marker-events-loaded", {
            detail: { events: eventsArray },
            bubbles: true,
            composed: true,
        });
        // بنبعت الحدث على document عشان يوصل للـ component بسهولة
        document.dispatchEvent(customEvent);
    }
}