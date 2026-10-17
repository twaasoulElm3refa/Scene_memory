<template>
    <aside class="weekly-stats" :class="`weekly-stats--${variant}`">
        <header class="weekly-stats__header">
            <span>{{ t("weeklyCompetition.thisWeek") }}</span>
            <small>{{ stats.week_start || "—" }} – {{ stats.week_end || "—" }}</small>
        </header>

        <div class="weekly-stats__values">
            <div class="weekly-stats__value">
                <i class="bi bi-star-fill" aria-hidden="true"></i>
                <span>{{ t("weeklyCompetition.points") }}</span>
                <strong>{{ formatNumber(stats.weekly_points) }}</strong>
            </div>
            <div class="weekly-stats__value">
                <i class="bi bi-trophy-fill" aria-hidden="true"></i>
                <span>{{ t("weeklyCompetition.rank") }}</span>
                <strong>{{ rankLabel }}</strong>
            </div>
        </div>
    </aside>
</template>

<script setup>
import { computed, onMounted, onUnmounted, watch } from "vue";
import { useI18n } from "vue-i18n";

const props = defineProps({
    stats: {
        type: Object,
        default: () => ({
            weekly_points: 0,
            weekly_rank: null,
            week_start: "",
            week_end: "",
        }),
    },
    variant: {
        type: String,
        default: "light",
        validator: (value) => ["light", "dark"].includes(value),
    },
});

const { locale, t } = useI18n();
const emit = defineEmits(['refresh']);
let rolloverTimer;
const refreshExpired = () => {
    if (Date.parse(props.stats.ends_at) <= Date.now()) emit('refresh');
};
watch(() => props.stats.ends_at, (endsAt) => {
    clearTimeout(rolloverTimer);
    const delay = Date.parse(endsAt) - Date.now();
    if (Number.isFinite(delay)) rolloverTimer = setTimeout(() => emit('refresh'), Math.max(1000, delay + 100));
}, { immediate: true });
onMounted(() => window.addEventListener('focus', refreshExpired));
onUnmounted(() => {
    clearTimeout(rolloverTimer);
    window.removeEventListener('focus', refreshExpired);
});

const rankLabel = computed(() => (
    props.stats.weekly_rank == null ? "—" : `#${props.stats.weekly_rank}`
));

const formatNumber = (value) => new Intl.NumberFormat(locale.value).format(Number(value) || 0);
</script>

<style scoped>
.weekly-stats {
    width: min(100%, 420px);
    padding: 14px 16px;
    border: 1px solid #d9e8e3;
    border-radius: 16px;
    background: rgba(255, 255, 255, .78);
}

.weekly-stats__header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    margin-bottom: 10px;
}

.weekly-stats__header span {
    font-size: .78rem;
    font-weight: 800;
    letter-spacing: .06em;
    text-transform: uppercase;
}

.weekly-stats__header small {
    color: #64748b;
    font-size: .72rem;
}

.weekly-stats__values {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 10px;
}

.weekly-stats__value {
    display: grid;
    grid-template-columns: auto 1fr;
    align-items: center;
    gap: 2px 8px;
    min-width: 0;
}

.weekly-stats__value i {
    grid-row: 1 / span 2;
    color: #0f9f78;
    font-size: 1.05rem;
}

.weekly-stats__value span {
    color: #64748b;
    font-size: .72rem;
}

.weekly-stats__value strong {
    color: #172036;
    font-size: 1rem;
}

.weekly-stats--dark {
    border-color: rgba(255, 255, 255, .18);
    color: white;
    background: rgba(255, 255, 255, .09);
    backdrop-filter: blur(8px);
}

.weekly-stats--dark .weekly-stats__header small,
.weekly-stats--dark .weekly-stats__value span {
    color: #cbd8e5;
}

.weekly-stats--dark .weekly-stats__value strong {
    color: white;
}

.weekly-stats--dark .weekly-stats__value i {
    color: #9fd6df;
}

@media (max-width: 480px) {
    .weekly-stats__values {
        grid-template-columns: 1fr;
    }
}
</style>
