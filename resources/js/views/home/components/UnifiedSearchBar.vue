<template>
    <section
        ref="wrapperRef"
        class="scemory-search-bar unified-search-control relative z-[9999] overflow-visible rounded-2xl p-4 backdrop-blur"
    >
        <div class="relative z-[10000] overflow-visible">
            <div
                class="unified-search-field flex min-h-[50px] w-full items-center gap-2 rounded-xl border px-3 py-2 text-sm transition"
                @click="focusInput"
            >
                <span class="shrink-0 rounded-full bg-blue-50 px-2 py-1 text-xs font-semibold text-blue-700">
                    <span class="shrink-0 text-gray-400">
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            class="h-5 w-5"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                            stroke-width="2"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M21 21l-4.35-4.35m1.35-5.65a7 7 0 11-14 0 7 7 0 0114 0z"
                            />
                        </svg>
                    </span>
                </span>

                <input
                    ref="inputRef"
                    :value="modelValue"
                    type="text"
                    :placeholder="$t('common.placeholder')"
                    class="min-w-0 flex-1 bg-transparent text-sm text-gray-700 outline-none placeholder:text-gray-400"
                    @input="handleInput"
                    @focus="handleFocus"
                    @keydown.enter.prevent="emit('search')"
                    @keydown.escape="closeDropdown"
                />

                <button
                    v-if="modelValue"
                    type="button"
                    class="shrink-0 rounded-full px-2 py-1 text-xs font-semibold text-gray-400 transition hover:bg-gray-100 hover:text-gray-700"
                    @click.stop="clearText"
                >
                    {{ $t('homeAudit.search.clearText') }}
                </button>

                <button
                    v-if="selectedTags.length"
                    type="button"
                    class="shrink-0 rounded-full px-2 py-1 text-xs font-semibold text-gray-400 transition hover:bg-red-50 hover:text-red-500"
                    @click.stop="clearTags"
                >
                    {{ $t('homeAudit.search.clearTags') }}
                </button>
            </div>

            <div
                v-if="showDropdown && hasSearch"
                class="unified-search-dropdown absolute left-0 right-0 top-full z-[99999] mt-2 max-h-72 overflow-y-auto rounded-xl border py-2"
            >
                <div
                    v-if="loading || loadingSuggestions"
                    class="px-3 py-3 text-sm text-gray-400"
                >
                    {{ $t('homeAudit.tags.loading') }}
                </div>

                <template v-else>
                    <button
                        v-for="tag in visibleSuggestions"
                        :key="tag.id"
                        type="button"
                        class="tag-option-row w-full px-3 py-2 text-left text-sm text-gray-700 transition hover:bg-blue-50"
                        :class="{ 'bg-blue-50': isTagSelected(tag.id) }"
                        @click="selectTag(tag)"
                    >
                        <input
                            type="checkbox"
                            class="tag-option-checkbox"
                            :checked="isTagSelected(tag.id)"
                            readonly
                            tabindex="-1"
                            @click.prevent
                        />

                        <span class="tag-option-name">
                            {{ getTagName(tag) }}
                        </span>
                    </button>

                    <div
                        v-if="visibleSuggestions.length === 0"
                        class="px-3 py-3 text-sm text-gray-400"
                    >
                        {{ $t('homeAudit.tags.none') }}
                    </div>
                </template>
            </div>
        </div>

        <div class="mt-3 min-h-[36px]">
            <div
                v-if="selectedTagObjects.length"
                class="flex flex-wrap gap-2"
            >
                <button
                    v-for="tag in selectedTagObjects"
                    :key="tag.id"
                    type="button"
                    class="inline-flex items-center gap-2 rounded-full border border-blue-100 bg-blue-50 px-3 py-1.5 text-xs font-semibold text-blue-700 transition hover:border-blue-200 hover:bg-blue-100"
                    @click="removeTag(tag.id)"
                >
                    <span>#{{ tag.name }}</span>
                    <span class="text-sm leading-none text-blue-400">x</span>
                </button>
            </div>
        </div>
    </section>
</template>

<script setup>
import {
    computed,
    onMounted,
    onUnmounted,
    ref,
    watch,
} from "vue";

const props = defineProps({
    modelValue: {
        type: String,
        default: "",
    },

    tags: {
        type: Array,
        default: () => [],
    },

    selectedTags: {
        type: Array,
        default: () => [],
    },

    loading: {
        type: Boolean,
        default: false,
    },

    tagSuggestions: {
        type: Array,
        default: () => [],
    },

    loadingSuggestions: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits([
    "update:modelValue",
    "update:selected-tags",
    "search",
    "fetch-tag-suggestions",
]);

const showDropdown = ref(false);
const inputRef = ref(null);
const wrapperRef = ref(null);
const cachedTags = ref([]);

const getTagName = (tag) => {
    return (
        tag?.translation?.name ||
        tag?.name ||
        tag?.title ||
        String(tag?.id || "")
    );
};

const mergeCachedTags = (tags) => {
    const next = [...cachedTags.value];

    for (const tag of tags || []) {
        if (!tag?.id) {
            continue;
        }

        const existingIndex = next.findIndex(
            (item) => String(item.id) === String(tag.id)
        );

        if (existingIndex >= 0) {
            next[existingIndex] = tag;
        } else {
            next.push(tag);
        }
    }

    cachedTags.value = next;
};

watch(
    () => [props.tags, props.tagSuggestions],
    ([tags, suggestions]) => {
        mergeCachedTags([
            ...(tags || []),
            ...(suggestions || []),
        ]);
    },
    {
        immediate: true,
        deep: true,
    }
);

watch(
    () => props.modelValue,
    (value) => {
        const normalizedValue = String(value || "").trim();

        if (!normalizedValue) {
            closeDropdown();
        }
    }
);

const normalizedSelectedIds = computed(() => {
    return props.selectedTags.map((id) => String(id));
});

const hasSearch = computed(() => {
    return String(props.modelValue || "").trim().length > 0;
});

const suggestionSource = computed(() => {
    if (!hasSearch.value) {
        return [];
    }

    return props.tagSuggestions || [];
});

const visibleSuggestions = computed(() => {
    const seen = new Set();

    return suggestionSource.value
        .filter((tag) => {
            if (!tag?.id) {
                return false;
            }

            const tagId = String(tag.id);

            if (seen.has(tagId)) {
                return false;
            }

            seen.add(tagId);

            return true;
        })
        .slice(0, 10);
});

const selectedTagObjects = computed(() => {
    return props.selectedTags.map((tagId) => {
        const found = cachedTags.value.find(
            (tag) => String(tag.id) === String(tagId)
        );

        return {
            id: tagId,
            name: found
                ? getTagName(found)
                : String(tagId),
        };
    });
});

const isTagSelected = (tagId) => {
    return normalizedSelectedIds.value.includes(
        String(tagId)
    );
};

const openDropdown = () => {
    if (!hasSearch.value) {
        showDropdown.value = false;
        return;
    }

    showDropdown.value = true;
};

const closeDropdown = () => {
    showDropdown.value = false;
};

const focusInput = () => {
    inputRef.value?.focus();
};

const requestSuggestions = (value) => {
    const normalizedValue = String(value || "").trim();

    if (!normalizedValue) {
        return;
    }

    emit("fetch-tag-suggestions", normalizedValue);
};

const handleInput = (event) => {
    const value = event.target.value;
    const normalizedValue = String(value || "").trim();

    emit("update:modelValue", value);

    if (!normalizedValue) {
        closeDropdown();
        return;
    }

    requestSuggestions(normalizedValue);
    showDropdown.value = true;
};

const handleFocus = () => {
    if (!hasSearch.value) {
        closeDropdown();
        return;
    }

    requestSuggestions(props.modelValue);
    openDropdown();
};

const selectTag = (tag) => {
    if (!tag?.id) {
        return;
    }

    mergeCachedTags([tag]);

    if (!isTagSelected(tag.id)) {
        emit("update:selected-tags", [
            ...props.selectedTags,
            tag.id,
        ]);
    }

    closeDropdown();
};

const removeTag = (tagId) => {
    emit(
        "update:selected-tags",
        props.selectedTags.filter(
            (id) => String(id) !== String(tagId)
        )
    );
};

const clearText = () => {
    emit("update:modelValue", "");
    closeDropdown();
    inputRef.value?.focus();
};

const clearTags = () => {
    emit("update:selected-tags", []);
    inputRef.value?.focus();
};

const handleOutsideClick = (event) => {
    if (!wrapperRef.value?.contains(event.target)) {
        closeDropdown();
    }
};

onMounted(() => {
    document.addEventListener(
        "mousedown",
        handleOutsideClick
    );
});

onUnmounted(() => {
    document.removeEventListener(
        "mousedown",
        handleOutsideClick
    );
});
</script>

<style scoped>
.unified-search-control {
    border-color: var(--scemory-border);
    border-radius: 24px;
    background: linear-gradient(145deg, var(--scemory-surface), var(--scemory-surface-soft));
    box-shadow: 0 12px 32px rgba(13, 77, 151, 0.08);
}

:global(.home-discovery-panel) .unified-search-control {
    border: 0;
    border-radius: 0;
    background: transparent;
    box-shadow: none;
    padding: 0;
}

.unified-search-field {
    border-color: var(--scemory-border-soft);
    background: #FFFFFF;
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.7);
}

:global(.home-discovery-panel) .unified-search-field {
    min-height: 54px;
    border-color: var(--scemory-border-soft);
    border-radius: 16px;
    background: #FFFFFF;
}

.unified-search-field:focus-within {
    border-color: var(--scemory-blue);
    background: #FFFFFF;
    box-shadow: 0 0 0 4px rgba(22, 119, 255, 0.08);
}

.unified-search-control svg,
.unified-search-control .text-blue-400,
.unified-search-control .text-blue-700 {
    color: var(--scemory-primary) !important;
}

.unified-search-control input {
    color: var(--scemory-heading);
}

.unified-search-control input::placeholder {
    color: #94A3B8;
}

.unified-search-control button {
    transition: var(--scemory-transition);
}

.unified-search-control button:hover {
    transform: translateY(-1px);
}

.unified-search-control .border-blue-100,
.unified-search-control .bg-blue-50 {
    border-color: rgba(22, 119, 255, 0.24) !important;
    background: var(--scemory-active) !important;
    color: var(--scemory-primary) !important;
}

.unified-search-dropdown {
    border-color: var(--scemory-border);
    background: linear-gradient(145deg, var(--scemory-surface), var(--scemory-surface-soft));
    box-shadow: var(--scemory-shadow-strong);
}

:global(.home-discovery-panel) .unified-search-dropdown {
    z-index: 100000;
    box-shadow: 0 18px 45px rgba(13, 77, 151, 0.12);
}

.tag-option-row {
    display: flex;
    align-items: center;
    gap: 10px;
    color: var(--scemory-text) !important;
}

.tag-option-row:hover,
.tag-option-row.bg-blue-50 {
    background: var(--scemory-hover) !important;
    color: var(--scemory-primary) !important;
}

.tag-option-checkbox {
    accent-color: var(--scemory-blue);
}

@media (max-width: 640px) {
    .unified-search-control {
        border-radius: 20px;
        padding: 12px;
    }

    :global(.home-discovery-panel) .unified-search-control {
        padding: 0;
    }

    :global(.home-discovery-panel) .unified-search-field {
        min-height: 52px;
    }
}
</style>

<style scoped>
.tag-option-row {
    display: flex !important;
    flex-direction: row !important;
    align-items: center !important;
    gap: 10px !important;
}

.tag-option-checkbox {
    width: 16px !important;
    height: 16px !important;
    margin: 0 !important;
    flex: 0 0 auto !important;
    display: inline-block !important;
}

.tag-option-name {
    display: inline-block !important;
    flex: 1 1 auto !important;
    min-width: 0;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
</style>
