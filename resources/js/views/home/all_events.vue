<template>
    <div class="scemory-page events-page">
        <!-- Header Section -->
        <header class="page-header">
            <div class="header-content">
                <h1 class="main-title">{{ $t("events.all_memories") }}</h1>
                <p class="subtitle">
                    {{ $t("events.showing_snapshots", { total: totalEvents }) }}
                </p>
            </div>

            <div class="header-actions">
                <div class="filter-control">
                    <label>Event Type:</label>
                    <select
                        v-model="eventTypeFilter"
                        @change="onEventTypeFilterChange"
                        class="sort-select"
                        aria-label="Event Type"
                    >
                        <option value="all">All Events</option>
                        <option value="real">Real Events</option>
                        <option value="general">General Events</option>
                    </select>
                </div>

                <div class="sort-control">
                    <label>{{ $t("events.sort_by") }}:</label>
                    <select v-model="sortBy" @change="sortEvents" class="sort-select">
                        <option value="newest">{{ $t("events.sort.newest") }}</option>
                        <option value="oldest">{{ $t("events.sort.oldest") }}</option>
                        <option value="title">{{ $t("events.sort.title") }}</option>
                    </select>
                </div>
            </div>
        </header>

        <!-- Loading State -->
        <div v-if="loading" class="loading-container">
            <div class="loader"></div>
            <p>{{ $t("events.loading") }}</p>
        </div>

        <!-- Error State -->
        <div v-else-if="error" class="error-container">
            <p>{{ error }}</p>
            <button @click="fetchEvents" class="retry-btn">
                {{ $t("events.retry") }}
            </button>
        </div>

        <!-- Empty State -->
        <div v-else-if="filteredEvents.length === 0" class="error-container">
            <p>لا توجد فعاليات متاحة حاليًا.</p>
        </div>

        <!-- Events Grid -->
        <div v-else class="events-grid">
            <div
                v-for="event in filteredEvents"
                :key="event.id"
                class="event-card"
                @click="viewEvent(event)"
            >
                <div class="event-image">
                    <img
                        :src="getEventImageUrl(event)"
                        :alt="event.translation?.title || event.title || 'Event image'"
                        loading="lazy"
                        @error="onImageError"
                    />

                    <span class="event-date">
                        {{ formatDate(event.start_date) }}
                    </span>
                </div>

                <div class="event-content">
                    <div class="event-location">
                        <svg class="location-icon" viewBox="0 0 24 24" fill="currentColor">
                            <path
                                d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"
                            />
                        </svg>

                        <span>{{ event.city?.translation?.name || event.city?.name || "-" }}</span>
                    </div>

                    <h3 class="event-title">
                        {{ event.translation?.title || event.title || "-" }}
                    </h3>

                    <p class="event-category">
                        {{ event.sub_categorey?.translation?.name || event.sub_categorey?.name || "-" }}
                    </p>

                    <router-link
                        :to="getEventRoute(event)"
                        class="btn-view"
                        @click.stop
                    >
                        تفاصيل الحدث
                    </router-link>
                </div>
            </div>
        </div>

        <!-- Pagination -->
        <div v-if="!loading && !error && lastPage > 1" class="pagination">
            <button
                @click="goToPage(currentPage - 1)"
                :disabled="currentPage === 1"
                class="page-btn"
            >
                <svg viewBox="0 0 24 24" fill="currentColor">
                    <path d="M15.41 7.41L14 6l-6 6 6 6 1.41-1.41L10.83 12z" />
                </svg>
            </button>

            <button
                v-for="(page, index) in visiblePages"
                :key="`${page}-${index}`"
                @click="goToPage(page)"
                :class="['page-number', { active: page === currentPage }]"
                :disabled="page === '...'"
            >
                {{ page }}
            </button>

            <button
                @click="goToPage(currentPage + 1)"
                :disabled="currentPage === lastPage"
                class="page-btn"
            >
                <svg viewBox="0 0 24 24" fill="currentColor">
                    <path d="M10 6L8.59 7.41 13.17 12l-4.58 4.59L10 18l6-6z" />
                </svg>
            </button>

            <p class="page-info">
                {{ $t("events.page_info", { current: currentPage, last: lastPage }) }}
            </p>
        </div>

        <!-- Stats Footer -->
        <footer class="stats-footer">
            <div class="stat-item">
                <div class="stat-number">{{ formatNumber(totalEvents) }}</div>
                <div class="stat-label">{{ $t("events.active_memories") }}</div>
            </div>

            <div class="stat-item">
                <div class="stat-number">{{ uniqueCities }}</div>
                <div class="stat-label">{{ $t("events.cities") }}</div>
            </div>

            <div class="stat-item">
                <div class="stat-number">{{ uniqueCategories }}</div>
                <div class="stat-label">{{ $t("events.categories") }}</div>
            </div>
        </footer>

        <div class="brand-footer">
            {{ $t("events.brand_footer") }}
        </div>
    </div>
</template>

<script>
import { EventService } from "../../services/EventService/EventService";

const FALLBACK_IMAGE =
    "https://developers.elementor.com/docs/assets/img/elementor-placeholder-image.png";

export default {
    name: "AllEventsPage",

    data() {
        return {
            events: [],
            loading: true,
            error: null,
            currentPage: 1,
            lastPage: 1,
            totalEvents: 0,
            perPage: 8,
            sortBy: "newest",
            eventTypeFilter: "all",
            uniqueCities: 0,
            uniqueCategories: 0,
            fallbackImage: FALLBACK_IMAGE,
            placeholderImage: FALLBACK_IMAGE,
        };
    },

    computed: {
        filteredEvents() {
            return this.events;
        },

        visiblePages() {
            const pages = [];
            const maxVisible = 5;

            if (this.lastPage <= maxVisible) {
                for (let i = 1; i <= this.lastPage; i += 1) {
                    pages.push(i);
                }

                return pages;
            }

            pages.push(1);

            if (this.currentPage > 3) {
                pages.push("...");
            }

            for (
                let i = Math.max(2, this.currentPage - 1);
                i <= Math.min(this.lastPage - 1, this.currentPage + 1);
                i += 1
            ) {
                if (!pages.includes(i)) {
                    pages.push(i);
                }
            }

            if (this.currentPage < this.lastPage - 2) {
                pages.push("...");
            }

            if (!pages.includes(this.lastPage)) {
                pages.push(this.lastPage);
            }

            return pages;
        },
    },

    mounted() {
        this.fetchEvents();
    },

    methods: {
        getBackendOrigin() {
            const apiUrl =
                import.meta.env.VITE_API_URL ||
                import.meta.env.VITE_API_BASE_URL ||
                import.meta.env.VITE_BACKEND_URL ||
                "";

            if (apiUrl) {
                try {
                    return new URL(apiUrl, window.location.origin).origin;
                } catch {
                    return String(apiUrl).replace(/\/+$/, "");
                }
            }

            /*
             * لو Vue شغال على Vite مثل localhost:5173
             * وLaravel شغال على localhost:8000
             * لازم الصور تيجي من 8000 مش 5173.
             */
            if (
                window.location.hostname === "localhost" ||
                window.location.hostname === "127.0.0.1"
            ) {
                if (window.location.port && window.location.port !== "8000") {
                    return `${window.location.protocol}//${window.location.hostname}:8000`;
                }
            }

            return window.location.origin;
        },

        getMediaRawPath(mediaOrPath) {
            if (!mediaOrPath) {
                return "";
            }

            if (typeof mediaOrPath === "string") {
                return mediaOrPath;
            }

            return (
                mediaOrPath.preview_url ||
                mediaOrPath.previewUrl ||
                mediaOrPath.image_url ||
                mediaOrPath.imageUrl ||
                mediaOrPath.webp_url ||
                mediaOrPath.webpUrl ||
                mediaOrPath.full_url ||
                mediaOrPath.fullUrl ||
                mediaOrPath.full_url_webp ||
                mediaOrPath.fullUrlWebp ||
                mediaOrPath.url ||
                mediaOrPath.path ||
                mediaOrPath.image ||
                mediaOrPath.file_path ||
                mediaOrPath.filePath ||
                mediaOrPath.file ||
                mediaOrPath.src ||
                ""
            );
        },

        getStorageUrl(mediaOrPath) {
            const rawPath = this.getMediaRawPath(mediaOrPath);

            if (!rawPath || typeof rawPath !== "string") {
                return this.fallbackImage;
            }

            const path = rawPath.replace(/\\/g, "/").trim();

            if (!path) {
                return this.fallbackImage;
            }

            if (path.startsWith("http://") || path.startsWith("https://")) {
                return path;
            }

            if (path.startsWith("//")) {
                return `${window.location.protocol}${path}`;
            }

            const backendOrigin = this.getBackendOrigin();

            if (path.startsWith("/storage/")) {
                return `${backendOrigin}${path}`;
            }

            if (path.startsWith("storage/")) {
                return `${backendOrigin}/${path}`;
            }

            if (path.startsWith("public/")) {
                return `${backendOrigin}/storage/${path.replace(/^public\//, "")}`;
            }

            if (path.startsWith("/uploads/")) {
                return `${backendOrigin}${path}`;
            }

            if (path.startsWith("uploads/")) {
                return `${backendOrigin}/${path}`;
            }

            /*
             * الريسبونس عندك بيرجع:
             * first_image.preview_url = events/preview/image.jpg
             *
             * إذن الرابط الصحيح:
             * http://localhost:8000/storage/events/preview/image.jpg
             */
            return `${backendOrigin}/storage/${path.replace(/^\/+/, "")}`;
        },

        getEventImageCandidate(event) {
            return (
                event?.first_image?.preview_url ||
                event?.first_image?.previewUrl ||
                event?.first_image?.webp_url ||
                event?.first_image?.webpUrl ||
                event?.first_image?.full_url ||
                event?.first_image?.fullUrl ||
                event?.first_image?.url ||
                event?.first_image?.image ||

                event?.firstImage?.preview_url ||
                event?.firstImage?.previewUrl ||
                event?.firstImage?.webp_url ||
                event?.firstImage?.webpUrl ||
                event?.firstImage?.full_url ||
                event?.firstImage?.fullUrl ||
                event?.firstImage?.url ||
                event?.firstImage?.image ||

                event?.image_webp_url ||
                event?.imageWebpUrl ||
                event?.image_url ||
                event?.imageUrl ||

                event?.images?.[0]?.preview_url ||
                event?.images?.[0]?.previewUrl ||
                event?.images?.[0]?.webp_url ||
                event?.images?.[0]?.webpUrl ||
                event?.images?.[0]?.full_url ||
                event?.images?.[0]?.fullUrl ||
                event?.images?.[0]?.url ||
                event?.images?.[0]?.image ||

                event?.image ||
                ""
            );
        },

        getEventImageUrl(event) {
            return this.getStorageUrl(this.getEventImageCandidate(event));
        },

        onImageError(event) {
            const target = event?.target;
            const fallback = this.fallbackImage || this.placeholderImage || FALLBACK_IMAGE;

            if (!target) {
                return;
            }

            if (target.dataset.fallbackApplied === "1") {
                return;
            }

            target.dataset.fallbackApplied = "1";
            target.src = fallback;
        },

        async fetchEvents(page = 1) {
            this.loading = true;
            this.error = null;

            try {
                const response = await EventService.getAll(page, this.eventTypeFilter);
                const result = response.data;

                if (result.status !== "success") {
                    throw new Error(result.message || "Failed to fetch events");
                }

                const payload = result.data;

                this.events = Array.isArray(payload?.data)
                    ? payload.data
                    : payload?.data
                        ? [payload.data]
                        : Array.isArray(payload)
                            ? payload
                            : [];

                this.currentPage = Number(payload?.current_page) || 1;
                this.lastPage = Number(payload?.last_page) || 1;
                this.totalEvents = Number(payload?.total) || this.events.length;
                this.perPage = Number(payload?.per_page) || 8;

                this.calculateStats();

                if (import.meta.env.DEV && this.events.length > 0) {
                    console.log("AllEvents first event:", this.events[0]);
                    console.log("AllEvents image candidate:", this.getEventImageCandidate(this.events[0]));
                    console.log("AllEvents final image url:", this.getEventImageUrl(this.events[0]));
                }
            } catch (err) {
                console.error("Error fetching events:", err);
                this.events = [];
                this.error = "فشل في تحميل الفعاليات. الرجاء المحاولة مرة أخرى.";
            } finally {
                this.loading = false;
            }
        },

        onEventTypeFilterChange() {
            this.currentPage = 1;
            this.fetchEvents(1);
        },

        calculateStats() {
            const cities = new Set();
            const categories = new Set();

            this.events.forEach((event) => {
                if (event?.city?.id) {
                    cities.add(event.city.id);
                }

                if (event?.sub_categorey?.id) {
                    categories.add(event.sub_categorey.id);
                }
            });

            this.uniqueCities = cities.size;
            this.uniqueCategories = categories.size;
        },

        goToPage(page) {
            if (page === "...") {
                return;
            }

            const pageNumber = Number(page);

            if (
                pageNumber >= 1 &&
                pageNumber <= this.lastPage &&
                pageNumber !== this.currentPage
            ) {
                this.fetchEvents(pageNumber);
                window.scrollTo({ top: 0, behavior: "smooth" });
            }
        },

        sortEvents() {
            const sorted = [...this.events];

            switch (this.sortBy) {
                case "newest":
                    sorted.sort((a, b) => new Date(b.start_date) - new Date(a.start_date));
                    break;

                case "oldest":
                    sorted.sort((a, b) => new Date(a.start_date) - new Date(b.start_date));
                    break;

                case "title":
                    sorted.sort((a, b) => {
                        const titleA = a.translation?.title || a.title || "";
                        const titleB = b.translation?.title || b.title || "";

                        return titleA.localeCompare(titleB, "ar");
                    });
                    break;

                default:
                    break;
            }

            this.events = sorted;
        },

        formatDate(dateString) {
            if (!dateString) {
                return "-";
            }

            const date = new Date(dateString);

            if (Number.isNaN(date.getTime())) {
                return "-";
            }

            const lang = (localStorage.getItem("language") || "ar").toLowerCase();

            const months = {
                ar: [
                    "يناير",
                    "فبراير",
                    "مارس",
                    "أبريل",
                    "مايو",
                    "يونيو",
                    "يوليو",
                    "أغسطس",
                    "سبتمبر",
                    "أكتوبر",
                    "نوفمبر",
                    "ديسمبر",
                ],
                en: [
                    "January",
                    "February",
                    "March",
                    "April",
                    "May",
                    "June",
                    "July",
                    "August",
                    "September",
                    "October",
                    "November",
                    "December",
                ],
                fr: [
                    "Janvier",
                    "Février",
                    "Mars",
                    "Avril",
                    "Mai",
                    "Juin",
                    "Juillet",
                    "Août",
                    "Septembre",
                    "Octobre",
                    "Novembre",
                    "Décembre",
                ],
            };

            const monthNames = months[lang] || months.ar;

            return `${date.getDate()} ${monthNames[date.getMonth()]} ${date.getFullYear()}`;
        },

        formatNumber(num) {
            const number = Number(num) || 0;

            return number >= 1000 ? `${(number / 1000).toFixed(0)}k` : number;
        },

        getPlaceholderImage() {
            return this.fallbackImage;
        },

        getEventRoute(event) {
            if (!event?.slug) {
                return "#";
            }

            const lang = localStorage.getItem("language") || "ar";

            return {
                name: "single_event",
                params: {
                    lang,
                    slug: event.slug,
                },
            };
        },

        viewEvent(event) {
            if (!event?.slug) {
                return;
            }

            this.$router.push(this.getEventRoute(event));
        },
    },
};
</script>


<style scoped>
@import url("https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&family=Syne:wght@400;600;700;800&display=swap");

* {
    box-sizing: border-box;
    margin: 0;
    padding: 0;
}

.events-page {
    min-height: 100vh;
    background: linear-gradient(135deg, #f5f7fa 0%, #e8ecf1 100%);
    font-family: "Outfit", sans-serif;
    padding: 2rem 1rem;
    direction: rtl;
}

/* Header */
.page-header {
    max-width: 1400px;
    margin: 0 auto 3rem;
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    flex-wrap: wrap;
    gap: 2rem;
}

.header-content {
    flex: 1;
}

.main-title {
    font-family: "Syne", sans-serif;
    font-size: clamp(2rem, 5vw, 3.5rem);
    font-weight: 800;
    color: #1a1a2e;
    margin-bottom: 0.5rem;
    letter-spacing: -0.02em;
}

.subtitle {
    font-size: 1.1rem;
    color: #64748b;
    font-weight: 300;
}

.header-actions {
    display: flex;
    align-items: center;
    gap: 1rem;
    flex-wrap: wrap;
}

.sort-control,
.filter-control {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1rem 1.5rem;
    background: white;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
}

.sort-control label,
.filter-control label {
    font-weight: 500;
    color: #475569;
    white-space: nowrap;
}

.sort-select {
    padding: 0.5rem 1rem;
    border: 2px solid #e2e8f0;
    border-radius: 8px;
    font-family: inherit;
    font-size: 1rem;
    cursor: pointer;
    transition: all 0.3s ease;
    background: white;
}

.sort-select:hover,
.sort-select:focus {
    border-color: #3b82f6;
    outline: none;
}

.btn-view {
    display: inline-block;
    padding: 0.4rem 1rem;
    background: #f1f5f9;
    color: #475569;
    border-radius: 20px;
    font-size: 0.85rem;
    font-weight: 500;
    text-decoration: underline;
    margin-right: 3%;
}

.btn-view:hover {
    background: #e2e8f0;
    color: #1a1a2e;
    scale: 1.2;
    transition: all 0.5s ease;
}

/* Loading & Error States */
.loading-container,
.error-container {
    text-align: center;
    padding: 4rem 2rem;
    max-width: 600px;
    margin: 0 auto;
}

.loader {
    width: 50px;
    height: 50px;
    border: 4px solid #e2e8f0;
    border-top-color: #3b82f6;
    border-radius: 50%;
    margin: 0 auto 1rem;
    animation: spin 0.8s linear infinite;
}

@keyframes spin {
    to {
        transform: rotate(360deg);
    }
}

.error-container p {
    color: #ef4444;
    font-size: 1.1rem;
    margin-bottom: 1rem;
}

.retry-btn {
    padding: 0.75rem 2rem;
    background: #3b82f6;
    color: white;
    border: none;
    border-radius: 8px;
    font-family: inherit;
    font-size: 1rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
}

.retry-btn:hover {
    background: #2563eb;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
}

/* Events Grid */
.events-grid {
    max-width: 1400px;
    margin: 0 auto;
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 2rem;
    margin-bottom: 4rem;
}

.event-card {
    background: white;
    border-radius: 16px;
    overflow: hidden;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
    cursor: pointer;
    transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    position: relative;
}

.event-card:hover {
    transform: translateY(-8px);
    box-shadow: 0 12px 28px rgba(0, 0, 0, 0.15);
}

.event-image {
    position: relative;
    width: 100%;
    height: 240px;
    overflow: hidden;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}

.event-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.6s ease;
}

.event-card:hover .event-image img {
    transform: scale(1.08);
}

.event-date {
    position: absolute;
    top: 1rem;
    right: 1rem;
    background: white;
    padding: 0.5rem 1rem;
    border-radius: 8px;
    font-weight: 600;
    font-size: 0.9rem;
    color: #1a1a2e;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
}

.event-content {
    padding: 1.5rem;
}

.event-location {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    color: #64748b;
    font-size: 0.9rem;
    margin-bottom: 0.75rem;
}

.location-icon {
    width: 16px;
    height: 16px;
}

.event-title {
    font-family: "Syne", sans-serif;
    font-size: 1.3rem;
    font-weight: 700;
    color: #1a1a2e;
    margin-bottom: 0.5rem;
    line-height: 1.3;
    min-height: 2.6rem;
}

.event-category {
    display: inline-block;
    padding: 0.4rem 1rem;
    background: #f1f5f9;
    color: #475569;
    border-radius: 20px;
    font-size: 0.85rem;
    font-weight: 500;
}

/* Pagination */
.pagination {
    max-width: 1400px;
    margin: 0 auto 3rem;
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.page-btn,
.page-number {
    min-width: 40px;
    height: 40px;
    border: none;
    background: white;
    color: #475569;
    border-radius: 8px;
    cursor: pointer;
    font-family: inherit;
    font-size: 1rem;
    font-weight: 500;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 0 0.75rem;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
}

.page-btn:hover:not(:disabled),
.page-number:hover {
    background: #f1f5f9;
    transform: translateY(-2px);
}

.page-btn:disabled {
    opacity: 0.4;
    cursor: not-allowed;
}

.page-btn svg {
    width: 20px;
    height: 20px;
}

.page-number.active {
    background: #3b82f6;
    color: white;
}

.page-info {
    margin-right: 1rem;
    color: #64748b;
    font-size: 0.95rem;
}

/* Stats Footer */
.stats-footer {
    max-width: 1400px;
    margin: 0 auto 2rem;
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 2rem;
    padding: 3rem 2rem;
    background: white;
    border-radius: 16px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
}

.stat-item {
    text-align: center;
    padding: 1rem;
}

.stat-number {
    font-family: "Syne", sans-serif;
    font-size: 3rem;
    font-weight: 800;
    color: #1a1a2e;
    margin-bottom: 0.5rem;
}

.stat-label {
    font-size: 0.85rem;
    font-weight: 600;
    color: #64748b;
    letter-spacing: 0.1em;
}

.brand-footer {
    text-align: center;
    color: #94a3b8;
    font-size: 0.9rem;
    margin-top: 2rem;
    padding-bottom: 2rem;
}

/* Responsive */
@media (max-width: 768px) {
    .events-page {
        padding: 1rem 0.5rem;
    }

    .page-header {
        flex-direction: column;
        gap: 1.5rem;
    }

    .header-actions {
        width: 100%;
        flex-direction: column;
        align-items: stretch;
    }

    .sort-control,
    .filter-control {
        width: 100%;
        justify-content: space-between;
    }

    .sort-select {
        flex: 1;
        min-width: 0;
    }

    .events-grid {
        grid-template-columns: 1fr;
        gap: 1.5rem;
    }

    .main-title {
        font-size: 2rem;
    }

    .stat-number {
        font-size: 2.5rem;
    }

    .stats-footer {
        grid-template-columns: 1fr;
        gap: 1.5rem;
    }
}

/* Animations */
@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(30px);
    }

    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.event-card {
    animation: fadeInUp 0.6s ease backwards;
}

.event-card:nth-child(1) {
    animation-delay: 0.1s;
}

.event-card:nth-child(2) {
    animation-delay: 0.2s;
}

.event-card:nth-child(3) {
    animation-delay: 0.3s;
}

.event-card:nth-child(4) {
    animation-delay: 0.4s;
}

.event-card:nth-child(5) {
    animation-delay: 0.5s;
}

.event-card:nth-child(6) {
    animation-delay: 0.6s;
}

.event-card:nth-child(7) {
    animation-delay: 0.7s;
}

.event-card:nth-child(8) {
    animation-delay: 0.8s;
}

.events-page {
    background:
        radial-gradient(circle at top left, rgba(48, 168, 255, 0.10), transparent 32rem),
        linear-gradient(180deg, #FFFFFF, #F8FAFC);
    color: #0F172A;
}

.page-header {
    padding: 2rem;
    border: 1px solid #DCE8F5;
    border-radius: 28px;
    background:
        radial-gradient(circle at top right, rgba(48, 168, 255, 0.16), transparent 20rem),
        linear-gradient(135deg, #FFFFFF, #F4F8FC);
    box-shadow: 0 10px 35px rgba(13, 77, 151, 0.06);
}

.main-title {
    color: #06142A;
}

.subtitle {
    color: #64748B;
}

.sort-control,
.filter-control {
    border: 1px solid #DCE8F5;
    border-radius: 16px;
    box-shadow: 0 10px 30px rgba(13, 77, 151, 0.06);
}

.sort-select {
    border-color: #DCE8F5;
    border-radius: 12px;
}

.sort-select:focus {
    border-color: #1677FF;
    box-shadow: 0 0 0 4px rgba(22, 119, 255, 0.10);
}

.event-card {
    border: 1px solid #E5EDF6;
    border-radius: 22px;
    background: #FFFFFF;
    box-shadow: 0 10px 35px rgba(13, 77, 151, 0.06);
}

.event-card:hover {
    border-color: #CFE2F6;
    box-shadow: 0 18px 55px rgba(13, 77, 151, 0.12);
}

.btn-view {
    background: linear-gradient(135deg, #0D4D97, #1677FF);
}

.pagination button,
.page-btn {
    border-color: #DCE8F5;
    border-radius: 12px;
}
</style>
