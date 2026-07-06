<template>
    <div class="bg-white border-b shadow-sm sticky top-0 z-10">
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
