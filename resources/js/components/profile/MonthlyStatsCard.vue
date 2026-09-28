<template>
    <aside class="monthly-stats" :class="`monthly-stats--${variant}`">
        <header class="monthly-stats__header">
            <span>{{ t("timeline.filters.this_month") }}</span>
            <small>{{ stats.month || "—" }}</small>
        </header>

        <div class="monthly-stats__values">
            <div class="monthly-stats__value">
                <i class="bi bi-star-fill" aria-hidden="true"></i>
                <span>{{ t("profilePage.fields.points") }}</span>
                <strong>{{ formatNumber(stats.monthly_points) }}</strong>
            </div>
            <div class="monthly-stats__value">
                <i class="bi bi-trophy-fill" aria-hidden="true"></i>
                <span>{{ t("profilePage.fields.rank") }}</span>
                <strong>{{ rankLabel }}</strong>
            </div>
        </div>
    </aside>
</template>

<script setup>
import { computed } from "vue";
import { useI18n } from "vue-i18n";

const props = defineProps({
    stats: {
        type: Object,
        default: () => ({
            monthly_points: 0,
            monthly_rank: null,
            month: "",
        }),
    },
    variant: {
        type: String,
        default: "light",
        validator: (value) => ["light", "dark"].includes(value),
    },
});

const { locale, t } = useI18n();

const rankLabel = computed(() => (
    props.stats.monthly_rank == null ? "—" : `#${props.stats.monthly_rank}`
));

const formatNumber = (value) => new Intl.NumberFormat(locale.value).format(Number(value) || 0);
</script>

<style scoped>
.monthly-stats {
    width: min(100%, 420px);
    padding: 14px 16px;
    border: 1px solid #d9e8e3;
    border-radius: 16px;
    background: rgba(255, 255, 255, .78);
}

.monthly-stats__header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    margin-bottom: 10px;
}

.monthly-stats__header span {
    font-size: .78rem;
    font-weight: 800;
    letter-spacing: .06em;
    text-transform: uppercase;
}

.monthly-stats__header small {
    color: #64748b;
    font-size: .72rem;
}

.monthly-stats__values {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 10px;
}

.monthly-stats__value {
    display: grid;
    grid-template-columns: auto 1fr;
    align-items: center;
    gap: 2px 8px;
    min-width: 0;
}

.monthly-stats__value i {
    grid-row: 1 / span 2;
    color: #0f9f78;
    font-size: 1.05rem;
}

.monthly-stats__value span {
    color: #64748b;
    font-size: .72rem;
}

.monthly-stats__value strong {
    color: #172036;
    font-size: 1rem;
}

.monthly-stats--dark {
    border-color: rgba(255, 255, 255, .18);
    color: white;
    background: rgba(255, 255, 255, .09);
    backdrop-filter: blur(8px);
}

.monthly-stats--dark .monthly-stats__header small,
.monthly-stats--dark .monthly-stats__value span {
    color: #cbd8e5;
}

.monthly-stats--dark .monthly-stats__value strong {
    color: white;
}

.monthly-stats--dark .monthly-stats__value i {
    color: #9fd6df;
}

@media (max-width: 480px) {
    .monthly-stats__values {
        grid-template-columns: 1fr;
    }
}
</style>
