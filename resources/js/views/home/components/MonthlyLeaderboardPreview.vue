<template>
    <section class="monthly-leaderboard" aria-labelledby="monthly-leaderboard-title">
        <div class="monthly-leaderboard__container">
            <div class="monthly-leaderboard__panel">
                <div class="monthly-leaderboard__glow" aria-hidden="true"></div>

                <header class="monthly-leaderboard__header">
                    <div>
                        <span class="monthly-leaderboard__eyebrow">
                            <span aria-hidden="true">🏆</span>
                            Monthly leaderboard
                        </span>
                        <h2 id="monthly-leaderboard-title">Monthly Memory Leaders</h2>
                        <p>Top contributors this month</p>
                    </div>

                    <div class="monthly-leaderboard__period" aria-live="polite">
                        <strong>{{ periodLabel }}</strong>
                        <span>{{ resetLabel }}</span>
                    </div>
                </header>

                <div v-if="loading" class="monthly-leaderboard__grid" aria-label="Loading monthly leaderboard">
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

                <div v-else-if="error" class="monthly-leaderboard__state" role="alert">
                    <span class="monthly-leaderboard__state-icon" aria-hidden="true">↻</span>
                    <strong>We couldn't load this month's leaders.</strong>
                    <p>Please try again in a moment.</p>
                    <button type="button" @click="loadLeaderboard">Try again</button>
                </div>

                <div v-else-if="users.length === 0" class="monthly-leaderboard__state">
                    <span class="monthly-leaderboard__state-icon" aria-hidden="true">✦</span>
                    <strong>This month's leaderboard is ready for its first memory.</strong>
                    <p>Contributors will appear here as soon as they earn points.</p>
                </div>

                <ol v-else class="monthly-leaderboard__grid">
                    <li
                        v-for="user in users"
                        :key="user.id"
                        class="leader-card"
                        :class="`leader-card--rank-${user.rank}`"
                    >
                        <span class="leader-card__rank" :aria-label="`Rank ${user.rank}`">
                            <span v-if="user.rank <= 3" aria-hidden="true">{{ medalFor(user.rank) }}</span>
                            <span>#{{ user.rank }}</span>
                        </span>

                        <img
                            v-if="user.avatar && !failedAvatars[user.id]"
                            class="leader-card__avatar"
                            :src="user.avatar"
                            :alt="`${user.name}'s avatar`"
                            loading="lazy"
                            @error="markAvatarFailed(user.id)"
                        />
                        <span v-else class="leader-card__avatar leader-card__avatar--fallback" aria-hidden="true">
                            {{ initialsFor(user.name) }}
                        </span>

                        <span class="leader-card__identity">
                            <strong>{{ user.name }}</strong>
                            <span class="leader-card__badge">{{ user.badge }}</span>
                        </span>

                        <span class="leader-card__points">
                            <strong>{{ formatPoints(user.points) }}</strong>
                            <small>pts</small>
                        </span>
                    </li>
                </ol>

                <footer class="monthly-leaderboard__footer">
                    <a href="/leaderboard" class="monthly-leaderboard__link">
                        <span class="monthly-leaderboard__link-desktop">View Full Leaderboard</span>
                        <span class="monthly-leaderboard__link-mobile">View All</span>
                        <span aria-hidden="true">→</span>
                    </a>
                </footer>
            </div>
        </div>
    </section>
</template>

<script setup>
import { computed, onMounted, ref } from "vue";
import { getMonthlyLeaderboardPreview } from "@/services/leaderboardService";

const users = ref([]);
const loading = ref(true);
const error = ref(false);
const month = ref("");
const year = ref(null);
const failedAvatars = ref({});

const periodLabel = computed(() => {
    if (month.value && year.value) {
        return `${month.value} ${year.value}`;
    }

    return new Intl.DateTimeFormat(undefined, {
        month: "long",
        year: "numeric",
    }).format(new Date());
});

const resetLabel = computed(() => {
    const now = new Date();
    const nextMonth = new Date(now.getFullYear(), now.getMonth() + 1, 1);
    const days = Math.max(1, Math.ceil((nextMonth.getTime() - now.getTime()) / 86400000));

    return `Resets in ${days} ${days === 1 ? "day" : "days"}`;
});

async function loadLeaderboard() {
    loading.value = true;
    error.value = false;

    try {
        const response = await getMonthlyLeaderboardPreview();
        const payload = response.data || {};

        users.value = Array.isArray(payload.users) ? payload.users.slice(0, 10) : [];
        month.value = payload.month || "";
        year.value = Number(payload.year) || null;
    } catch {
        error.value = true;
        users.value = [];
    } finally {
        loading.value = false;
    }
}

function formatPoints(points) {
    return new Intl.NumberFormat().format(Number(points) || 0);
}

function initialsFor(name) {
    return String(name || "?")
        .trim()
        .split(/\s+/)
        .slice(0, 2)
        .map((part) => part.charAt(0).toUpperCase())
        .join("") || "?";
}

function medalFor(rank) {
    return { 1: "🥇", 2: "🥈", 3: "🥉" }[rank] || "";
}

function markAvatarFailed(userId) {
    failedAvatars.value = { ...failedAvatars.value, [userId]: true };
}

onMounted(loadLeaderboard);
</script>

<style scoped>
.monthly-leaderboard {
    position: relative;
    overflow: hidden;
    padding: 48px 0;
    background: linear-gradient(180deg, var(--scemory-surface) 0%, var(--scemory-surface-soft) 100%);
}

.monthly-leaderboard__container {
    box-sizing: border-box;
    width: min(100%, 1320px);
    margin-inline: auto;
    padding-inline: 32px;
}

.monthly-leaderboard__panel {
    position: relative;
    isolation: isolate;
    overflow: hidden;
    padding: clamp(24px, 4vw, 44px);
    border: 1px solid var(--scemory-border);
    border-radius: 28px;
    background:
        radial-gradient(circle at 92% 5%, rgba(56, 174, 234, 0.16), transparent 24rem),
        linear-gradient(145deg, #fff 0%, #f8fbff 55%, #eef7ff 100%);
    box-shadow: var(--scemory-shadow);
}

.monthly-leaderboard__glow {
    position: absolute;
    z-index: -1;
    inset-block-end: -140px;
    inset-inline-start: -90px;
    width: 320px;
    height: 320px;
    border-radius: 50%;
    background: rgba(22, 119, 255, 0.07);
}

.monthly-leaderboard__header {
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    gap: 24px;
    margin-block-end: 28px;
}

.monthly-leaderboard__eyebrow {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 9px 13px;
    border: 1px solid rgba(22, 119, 255, 0.16);
    border-radius: 999px;
    background: rgba(221, 236, 249, 0.72);
    color: var(--scemory-primary);
    font-size: 0.72rem;
    font-weight: 800;
    letter-spacing: 0.08em;
    text-transform: uppercase;
}

.monthly-leaderboard__header h2 {
    margin: 14px 0 0;
    color: var(--scemory-heading);
    font-size: clamp(1.75rem, 3.2vw, 2.75rem);
    font-weight: 850;
    letter-spacing: -0.035em;
    line-height: 1.08;
}

.monthly-leaderboard__header p {
    margin: 8px 0 0;
    color: var(--scemory-muted);
}

.monthly-leaderboard__period {
    display: flex;
    flex: 0 0 auto;
    flex-direction: column;
    align-items: flex-end;
    gap: 4px;
    padding: 12px 16px;
    border: 1px solid rgba(22, 119, 255, 0.12);
    border-radius: 16px;
    background: rgba(255, 255, 255, 0.78);
    box-shadow: 0 8px 24px rgba(17, 75, 125, 0.08);
}

.monthly-leaderboard__period strong {
    color: var(--scemory-heading);
    font-size: 0.95rem;
}

.monthly-leaderboard__period span {
    color: var(--scemory-muted);
    font-size: 0.78rem;
}

.monthly-leaderboard__grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 12px;
    margin: 0;
    padding: 0;
    list-style: none;
}

.leader-card {
    display: grid;
    grid-template-columns: auto auto minmax(0, 1fr) auto;
    align-items: center;
    gap: 13px;
    min-width: 0;
    padding: 14px 16px;
    border: 1px solid rgba(13, 77, 151, 0.1);
    border-radius: 18px;
    background: rgba(255, 255, 255, 0.9);
    box-shadow: 0 7px 20px rgba(17, 75, 125, 0.06);
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
    width: 42px;
    flex-direction: column;
    align-items: center;
    color: var(--scemory-heading);
    font-size: 0.78rem;
    font-weight: 850;
    line-height: 1.15;
}

.leader-card__rank > span:first-child:not(:last-child) {
    font-size: 1rem;
}

.leader-card__avatar {
    display: grid;
    width: 46px;
    height: 46px;
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
    gap: 3px;
}

.leader-card__identity strong,
.leader-card__badge {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.leader-card__identity strong {
    color: var(--scemory-heading);
    font-size: 0.93rem;
    font-weight: 800;
}

.leader-card__badge {
    color: var(--scemory-muted);
    font-size: 0.73rem;
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
    font-size: 1rem;
    font-weight: 900;
}

.leader-card__points small {
    color: var(--scemory-muted);
    font-size: 0.7rem;
    font-weight: 700;
}

.monthly-leaderboard__state {
    display: flex;
    min-height: 210px;
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

.monthly-leaderboard__state-icon {
    margin-block-end: 10px;
    color: var(--scemory-light-blue);
    font-size: 1.8rem;
}

.monthly-leaderboard__state strong {
    color: var(--scemory-heading);
}

.monthly-leaderboard__state p {
    margin: 6px 0 0;
    font-size: 0.88rem;
}

.monthly-leaderboard__state button {
    margin-block-start: 14px;
    padding: 9px 16px;
    border: 0;
    border-radius: 999px;
    background: var(--scemory-primary);
    color: #fff;
    font-size: 0.82rem;
    font-weight: 800;
}

.monthly-leaderboard__footer {
    display: flex;
    justify-content: center;
    margin-block-start: 26px;
}

.monthly-leaderboard__link {
    display: inline-flex;
    min-height: 44px;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 11px 20px;
    border-radius: 999px;
    background: linear-gradient(135deg, var(--scemory-primary), #1677ff);
    color: #fff;
    font-size: 0.84rem;
    font-weight: 800;
    text-decoration: none;
    box-shadow: 0 9px 24px rgba(13, 77, 151, 0.2);
    transition: transform 180ms ease, box-shadow 180ms ease;
}

.monthly-leaderboard__link:hover {
    transform: translateY(-2px);
    color: #fff;
    box-shadow: 0 13px 30px rgba(13, 77, 151, 0.26);
}

.monthly-leaderboard__link-mobile {
    display: none;
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
.skeleton--avatar { width: 46px; height: 46px; }
.skeleton--name { width: min(150px, 80%); height: 12px; }
.skeleton--badge { width: min(105px, 60%); height: 9px; }
.skeleton--points { width: 52px; height: 16px; }

@keyframes leaderboard-shimmer {
    to { background-position: -200% 0; }
}

@media (max-width: 760px) {
    .monthly-leaderboard { padding: 36px 0; }
    .monthly-leaderboard__container { padding-inline: 18px; }
    .monthly-leaderboard__panel { padding: 22px 16px; border-radius: 22px; }

    .monthly-leaderboard__header {
        align-items: flex-start;
        flex-direction: column;
        gap: 16px;
        margin-block-end: 22px;
    }

    .monthly-leaderboard__period {
        width: 100%;
        align-items: center;
        flex-direction: row;
        justify-content: space-between;
    }

    .monthly-leaderboard__grid { grid-template-columns: minmax(0, 1fr); }
    .monthly-leaderboard__grid > .leader-card:nth-child(n + 6) { display: none; }
    .leader-card { gap: 10px; padding: 12px; }
    .leader-card__rank { width: 34px; }
    .leader-card__avatar, .skeleton--avatar { width: 42px; height: 42px; }
    .monthly-leaderboard__link-desktop { display: none; }
    .monthly-leaderboard__link-mobile { display: inline; }
}

@media (prefers-reduced-motion: reduce) {
    .leader-card,
    .monthly-leaderboard__link,
    .skeleton {
        transition: none;
        animation: none;
    }
}
</style>
