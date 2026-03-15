import maplibregl from "maplibre-gl";
import "maplibre-gl/dist/maplibre-gl.css";

if (maplibregl.getRTLTextPluginStatus() === "unavailable") {
    maplibregl.setRTLTextPlugin(
        'https://unpkg.com/@mapbox/mapbox-gl-rtl-text@0.3.0/dist/mapbox-gl-rtl-text.js',
        null,
        true
    );
}

// ─────────────────────────────────────────────
// Constants
// ─────────────────────────────────────────────
const STYLE_URL = "https://tiles.openfreemap.org/styles/liberty";
const STYLE_LS_KEY = "mapservice_style_cache_v2";
const STYLE_TTL_MS = 24 * 60 * 60 * 1000;
const GEOCODE_DEBOUNCE_MS = 600;

export default class MapService {
    constructor(markerRef) {
        this.markerRef = markerRef;
        this.map = null;
        this.marker = null;
        this.fullMap = null;
        this.fullMarker = null;
        this.eventMarkers = [];

        this.reverseGeocodeCache = new Map();
        this.cityEventCache = new Map();
        this._styleCache = {};
        this._styleFetchPromise = null;
        this._geocodeTimer = null;
        const stored = localStorage.getItem("language") || "ar";
        this._currentLang = stored === "ar" ? "ar" : "en";
        this._prefetchStyle();
    }

    initMap(mapId, zoom = 10) {
        this._buildMap(mapId, zoom).then(map => {
            this.map = map;
            this.map.addControl(new maplibregl.NavigationControl());
            this._addDraggableMarker(this.map, false);
        });
    }

    openFullscreen(mapId, zoom = 12) {
        this._buildMap(mapId, zoom).then(map => {
            this.fullMap = map;
            this.fullMap.addControl(new maplibregl.NavigationControl());
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

    setLocation(lat, lng) {
        this._updateLocation(lat, lng);
        this._debouncedReverseGeocode(lat, lng);
    }

    addEventMarkers(events, targetMap = this.map) {
        this.eventMarkers.forEach(m => m.remove());
        this.eventMarkers = [];

        if (!events?.length) return;

        const isAr = this._currentLang === "ar";
        const bounds = new maplibregl.LngLatBounds();
        let validCount = 0;

        const fragment = events.map(event => {
            const lat = parseFloat(event.lattitude);
            const lng = parseFloat(event.langitude);
            if (isNaN(lat) || isNaN(lng)) return null;

            bounds.extend([lng, lat]);
            validCount++;

            const el = this._createEventMarkerEl();
            const marker = new maplibregl.Marker({ element: el, anchor: "bottom" })
                .setLngLat([lng, lat])
                .addTo(targetMap);

            marker.setPopup(
                new maplibregl.Popup({ offset: 25 }).setHTML(
                    this._buildPopupHTML(event, isAr)
                )
            );

            return marker;
        }).filter(Boolean);

        this.eventMarkers = fragment;

        if (validCount > 1 && bounds.isValid()) {
            targetMap.fitBounds(bounds, { padding: 60 });
        }
    }

    // ─────────────────────────────────────────────
    // Private — Style with Persistent LS Cache
    // ─────────────────────────────────────────────

    _prefetchStyle() {
        this._getOrFetchStyle(this._currentLang).catch(() => { });
    }

    async _getOrFetchStyle(lang) {
        if (this._styleCache[lang]) {
            return this._deepClone(this._styleCache[lang]);
        }

        const fromLS = this._loadStyleFromLS(lang);
        if (fromLS) {
            this._styleCache[lang] = fromLS;
            return this._deepClone(fromLS);
        }

        if (!this._styleFetchPromise) {
            this._styleFetchPromise = this._fetchAndCacheStyle(lang).finally(() => {
                this._styleFetchPromise = null;
            });
        }

        return this._styleFetchPromise;
    }

    async _fetchAndCacheStyle(lang) {
        const response = await fetch(STYLE_URL);
        if (!response.ok) throw new Error(`[MapService] Style fetch failed: ${response.status}`);

        const style = await response.json();
        this._patchStyleLanguage(style, lang);

        this._styleCache[lang] = this._deepClone(style);

        try {
            localStorage.setItem(
                `${STYLE_LS_KEY}_${lang}`,
                JSON.stringify({ ts: Date.now(), style })
            );
        } catch { }

        return style;
    }

    _loadStyleFromLS(lang) {
        try {
            const raw = localStorage.getItem(`${STYLE_LS_KEY}_${lang}`);
            if (!raw) return null;
            const { ts, style } = JSON.parse(raw);
            if (Date.now() - ts > STYLE_TTL_MS) {
                localStorage.removeItem(`${STYLE_LS_KEY}_${lang}`);
                return null;
            }
            return style;
        } catch {
            return null;
        }
    }

    _patchStyleLanguage(style, lang) {
        if (!style?.layers) return;

        const isAr = lang === "ar";
        const langField = isAr ? "name:ar" : "name:en";
        const nameExpr = ["coalesce", ["get", langField], ["get", "name"]];
        const fontStack = ["Noto Sans Regular"];
        const skipPatterns = ["road-shield", "road-number", "highway-shield"];

        style.layers.forEach(layer => {
            if (layer.type !== "symbol") return;
            if (!layer.layout?.["text-field"]) return;
            if (skipPatterns.some(p => layer.id.includes(p))) return;
            layer.layout["text-field"] = nameExpr;
            layer.layout["text-font"] = fontStack;
            if (isAr) layer.layout["text-writing-mode"] = ["horizontal"];
        });

        style.glyphs = "https://tiles.openfreemap.org/fonts/{fontstack}/{range}.pbf";
    }

    // ─────────────────────────────────────────────
    // Private — Map Builder
    // ─────────────────────────────────────────────

    async _buildMap(containerId, zoom) {
        const style = await this._getOrFetchStyle(this._currentLang);

        const map = new maplibregl.Map({
            container: containerId,
            style,
            center: [this.markerRef.value.lng, this.markerRef.value.lat],
            zoom,
            fadeDuration: 0,
            trackResize: true,
        });

        return new Promise(resolve => map.on("load", () => resolve(map)));
    }

    // ─────────────────────────────────────────────
    // Private — Marker & Location
    // ─────────────────────────────────────────────

    _addDraggableMarker(mapInstance, isFullscreen = false) {
        const marker = new maplibregl.Marker({ draggable: true, color: "#e53e3e" })
            .setLngLat([this.markerRef.value.lng, this.markerRef.value.lat])
            .addTo(mapInstance);

        if (isFullscreen) this.fullMarker = marker;
        else this.marker = marker;

        const handleMove = ({ lat, lng }) => {
            this._updateLocation(lat, lng);
            this._debouncedReverseGeocode(lat, lng);
        };

        marker.on("dragend", () => handleMove(marker.getLngLat()));
        mapInstance.on("click", e => handleMove(e.lngLat));
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

    // ─────────────────────────────────────────────
    // Private — Reverse Geocode (debounced)
    // ─────────────────────────────────────────────

    _debouncedReverseGeocode(lat, lng) {
        clearTimeout(this._geocodeTimer);
        this._geocodeTimer = setTimeout(
            () => this._reverseGeocode(lat, lng),
            GEOCODE_DEBOUNCE_MS
        );
    }

    _reverseGeocode(lat, lng) {
        const key = `${lat.toFixed(5)},${lng.toFixed(5)}`;

        if (this.reverseGeocodeCache.has(key)) {
            const { city, state, stateEn } = this.reverseGeocodeCache.get(key);
            this._setCityState(city, state);
            // ─── التعديل الرئيسي هنا ───
            state ? this._sendCityToBackend(state) : this._dispatchMarkerEvent([]);
            return;
        }

        const fetchLang = lang =>
            fetch(
                `https://nominatim.openstreetmap.org/reverse?format=jsonv2&lat=${lat}&lon=${lng}&zoom=16&addressdetails=1&accept-language=${lang}`,
                { headers: { "User-Agent": "SceneMemoryApp/1.0" } }
            ).then(r => r.json());

        Promise.all([fetchLang("ar"), fetchLang("en")])
            .then(([arData, enData]) => {
                const city = this._extractCity(arData) || this._extractCity(enData);
                const state = this._extractState(arData) || this._extractState(enData);
                const stateEn = this._extractState(enData);

                this.reverseGeocodeCache.set(key, { city, state, stateEn });
                this._setCityState(city, state);
                // ─── التعديل الرئيسي هنا ───
                state ? this._sendCityToBackend(state) : this._dispatchMarkerEvent([]);
            })
            .catch(() => {
                this._setCityState(null, null);
                this._dispatchMarkerEvent([]);
            });
    }

    _extractCity(data) {
        if (!data || data.error) return null;
        const a = data.address || {};
        return (
            a.city || a.town || a.village || a.municipality ||
            a.hamlet || a.suburb || a.neighbourhood || a.city_district || null
        );
    }

    _extractState(data) {
        if (!data || data.error) return null;
        const a = data.address || {};
        return a.state || a.region || a.province || a.county || a.state_district || null;
    }

    _setCityState(city, state) {
        this.markerRef.value.city = city;
        this.markerRef.value.state = state;
    }

    // ─────────────────────────────────────────────
    // Private — Backend & Events
    // ─────────────────────────────────────────────

    _sendCityToBackend(city) {
        if (this.cityEventCache.has(city)) {
            this._dispatchMarkerEvent(this.cityEventCache.get(city));
            return;
        }

        fetch(`/api/v1/events/${encodeURIComponent(city)}/marker/search`, {
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
            .catch(() => this._dispatchMarkerEvent([]));
    }

    _dispatchMarkerEvent(eventsArray) {
        document.dispatchEvent(
            new CustomEvent("marker-events-loaded", { detail: { events: eventsArray } })
        );
    }

    // ─────────────────────────────────────────────
    // Private — Popup HTML Builder
    // ─────────────────────────────────────────────

    _buildPopupHTML(event, isAr) {
        const fontFamily = isAr
            ? "'Noto Sans Arabic', Tahoma, sans-serif"
            : "sans-serif";

        let html = `
            <div style="
                min-width:180px;
                font-family:${fontFamily};
                direction:${isAr ? "rtl" : "ltr"};
                text-align:${isAr ? "right" : "left"};
                unicode-bidi:embed;
            ">
                <strong>${event.title || (isAr ? "فعالية" : "Event")}</strong><br>
                ${event.start_date
                ? new Date(event.start_date).toLocaleDateString(isAr ? "ar-EG" : "en-US")
                : ""}
        `;

        if (event.image_url) {
            html += `<img src="${event.image_url}" loading="lazy" style="width:100%;border-radius:6px;margin-top:6px">`;
        }

        if (event.slug) {
            html += `
                <br>
                <a href="/events/${event.slug}" target="_blank"
                   style="color:#e53e3e;display:block;margin-top:4px">
                    ${isAr ? "عرض التفاصيل" : "View Details"}
                </a>`;
        }

        return html + "</div>";
    }

    // ─────────────────────────────────────────────
    // Private — Marker Element
    // ─────────────────────────────────────────────

    _createEventMarkerEl() {
        const el = document.createElement("div");
        el.className = "map-event-marker";
        el.style.cssText = `
            width:32px; height:32px;
            background:#e53e3e;
            border:3px solid #fff;
            border-radius:50% 50% 50% 0;
            transform:rotate(-45deg);
            box-shadow:0 2px 8px rgba(0,0,0,0.35);
            cursor:pointer;
            will-change:transform;
            transition:transform 0.15s ease;
        `;
        el.addEventListener("mouseenter", () => {
            el.style.transform = "rotate(-45deg) scale(1.2)";
        });
        el.addEventListener("mouseleave", () => {
            el.style.transform = "rotate(-45deg) scale(1)";
        });
        return el;
    }

    // ─────────────────────────────────────────────
    // Utils
    // ─────────────────────────────────────────────

    _deepClone(obj) {
        return typeof structuredClone === "function"
            ? structuredClone(obj)
            : JSON.parse(JSON.stringify(obj));
    }
}
