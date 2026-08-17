<script setup>
defineProps({
    currentPage: { type: Number, required: true },
    lastPage: { type: Number, required: true },
    pages: { type: Array, required: true },
});

defineEmits(["change"]);
</script>

<template>
    <nav v-if="lastPage > 1" class="events-pagination" :aria-label="$t('events.directory.pagination')">
        <button
            type="button"
            class="events-pagination__nav"
            :disabled="currentPage === 1"
            @click="$emit('change', currentPage - 1)"
        >
            <span class="events-pagination__previous-arrow" aria-hidden="true">←</span>
            <span class="events-pagination__label">{{ $t("events.directory.previous") }}</span>
        </button>

        <template v-for="(page, index) in pages" :key="`${page}-${index}`">
            <span v-if="page === '…'" class="events-pagination__ellipsis" aria-hidden="true">…</span>
            <button
                v-else
                type="button"
                class="events-pagination__page"
                :class="{ 'is-active': page === currentPage }"
                :aria-current="page === currentPage ? 'page' : undefined"
                @click="$emit('change', page)"
            >
                {{ page }}
            </button>
        </template>

        <button
            type="button"
            class="events-pagination__nav"
            :disabled="currentPage === lastPage"
            @click="$emit('change', currentPage + 1)"
        >
            <span class="events-pagination__label">{{ $t("events.directory.next") }}</span>
            <span class="events-pagination__next-arrow" aria-hidden="true">→</span>
        </button>
    </nav>
</template>

<style scoped>
.events-pagination {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: center;
    gap: 7px;
    margin-top: 34px;
}

.events-pagination button,
.events-pagination__ellipsis {
    display: inline-flex;
    min-width: 44px;
    height: 44px;
    align-items: center;
    justify-content: center;
    border: 1px solid #dce8f5;
    border-radius: 11px;
    background: #fff;
    color: #49627d;
    font: inherit;
    font-size: 0.84rem;
    font-weight: 700;
}

.events-pagination button {
    cursor: pointer;
    transition: border-color 160ms ease, background 160ms ease, color 160ms ease;
}

.events-pagination button:hover:not(:disabled) {
    border-color: #a9ccef;
    background: #eef6ff;
    color: #0d4d97;
}

.events-pagination button:focus-visible {
    outline: 3px solid rgba(22, 119, 255, 0.3);
    outline-offset: 2px;
}

.events-pagination button:disabled {
    opacity: 0.42;
    cursor: not-allowed;
}

.events-pagination__page.is-active {
    border-color: #1677ff;
    background: #1677ff;
    color: #fff;
    box-shadow: 0 7px 16px rgba(22, 119, 255, 0.2);
}

.events-pagination__nav {
    gap: 7px;
    padding: 0 14px;
}

.events-pagination__ellipsis {
    border-color: transparent;
    background: transparent;
}

:global([dir="rtl"]) .events-pagination__previous-arrow,
:global([dir="rtl"]) .events-pagination__next-arrow {
    transform: scaleX(-1);
}

@media (max-width: 575px) {
    .events-pagination {
        gap: 5px;
    }

    .events-pagination button,
    .events-pagination__ellipsis {
        min-width: 40px;
        height: 40px;
    }

    .events-pagination__nav {
        padding: 0 10px;
    }

    .events-pagination__label {
        position: absolute;
        width: 1px;
        height: 1px;
        overflow: hidden;
        clip: rect(0, 0, 0, 0);
        white-space: nowrap;
    }
}
</style>
