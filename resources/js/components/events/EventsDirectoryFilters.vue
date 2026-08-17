<script setup>
defineProps({
    mode: { type: String, required: true },
    filters: { type: Object, required: true },
    countries: { type: Array, default: () => [] },
    cities: { type: Array, default: () => [] },
    categories: { type: Array, default: () => [] },
    subCategories: { type: Array, default: () => [] },
    createRoute: { type: [String, Object], required: true },
});

const emit = defineEmits(["update", "clear", "close"]);

function updateFilter(key, value) {
    emit("update", { key, value });
}

function optionName(option) {
    return option?.translation?.name || option?.name || "—";
}
</script>

<template>
    <div class="events-filters">
        <div class="events-filters__heading">
            <div>
                <span class="events-filters__eyebrow">{{ $t("events.directory.refine") }}</span>
                <h2>{{ $t("events.directory.filters") }}</h2>
            </div>
            <button
                type="button"
                class="events-filters__close"
                :aria-label="$t('events.directory.closeFilters')"
                @click="$emit('close')"
            >
                <span aria-hidden="true">×</span>
            </button>
        </div>

        <div class="events-filters__fields">
            <div class="events-filter-field">
                <label for="events-directory-search">{{ $t("events.directory.searchLabel") }}</label>
                <div class="events-filter-field__search">
                    <svg viewBox="0 0 24 24" aria-hidden="true">
                        <path d="m21 20-4.35-4.35a8 8 0 1 0-1.41 1.41L19.59 21 21 20ZM5 11a6 6 0 1 1 12 0 6 6 0 0 1-12 0Z" />
                    </svg>
                    <input
                        id="events-directory-search"
                        type="search"
                        :value="filters.search"
                        :placeholder="$t('events.directory.searchPlaceholder')"
                        @input="updateFilter('search', $event.target.value)"
                    />
                </div>
            </div>

            <fieldset v-if="mode === 'normal'" class="events-filter-field events-filter-field--type">
                <legend>{{ $t("events.directory.eventType") }}</legend>
                <label v-for="type in ['all', 'real', 'general']" :key="type" class="events-type-option">
                    <input
                        type="radio"
                        name="event-directory-type"
                        :value="type"
                        :checked="filters.eventType === type"
                        @change="updateFilter('eventType', type)"
                    />
                    <span>{{ $t(`events.directory.types.${type}`) }}</span>
                </label>
            </fieldset>

            <div class="events-filter-field">
                <label for="events-directory-country">{{ $t("events.directory.country") }}</label>
                <select
                    id="events-directory-country"
                    :value="filters.countryId"
                    @change="updateFilter('countryId', $event.target.value)"
                >
                    <option value="">{{ $t("events.directory.allCountries") }}</option>
                    <option v-for="country in countries" :key="country.id" :value="country.id">
                        {{ optionName(country) }}
                    </option>
                </select>
            </div>

            <div class="events-filter-field">
                <label for="events-directory-category">{{ $t("events.directory.category") }}</label>
                <select
                    id="events-directory-category"
                    :value="filters.categoryId"
                    @change="updateFilter('categoryId', $event.target.value)"
                >
                    <option value="">{{ $t("events.directory.allCategories") }}</option>
                    <option v-for="category in categories" :key="category.id" :value="category.id">
                        {{ optionName(category) }}
                    </option>
                </select>
            </div>

            <div class="events-filter-field">
                <label for="events-directory-city">{{ $t("events.directory.city") }}</label>
                <select
                    id="events-directory-city"
                    :value="filters.cityId"
                    :disabled="!filters.countryId"
                    @change="updateFilter('cityId', $event.target.value)"
                >
                    <option value="">{{ $t("events.directory.allCities") }}</option>
                    <option v-for="city in cities" :key="city.id" :value="city.id">
                        {{ optionName(city) }}
                    </option>
                </select>
            </div>

            <div class="events-filter-field">
                <label for="events-directory-subcategory">{{ $t("events.directory.subcategory") }}</label>
                <select
                    id="events-directory-subcategory"
                    :value="filters.subCategoryId"
                    :disabled="!filters.categoryId"
                    @change="updateFilter('subCategoryId', $event.target.value)"
                >
                    <option value="">{{ $t("events.directory.allSubcategories") }}</option>
                    <option v-for="subcategory in subCategories" :key="subcategory.id" :value="subcategory.id">
                        {{ optionName(subcategory) }}
                    </option>
                </select>
            </div>

            <div class="events-filter-field events-filter-field--dates">
                <div>
                    <label for="events-directory-from">{{ $t("events.directory.fromDate") }}</label>
                    <input
                        id="events-directory-from"
                        type="date"
                        :value="filters.fromDate"
                        :max="filters.toDate || undefined"
                        @change="updateFilter('fromDate', $event.target.value)"
                    />
                </div>

                <div>
                    <label for="events-directory-to">{{ $t("events.directory.toDate") }}</label>
                    <input
                        id="events-directory-to"
                        type="date"
                        :value="filters.toDate"
                        :min="filters.fromDate || undefined"
                        @change="updateFilter('toDate', $event.target.value)"
                    />
                </div>
            </div>

            <div class="events-filter-field">
                <label for="events-directory-sort">{{ $t("events.sort_by") }}</label>
                <select
                    id="events-directory-sort"
                    :value="filters.sort"
                    @change="updateFilter('sort', $event.target.value)"
                >
                    <option value="newest">{{ $t("events.sort.newest") }}</option>
                    <option value="oldest">{{ $t("events.sort.oldest") }}</option>
                    <option value="title">{{ $t("events.sort.title") }}</option>
                </select>
            </div>
        </div>

        <button type="button" class="events-filters__clear" @click="$emit('clear')">
            {{ $t("events.directory.clearFilters") }}
        </button>

        <RouterLink :to="createRoute" class="events-filters__create" @click="$emit('close')">
            <span aria-hidden="true">+</span>
            {{ $t(mode === "historical" ? "events.directory.createHistorical" : "events.directory.createEvent") }}
        </RouterLink>
    </div>
</template>

<style scoped>
.events-filters {
    border: 1px solid #dce8f5;
    border-radius: 20px;
    padding: 22px;
    background: #fff;
    box-shadow: 0 10px 30px rgba(13, 77, 151, 0.07);
}

.events-filters__heading {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 22px;
}

.events-filters__eyebrow {
    display: block;
    margin-bottom: 3px;
    color: #1677ff;
    font-size: 0.7rem;
    font-weight: 800;
    letter-spacing: 0.12em;
    text-transform: uppercase;
}

.events-filters h2 {
    margin: 0;
    color: #06142a;
    font-size: 1.15rem;
    font-weight: 780;
}

.events-filters__close {
    display: none;
    width: 36px;
    height: 36px;
    border: 1px solid #dce8f5;
    border-radius: 10px;
    background: #f8fafc;
    color: #49627d;
    font-size: 1.4rem;
    line-height: 1;
}

.events-filters__fields {
    display: grid;
    gap: 18px;
}

.events-filter-field {
    min-width: 0;
}

.events-filter-field label,
.events-filter-field legend {
    display: block;
    margin-bottom: 7px;
    color: #334155;
    font-size: 0.8rem;
    font-weight: 700;
}

.events-filter-field input[type="search"],
.events-filter-field input[type="date"],
.events-filter-field select {
    width: 100%;
    height: 44px;
    border: 1px solid #cfddeb;
    border-radius: 11px;
    padding: 0 12px;
    background: #fff;
    color: #06142a;
    font: inherit;
    font-size: 0.86rem;
    outline: none;
    transition: border-color 160ms ease, box-shadow 160ms ease;
}

.events-filter-field select {
    cursor: pointer;
}

.events-filter-field select:disabled {
    background: #f4f7fa;
    color: #94a3b8;
    cursor: not-allowed;
}

.events-filter-field input:focus,
.events-filter-field select:focus {
    border-color: #1677ff;
    box-shadow: 0 0 0 3px rgba(22, 119, 255, 0.13);
}

.events-filter-field__search {
    position: relative;
}

.events-filter-field__search svg {
    position: absolute;
    top: 50%;
    inset-inline-start: 13px;
    width: 17px;
    height: 17px;
    transform: translateY(-50%);
    fill: #8293a7;
    pointer-events: none;
}

.events-filter-field__search input {
    padding-inline-start: 39px;
}

.events-filter-field--type {
    margin: 0;
    border: 0;
    padding: 0;
}

.events-filter-field--dates {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 7px 10px;
}

.events-filter-field--dates input {
    min-width: 0;
}

.events-type-option {
    display: flex !important;
    align-items: center;
    gap: 9px;
    margin: 0 !important;
    border-radius: 9px;
    padding: 8px 9px;
    cursor: pointer;
    font-weight: 600 !important;
}

.events-type-option:hover {
    background: #f4f8fc;
}

.events-type-option input {
    width: 16px;
    height: 16px;
    accent-color: #1677ff;
}

.events-filters__clear,
.events-filters__create {
    display: flex;
    width: 100%;
    min-height: 44px;
    align-items: center;
    justify-content: center;
    gap: 8px;
    border-radius: 11px;
    font-size: 0.86rem;
    font-weight: 750;
}

.events-filters__clear {
    margin-top: 20px;
    border: 1px solid #dce8f5;
    background: #f8fafc;
    color: #49627d;
}

.events-filters__create {
    margin-top: 10px;
    border: 0;
    background: linear-gradient(135deg, #0d4d97, #1677ff);
    color: #fff;
    text-decoration: none;
    box-shadow: 0 8px 18px rgba(22, 119, 255, 0.2);
}

.events-filters__create span {
    font-size: 1.15rem;
}

.events-filters__clear:hover {
    border-color: #b9d5f2;
    background: #eef6ff;
}

.events-filters__create:hover {
    color: #fff;
    filter: brightness(1.04);
}

.events-filters button:focus-visible,
.events-filters a:focus-visible {
    outline: 3px solid rgba(22, 119, 255, 0.3);
    outline-offset: 3px;
}

@media (max-width: 991px) {
    .events-filters {
        min-height: 100%;
        border: 0;
        border-radius: 0;
        padding: 22px 20px 32px;
        box-shadow: none;
    }

    .events-filters__close {
        display: inline-grid;
        place-items: center;
    }
}
</style>
