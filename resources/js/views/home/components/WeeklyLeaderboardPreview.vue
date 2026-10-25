<template>
    <section class="weekly-leaderboard" :class="{ 'weekly-leaderboard--full': full }" :dir="isRtl ? 'rtl' : 'ltr'" aria-labelledby="weekly-leaderboard-title">
        <div class="weekly-leaderboard__container">
            <div class="weekly-leaderboard__layout" :class="{ 'weekly-leaderboard__layout--full': full }">
                <div v-if="!full" class="weekly-leaderboard__media-column">
                    <div class="weekly-leaderboard__media-card">
                        <video
                            class="weekly-leaderboard__video"
                            :src="videoUrl"
                            autoplay
                            muted
                            loop
                            playsinline
                            preload="auto"
                            aria-label="Scemory preview"
                        ></video>
                        <div class="weekly-leaderboard__video-wash" aria-hidden="true"></div>
                    </div>
                </div>

                <div class="weekly-leaderboard__panel">
                    <div class="weekly-leaderboard__glow" aria-hidden="true"></div>

                    <header class="weekly-leaderboard__header">
                        <div>
                            <span class="weekly-leaderboard__eyebrow">
                                <span aria-hidden="true">🏆</span>
                                {{ t("weeklyCompetition.title") }}
                            </span>
                            <h2 id="weekly-leaderboard-title">{{ t('weeklyCompetition.leaders') }}</h2>
                            <p>{{ t('weeklyCompetition.subtitle') }}</p>
                        </div>

                        <div class="weekly-leaderboard__period" aria-live="polite">
                            <strong>{{ periodLabel }}</strong>
                            <span>{{ resetLabel }}</span>
                        </div>
                    </header>

                    <div v-if="loading" class="weekly-leaderboard__grid" :aria-label="t('weeklyCompetition.loading')">
                        <div v-for="index in 6" :key="index" class="leader-card leader-card--skeleton">
                            <span class="skeleton skeleton--rank"></span>
                            <span class="skeleton skeleton--avatar"></span>
                            <span class="leader-card__identity">
                                <span class="skeleton skeleton--name"></span>
                                <span class="skeleton skeleton--badge"></span>
                            </span>
                            <span class="skeleton skeleton--points"></span>
                        </div>
                    </div>

                    <div v-else-if="error" class="weekly-leaderboard__state" role="alert">
                        <span class="weekly-leaderboard__state-icon" aria-hidden="true">↻</span>
                        <strong>{{ t('weeklyCompetition.error') }}</strong>
                        <p>{{ t('weeklyCompetition.tryLater') }}</p>
                        <button type="button" @click="loadLeaderboard(page)">{{ t('weeklyCompetition.retry') }}</button>
                    </div>

                    <div v-else-if="users.length === 0" class="weekly-leaderboard__state">
                        <span class="weekly-leaderboard__state-icon" aria-hidden="true">✦</span>
                        <strong>{{ t('weeklyCompetition.empty') }}</strong>
                        <p>{{ t('weeklyCompetition.emptyHint') }}</p>
                    </div>

                    <ol v-else class="weekly-leaderboard__grid">
                        <li
                            v-for="user in users"
                            :key="user.id"
                            class="leader-card"
                            :class="`leader-card--rank-${user.rank}`"
                        >
                            <span class="leader-card__rank" :aria-label="t('weeklyCompetition.rankValue', { rank: user.rank })">
                                <span v-if="user.rank <= 3" aria-hidden="true">{{ medalFor(user.rank) }}</span>
                                <span>#{{ user.rank }}</span>
                            </span>

                            <img
                                v-if="user.avatar && !failedAvatars[user.id]"
                                class="leader-card__avatar"
                                :src="user.avatar"
                                :alt="user.name"
                                loading="lazy"
                                @error="markAvatarFailed(user.id)"
                            />
                            <span v-else class="leader-card__avatar leader-card__avatar--fallback" aria-hidden="true">
                                {{ initialsFor(user.name) }}
                            </span>

                            <span class="leader-card__identity">
                                <strong>{{ user.name }}</strong>
                                <span class="leader-card__badge">{{ badgeLabel(user.badge) }}</span>
                            </span>

                            <span class="leader-card__points">
                                <strong>{{ formatPoints(user.points) }}</strong>
                                <small>{{ t('weeklyCompetition.pts') }}</small>
                            </span>
                        </li>
                    </ol>

                    <footer class="weekly-leaderboard__footer">
                        <RouterLink v-if="!full" :to="`/${locale}/leaderboard`" class="weekly-leaderboard__link">
                            <span class="weekly-leaderboard__link-desktop">{{ t('weeklyCompetition.viewFull') }}</span>
                            <span class="weekly-leaderboard__link-mobile">{{ t('weeklyCompetition.viewAll') }}</span>
                            <span class="weekly-leaderboard__arrow" aria-hidden="true">→</span>
                        </RouterLink>
                        <template v-else>
                            <button type="button" :disabled="loading || page <= 1" @click="loadLeaderboard(page - 1)">{{ t('weeklyCompetition.previous') }}</button>
                            <span>{{ page }} / {{ lastPage }}</span>
                            <button type="button" :disabled="loading || page >= lastPage" @click="loadLeaderboard(page + 1)">{{ t('weeklyCompetition.next') }}</button>
                        </template>
                    </footer>
                </div>
            </div>
        </div>
    </section>
</template>

<script setup>
import { computed, onMounted, onUnmounted, ref } from "vue";
import { useI18n } from "vue-i18n";
import { RouterLink } from "vue-router";
import { getWeeklyLeaderboardPreview, getWeeklyLeaderboard } from "@/services/leaderboardService";
import { toAssetUrl } from "@/services/runtimeUrls";

const props = defineProps({ full: { type: Boolean, default: false } });
const { locale, t } = useI18n();
const users = ref([]);
const loading = ref(true);
const error = ref(false);
const weekStart = ref("");
const weekEnd = ref("");
const timezone = ref("");
const page = ref(1);
const lastPage = ref(1);
const failedAvatars = ref({});
let rolloverTimer;

const videoUrl = typeof toAssetUrl === "function" ? toAssetUrl("/video/Final_Scemory.mp4") : "/video/Final_Scemory.mp4";
const isRtl = computed(() => ["ar", "fa", "ur"].includes(locale.value));

function formatDate(date) {
    if (!date) return "—";
    // These are business calendar dates, not browser-local instants.
    return new Intl.DateTimeFormat(locale.value, { dateStyle: "medium", timeZone: "UTC" })
        .format(new Date(`${date}T00:00:00Z`));
}
const periodLabel = computed(() => `${formatDate(weekStart.value)} – ${formatDate(weekEnd.value)}`);
const resetLabel = computed(() => t("weeklyCompetition.resets", { timezone: timezone.value }));

async function loadLeaderboard(requestedPage = 1) {
    loading.value = true;
    error.value = false;
    clearTimeout(rolloverTimer);
    try {
        const response = await (props.full ? getWeeklyLeaderboard(requestedPage) : getWeeklyLeaderboardPreview());
        const payload = response.data || {};
        users.value = Array.isArray(payload.users) ? (props.full ? payload.users : payload.users.slice(0, 10)) : [];
        weekStart.value = payload.week_start || "";
        weekEnd.value = payload.week_end || "";
        timezone.value = payload.timezone || "";
        page.value = payload.current_page || 1;
        lastPage.value = payload.last_page || 1;
        const delay = Date.parse(payload.ends_at) - Date.now();
        if (Number.isFinite(delay)) {
            rolloverTimer = setTimeout(() => loadLeaderboard(1), Math.max(1000, delay + 100));
        }
    } catch {
        error.value = true;
        users.value = [];
    } finally {
        loading.value = false;
    }
}
const formatPoints = (points) => new Intl.NumberFormat(locale.value).format(Number(points) || 0);
function initialsFor(name) {
    return String(name || "?").trim().split(/\s+/).slice(0, 2).map((part) => part.charAt(0).toUpperCase()).join("") || "?";
}
const medalFor = (rank) => ({ 1: "🥇", 2: "🥈", 3: "🥉" }[rank] || "");
function badgeLabel(badge) {
    const key = { "Memory Master": "master", "Story Keeper": "keeper", "Moment Curator": "curator", "Memory Contributor": "contributor" }[badge];
    return key ? t(`weeklyCompetition.${key}`) : badge;
}
function markAvatarFailed(userId) {
    failedAvatars.value = { ...failedAvatars.value, [userId]: true };
}
const refresh = () => loadLeaderboard(page.value);
onMounted(() => {
    loadLeaderboard();
    window.addEventListener("focus", refresh);
});
onUnmounted(() => {
    clearTimeout(rolloverTimer);
    window.removeEventListener("focus", refresh);
});
</script>

<style scoped>
.weekly-leaderboard {
    position: relative;
    overflow: hidden;
    width: 100%;
    padding: clamp(56px, 6vw, 88px) 0;
    background: linear-gradient(180deg, var(--scemory-surface) 0%, #ffffff 50%, var(--scemory-surface-soft) 100%);
}

.weekly-leaderboard__container {
    box-sizing: border-box;
    width: 100%;
    max-width: none;
    margin: 0;
    padding-inline: clamp(16px, 3.5vw, 48px);
}

.weekly-leaderboard__layout {
    display: grid;
    grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
    gap: clamp(24px, 3vw, 40px);
    align-items: stretch;
    width: 100%;
}

.weekly-leaderboard__layout--full {
    grid-template-columns: minmax(0, 1fr);
}

.weekly-leaderboard__media-column {
    display: flex;
    min-width: 0;
    height: 100%;
}

.weekly-leaderboard__media-card {
    position: relative;
    display: flex;
    width: 100%;
    height: 100%;
    min-height: clamp(540px, 60vh, 760px);
    overflow: hidden;
    border: 1px solid var(--scemory-border);
    border-radius: 28px;
    background: linear-gradient(145deg, #0d2847 0%, #163860 50%, #0b1f36 100%);
    box-shadow: var(--scemory-shadow);
    isolation: isolate;
}

.weekly-leaderboard__video {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
    direction: ltr;
    transform: none !important;
}

.weekly-leaderboard__video-wash {
    position: absolute;
    inset: 0;
    pointer-events: none;
    background:
        radial-gradient(circle at 15% 20%, rgba(56, 174, 234, 0.18), transparent 22rem),
        linear-gradient(180deg, rgba(13, 77, 151, 0.08) 0%, rgba(11, 31, 54, 0.28) 100%);
    border-radius: inherit;
}

.weekly-leaderboard__panel {
    position: relative;
    isolation: isolate;
    overflow: hidden;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    min-height: clamp(540px, 60vh, 760px);
    padding: clamp(24px, 3.5vw, 40px);
    border: 1px solid var(--scemory-border);
    border-radius: 28px;
    background:
        radial-gradient(circle at 92% 5%, rgba(56, 174, 234, 0.16), transparent 24rem),
        linear-gradient(145deg, #ffffff 0%, #f8fbff 55%, #eef7ff 100%);
    box-shadow: var(--scemory-shadow);
}

.weekly-leaderboard__glow {
    position: absolute;
    z-index: -1;
    inset-block-end: -140px;
    inset-inline-start: -90px;
    width: 320px;
    height: 320px;
    border-radius: 50%;
    background: rgba(22, 119, 255, 0.07);
}

.weekly-leaderboard__header {
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    gap: 20px;
    margin-block-end: 24px;
}

.weekly-leaderboard__eyebrow {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 8px 14px;
    border: 1px solid rgba(22, 119, 255, 0.16);
    border-radius: 999px;
    background: rgba(221, 236, 249, 0.72);
    color: var(--scemory-primary);
    font-size: 0.72rem;
    font-weight: 800;
    letter-spacing: 0.08em;
    text-transform: uppercase;
}

.weekly-leaderboard__header h2 {
    margin: 12px 0 0;
    color: var(--scemory-heading);
    font-size: clamp(1.6rem, 2.5vw, 2.35rem);
    font-weight: 850;
    letter-spacing: -0.035em;
    line-height: 1.1;
}

.weekly-leaderboard__header p {
    margin: 6px 0 0;
    color: var(--scemory-muted);
    font-size: 0.92rem;
}

.weekly-leaderboard__period {
    display: flex;
    flex: 0 0 auto;
    flex-direction: column;
    align-items: flex-end;
    gap: 4px;
    padding: 10px 14px;
    border: 1px solid rgba(22, 119, 255, 0.12);
    border-radius: 16px;
    background: rgba(255, 255, 255, 0.85);
    box-shadow: 0 8px 24px rgba(17, 75, 125, 0.08);
}

.weekly-leaderboard__period strong {
    color: var(--scemory-heading);
    font-size: 0.9rem;
}

.weekly-leaderboard__period span {
    color: var(--scemory-muted);
    font-size: 0.75rem;
}

.weekly-leaderboard__grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 12px;
    margin: 0;
    padding: 0;
    list-style: none;
}

.weekly-leaderboard:not(.weekly-leaderboard--full) .weekly-leaderboard__grid {
    grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
}

.leader-card {
    display: grid;
    grid-template-columns: auto auto minmax(0, 1fr) auto;
    align-items: center;
    gap: 12px;
    min-width: 0;
    padding: 12px 14px;
    border: 1px solid rgba(13, 77, 151, 0.1);
    border-radius: 18px;
    background: rgba(255, 255, 255, 0.92);
    box-shadow: 0 6px 18px rgba(17, 75, 125, 0.05);
    transition: transform 180ms ease, border-color 180ms ease, box-shadow 180ms ease;
}

.leader-card:hover {
    transform: translateY(-2px);
    border-color: rgba(56, 174, 234, 0.28);
    box-shadow: 0 12px 28px rgba(17, 75, 125, 0.1);
}

.leader-card--rank-1 {
    border-color: rgba(214, 164, 41, 0.38);
    background: linear-gradient(120deg, rgba(255, 249, 225, 0.98), rgba(255, 255, 255, 0.94));
}

.leader-card--rank-2 {
    border-color: rgba(148, 163, 184, 0.38);
    background: linear-gradient(120deg, rgba(244, 247, 250, 0.98), rgba(255, 255, 255, 0.94));
}

.leader-card--rank-3 {
    border-color: rgba(180, 115, 70, 0.35);
    background: linear-gradient(120deg, rgba(252, 241, 233, 0.98), rgba(255, 255, 255, 0.94));
}

.leader-card__rank {
    display: flex;
    width: 38px;
    flex-direction: column;
    align-items: center;
    color: var(--scemory-heading);
    font-size: 0.78rem;
    font-weight: 850;
    line-height: 1.15;
}

.leader-card__rank > span:first-child:not(:last-child) {
    font-size: 0.95rem;
}

.leader-card__avatar {
    display: grid;
    width: 44px;
    height: 44px;
    place-items: center;
    border: 2px solid rgba(255, 255, 255, 0.92);
    border-radius: 50%;
    object-fit: cover;
    box-shadow: 0 4px 12px rgba(13, 77, 151, 0.14);
}

.leader-card__avatar--fallback {
    background: linear-gradient(145deg, var(--scemory-primary), var(--scemory-light-blue));
    color: #fff;
    font-size: 0.76rem;
    font-weight: 850;
    letter-spacing: 0.03em;
}

.leader-card__identity {
    display: flex;
    min-width: 0;
    flex-direction: column;
    gap: 2px;
}

.leader-card__identity strong,
.leader-card__badge {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.leader-card__identity strong {
    color: var(--scemory-heading);
    font-size: 0.9rem;
    font-weight: 800;
}

.leader-card__badge {
    color: var(--scemory-muted);
    font-size: 0.72rem;
    font-weight: 600;
}

.leader-card__points {
    display: flex;
    align-items: baseline;
    gap: 4px;
    color: var(--scemory-primary);
    white-space: nowrap;
}

.leader-card__points strong {
    font-size: 0.95rem;
    font-weight: 900;
}

.leader-card__points small {
    color: var(--scemory-muted);
    font-size: 0.68rem;
    font-weight: 700;
}

.weekly-leaderboard__state {
    display: flex;
    min-height: 240px;
    align-items: center;
    justify-content: center;
    flex-direction: column;
    padding: 28px;
    border: 1px dashed rgba(13, 77, 151, 0.2);
    border-radius: 20px;
    background: rgba(255, 255, 255, 0.7);
    color: var(--scemory-muted);
    text-align: center;
}

.weekly-leaderboard__state-icon {
    margin-block-end: 10px;
    color: var(--scemory-light-blue);
    font-size: 1.8rem;
}

.weekly-leaderboard__state strong {
    color: var(--scemory-heading);
}

.weekly-leaderboard__state p {
    margin: 6px 0 0;
    font-size: 0.88rem;
}

.weekly-leaderboard__state button {
    margin-block-start: 14px;
    padding: 9px 18px;
    border: 0;
    border-radius: 999px;
    background: var(--scemory-primary);
    color: #fff;
    font-size: 0.82rem;
    font-weight: 800;
    cursor: pointer;
}

.weekly-leaderboard__footer {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 16px;
    margin-block-start: 24px;
}

.weekly-leaderboard__footer button {
    padding: 9px 18px;
    border: 1px solid var(--scemory-border);
    border-radius: 999px;
    background: #ffffff;
    color: var(--scemory-heading);
    font-size: 0.82rem;
    font-weight: 700;
    cursor: pointer;
    transition: all 180ms ease;
}

.weekly-leaderboard__footer button:not(:disabled):hover {
    border-color: var(--scemory-primary);
    color: var(--scemory-primary);
}

.weekly-leaderboard__footer button:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.weekly-leaderboard__link {
    display: inline-flex;
    min-height: 44px;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 11px 22px;
    border-radius: 999px;
    background: linear-gradient(135deg, var(--scemory-primary), #1677ff);
    color: #fff;
    font-size: 0.84rem;
    font-weight: 800;
    text-decoration: none;
    box-shadow: 0 9px 24px rgba(13, 77, 151, 0.2);
    transition: transform 180ms ease, box-shadow 180ms ease;
}

.weekly-leaderboard__link:hover {
    transform: translateY(-2px);
    color: #fff;
    box-shadow: 0 13px 30px rgba(13, 77, 151, 0.26);
}

.weekly-leaderboard__link-mobile {
    display: none;
}

.weekly-leaderboard__arrow {
    display: inline-block;
    transition: transform 180ms ease;
}

.leader-card--skeleton {
    pointer-events: none;
}

.skeleton {
    display: block;
    border-radius: 999px;
    background: linear-gradient(90deg, #e7eef5 25%, #f5f8fb 50%, #e7eef5 75%);
    background-size: 200% 100%;
    animation: leaderboard-shimmer 1.25s infinite linear;
}

.skeleton--rank { width: 32px; height: 18px; }
.skeleton--avatar { width: 44px; height: 44px; }
.skeleton--name { width: min(150px, 80%); height: 12px; }
.skeleton--badge { width: min(105px, 60%); height: 9px; }
.skeleton--points { width: 52px; height: 16px; }

@keyframes leaderboard-shimmer {
    to { background-position: -200% 0; }
}

/* RTL Support */
.weekly-leaderboard[dir="rtl"] .weekly-leaderboard__video,
:global([dir="rtl"]) .weekly-leaderboard__video {
    direction: ltr !important;
    transform: none !important;
}

.weekly-leaderboard[dir="rtl"] .weekly-leaderboard__period,
:global([dir="rtl"]) .weekly-leaderboard__period {
    align-items: flex-start;
    text-align: start;
}

.weekly-leaderboard[dir="rtl"] .weekly-leaderboard__arrow,
:global([dir="rtl"]) .weekly-leaderboard__arrow {
    transform: scaleX(-1);
}

/* Responsive: Tablet and Mobile */
@media (max-width: 960px) {
    .weekly-leaderboard {
        padding: clamp(36px, 5vw, 52px) 0;
    }

    .weekly-leaderboard__layout {
        grid-template-columns: minmax(0, 1fr);
        gap: 20px;
    }

    .weekly-leaderboard__media-card {
        min-height: unset;
        height: auto;
        aspect-ratio: 16 / 9;
        border-radius: 22px;
    }

    .weekly-leaderboard__panel {
        min-height: unset;
        border-radius: 22px;
        padding: 24px 18px;
    }
}

@media (max-width: 760px) {
    .weekly-leaderboard { padding: 36px 0; }
    .weekly-leaderboard__container { padding-inline: 16px; }
    .weekly-leaderboard__panel { padding: 20px 14px; border-radius: 20px; }

    .weekly-leaderboard__header {
        align-items: flex-start;
        flex-direction: column;
        gap: 16px;
        margin-block-end: 20px;
    }

    .weekly-leaderboard__period {
        width: 100%;
        align-items: center;
        flex-direction: row;
        justify-content: space-between;
    }

    .weekly-leaderboard__grid { grid-template-columns: minmax(0, 1fr); }
    .weekly-leaderboard:not(.weekly-leaderboard--full) .weekly-leaderboard__grid > .leader-card:nth-child(n + 6) { display: none; }
    .leader-card { gap: 10px; padding: 12px; }
    .leader-card__rank { width: 34px; }
    .leader-card__avatar, .skeleton--avatar { width: 42px; height: 42px; }
    .weekly-leaderboard__link-desktop { display: none; }
    .weekly-leaderboard__link-mobile { display: inline; }
}

@media (prefers-reduced-motion: reduce) {
    .leader-card,
    .weekly-leaderboard__link,
    .skeleton {
        transition: none;
        animation: none;
    }
}
</style>
