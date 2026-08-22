<template>
    <div class="scemory-filter-panel home-filter-panel">
        <div class="filter-panel-inner">
            <div class="filter-panel-heading">
                <span>{{ $t("filters.title") }}</span>
            </div>

            <div class="filter-controls-grid">
                <div class="filter-control">
                    <label>{{ $t("filters.category") }}</label>
                    <select :value="selectedCategory" @change="onCategoryChange"
                        class="filter-field">
                        <option value="">{{ $t("filters.allCategories") }}</option>
                        <option v-for="cat in categories" :key="cat.id" :value="cat.id">
                            {{ cat.translation.name }}
                        </option>
                    </select>
                </div>

                <div v-if="subCategories.length > 0 || selectedCategory" class="filter-control">
                    <label>{{ $t("filters.subCategory") }}</label>
                    <select :value="selectedSubCategory" :disabled="loadingSubCategories"
                        @change="$emit('update:selectedSubCategory', $event.target.value)"
                        class="filter-field">
                        <option value="">{{ $t("filters.allSubCategories") }}</option>
                        <option v-for="sub in subCategories" :key="sub.id" :value="sub.id">
                            {{ sub.translation.name }}
                        </option>
                    </select>
                </div>

                <div class="filter-control filter-country-control">
                    <label>{{ $t("filters.country") }}</label>
                    <input :value="countrySearch" @focus="$emit('country-focus')"
                        @input="$emit('update:countrySearch', $event.target.value)" type="text"
                        :placeholder="$t('filters.country')"
                        class="filter-field" />

                    <div v-if="showDropdown"
                        class="filter-country-options">
                        <button type="button" class="filter-country-option"
                            @click="$emit('select-country', null)">
                            {{ $t("discovery.filters.allCountries") }}
                        </button>
                        <button v-for="country in filteredCountries" :key="country.id" type="button"
                            @click="$emit('select-country', country)"
                            class="filter-country-option">
                            {{ country.translation.name }}
                        </button>
                    </div>
                </div>

                <div class="filter-control">
                    <label>{{ $t("filters.city") }}</label>
                    <select :value="selectedCity" :disabled="!selectedCountry"
                        @change="$emit('update:selectedCity', $event.target.value)"
                        class="filter-field">
                        <option value="">{{ $t("discovery.filters.allCities") }}</option>
                        <option v-for="city in cities" :key="city.id" :value="city.id">
                            {{ city.translation.name }}
                        </option>
                    </select>
                </div>

                <div class="filter-control">
                    <label>{{ $t("filters.from") }}</label>
                    <input :value="fromDate" @input="$emit('update:fromDate', $event.target.value)" type="date"
                        class="filter-field" />
                </div>

                <div class="filter-control">
                    <label>{{ $t("filters.to") }}</label>
                    <input :value="toDate" @input="$emit('update:toDate', $event.target.value)" type="date"
                        class="filter-field" />
                </div>

                <div class="filter-actions">
                    <button type="button" class="filter-search-button" @click="$emit('search')">
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
    border-radius: 22px;
    background: #FFFFFF;
    box-shadow: 0 14px 34px rgba(13, 77, 151, 0.08);
}

.filter-panel-inner {
    max-width: none;
    margin: 0;
    padding: 20px;
}

.filter-panel-heading {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 16px;
    color: var(--scemory-heading);
    font-size: 16px;
    font-weight: 900;
}

.filter-panel-heading::after {
    content: "";
    width: 34px;
    height: 3px;
    border-radius: 999px;
    background: linear-gradient(90deg, var(--scemory-primary), var(--scemory-light-blue));
}

.filter-controls-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 12px;
}

.filter-control {
    min-width: 0;
}

.filter-country-control {
    position: relative;
    z-index: 4;
}

.filter-control label {
    display: block;
    margin-bottom: 6px;
    color: var(--scemory-primary);
    font-size: 13px;
    font-weight: 800;
}

.filter-field {
    box-sizing: border-box;
    width: 100%;
    min-height: 46px;
    border: 1px solid var(--scemory-border);
    border-radius: 13px;
    background: #FFFFFF;
    padding: 9px 12px;
    color: var(--scemory-text);
    font-size: 13px;
    box-shadow: none;
    transition: border-color 180ms ease, background-color 180ms ease, box-shadow 180ms ease;
}

.filter-field:hover {
    border-color: rgba(22, 119, 255, 0.30);
    background: var(--scemory-control);
}

.filter-field:focus {
    border-color: var(--scemory-blue);
    box-shadow: 0 0 0 4px rgba(22, 119, 255, 0.08);
    background: #FFFFFF;
    outline: none;
}

.filter-field:disabled {
    cursor: not-allowed;
    background: #F4F7FA;
    color: #94A3B8;
}

.filter-country-options {
    position: absolute;
    inset-inline: 0;
    top: calc(100% + 6px);
    z-index: 20;
    display: flex;
    max-height: 240px;
    flex-direction: column;
    overflow-y: auto;
    border: 1px solid var(--scemory-border);
    border-radius: 14px;
    background: #FFFFFF;
    padding: 6px;
    box-shadow: 0 16px 38px rgba(13, 77, 151, 0.12);
}

.filter-country-option {
    width: 100%;
    border: 0;
    border-radius: 9px;
    background: transparent;
    padding: 9px 10px;
    color: var(--scemory-text);
    font-size: 13px;
    text-align: start;
    cursor: pointer;
}

.filter-country-option:hover {
    background: var(--scemory-hover);
    color: var(--scemory-primary);
}

.filter-actions {
    margin-top: 4px;
}

.filter-search-button {
    width: 100%;
    min-height: 46px;
    min-width: 0;
    border: 1px solid rgba(22, 119, 255, 0.20);
    border-radius: 14px;
    background: var(--scemory-primary);
    color: #FFFFFF;
    padding: 10px 16px;
    font-size: 14px;
    font-weight: 800;
    box-shadow: 0 8px 20px rgba(13, 77, 151, 0.16);
    cursor: pointer;
    transition: background-color 180ms ease, transform 180ms ease, box-shadow 180ms ease;
}

.filter-search-button:hover {
    transform: translateY(-1px);
    background: var(--scemory-blue);
    box-shadow: var(--scemory-shadow-hover);
}

@media (min-width: 992px) and (max-width: 1199px) {
    .filter-panel-inner {
        padding: 18px;
    }
}

@media (max-width: 991px) {
    .home-filter-panel {
        border-radius: 20px;
    }

    .filter-panel-inner {
        padding: 16px;
    }

    .filter-controls-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 640px) {
    .filter-controls-grid {
        grid-template-columns: 1fr;
        gap: 10px;
    }
}
</style>
