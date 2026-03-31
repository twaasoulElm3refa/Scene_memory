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

        // ── دبابيس الأحداث ──
        this.eventMarkers = [];
        this.fullEventMarkers = [];

        // ── الـ popup المفتوح حالياً ──
        this._activePopup = null;

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

            // ── هنا الجزء الجديد ──
            this.loadDailyEvents();
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
            this.fullEventMarkers.forEach(m => m.remove());
            this.fullEventMarkers = [];
            this.fullMap.remove();
            this.fullMap = null;
            this.fullMarker = null;
        }
    }

    setLocation(lat, lng) {
        this._updateLocation(lat, lng);
        this._debouncedReverseGeocode(lat, lng);
    }

    /**
     * addEventMarkers
     * events  → مصفوفة الأحداث (نفس شكل الـ response اللي بعته)
     * targetMap → الخريطة المستهدفة (افتراضي: this.map)
     * isFullscreen → هل هي خريطة fullscreen؟
     */
    addEventMarkers(events, targetMap = this.map, isFullscreen = false) {
        // امسح الدبابيس القديمة على الخريطة المستهدفة فقط
        if (isFullscreen) {
            this.fullEventMarkers.forEach(m => m.remove());
            this.fullEventMarkers = [];
        } else {
            this.eventMarkers.forEach(m => m.remove());
            this.eventMarkers = [];
        }

        // أغلق أي popup مفتوح
        if (this._activePopup) {
            this._activePopup.remove();
            this._activePopup = null;
        }

        if (!events?.length || !targetMap) return;

        const isAr = this._currentLang === "ar";
        const bounds = new maplibregl.LngLatBounds();
        let validCount = 0;

        events.forEach(event => {
            const lat = parseFloat(event.lattitude);
            const lng = parseFloat(event.langitude);
            if (isNaN(lat) || isNaN(lng)) return;

            bounds.extend([lng, lat]);
            validCount++;

            const el = this._createEventMarkerEl();

            // ── استخدام عنوان الترجمة أولوية ──
            const displayTitle = event.translation?.title || event.title || (isAr ? "فعالية" : "Event");

            // ── Popup بعنوان الحدث ──
            const popup = new maplibregl.Popup({
                offset: 30,
                closeButton: true,
                closeOnClick: false,
                maxWidth: "240px",
            }).setHTML(this._buildPopupHTML(event, displayTitle, isAr));

            const marker = new maplibregl.Marker({ element: el, anchor: "bottom" })
                .setLngLat([lng, lat])
                .setPopup(popup)
                .addTo(targetMap);

            // ── كليك على الدبوس نفسه ──
            el.addEventListener("click", (e) => {
                e.stopPropagation();

                if (this._activePopup && this._activePopup !== popup) {
                    this._activePopup.remove();
                }

                if (marker.getPopup().isOpen()) {
                    marker.getPopup().remove();
                    this._activePopup = null;
                } else {
                    marker.togglePopup();
                    this._activePopup = popup;
                }
            });

            // ── كليك على زرار "عرض التفاصيل" داخل الـ popup ──
            popup.on("open", () => {
                const link = popup.getElement()?.querySelector(".popup-details-link");
                if (link) {
                    link.addEventListener("click", (e) => {
                        e.preventDefault();
                        e.stopPropagation();

                        document.dispatchEvent(
                            new CustomEvent("event-marker-clicked", {
                                detail: { slug: event.slug }
                            })
                        );

                        // إغلاق الـ popup بعد الضغط
                        popup.remove();
                        this._activePopup = null;
                    });
                }
            });

            if (isFullscreen) {
                this.fullEventMarkers.push(marker);
            } else {
                this.eventMarkers.push(marker);
            }
        });

        // تكبير الخريطة لتشمل كل الدبابيس لو أكتر من واحد
        if (validCount > 1) {
            targetMap.fitBounds(bounds, { padding: 60, maxZoom: 15 });
        } else if (validCount === 1) {
            targetMap.flyTo({
                center: [parseFloat(events[0].langitude), parseFloat(events[0].lattitude)],
                zoom: 14,
                essential: true
            });
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
        mapInstance.on("click", e => {
            const target = e.originalEvent?.target;
            if (target?.closest(".map-event-marker")) return;
            if (target?.closest(".maplibregl-popup")) return;
            handleMove(e.lngLat);
        });
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
                console.log("🔥 Events Returned:", events); // هنا الطباعة
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
    // Private — Popup HTML Builder   ←   محدث ليستخدم displayTitle
    // ─────────────────────────────────────────────

    _buildPopupHTML(event, displayTitle, isAr) {
        const fontFamily = isAr
            ? "'Noto Sans Arabic', Tahoma, sans-serif"
            : "sans-serif";

        const dateStr = event.start_date
            ? new Date(event.start_date).toLocaleDateString(isAr ? "ar-EG" : "en-US", {
                year: "numeric",
                month: "short",
                day: "numeric"
            })
            : "";

        let html = `
            <div style="
                min-width: 190px;
                max-width: 240px;
                font-family: ${fontFamily};
                direction: ${isAr ? 'rtl' : 'ltr'};
                text-align: ${isAr ? 'right' : 'left'};
                unicode-bidi: embed;
                padding: 4px 0;
            ">
                <p style="
                    margin: 0 0 6px;
                    font-size: 15px;
                    font-weight: 700;
                    color: #111;
                    line-height: 1.4;
                ">${displayTitle}</p>
        `;

        if (dateStr) {
            html += `<p style="margin:0 0 8px; font-size:13px; color:#555;">📅 ${dateStr}</p>`;
        }

        if (event.image_url) {
            html += `<img src="${event.image_url}" loading="lazy"
                style="width:100%; max-height:140px; object-fit:cover; border-radius:8px; margin-bottom:10px; display:block;">`;
        }

        if (event.slug) {
            html += `
                <a href="/single_event/${event.slug}"
                   class="popup-details-link"
                   style="
                       display: inline-block;
                       margin-top: 6px;
                       padding: 8px 16px;
                       background: #e53e3e;
                       color: white;
                       border-radius: 9999px;
                       font-size: 13px;
                       font-weight: 600;
                       text-decoration: none;
                       cursor: pointer;
                       transition: background 0.2s;
                   ">
                    ${isAr ? "عرض التفاصيل" : "View Details"}
                </a>`;
        }

        html += `</div>`;
        return html;
    }

    // ─────────────────────────────────────────────
    // Private — Marker Element
    // ─────────────────────────────────────────────

    _createEventMarkerEl() {
        const el = document.createElement("div");
        el.className = "map-event-marker";

        el.style.width = "36px";
        el.style.height = "36px";
        el.style.cursor = "pointer";

        el.innerHTML = `
        <svg viewBox="0 0 24 24" width="36" height="36">
            <path fill="#1e88e5" d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7z"/>
            <circle cx="12" cy="9" r="2.5" fill="white"/>
        </svg>
    `;

        el.style.transform = "translate(-50%, -100%)"; // يخليه ثابت فوق النقطة

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

    /**
 * Load and display daily events on map initialization
 */
    async loadDailyEvents() {
        try {
            const token = localStorage.getItem("auth_token") || "";
            const lang = localStorage.getItem("language") || "ar";

            const res = await fetch("/api/v1/events/daily", {
                headers: {
                    Accept: "application/json",
                    Authorization: `Bearer ${token}`,
                    "Accept-Language": lang,
                },
            });

            if (!res.ok) {
                console.warn("Daily events fetch failed", res.status);
                return;
            }

            const json = await res.json();
            const events = json?.data || [];

            if (events.length > 0 && this.map) {
                this.addEventMarkers(events, this.map, false);
            }
        } catch (err) {
            console.error("Error loading daily events:", err);
        }
    }
}
