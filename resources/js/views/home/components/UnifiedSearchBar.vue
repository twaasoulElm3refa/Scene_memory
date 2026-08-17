<template>
    <section ref="wrapperRef" class="scemory-search-bar unified-search-control">
        <div class="unified-search-main">
            <div
                class="unified-search-field"
                @click="focusInput"
            >
                <button
                    type="button"
                    class="unified-search-submit"
                    :aria-label="$t('common.search')"
                    @click.stop="emit('search')"
                >
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35m1.35-5.65a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                </button>

                <input
                    id="events-search-input"
                    ref="inputRef"
                    :value="modelValue"
                    type="text"
                    :placeholder="$t('common.placeholder')"
                    class="unified-search-input"
                    @input="handleInput"
                    @focus="handleFocus"
                    @keydown.enter.prevent="emit('search')"
                    @keydown.escape="closeDropdown"
                />

                <button
                    v-if="modelValue"
                    type="button"
                    class="unified-search-clear"
                    @click.stop="clearText"
                >
                    {{ $t('homeAudit.search.clearText') }}
                </button>

                <button
                    v-if="selectedTags.length"
                    type="button"
                    class="unified-search-clear unified-search-clear-tags"
                    @click.stop="clearTags"
                >
                    {{ $t('homeAudit.search.clearTags') }}
                </button>
            </div>

            <div
                v-if="showDropdown && hasSearch"
                class="unified-search-dropdown"
            >
                <div
                    v-if="loading || loadingSuggestions"
                    class="unified-search-message"
                >
                    {{ $t('homeAudit.tags.loading') }}
                </div>

                <template v-else>
                    <button
                        v-for="tag in visibleSuggestions"
                        :key="tag.id"
                        type="button"
                        class="tag-option-row"
                        :class="{ 'is-selected': isTagSelected(tag.id) }"
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
                        class="unified-search-message"
                    >
                        {{ $t('homeAudit.tags.none') }}
                    </div>
                </template>
            </div>
        </div>

        <div v-if="selectedTagObjects.length" class="selected-tags-tray">
            <div
                class="selected-tags-list"
            >
                <button
                    v-for="tag in selectedTagObjects"
                    :key="tag.id"
                    type="button"
                    class="selected-tag-chip"
                    @click="removeTag(tag.id)"
                >
                    <span>#{{ tag.name }}</span>
                    <span class="selected-tag-remove" aria-hidden="true">x</span>
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

defineExpose({
    focusInput,
});

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
    position: relative;
    z-index: 60;
    width: 100%;
    overflow: visible;
}

.unified-search-main {
    position: relative;
    z-index: 61;
    overflow: visible;
}

.unified-search-field {
    display: flex;
    width: 100%;
    min-height: 64px;
    align-items: center;
    gap: 10px;
    box-sizing: border-box;
    border: 1px solid rgba(13, 77, 151, 0.16);
    border-radius: 999px;
    background: #FFFFFF;
    padding: 7px 16px 7px 8px;
    box-shadow: 0 18px 42px rgba(13, 77, 151, 0.15);
    transition: border-color 180ms ease, box-shadow 180ms ease;
}

.unified-search-submit {
    display: inline-flex;
    width: 50px;
    height: 50px;
    flex: 0 0 50px;
    align-items: center;
    justify-content: center;
    border: 0;
    border-radius: 999px;
    background: var(--scemory-primary);
    color: #FFFFFF;
    box-shadow: 0 10px 24px rgba(13, 77, 151, 0.22);
    cursor: pointer;
    transition: background-color 180ms ease, transform 180ms ease, box-shadow 180ms ease;
}

.unified-search-submit:hover {
    transform: translateY(-1px);
    background: var(--scemory-blue);
    box-shadow: 0 12px 28px rgba(13, 77, 151, 0.28);
}

.unified-search-submit svg {
    width: 22px;
    height: 22px;
    color: #FFFFFF;
}

.unified-search-input {
    min-width: 0;
    flex: 1 1 auto;
    border: 0;
    outline: 0;
    background: transparent;
    color: var(--scemory-heading);
    font-size: 15px;
}

.unified-search-input::placeholder {
    color: #94A3B8;
}

.unified-search-clear {
    flex: 0 0 auto;
    border: 0;
    border-radius: 999px;
    background: transparent;
    padding: 7px 9px;
    color: #64748B;
    font-size: 12px;
    font-weight: 700;
    cursor: pointer;
    transition: background-color 180ms ease, color 180ms ease;
}

.unified-search-clear:hover {
    background: #EFF6FF;
    color: var(--scemory-primary);
}

.unified-search-clear-tags:hover {
    background: #FFF1F2;
    color: #E11D48;
}

.unified-search-field:focus-within {
    border-color: var(--scemory-blue);
    background: #FFFFFF;
    box-shadow: 0 0 0 4px rgba(22, 119, 255, 0.08);
}

.unified-search-dropdown {
    position: absolute;
    inset-inline: 0;
    top: calc(100% + 10px);
    z-index: 100;
    max-height: 288px;
    overflow-y: auto;
    border: 1px solid var(--scemory-border);
    border-radius: 18px;
    background: #FFFFFF;
    padding: 8px;
    box-shadow: 0 18px 45px rgba(13, 77, 151, 0.12);
}

.unified-search-message {
    padding: 12px;
    color: #94A3B8;
    font-size: 14px;
}

.tag-option-row {
    display: flex;
    width: 100%;
    align-items: center;
    gap: 10px;
    border: 0;
    border-radius: 10px;
    background: transparent;
    padding: 10px 12px;
    color: var(--scemory-text);
    font-size: 14px;
    text-align: start;
    cursor: pointer;
    transition: background-color 160ms ease, color 160ms ease;
}

.tag-option-row:hover,
.tag-option-row.is-selected {
    background: var(--scemory-hover);
    color: var(--scemory-primary);
}

.tag-option-checkbox {
    accent-color: var(--scemory-blue);
}

.selected-tags-tray {
    margin-top: 12px;
}

.selected-tags-list {
    display: flex;
    flex-wrap: wrap;
    justify-content: center;
    gap: 8px;
}

.selected-tag-chip {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    border: 1px solid rgba(22, 119, 255, 0.18);
    border-radius: 999px;
    background: #FFFFFF;
    padding: 7px 12px;
    color: var(--scemory-primary);
    font-size: 12px;
    font-weight: 700;
    box-shadow: 0 5px 14px rgba(13, 77, 151, 0.07);
    cursor: pointer;
}

.selected-tag-chip:hover {
    border-color: rgba(22, 119, 255, 0.34);
    background: var(--scemory-active);
}

.selected-tag-remove {
    color: var(--scemory-blue);
    font-size: 14px;
    line-height: 1;
}

@media (max-width: 640px) {
    .unified-search-control {
        width: 100%;
    }

    .unified-search-field {
        min-height: 56px;
        gap: 7px;
        padding: 6px 10px 6px 6px;
    }

    .unified-search-submit {
        width: 44px;
        height: 44px;
        flex-basis: 44px;
    }

    .unified-search-clear {
        max-width: 72px;
        padding-inline: 5px;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
}

.tag-option-row {
    flex-direction: row;
}

.tag-option-checkbox {
    display: inline-block;
    width: 16px;
    height: 16px;
    flex: 0 0 auto;
    margin: 0;
}

.tag-option-name {
    display: inline-block;
    flex: 1 1 auto;
    min-width: 0;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
</style>
