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
        if (!city) {
            console.warn("No city to send to backend");
            this._dispatchMarkerEvent([]);
            return;
        }

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
                const events = data?.data || [];
                this._dispatchMarkerEvent(events);
            })
            .catch(err => {
                console.error("MarkerSearch fetch failed:", err);
                this._dispatchMarkerEvent([]);
            });
    }

    _dispatchMarkerEvent(eventsArray) {
        const customEvent = new CustomEvent("marker-events-loaded", {
            detail: { events: eventsArray },
            bubbles: true,
            composed: true,
        });
        document.dispatchEvent(customEvent);
    }
    addEventMarkers(events, targetMap = this.map) {
        if (!events || !Array.isArray(events) || events.length === 0) {
            console.log("No events to display on map");
            return;
        }

        // 1. إنشاء أو تنظيف الـ Layer Group
        if (!this.eventMarkersLayer) {
            this.eventMarkersLayer = L.layerGroup().addTo(targetMap);
        } else {
            this.eventMarkersLayer.clearLayers();
        }

        events.forEach(event => {
            const lat = parseFloat(event.lattitude);
            const lng = parseFloat(event.langitude);

            if (isNaN(lat) || isNaN(lng)) {
                console.warn("Invalid coordinates for event:", event.title);
                return;
            }

            const eventIcon = L.divIcon({
                className: "custom-event-marker",
                html: `
        <div class="marker-label">${(event.title || "حدث").replace(/</g, "&lt;")}</div>
        <svg width="32" height="42" viewBox="0 0 32 42" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M16 0C7.16 0 0 7.16 0 16c0 12 16 26 16 26s16-14 16-26c0-8.84-7.16-16-16-16z" fill="#e53e3e"/>
            <circle cx="16" cy="16" r="6" fill="white"/>
        </svg>
    `,
                iconSize: [30, 42],
                iconAnchor: [15, 42],
                popupAnchor: [0, -45],    
            });

            const marker = L.marker([lat, lng], {
                icon: eventIcon
            });

            // محتوى الـ popup (نفس السابق أو يمكن تطويره)
            let popupContent = `
            <div dir="rtl" style="text-align:right; min-width:180px; font-family: Tajawal, sans-serif;">
                <strong style="font-size:1.1em;">${event.title || "حدث بدون عنوان"}</strong><br>
                <div style="color:#555; margin:6px 0;">
                    ${event.start_date ? new Date(event.start_date).toLocaleDateString('ar-EG') : "التاريخ غير محدد"}
                </div>
        `;

            if (event.image_url) {
                popupContent += `
                <img src="${event.image_url}" alt="${event.title}" 
                     style="max-width:100%; height:auto; border-radius:6px; margin:8px 0;">
                <br>
            `;
            }

            if (event.slug) {
                popupContent += `
                <a href="/events/${event.slug}" target="_blank" 
                   style="color:#2563eb; text-decoration:underline;">
                   عرض التفاصيل →
                </a>
            `;
            }

            popupContent += `</div>`;

            marker.bindPopup(popupContent, {
                maxWidth: 260,
                className: 'custom-event-popup'
            });

            marker.addTo(this.eventMarkersLayer);
        });

        // ضبط الخريطة لتشمل كل الدبابيس (اختياري)
        if (events.length > 1 && this.eventMarkersLayer) {
            const group = L.featureGroup(this.eventMarkersLayer.getLayers());
            targetMap.fitBounds(group.getBounds(), { padding: [60, 60] });
        }
    }
}