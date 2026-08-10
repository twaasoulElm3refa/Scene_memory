<template>
    <div class="scemory-filter-panel home-filter-panel sticky top-0 z-10">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-7 gap-3 items-end">
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
defineProps({
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
</script>

<style scoped>
.home-filter-panel {
    border: 1px solid var(--scemory-border-soft);
    border-radius: 24px;
    background: linear-gradient(145deg, var(--scemory-surface), var(--scemory-surface-soft));
    box-shadow: var(--scemory-shadow);
}

:global(.home-discovery-panel) .home-filter-panel {
    position: relative !important;
    top: auto !important;
    z-index: 1;
    margin: 0;
    border: 0;
    border-radius: 0;
    background: transparent;
    box-shadow: none;
}

:global(.home-discovery-panel) .home-filter-panel > div {
    max-width: none;
    padding: 0;
}

:global(.home-discovery-panel) .home-filter-panel .grid {
    grid-template-columns: repeat(auto-fit, minmax(145px, 1fr));
    gap: 12px;
    align-items: center;
}

.home-filter-panel select,
.home-filter-panel input {
    min-height: 46px;
    border-color: var(--scemory-border) !important;
    border-radius: 14px !important;
    background: var(--scemory-surface) !important;
    color: var(--scemory-text) !important;
    box-shadow: none !important;
    transition: var(--scemory-transition);
}

.home-filter-panel select:hover,
.home-filter-panel input:hover {
    border-color: rgba(22, 119, 255, 0.30) !important;
    background: var(--scemory-control) !important;
}

.home-filter-panel select:focus,
.home-filter-panel input:focus {
    border-color: var(--scemory-blue) !important;
    box-shadow: 0 0 0 4px rgba(22, 119, 255, 0.08) !important;
    background: #FFFFFF !important;
    outline: none;
}

.home-filter-panel [class*="absolute"][class*="bg-white"] {
    border-color: var(--scemory-border) !important;
    border-radius: 14px !important;
    background: var(--scemory-surface) !important;
    box-shadow: 0 16px 38px rgba(13, 77, 151, 0.12) !important;
    z-index: 100000 !important;
}

.home-filter-panel [class*="absolute"] [class*="hover\\:bg-blue-50"]:hover {
    background: var(--scemory-hover) !important;
    color: var(--scemory-primary) !important;
}

.home-filter-panel button {
    min-height: 46px;
    min-width: 130px;
    border: 1px solid rgba(22, 119, 255, 0.20);
    border-radius: 14px !important;
    background: linear-gradient(135deg, var(--scemory-primary), var(--scemory-blue)) !important;
    color: #FFFFFF;
    box-shadow: 0 8px 20px rgba(13, 77, 151, 0.16);
    transition: var(--scemory-transition);
}

.home-filter-panel button:hover {
    transform: translateY(-1px);
    background: linear-gradient(135deg, var(--scemory-blue), var(--scemory-light-blue)) !important;
    box-shadow: var(--scemory-shadow-hover);
}

@media (max-width: 640px) {
    .home-filter-panel {
        border-radius: 20px;
    }

    :global(.home-discovery-panel) .home-filter-panel {
        border-radius: 0;
    }

    :global(.home-discovery-panel) .home-filter-panel .grid {
        grid-template-columns: 1fr;
        gap: 10px;
    }

    :global(.home-discovery-panel) .home-filter-panel button {
        width: 100%;
    }
}

@media (min-width: 641px) and (max-width: 1199px) {
    :global(.home-discovery-panel) .home-filter-panel .grid {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }
}
</style>
