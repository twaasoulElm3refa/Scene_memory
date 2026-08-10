<template>
    <section
        class="scemory-search-bar scemory-tags-search-bar relative z-[9999] overflow-visible rounded-2xl border p-4 backdrop-blur"
    >
        <div class="flex flex-col gap-3">
            <div class="w-full">
                <div ref="tagsDropdownRef" class="relative mt-2 z-[10000]">
                    <div
                        class="flex min-h-[46px] w-full items-center gap-2 rounded-xl border border-gray-200 bg-gray-50 px-3 py-2 text-sm transition focus-within:border-blue-500 focus-within:bg-white focus-within:ring-2 focus-within:ring-blue-500/20"
                        :class="{ 'cursor-not-allowed bg-gray-100': loading }"
                        @click="focusTagInput"
                    >
                        <span class="shrink-0 rounded-full bg-blue-50 px-2 py-1 text-xs font-semibold text-blue-700">
                            <!-- # -->
                        </span>

                        <span v-if="loading" class="truncate text-sm text-gray-500">
                            Loading tags...
                        </span>

                        <input
                            v-else
                            ref="tagInputRef"
                            v-model="tagSearch"
                            type="text"
                            :placeholder="selectedTags.length ? 'Search more tags' : 'Search tags'"
                            class="min-w-0 flex-1 bg-transparent text-sm text-gray-700 outline-none placeholder:text-gray-400"
                            @focus="openTagDropdown"
                            @input="openTagDropdown"
                            @keydown.escape="closeTagDropdown"
                        />

                        <button
                            v-if="selectedTags.length"
                            type="button"
                            class="shrink-0 rounded-full px-2 py-1 text-xs font-semibold text-gray-400 transition hover:bg-red-50 hover:text-red-500"
                            @click.stop="clearTags"
                        >
                            Clear
                        </button>
                    </div>

                    <div
                        v-if="showTagDropdown && !loading"
                        class="absolute left-0 right-0 top-full z-[99999] mt-2 max-h-72 overflow-y-auto rounded-xl border border-gray-200 bg-white py-2 shadow-2xl"
                    >
                        <div
                            v-if="!hasSearch && tags.length > visibleTagsLimit"
                            class="border-b border-gray-100 px-3 pb-2 text-xs text-gray-400"
                        >
                            Showing first {{ visibleTagsLimit }} tags. Search to find more.
                        </div>

                        <div
                            v-for="tag in filteredTags"
                            :key="tag.id"
                            class="tag-option-row cursor-pointer px-3 py-2 text-sm text-gray-700 transition hover:bg-blue-50"
                            :class="{ 'bg-blue-50': isTagSelected(tag.id) }"
                            @click="toggleTag(tag.id)"
                        >
                            <input
                                type="checkbox"
                                class="tag-option-checkbox"
                                :checked="isTagSelected(tag.id)"
                                @click.stop
                                @change="toggleTag(tag.id)"
                            />

                            <span class="tag-option-name">
                                {{ getTagName(tag) }}
                            </span>
                        </div>

                        <div
                            v-if="filteredTags.length === 0"
                            class="px-3 py-3 text-sm text-gray-400"
                        >
                            No tags found
                        </div>
                    </div>
                </div>
            </div>

            <div class="min-h-[46px] w-full">
                <div v-if="selectedTagObjects.length" class="flex flex-wrap gap-2">
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

                <div
                    v-else
                    class="flex min-h-[46px] items-center rounded-xl border border-dashed border-gray-200 px-3 text-sm text-gray-400"
                >
                    Select one or more tags to filter map events.
                </div>
            </div>
        </div>
    </section>
</template>

<script setup>
import { computed, onMounted, onUnmounted, ref } from "vue";

const props = defineProps({
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
});

const emit = defineEmits(["update:selected-tags"]);

const visibleTagsLimit = 5;

const tagSearch = ref("");
const showTagDropdown = ref(false);
const tagInputRef = ref(null);
const tagsDropdownRef = ref(null);

const tags = computed(() => props.tags || []);

const getTagName = (tag) => {
    return tag?.translation?.name || tag?.name || tag?.title || String(tag?.id || "");
};

const normalizedSelectedIds = computed(() => {
    return props.selectedTags.map((id) => String(id));
});

const hasSearch = computed(() => {
    return tagSearch.value.trim().length > 0;
});

const filteredTags = computed(() => {
    const search = tagSearch.value.trim().toLowerCase();

    if (!search) {
        return tags.value.slice(0, visibleTagsLimit);
    }

    return tags.value.filter((tag) =>
        getTagName(tag).toLowerCase().includes(search)
    );
});

const selectedTagObjects = computed(() => {
    return props.selectedTags.map((tagId) => {
        const found = tags.value.find((tag) => String(tag.id) === String(tagId));

        return {
            id: tagId,
            name: found ? getTagName(found) : String(tagId),
        };
    });
});

const openTagDropdown = () => {
    showTagDropdown.value = true;
};

const closeTagDropdown = () => {
    showTagDropdown.value = false;
    tagSearch.value = "";
};

const isTagSelected = (tagId) => {
    return normalizedSelectedIds.value.includes(String(tagId));
};

const toggleTag = (tagId) => {
    const normalizedId = String(tagId);

    const nextTags = isTagSelected(tagId)
        ? props.selectedTags.filter((id) => String(id) !== normalizedId)
        : [...props.selectedTags, tagId];

    emit("update:selected-tags", nextTags);
};

const removeTag = (tagId) => {
    emit(
        "update:selected-tags",
        props.selectedTags.filter((id) => String(id) !== String(tagId))
    );
};

const clearTags = () => {
    tagSearch.value = "";
    emit("update:selected-tags", []);
};

const focusTagInput = () => {
    if (props.loading) return;

    tagInputRef.value?.focus();
    openTagDropdown();
};

const handleOutsideTagClick = (event) => {
    if (!tagsDropdownRef.value?.contains(event.target)) {
        closeTagDropdown();
    }
};

onMounted(() => {
    document.addEventListener("mousedown", handleOutsideTagClick);
});

onUnmounted(() => {
    document.removeEventListener("mousedown", handleOutsideTagClick);
});
</script>

<style scoped>
.scemory-tags-search-bar {
    border-color: var(--scemory-border-soft);
    background: linear-gradient(145deg, rgba(247, 250, 253, 0.98), rgba(237, 244, 250, 0.94));
    box-shadow: var(--scemory-shadow);
}

.scemory-tags-search-bar .rounded-xl {
    border-color: var(--scemory-border);
    background: var(--scemory-control);
}

.scemory-tags-search-bar input {
    color: var(--scemory-heading);
}

.scemory-tags-search-bar input::placeholder {
    color: #94A3B8;
}

.scemory-tags-search-bar .tag-option-row {
    display: flex;
    align-items: center;
    gap: 10px;
    color: var(--scemory-text);
}

.scemory-tags-search-bar .tag-option-row:hover {
    background: var(--scemory-hover);
}

.scemory-tags-search-bar .tag-option-checkbox {
    accent-color: var(--scemory-blue);
}
</style>

<style scoped>
.tag-option-row {
    display: flex !important;
    flex-direction: row !important;
    align-items: center !important;
    gap: 10px !important;
    width: 100%;
    min-height: 38px;
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
