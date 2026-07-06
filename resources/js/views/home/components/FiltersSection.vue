<template>
    <div class="bg-white border-b shadow-sm sticky top-0 z-10">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-8 gap-3 items-end">
                <div class="space-y-1">
                    <select :value="selectedCategory" @change="onCategoryChange"
                        class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg bg-gray-50 hover:bg-white focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition">
                        <option value="">{{ $t("filters.allCategories") }}</option>
                        <option v-for="cat in categories" :key="cat.id" :value="cat.id">
                            {{ cat.translation.name }}
                        </option>
                    </select>
                </div>

                <div v-if="subCategories.length > 0 || selectedCategory">
                    <select :value="selectedSubCategory" :disabled="loadingSubCategories"
                        @change="$emit('update:selectedSubCategory', $event.target.value)"
                        class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg bg-gray-50 hover:bg-white focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition">
                        <option value="">{{ $t("filters.allSubCategories") }}</option>
                        <option v-for="sub in subCategories" :key="sub.id" :value="sub.id">
                            {{ sub.translation.name }}
                        </option>
                    </select>
                </div>

                <div ref="tagsDropdownRef" class="relative">
                    <div class="flex min-h-[38px] w-full items-center gap-2 rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm transition focus-within:border-blue-500 focus-within:ring-2 focus-within:ring-blue-500/20"
                        :class="{ 'cursor-not-allowed bg-gray-100': loadingTags }" @click="focusTagInput">
                        <span v-if="loadingTags" class="truncate text-[11px] text-gray-500">
                            Loading tags...
                        </span>

                        <template v-else>
                            <span v-if="selectedTags.length && !tagSearch"
                                class="truncate rounded-full bg-blue-50 px-2 py-0.5 text-[10px] font-medium text-blue-700">
                                {{ selectedTags.length }} Tags...
                            </span>

                            <input ref="tagInputRef" :value="tagSearch" type="text"
                                :placeholder="selectedTags.length ? '' : 'All Tags'"
                                class="min-w-0 flex-1 bg-transparent text-[11px] text-gray-700 outline-none placeholder:text-gray-500"
                                @focus="openTagDropdown" @input="onTagSearchInput" />

                            <button v-if="selectedTags.length" type="button"
                                class="shrink-0 rounded-full px-1.5 text-[10px] font-normal text-gray-400 transition hover:bg-red-50 hover:text-red-500"
                                @click.stop="clearTags">
                                x
                            </button>
                        </template>
                    </div>

                    <div v-if="showTagDropdown && !loadingTags"
                        class="absolute z-50 mt-1 max-h-60 min-w-[150px] overflow-y-auto rounded-lg border border-gray-200 bg-white shadow-lg">
                        <button v-if="selectedTags.length" type="button"
                            class="w-full cursor-pointer whitespace-nowrap px-3 py-1 text-left text-[10px] font-normal leading-4 text-red-500 hover:bg-red-50"
                            @click="clearTags">
                            Clear selected tags
                        </button>

                        <label v-for="tag in filteredTags" :key="tag.id"
                            class="flex cursor-pointer items-center px-3 py-1.5 text-[11px] leading-4 text-gray-700 hover:bg-blue-50">
                            <input type="checkbox"
                                class="h-3 w-3 shrink-0 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                :checked="isTagSelected(tag.id)" @change="toggleTag(tag.id)" />

                            <span class="ml-3 truncate text-[11px] leading-4">
                                #{{ tag.name }}
                            </span>
                        </label>

                        <div v-if="filteredTags.length === 0" class="px-3 py-1.5 text-[11px] text-gray-400">
                            No tags found
                        </div>
                    </div>
                </div>

                <div class="relative">
                    <input :value="countrySearch" @focus="$emit('country-focus')"
                        @input="$emit('update:countrySearch', $event.target.value)" type="text"
                        :placeholder="$t('filters.country')"
                        class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg bg-gray-50 focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition" />

                    <div v-if="showDropdown && filteredCountries.length"
                        class="absolute z-50 w-full mt-1 bg-white border border-gray-200 rounded-lg shadow-lg max-h-60 overflow-y-auto">
                        <div v-for="country in filteredCountries" :key="country.id"
                            @click="$emit('select-country', country)"
                            class="px-3 py-2 text-sm cursor-pointer hover:bg-blue-50">
                            {{ country.translation.name }}
                        </div>
                    </div>
                </div>

                <div>
                    <select :value="selectedCity" :disabled="!selectedCountry"
                        @change="$emit('update:selectedCity', $event.target.value)"
                        class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg bg-gray-50 hover:bg-white focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition text-gray-900">
                        <option value="">{{ $t("filters.city") }}</option>
                        <option v-for="city in cities" :key="city.id" :value="city.id">
                            {{ city.translation.name }}
                        </option>
                    </select>
                </div>

                <div>
                    <input :value="fromDate" @input="$emit('update:fromDate', $event.target.value)" type="date"
                        class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg bg-gray-50 hover:bg-white focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition" />
                </div>

                <div>
                    <input :value="toDate" @input="$emit('update:toDate', $event.target.value)" type="date"
                        class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg bg-gray-50 hover:bg-white focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition" />
                </div>

                <div class="flex items-end">
                    <button @click="$emit('search')"
                        class="w-full sm:w-auto px-5 py-2 text-sm rounded-full bg-gradient-to-r from-blue-600 to-blue-700 text-white hover:from-blue-700 hover:to-blue-800 transition shadow hover:shadow-md active:scale-95">
                        {{ $t("common.search") }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, onMounted, onUnmounted, ref } from "vue";

const props = defineProps({
    categories: {
        type: Array,
        default: () => [],
    },
    selectedCategory: {
        type: [String, Number],
        default: "",
    },
    subCategories: {
        type: Array,
        default: () => [],
    },
    selectedSubCategory: {
        type: [String, Number],
        default: "",
    },
    loadingSubCategories: {
        type: Boolean,
        default: false,
    },
    tags: {
        type: Array,
        default: () => [],
    },
    selectedTags: {
        type: Array,
        default: () => [],
    },
    loadingTags: {
        type: Boolean,
        default: false,
    },
    countrySearch: {
        type: String,
        default: "",
    },
    showDropdown: {
        type: Boolean,
        default: false,
    },
    filteredCountries: {
        type: Array,
        default: () => [],
    },
    selectedCountry: {
        type: [String, Number],
        default: "",
    },
    cities: {
        type: Array,
        default: () => [],
    },
    selectedCity: {
        type: [String, Number],
        default: "",
    },
    fromDate: {
        type: String,
        default: "",
    },
    toDate: {
        type: String,
        default: "",
    },
});

const emit = defineEmits([
    "update:selectedCategory",
    "update:selectedSubCategory",
    "update:selectedTags",
    "update:countrySearch",
    "update:selectedCity",
    "update:fromDate",
    "update:toDate",
    "country-focus",
    "select-country",
    "category-changed",
    "search",
]);

const onCategoryChange = (event) => {
    emit("update:selectedCategory", event.target.value);
    emit("category-changed");
};

const tagSearch = ref("");
const showTagDropdown = ref(false);
const tagInputRef = ref(null);
const tagsDropdownRef = ref(null);

const filteredTags = computed(() => {
    const search = tagSearch.value.trim().toLowerCase();

    if (!search) {
        return props.tags;
    }

    return props.tags.filter((tag) =>
        String(tag.name || "")
            .toLowerCase()
            .includes(search)
    );
});

const openTagDropdown = () => {
    showTagDropdown.value = true;
};

const onTagSearchInput = (event) => {
    tagSearch.value = event.target.value;
    showTagDropdown.value = true;
};

const isTagSelected = (tagId) => {
    return props.selectedTags.map(String).includes(String(tagId));
};

const toggleTag = (tagId) => {
    const normalizedId = String(tagId);
    const exists = isTagSelected(tagId);
    const nextTags = exists
        ? props.selectedTags.filter((id) => String(id) !== normalizedId)
        : [...props.selectedTags, tagId];

    emit("update:selectedTags", nextTags);
};

const clearTags = () => {
    tagSearch.value = "";
    emit("update:selectedTags", []);
};

const focusTagInput = () => {
    if (props.loadingTags) return;

    tagInputRef.value?.focus();
    openTagDropdown();
};

const handleOutsideTagClick = (event) => {
    if (!tagsDropdownRef.value?.contains(event.target)) {
        showTagDropdown.value = false;
        tagSearch.value = "";
    }
};

onMounted(() => {
    document.addEventListener("mousedown", handleOutsideTagClick);
});

onUnmounted(() => {
    document.removeEventListener("mousedown", handleOutsideTagClick);
});
</script>
