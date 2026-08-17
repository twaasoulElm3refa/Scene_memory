<script setup>
defineProps({
    comment: { type: Object, required: true },
    selectedReaction: { type: String, default: null },
    loading: { type: Boolean, default: false },
});

defineEmits(["select"]);

const reactions = [
    { type: "support", code: "YES", label: "commentsPage.reactions.support", tone: "support" },
    { type: "neutral", code: "MID", label: "commentsPage.reactions.neutral", tone: "neutral" },
    { type: "exhibitions", code: "NO", label: "commentsPage.reactions.oppose", tone: "oppose" },
];
</script>

<template>
    <div class="comment-reactions" role="group" :aria-busy="loading">
        <button
            v-for="reaction in reactions"
            :key="reaction.type"
            type="button"
            class="reaction-button"
            :class="[`reaction-button--${reaction.tone}`, { 'is-selected': selectedReaction === reaction.type }]"
            :disabled="loading"
            :aria-pressed="selectedReaction === reaction.type"
            @click="$emit('select', reaction.type)"
        >
            <span class="reaction-code">{{ reaction.code }}</span>
            <span>{{ $t(reaction.label) }}</span>
            <span class="reaction-count">{{ comment[`${reaction.type}_count`] ?? 0 }}</span>
        </button>
    </div>
</template>

<style scoped>
.comment-reactions {
    display: flex;
    flex-wrap: wrap;
    gap: 0.55rem;
    align-items: center;
}

.reaction-button {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.4rem;
    min-height: 34px;
    padding: 0.35rem 0.75rem;
    border: 1px solid #d9e4f0;
    border-radius: 999px;
    background: #fff;
    color: #334155;
    font-size: 0.75rem;
    font-weight: 600;
    transition: border-color 0.15s ease, background 0.15s ease, color 0.15s ease, transform 0.15s ease;
}

.reaction-button:not(:disabled):hover {
    transform: translateY(-1px);
}

.reaction-button--support:not(.is-selected):hover { border-color: #34d399; background: #ecfdf5; }
.reaction-button--neutral:not(.is-selected):hover { border-color: #fbbf24; background: #fffbeb; }
.reaction-button--oppose:not(.is-selected):hover { border-color: #fb7185; background: #fff1f2; }
.reaction-button--support.is-selected { border-color: #059669; background: #059669; color: #fff; }
.reaction-button--neutral.is-selected { border-color: #f59e0b; background: #f59e0b; color: #fff; }
.reaction-button--oppose.is-selected { border-color: #e11d48; background: #e11d48; color: #fff; }

.reaction-button:disabled {
    cursor: wait;
    opacity: 0.62;
}

.reaction-code {
    font-size: 0.65rem;
    font-weight: 800;
    letter-spacing: 0.03em;
}

.reaction-count {
    min-width: 1.25rem;
    padding: 0.05rem 0.35rem;
    border-radius: 999px;
    background: rgba(148, 163, 184, 0.15);
    text-align: center;
    font-size: 0.68rem;
}

.is-selected .reaction-count {
    background: rgba(255, 255, 255, 0.22);
}

@media (max-width: 420px) {
    .reaction-button {
        flex: 1 1 calc(50% - 0.55rem);
        padding-inline: 0.55rem;
    }
}
</style>
