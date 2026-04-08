<template>
    <div class="events-page">
        <!-- Header Section -->
        <header class="page-header">
            <div class="header-content">
                <h1 class="main-title">{{ $t("events.all_memories") }}</h1>
                <p class="subtitle">
                    {{ $t("events.showing_snapshots", { total: totalEvents }) }}
                </p>
            </div>

            <div class="header-actions">
                <!-- زرار RouterLink -->
                <RouterLink :to="`/${lang}/add_event/historical`" class="btn btn-primary">
                    {{ $t("events.add_historical_event") }}
                </RouterLink>
            </div>

            <div class="sort-control">
                <label>{{ $t("events.sort_by") }}:</label>
                <select v-model="sortBy" @change="sortEvents" class="sort-select">
                    <option value="newest">{{ $t("events.sort.newest") }}</option>
                    <option value="oldest">{{ $t("events.sort.oldest") }}</option>
                    <option value="title">{{ $t("events.sort.title") }}</option>
                </select>
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
            <button @click="fetchEvents" class="retry-btn">{{ $t("events.retry") }}</button>
        </div>

        <!-- Events Grid -->
        <div v-else class="events-grid">
            <div v-for="event in events" :key="event.id" class="event-card" @click="viewEvent(event)">
                <div class="event-image">
                    <img :src="event.images?.length > 0 ? event.images[0].url : getPlaceholderImage(event)
                        " :alt="event.translation.title" />
                    <span class="event-date">{{ formatDate(event.start_date) }}</span>
                </div>
                <div class="event-content">
                    <div class="event-location">
                        <svg class="location-icon" viewBox="0 0 24 24" fill="currentColor">
                            <path
                                d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z" />
                        </svg>
                        <span>{{ event.city?.translation.name || "-" }}</span>
                    </div>
                    <h3 class="event-title">{{ event.translation.title || "-" }}</h3>
                    <p class="event-category">{{ event.sub_categorey?.translation.name || "-" }}</p>
                    <router-link :to="{ path: `/${lang}/single_event/${event.slug}` }" class="btn-view">
                        تفاصيل الحدث
                    </router-link>
                </div>
            </div>
        </div>

        <!-- Pagination -->
        <div v-if="!loading && !error" class="pagination">
            <button @click="goToPage(currentPage - 1)" :disabled="currentPage === 1" class="page-btn">
                <svg viewBox="0 0 24 24" fill="currentColor">
                    <path d="M15.41 7.41L14 6l-6 6 6 6 1.41-1.41L10.83 12z" />
                </svg>
            </button>

            <button v-for="page in visiblePages" :key="page" @click="goToPage(page)"
                :class="['page-number', { active: page === currentPage }]">
                {{ page }}
            </button>

            <button @click="goToPage(currentPage + 1)" :disabled="currentPage === lastPage" class="page-btn">
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
            apiBaseUrl: "http://127.0.0.1:8000/api/v1",
            uniqueCities: 0,
            uniqueCategories: 0,
            lang: localStorage.getItem("language") || "ar",
        };
    },
    computed: {
        visiblePages() {
            const pages = [];
            const maxVisible = 5;

            if (this.lastPage <= maxVisible) {
                for (let i = 1; i <= this.lastPage; i++) pages.push(i);
            } else {
                pages.push(1);
                if (this.currentPage > 3) pages.push("...");
                for (
                    let i = Math.max(2, this.currentPage - 1);
                    i <= Math.min(this.lastPage - 1, this.currentPage + 1);
                    i++
                ) {
                    if (!pages.includes(i)) pages.push(i);
                }
                if (this.currentPage < this.lastPage - 2) pages.push("...");
                if (!pages.includes(this.lastPage)) pages.push(this.lastPage);
            }

            return pages;
        },
    },
    mounted() {
        this.fetchEvents();
    },
    methods: {
        async fetchEvents(page = 1) {
            this.loading = true;
            this.error = null;

            try {
                const token = localStorage.getItem("auth_token");

                const response = await fetch(`${this.apiBaseUrl}/events/historical?page=${page}`, {
                    method: "GET",
                    headers: {
                        "Accept": "application/json",
                        "Content-Type": "application/json",
                        "Accept-Language": localStorage.getItem("language") || "ar",
                        "Authorization": `Bearer ${token}`,
                    },
                });

                if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);

                const result = await response.json();

                if (result.status === "success") {
                    this.events = Array.isArray(result.data.data)
                        ? result.data.data
                        : result.data.data
                            ? [result.data.data]
                            : [];

                    this.currentPage = result.data.current_page || 1;
                    this.lastPage = result.data.last_page || 1;
                    this.totalEvents = result.data.total || this.events.length;
                    this.perPage = result.data.per_page || 8;

                    this.calculateStats();
                    console.log(this.events);
                } else {
                    throw new Error(result.message || "Failed to fetch events");
                }
            } catch (err) {
                console.error("Error fetching events:", err);
                this.events = [];
                this.error = "فشل في تحميل الفعاليات. الرجاء المحاولة مرة أخرى.";
            } finally {
                this.loading = false;
            }
        },

        calculateStats() {
            const cities = new Set();
            const categories = new Set();

            this.events.forEach((e) => {
                if (e?.city?.id) cities.add(e.city.id);
                if (e?.sub_categorey?.id) categories.add(e.sub_categorey.id);
            });

            this.uniqueCities = cities.size;
            this.uniqueCategories = categories.size;
        },

        goToPage(page) {
            if (page >= 1 && page <= this.lastPage && page !== this.currentPage) {
                this.fetchEvents(page);
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
                    sorted.sort((a, b) => a.title.localeCompare(b.title, "ar"));
                    break;
            }

            this.events = sorted;
        },

        formatDate(dateString) {
            if (!dateString) return "-";

            const date = new Date(dateString);

            const lang = (localStorage.getItem("language") || "ar").toLowerCase();

            const months = {
                ar: [
                    "يناير", "فبراير", "مارس", "أبريل", "مايو", "يونيو",
                    "يوليو", "أغسطس", "سبتمبر", "أكتوبر", "نوفمبر", "ديسمبر"
                ],
                en: [
                    "January", "February", "March", "April", "May", "June",
                    "July", "August", "September", "October", "November", "December"
                ],
                fr: [
                    "Janvier", "Février", "Mars", "Avril", "Mai", "Juin",
                    "Juillet", "Août", "Septembre", "Octobre", "Novembre", "Décembre"
                ]
            };

            const monthNames = months[lang] || months["ar"]; // fallback للعربية

            return `${date.getDate()} ${monthNames[date.getMonth()]} ${date.getFullYear()}`;
        },

        formatNumber(num) {
            return num >= 1000 ? (num / 1000).toFixed(0) + "k" : num;
        },

        getPlaceholderImage(event) {
            const colors = ["#1e3a5f", "#2d5a3d", "#5a3d2d", "#3d2d5a", "#2d4d5a"];
            const color = colors[event?.id % colors.length] || "#1e3a5f";
            const subName = event?.sub_categorey?.translation.name || "حدث";

            return `data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='400' height='300'%3E%3Crect fill='${encodeURIComponent(
                color
            )}' width='400' height='300'/%3E%3Ctext x='50%25' y='50%25' dominant-baseline='middle' text-anchor='middle' fill='white' font-family='Arial' font-size='20'%3E${encodeURIComponent(
                subName
            )}%3C/text%3E%3C/svg%3E`;
        },

        viewEvent(event) {
            if (!event?.slug) return;
            this.$router.push({ name: "event-details", params: { slug: event.slug } });
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

.sort-control {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1rem 1.5rem;
    background: white;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
}

.sort-control label {
    font-weight: 500;
    color: #475569;
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
</style>
