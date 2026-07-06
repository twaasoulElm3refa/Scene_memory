<template>
    <section class="rounded-2xl border border-white/70 bg-white/90 p-4 shadow-sm backdrop-blur">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div class="w-full lg:max-w-md">
                <!-- <label class="text-xs font-semibold uppercase tracking-wide text-gray-500">
                    Tags
                </label> -->

                <div ref="tagsDropdownRef" class="relative mt-2">
                    <div class="flex min-h-[46px] w-full items-center gap-2 rounded-xl border border-gray-200 bg-gray-50 px-3 py-2 text-sm transition focus-within:border-blue-500 focus-within:bg-white focus-within:ring-2 focus-within:ring-blue-500/20"
                        :class="{ 'cursor-not-allowed bg-gray-100': loading }" @click="focusTagInput">
                        <span class="shrink-0 rounded-full bg-blue-50 px-2 py-1 text-xs font-semibold text-blue-700">
                            #
                        </span>

                        <span v-if="loading" class="truncate text-sm text-gray-500">
                            Loading tags...
                        </span>

                        <input v-else ref="tagInputRef" v-model="tagSearch" type="text"
                            :placeholder="selectedTags.length ? 'Search more tags' : 'Search tags'"
                            class="min-w-0 flex-1 bg-transparent text-sm text-gray-700 outline-none placeholder:text-gray-400"
                            @focus="openTagDropdown" @input="openTagDropdown" @keydown.escape="closeTagDropdown" />

                        <button v-if="selectedTags.length" type="button"
                            class="shrink-0 rounded-full px-2 py-1 text-xs font-semibold text-gray-400 transition hover:bg-red-50 hover:text-red-500"
                            @click.stop="clearTags">
                            Clear
                        </button>
                    </div>

                    <div v-if="showTagDropdown && !loading"
                        class="absolute left-0 right-0 z-50 mt-2 max-h-64 overflow-y-auto rounded-xl border border-gray-200 bg-white py-2 shadow-xl">
                        <label v-for="tag in filteredTags" :key="tag.id"
                            class="flex cursor-pointer items-center gap-3 px-3 py-2 text-sm text-gray-700 hover:bg-blue-50">
                            <input type="checkbox"
                                class="h-4 w-4 shrink-0 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                :checked="isTagSelected(tag.id)" @change="toggleTag(tag.id)" />

                            <span class="truncate">
                                #{{ getTagName(tag) }}
                            </span>
                        </label>

                        <div v-if="filteredTags.length === 0" class="px-3 py-3 text-sm text-gray-400">
                            No tags found
                        </div>
                    </div>
                </div>
            </div>

            <div class="min-h-[46px] flex-1 mt-2">
                <div v-if="selectedTagObjects.length" class="flex flex-wrap gap-2">
                    <button v-for="tag in selectedTagObjects" :key="tag.id" type="button"
                        class="inline-flex items-center gap-2 rounded-full border border-blue-100 bg-blue-50 px-3 py-1.5 text-xs font-semibold text-blue-700 transition hover:border-blue-200 hover:bg-blue-100"
                        @click="removeTag(tag.id)">
                        <span>#{{ tag.name }}</span>
                        <span class="text-sm leading-none text-blue-400">x</span>
                    </button>
                </div>

                <div v-else class="flex min-h-[46px] items-center rounded-xl border border-dashed border-gray-200 px-3 text-sm text-gray-400">
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

const tagSearch = ref("");
const showTagDropdown = ref(false);
const tagInputRef = ref(null);
const tagsDropdownRef = ref(null);

const getTagName = (tag) => {
    return tag?.translation?.name || tag?.name || tag?.title || String(tag?.id || "");
};

const normalizedSelectedIds = computed(() => props.selectedTags.map((id) => String(id)));

const filteredTags = computed(() => {
    const search = tagSearch.value.trim().toLowerCase();

    if (!search) {
        return props.tags;
    }

    return props.tags.filter((tag) => getTagName(tag).toLowerCase().includes(search));
});

const selectedTagObjects = computed(() => {
    return props.selectedTags.map((tagId) => {
        const found = props.tags.find((tag) => String(tag.id) === String(tagId));

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
