<template>
    <main class="activity-page">
        <section class="activity-hero">
            <div>
                <span class="eyebrow">Scemory</span>
                <h1>{{ t("timeline.title") }}</h1>
                <p>{{ t("timeline.subtitle") }}</p>
            </div>

            <div class="summary-grid">
                <div class="summary-card">
                    <i class="bi bi-activity"></i>
                    <div>
                        <span>{{ t("timeline.totalActivity") }}</span>
                        <strong>{{ summary.total }}</strong>
                    </div>
                </div>
                <div class="summary-card">
                    <i class="bi bi-clock-history"></i>
                    <div>
                        <span>{{ t("timeline.latestActivity") }}</span>
                        <strong class="summary-date">
                            {{ summary.latest_activity_at ? formatDate(summary.latest_activity_at) : t("timeline.noLatestActivity") }}
                        </strong>
                    </div>
                </div>
            </div>
        </section>

        <section class="filter-panel">
            <label>
                <span>{{ t("timeline.filters.label") }}</span>
                <select v-model="period" @change="handlePeriodChange">
                    <option v-for="option in periodOptions" :key="option.value" :value="option.value">
                        {{ option.label }}
                    </option>
                </select>
            </label>

            <template v-if="period === 'custom'">
                <label>
                    <span>{{ t("timeline.filters.from") }}</span>
                    <input v-model="customFrom" type="date" :max="customTo || undefined" />
                </label>
                <label>
                    <span>{{ t("timeline.filters.to") }}</span>
                    <input v-model="customTo" type="date" :min="customFrom || undefined" />
                </label>
                <button class="apply-button" type="button" @click="applyDateFilter">
                    {{ t("timeline.filters.apply") }}
                </button>
            </template>

            <p v-if="dateError" class="filter-error">{{ dateError }}</p>
        </section>

        <nav class="activity-tabs" aria-label="Activity sections">
            <button
                v-for="tab in tabs"
                :key="tab.key"
                type="button"
                :class="['activity-tab', { active: activeTab === tab.key }]"
                @click="selectTab(tab.key)"
            >
                <i :class="tab.icon"></i>
                <span>{{ tab.label }}</span>
                <small>{{ tabCount(tab.key) }}</small>
            </button>
        </nav>

        <section v-if="loading" class="state-panel">
            <span class="spinner-border spinner-border-sm" aria-hidden="true"></span>
            {{ t("timeline.loading") }}
        </section>

        <section v-else-if="loadError" class="state-panel error-state">
            <i class="bi bi-exclamation-circle"></i>
            <p>{{ t("timeline.loadError") }}</p>
            <button type="button" @click="reloadCurrent">{{ t("timeline.retry") }}</button>
        </section>

        <section v-else-if="activeTab === 'all'" class="overview-sections">
            <article v-for="group in activityGroups" :key="group.key" class="activity-section">
                <header class="section-heading">
                    <div>
                        <i :class="group.icon"></i>
                        <h2>{{ group.label }}</h2>
                        <span>{{ sections[group.key].total }}</span>
                    </div>
                    <button
                        v-if="sections[group.key].total"
                        type="button"
                        class="view-all-button"
                        @click="selectTab(group.key)"
                    >
                        {{ t("timeline.viewAll") }}
                        <i class="bi bi-arrow-right"></i>
                    </button>
                </header>

                <div v-if="sections[group.key].data.length" class="activity-grid">
                    <article
                        v-for="item in sections[group.key].data"
                        :key="`${group.key}-${item.id}`"
                        class="activity-item"
                    >
                        <ActivityContent :item="item" :section="group.key" />
                    </article>
                </div>
                <p v-else class="section-empty">{{ t("timeline.empty") }}</p>
            </article>
        </section>

        <section v-else class="activity-section focused-section">
            <header class="section-heading">
                <div>
                    <i :class="currentTab.icon"></i>
                    <h2>{{ currentTab.label }}</h2>
                    <span>{{ currentSection.total }}</span>
                </div>
            </header>

            <div v-if="currentSection.data.length" class="activity-grid">
                <article
                    v-for="item in currentSection.data"
                    :key="`${activeTab}-${item.id}`"
                    class="activity-item"
                >
                    <ActivityContent :item="item" :section="activeTab" />
                </article>
            </div>
            <p v-else class="section-empty large-empty">{{ t("timeline.empty") }}</p>

            <footer v-if="currentSection.pagination?.last_page > 1" class="pagination-bar">
                <button
                    type="button"
                    :disabled="currentSection.pagination.current_page <= 1"
                    @click="changePage(currentSection.pagination.current_page - 1)"
                >
                    <i class="bi bi-chevron-left"></i>
                    {{ t("timeline.previous") }}
                </button>
                <span>
                    {{ t("timeline.pageOf", {
                        current: currentSection.pagination.current_page,
                        last: currentSection.pagination.last_page,
                    }) }}
                </span>
                <button
                    type="button"
                    :disabled="!currentSection.pagination.has_more"
                    @click="changePage(currentSection.pagination.current_page + 1)"
                >
                    {{ t("timeline.next") }}
                    <i class="bi bi-chevron-right"></i>
                </button>
            </footer>
        </section>
    </main>
</template>

<script setup>
import { computed, defineComponent, h, onBeforeUnmount, onMounted, ref } from "vue";
import { RouterLink, useRoute } from "vue-router";
import { useI18n } from "vue-i18n";
import { profileTimeline } from "@/services/profileTimeline/profileTimeline";

const { t, locale } = useI18n();
const route = useRoute();
const sectionKeys = ["events", "likes", "comments", "replies", "comment_images", "comment_interactions", "wishlists"];

const emptySection = () => ({ data: [], total: 0, latest_activity_at: null, pagination: null, has_more: false });
const sections = ref(Object.fromEntries(sectionKeys.map((key) => [key, emptySection()])));
const summary = ref({ total: 0, latest_activity_at: null, counts: {} });
const activeTab = ref("all");
const period = ref("all");
const customFrom = ref("");
const customTo = ref("");
const dateError = ref("");
const loading = ref(false);
const loadError = ref(false);
let abortController = null;

const tabs = computed(() => [
    { key: "all", label: t("timeline.tabs.all"), icon: "bi bi-grid" },
    { key: "events", label: t("timeline.tabs.events"), icon: "bi bi-calendar-event" },
    { key: "likes", label: t("timeline.tabs.likes"), icon: "bi bi-heart-fill" },
    { key: "comments", label: t("timeline.tabs.comments"), icon: "bi bi-chat-dots" },
    { key: "replies", label: t("timeline.tabs.replies"), icon: "bi bi-reply" },
    { key: "comment_images", label: t("timeline.tabs.comment_images"), icon: "bi bi-image" },
    { key: "comment_interactions", label: t("timeline.tabs.comment_interactions"), icon: "bi bi-emoji-smile" },
    { key: "wishlists", label: t("timeline.tabs.wishlists"), icon: "bi bi-bookmark-fill" },
]);

const activityGroups = computed(() => tabs.value.filter((tab) => tab.key !== "all"));
const currentTab = computed(() => tabs.value.find((tab) => tab.key === activeTab.value) || tabs.value[0]);
const currentSection = computed(() => sections.value[activeTab.value] || emptySection());
const periodOptions = computed(() => [
    { value: "all", label: t("timeline.filters.all") },
    { value: "today", label: t("timeline.filters.today") },
    { value: "this_week", label: t("timeline.filters.this_week") },
    { value: "this_month", label: t("timeline.filters.this_month") },
    { value: "custom", label: t("timeline.filters.custom") },
]);

const localizedEventPath = (slug) => {
    const lang = route.params.lang || localStorage.getItem("lang") || "en";
    return `/${lang}/single_event/${slug}`;
};

const eventFor = (item, section) => section === "events" ? item : item.event;

const itemTitle = (item, section) => {
    if (section === "events" || section === "likes" || section === "wishlists") {
        return eventFor(item, section)?.title || t("timeline.unknownEvent");
    }
    if (section === "comment_images") {
        return item.original_name || item.comment?.text || t("timeline.tabs.comment_images");
    }
    if (section === "comment_interactions") {
        return t(`timeline.reactions.${item.type}`);
    }
    return item.text || t(`timeline.tabs.${section}`);
};

const itemDescription = (item, section) => {
    const event = eventFor(item, section);
    if (section === "events") {
        return [item.category?.name, item.location?.city?.name, statusLabel(item.status)].filter(Boolean).join(" · ");
    }
    if (section === "replies") {
        return t("timeline.parentComment", { comment: truncate(item.comment?.text) });
    }
    if (section === "comment_interactions") {
        return t("timeline.reactionTo", { comment: truncate(item.comment?.text) });
    }
    if (section === "comment_images") {
        return t("timeline.onEvent", { event: event?.title || t("timeline.unknownEvent") });
    }
    if (section === "comments") {
        return t("timeline.onEvent", { event: event?.title || t("timeline.unknownEvent") });
    }
    return statusLabel(event?.status);
};

const itemImage = (item, section) => {
    if (section === "events") return item.image?.url;
    if (section === "comment_images") return item.url;
    if (section === "comments") return item.images?.[0]?.url || item.event?.image_url;
    return item.event?.image_url;
};

const truncate = (value, length = 80) => {
    const text = String(value || "").trim();
    return text.length > length ? `${text.slice(0, length)}…` : text;
};

const statusLabel = (status) => status ? t(`timeline.status.${status}`) : "";

const formatDate = (value) => {
    if (!value) return "";
    return new Intl.DateTimeFormat(locale.value, {
        dateStyle: "medium",
        timeStyle: "short",
    }).format(new Date(value));
};

const ActivityContent = defineComponent({
    props: {
        item: { type: Object, required: true },
        section: { type: String, required: true },
    },
    setup(props) {
        return () => {
            const event = eventFor(props.item, props.section);
            const image = itemImage(props.item, props.section);
            const content = [
                image
                    ? h("img", { class: "item-image", src: image, alt: "", loading: "lazy" })
                    : h("div", { class: "item-placeholder" }, [h("i", { class: currentIcon(props.section) })]),
                h("div", { class: "item-body" }, [
                    h("span", { class: "item-label" }, t(`timeline.tabs.${props.section}`)),
                    h("h3", itemTitle(props.item, props.section)),
                    itemDescription(props.item, props.section)
                        ? h("p", itemDescription(props.item, props.section))
                        : null,
                    props.section === "comments" && props.item.images?.length > 1
                        ? h("div", { class: "comment-thumbnails" }, props.item.images.slice(0, 4).map((attachment) =>
                            h("img", { key: attachment.id, src: attachment.url, alt: "", loading: "lazy" })
                        ))
                        : null,
                    h("time", { datetime: props.item.created_at }, formatDate(props.item.created_at)),
                ]),
            ];

            if (event?.slug) {
                return h(RouterLink, { class: "item-link", to: localizedEventPath(event.slug) }, () => content);
            }

            return h("div", { class: "item-link" }, content);
        };
    },
});

const currentIcon = (section) => tabs.value.find((tab) => tab.key === section)?.icon || "bi bi-activity";
const tabCount = (key) => key === "all" ? summary.value.total : (summary.value.counts?.[key] ?? sections.value[key]?.total ?? 0);

const requestParams = (section, page = 1) => {
    const params = {
        section,
        period: period.value,
        page,
        per_page: 12,
        timezone: Intl.DateTimeFormat().resolvedOptions().timeZone || "UTC",
    };
    if (period.value === "custom") {
        params.from = customFrom.value;
        params.to = customTo.value;
    }
    return params;
};

const loadSection = async (section = activeTab.value, page = 1) => {
    abortController?.abort();
    abortController = new AbortController();
    loading.value = true;
    loadError.value = false;
    try {
        const response = await profileTimeline.getTimeline(requestParams(section, page), {
            signal: abortController.signal,
        });
        const payload = response.data.data;
        if (section === "all") {
            sectionKeys.forEach((key) => {
                sections.value[key] = { ...emptySection(), ...(payload[key] || {}) };
            });
            summary.value = payload.summary || summary.value;
        } else {
            sections.value[section] = { ...emptySection(), ...(payload[section] || {}) };
            summary.value = {
                ...summary.value,
                counts: {
                    ...summary.value.counts,
                    [section]: payload[section]?.total || 0,
                },
            };
            summary.value.total = Object.values(summary.value.counts).reduce((total, count) => total + Number(count || 0), 0);
        }
    } catch (error) {
        if (error?.name !== "CanceledError" && error?.code !== "ERR_CANCELED") {
            loadError.value = true;
        }
    } finally {
        loading.value = false;
    }
};

const selectTab = async (key) => {
    if (activeTab.value === key && sections.value[key]?.data?.length) return;
    activeTab.value = key;
    await loadSection(key, 1);
};

const handlePeriodChange = () => {
    dateError.value = "";
    if (period.value !== "custom") applyDateFilter();
};

const applyDateFilter = async () => {
    if (period.value === "custom" && (!customFrom.value || !customTo.value || customFrom.value > customTo.value)) {
        dateError.value = t("timeline.filters.invalidRange");
        return;
    }
    dateError.value = "";
    const selectedTab = activeTab.value;
    await loadSection("all", 1);
    if (selectedTab !== "all" && !loadError.value) {
        activeTab.value = selectedTab;
        await loadSection(selectedTab, 1);
    }
};

const changePage = async (page) => {
    await loadSection(activeTab.value, page);
    window.scrollTo({ top: 0, behavior: "smooth" });
};

const reloadCurrent = () => loadSection(activeTab.value, currentSection.value.pagination?.current_page || 1);

onMounted(() => loadSection("all", 1));
onBeforeUnmount(() => abortController?.abort());
</script>

<style scoped>
.activity-page { width: min(1180px, calc(100% - 32px)); margin: 110px auto 64px; color: #172036; }
.activity-hero { display: flex; justify-content: space-between; gap: 32px; align-items: flex-end; padding: 34px; border-radius: 24px; color: white; background: linear-gradient(135deg, #172036, #243b64 62%, #315f78); box-shadow: 0 22px 60px rgba(20, 34, 57, .18); }
.eyebrow { display: block; margin-bottom: 8px; color: #9fd6df; font-size: .75rem; font-weight: 800; letter-spacing: .16em; text-transform: uppercase; }
.activity-hero h1 { margin: 0 0 8px; font-size: clamp(2rem, 4vw, 3.25rem); font-weight: 800; }
.activity-hero p { margin: 0; max-width: 600px; color: #d8e3ee; }
.summary-grid { display: grid; grid-template-columns: repeat(2, minmax(150px, 1fr)); gap: 12px; }
.summary-card { display: flex; gap: 12px; align-items: center; min-width: 190px; padding: 16px; border: 1px solid rgba(255,255,255,.18); border-radius: 16px; background: rgba(255,255,255,.09); backdrop-filter: blur(8px); }
.summary-card i { color: #9fd6df; font-size: 1.25rem; }
.summary-card span { display: block; color: #cbd8e5; font-size: .72rem; }
.summary-card strong { display: block; margin-top: 2px; font-size: 1.2rem; }
.summary-card .summary-date { font-size: .82rem; }
.filter-panel { display: flex; align-items: end; flex-wrap: wrap; gap: 14px; margin: 24px 0 18px; padding: 18px; border: 1px solid #e4e9f0; border-radius: 18px; background: #fff; }
.filter-panel label { display: grid; gap: 6px; min-width: 180px; color: #536079; font-size: .78rem; font-weight: 700; }
.filter-panel select, .filter-panel input { height: 44px; padding: 0 12px; border: 1px solid #d8dee8; border-radius: 11px; color: #172036; background: #f9fbfd; }
.apply-button, .state-panel button, .pagination-bar button { min-height: 44px; padding: 0 18px; border: 0; border-radius: 11px; color: white; background: #244d65; font-weight: 700; }
.filter-error { width: 100%; margin: 0; color: #b42318; font-size: .82rem; }
.activity-tabs { display: flex; gap: 8px; overflow-x: auto; padding: 4px 2px 14px; scrollbar-width: thin; }
.activity-tab { display: inline-flex; align-items: center; gap: 8px; flex: 0 0 auto; padding: 11px 14px; border: 1px solid #dde4ec; border-radius: 999px; color: #536079; background: #fff; font-weight: 700; transition: .2s ease; }
.activity-tab small { min-width: 24px; padding: 2px 6px; border-radius: 999px; background: #eef2f6; text-align: center; }
.activity-tab.active { border-color: #244d65; color: white; background: #244d65; box-shadow: 0 8px 24px rgba(36,77,101,.2); }
.activity-tab.active small { color: #244d65; background: white; }
.overview-sections { display: grid; gap: 18px; }
.activity-section { padding: 22px; border: 1px solid #e4e9f0; border-radius: 20px; background: #fff; box-shadow: 0 10px 34px rgba(25, 39, 64, .06); }
.section-heading { display: flex; justify-content: space-between; align-items: center; gap: 16px; margin-bottom: 18px; }
.section-heading > div { display: flex; align-items: center; gap: 10px; }
.section-heading i { color: #2b7182; font-size: 1.1rem; }
.section-heading h2 { margin: 0; font-size: 1.1rem; font-weight: 800; }
.section-heading span { padding: 3px 8px; border-radius: 999px; color: #5d687d; background: #edf2f6; font-size: .72rem; font-weight: 800; }
.view-all-button { border: 0; color: #2b7182; background: transparent; font-size: .84rem; font-weight: 800; }
.activity-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; }
.activity-item { min-width: 0; overflow: hidden; border: 1px solid #e7ebf0; border-radius: 15px; background: #fbfcfe; transition: transform .2s ease, box-shadow .2s ease; }
.activity-item:hover { transform: translateY(-2px); box-shadow: 0 10px 28px rgba(26, 48, 70, .09); }
:deep(.item-link) { display: flex; gap: 14px; min-height: 122px; padding: 14px; color: inherit; text-decoration: none; }
:deep(.item-image), :deep(.item-placeholder) { width: 92px; height: 92px; flex: 0 0 92px; border-radius: 12px; object-fit: cover; }
:deep(.item-placeholder) { display: grid; place-items: center; color: #2b7182; background: #e9f1f4; font-size: 1.35rem; }
:deep(.item-body) { min-width: 0; display: flex; flex-direction: column; align-items: flex-start; }
:deep(.item-label) { color: #2b7182; font-size: .68rem; font-weight: 800; letter-spacing: .04em; text-transform: uppercase; }
:deep(.item-body h3) { width: 100%; margin: 5px 0; overflow: hidden; color: #172036; font-size: .98rem; font-weight: 800; text-overflow: ellipsis; white-space: nowrap; }
:deep(.item-body p) { display: -webkit-box; margin: 0 0 8px; overflow: hidden; color: #69758a; font-size: .78rem; -webkit-box-orient: vertical; -webkit-line-clamp: 2; }
:deep(.item-body time) { margin-top: auto; color: #8a94a6; font-size: .7rem; }
:deep(.comment-thumbnails) { display: flex; gap: 5px; margin-bottom: 8px; }
:deep(.comment-thumbnails img) { width: 28px; height: 28px; border-radius: 6px; object-fit: cover; }
.state-panel, .section-empty { display: flex; justify-content: center; align-items: center; gap: 10px; min-height: 150px; margin: 0; color: #6b768a; text-align: center; }
.error-state { flex-direction: column; }
.error-state i { color: #b42318; font-size: 1.8rem; }
.large-empty { min-height: 320px; }
.pagination-bar { display: flex; justify-content: center; align-items: center; gap: 18px; margin-top: 24px; padding-top: 20px; border-top: 1px solid #e7ebf0; color: #657086; font-size: .84rem; }
.pagination-bar button { display: inline-flex; align-items: center; gap: 6px; }
.pagination-bar button:disabled { cursor: not-allowed; opacity: .45; }
@media (max-width: 850px) { .activity-hero { align-items: stretch; flex-direction: column; } .summary-grid { width: 100%; } }
@media (max-width: 650px) { .activity-page { width: min(100% - 20px, 1180px); margin-top: 88px; } .activity-hero { padding: 24px; } .summary-grid, .activity-grid { grid-template-columns: 1fr; } .summary-card { min-width: 0; } .filter-panel label { width: 100%; } .apply-button { width: 100%; } :deep(.item-image), :deep(.item-placeholder) { width: 72px; height: 72px; flex-basis: 72px; } .section-heading { align-items: flex-start; } }
</style>
