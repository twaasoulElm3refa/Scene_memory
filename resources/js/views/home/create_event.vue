<template>
    <div class="min-vh-100 bg-light py-4">
        <div class="container px-3 px-md-4">
            <!-- العنوان -->
            <div class="mb-4">
                <h1 class="display-6 fw-bold text-dark">{{ $t('eventForm.title') }}</h1>
            </div>
            <form @submit.prevent="createEvent" class="row g-3 g-md-4">
                <!-- 1. المعلومات الأساسية -->
                <div class="col-12">
                    <div class="card shadow border-0 rounded-3">
                        <div class="card-body p-4">
                            <h2 class="card-title h4 fw-bold mb-3 d-flex align-items-center gap-2">
                                <span class="text-primary fs-3 fw-bolder">①</span>
                                {{ $t('eventForm.basicInfo') }}
                            </h2>
                            <div class="row g-3">
                                <div class="col-12">
                                    <label class="form-label fw-medium">
                                        {{ $t('eventForm.eventTitle') }} <span class="text-danger">*</span>
                                    </label>
                                    <input v-model="form.title" type="text"
                                        class="form-control form-control-md rounded-3"
                                        :placeholder="$t('eventForm.eventTitle') + ' ' + $t('commons.choose')"
                                        required />
                                </div>
                                <div class="col-12">
                                    <label class="form-label fw-medium">
                                        {{ $t('eventForm.description') }} <span class="text-danger">*</span>
                                    </label>
                                    <textarea v-model="form.description" class="form-control rounded-3" rows="5"
                                        :placeholder="$t('eventForm.description') + '، ' + $t('eventForm.basicInfo').toLowerCase() + '، ' + $t('eventForm.locationCategory').toLowerCase() + '...'"
                                        required style="min-height: 120px"></textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 2. الموقع والتصنيف + الخريطة -->
                <div class="col-12">
                    <div class="card shadow border-0 rounded-3">
                        <div class="card-body p-4">
                            <h2 class="card-title h4 fw-bold mb-3 d-flex align-items-center gap-2">
                                <span class="text-primary fs-3 fw-bolder">②</span>
                                {{ $t('eventForm.locationCategory') }}
                            </h2>
                            <div class="row g-3 mb-4">
                                <div class="col-12 col-sm-6 col-lg-3">
                                    <label class="form-label fw-medium">
                                        {{ $t('eventForm.country') }} <span class="text-danger">*</span>
                                    </label>
                                    <input v-model="countrySearch" type="text"
                                        class="form-control form-control-md rounded-3 mb-2"
                                        :placeholder="$t('eventForm.searchCountry')" />
                                    <select v-model="selectedCountryId" @change="loadCities"
                                        class="form-select form-select-md rounded-3" required>
                                        <option value="" disabled>{{ $t('eventForm.selectCountry') }}</option>
                                        <option v-for="c in filteredCountries" :key="c.id" :value="c.id">
                                            {{ c.translation.name }}
                                        </option>
                                    </select>
                                </div>
                                <div class="col-12 col-sm-6 col-lg-3">
                                    <label class="form-label fw-medium">
                                        {{ $t('eventForm.city') }} <span class="text-danger">*</span>
                                    </label>
                                    <select v-model="form.city_id" :disabled="!selectedCountryId || cities.length === 0"
                                        class="form-select form-select-md rounded-3" required>
                                        <option value="" disabled>
                                            {{ selectedCountryId ? $t('eventForm.selectCity') :
                                                $t('eventForm.selectCityFirst') }}
                                        </option>
                                        <option v-for="city in cities" :key="city.id" :value="city.id">
                                            {{ city.translation.name }}
                                        </option>
                                    </select>
                                </div>
                                <div class="col-12 col-sm-6 col-lg-3">
                                    <label class="form-label fw-medium">
                                        {{ $t('eventForm.mainCategory') }} <span class="text-danger">*</span>
                                    </label>
                                    <select v-model="selectedCategoryId" @change="loadSubCategories"
                                        class="form-select form-select-md rounded-3" required>
                                        <option value="" disabled>{{ $t('commons.choose') }}</option>
                                        <option v-for="cat in categories" :key="cat.id" :value="cat.id">
                                            {{ cat.translation.name }}
                                        </option>
                                    </select>
                                </div>
                                <div class="col-12 col-sm-6 col-lg-3">
                                    <label class="form-label fw-medium">
                                        {{ $t('eventForm.subCategory') }} <span class="text-danger">*</span>
                                    </label>
                                    <select v-model="form.sub_categorey_id"
                                        :disabled="!selectedCategoryId || subCategories.length === 0"
                                        class="form-select form-select-md rounded-3" required>
                                        <option value="" disabled>
                                            {{ selectedCategoryId ? $t('eventForm.selectSubFirst') :
                                                $t('eventForm.selectSubFirst') }}
                                        </option>
                                        <option v-for="sub in subCategories" :key="sub.id" :value="sub.id">
                                            {{ sub.translation.name }}
                                        </option>
                                    </select>
                                </div>
                                <div class="col-12">
                                    <label class="form-label fw-medium d-flex align-items-center justify-content-between">
                                        <span>Tags <span class="text-danger">*</span></span>
                                        <small class="text-muted">{{ selectedTags.length }} / 4</small>
                                    </label>

                                    <div class="position-relative">
                                        <button type="button"
                                            class="form-control form-control-md rounded-3 text-start d-flex flex-wrap align-items-center gap-2"
                                            :disabled="loadingTags" @click="showTagsDropdown = !showTagsDropdown">
                                            <span v-if="loadingTags" class="text-muted">
                                                Loading tags...
                                            </span>

                                            <template v-else-if="selectedTags.length">
                                                <span v-for="tag in selectedTags" :key="tag.id"
                                                    class="badge rounded-pill d-inline-flex align-items-center gap-1"
                                                    :class="tag.isNew ? 'text-bg-success' : 'text-bg-primary'">
                                                    #{{ tag.name }}

                                                    <span role="button" class="ms-1"
                                                        @click.stop="removeSelectedTag(tag)">
                                                        ×
                                                    </span>
                                                </span>
                                            </template>

                                            <span v-else class="text-muted">
                                                Select or create tags
                                            </span>
                                        </button>

                                        <div v-if="showTagsDropdown && !loadingTags"
                                            class="position-absolute z-3 w-100 mt-1 bg-white border rounded-3 shadow p-2"
                                            style="max-height: 280px; overflow-y: auto;">
                                            <input v-model="tagSearch" type="text"
                                                class="form-control form-control-sm rounded-3 mb-2"
                                                placeholder="Search or type new tag"
                                                @keydown.enter.prevent="canAddNewTag && addNewTag()" />

                                            <button v-if="canAddNewTag" type="button"
                                                class="btn btn-outline-success btn-sm w-100 mb-2 text-start"
                                                @click="addNewTag">
                                                + Add "{{ normalizeTagName(tagSearch) }}"
                                            </button>

                                            <button v-if="selectedTags.length" type="button"
                                                class="btn btn-link btn-sm text-danger text-decoration-none px-0 mb-1"
                                                @click="clearTags">
                                                Clear selected tags
                                            </button>

                                            <label v-for="tag in filteredTags" :key="tag.id"
                                                class="d-flex align-items-center gap-2 px-2 py-2 rounded-3"
                                                style="cursor: pointer;">
                                                <input class="form-check-input m-0" type="checkbox"
                                                    :checked="isTagSelected(tag.id)" @change="toggleTag(tag)" />

                                                <span>#{{ tag.name }}</span>
                                            </label>

                                            <div v-if="filteredTags.length === 0 && !canAddNewTag"
                                                class="text-muted small px-2 py-2">
                                                No tags found
                                            </div>
                                        </div>
                                    </div>

                                    <small class="text-muted d-block mt-1">
                                        You can select existing tags or create new ones. Maximum 4 tags.
                                    </small>
                                </div>
                            </div>

                            <div class="mt-3">
                                <label class="form-label fw-medium d-block mb-2">
                                    {{ $t('eventForm.selectLocationMap') }} <span class="text-danger">*</span>
                                </label>

                                <div ref="mapContainer"
                                    style="height: 350px; border-radius: 12px; border: 1px solid #dee2e6">
                                </div>

                                <div class="mt-2 small text-muted" v-if="locatingUser">
                                    جاري تحديد موقعك الحالي...
                                </div>

                                <div class="mt-2 small text-warning" v-if="locationError">
                                    {{ locationError }}
                                </div>

                                <div class="mt-2 small text-muted" v-if="hasSelectedLocation">
                                    {{ $t('eventForm.selectedCoords') }}
                                    <strong>{{ $t('eventForm.lat') }}: {{ form.latitude.toFixed(6) }}</strong> ,
                                    <strong>{{ $t('eventForm.lng') }}: {{ form.longitude.toFixed(6) }}</strong>
                                </div>

                                <div v-else class="mt-2 small text-danger">
                                    {{ $t('eventForm.pleaseSelectLocation') }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 3. المواعيد -->
                <div class="col-12">
                    <div class="card shadow border-0 rounded-3">
                        <div class="card-body p-4">
                            <h2 class="card-title h4 fw-bold mb-3 d-flex align-items-center gap-2">
                                <span class="text-primary fs-3 fw-bolder">③</span>
                                {{ $t('eventForm.dates') }}
                            </h2>
                            <div class="row g-3">
                                <div class="col-12 col-sm-4">
                                    <label class="form-label fw-medium">
                                        {{ $t('eventForm.startDate') }} <span class="text-danger">*</span>
                                    </label>
                                    <input v-model="form.start_date" type="date" class="form-control rounded-3"
                                        required />
                                </div>
                                <div class="col-12 col-sm-4">
                                    <label class="form-label fw-medium">{{ $t('eventForm.endDate') }}</label>
                                    <input v-model="form.end_date" type="date" class="form-control rounded-3" />
                                </div>
                                <div class="col-12 col-sm-4">
                                    <label class="form-label fw-medium">{{ $t('eventForm.startTime') }}</label>
                                    <input v-model="form.time" type="time" class="form-control rounded-3" />
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 4. صور وفيديوهات الحدث -->
                <div class="col-12">
                    <div class="card shadow border-0 rounded-3">
                        <div class="card-body p-4">
                            <h2 class="card-title h4 fw-bold mb-3 d-flex align-items-center gap-2">
                                <span class="text-primary fs-3 fw-bolder">④</span>
                                {{ $t('eventForm.media') || 'صور وفيديوهات الحدث' }}
                            </h2>
                            <div @dragover.prevent @drop.prevent="handleMediaDrop"
                                class="border border-2 border-dashed border-secondary-subtle rounded-3 p-4 text-center bg-body-tertiary"
                                style="min-height: 220px">
                                <input ref="fileInput" type="file"
                                    accept="image/png,image/jpeg,image/webp,video/mp4,video/webm,video/ogg" multiple
                                    hidden @change="handleMediaSelect" />

                                <!-- حالة بدون ملفات -->
                                <div v-if="form.media_items.length === 0" class="py-4">
                                    <div class="mx-auto mb-3 bg-primary-subtle rounded-circle d-flex align-items-center justify-content-center"
                                        style="width: 70px; height: 70px">
                                        <svg class="text-primary" width="36" height="36" fill="none"
                                            stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                                        </svg>
                                    </div>
                                    <p class="fs-5 fw-medium text-secondary mb-2">{{ $t('eventForm.dragDropHere') }}</p>
                                    <p class="text-muted small mb-3">
                                        {{ $t('eventForm.formatsMax') }}<br>
                                        <strong>PNG, JPG, WEBP, MP4, WEBM, OGG</strong> – الحد الأقصى 8 ملفات
                                    </p>
                                    <button type="button" @click="$refs.fileInput.click()"
                                        class="btn btn-primary btn-md px-4 py-2 rounded-pill">
                                        {{ $t('eventForm.chooseFiles') || 'اختر صور / فيديوهات' }}
                                    </button>
                                </div>

                                <!-- عرض الملفات بعد الرفع -->
                                <div v-else class="py-3">
                                    <div class="row row-cols-2 row-cols-sm-3 row-cols-md-4 g-3 mb-3">
                                        <div v-for="(item, index) in form.media_items" :key="item.preview || index"
                                            class="col">
                                            <div class="position-relative">
                                                <!-- صورة -->
                                                <img v-if="item.type === 'image'" :src="item.preview"
                                                    :alt="'media ' + index"
                                                    class="img-fluid rounded-3 shadow"
                                                    style="height: 140px; object-fit: cover; width: 100%" />
                                                <!-- فيديو -->
                                                <video v-else :src="item.preview" class="rounded-3 shadow w-100"
                                                    style="height: 140px; object-fit: cover;" controls muted></video>

                                                <button type="button" @click="removeMedia(index)"
                                                    class="btn btn-sm btn-danger position-absolute top-0 end-0 m-1 rounded-circle shadow-sm"
                                                    :title="$t('eventForm.remove')"
                                                    style="width: 28px; height: 28px; line-height: 1; font-size: 1.1rem; padding: 0;">
                                                    ×
                                                </button>

                                                <!-- علامة فيديو -->
                                                <span v-if="item.type === 'video'"
                                                    class="position-absolute bottom-0 start-50 translate-middle-x bg-dark text-white px-2 py-1 rounded-top small fw-bold"
                                                    style="font-size: 0.75rem;">
                                                    فيديو
                                                </span>
                                            </div>

                                            <div v-if="item.type === 'image'" class="mt-2 text-start">
                                                <!-- <div class="small text-muted">
                                                    {{ item.width }} × {{ item.height }} — {{ item.megapixels }}MP —
                                                    {{ item.file_size_mb }}MB
                                                </div>

                                                <div class="small text-success fw-semibold">
                                                    Quality Score: {{ item.quality_score }}%
                                                </div>

                                                <div class="small text-muted">
                                                    Sharpness: {{ item.sharpness_score }}% · Contrast:
                                                    {{ item.contrast_score }}% · Brightness:
                                                    {{ item.brightness_score }}%
                                                </div> -->

                                                <div class="small text-primary fw-semibold">
                                                    Suggested Price: ${{ item.suggested_price }}
                                                </div>

                                                <label class="form-label small mb-1 mt-1">
                                                    Your Price
                                                </label>

                                                <input v-model.number="item.custom_price" type="number" min="1"
                                                    step="1" class="form-control form-control-sm rounded-3"
                                                    placeholder="Enter your price" />
                                            </div>
                                        </div>
                                    </div>

                                    <button v-if="form.media_items.length < MAX_MEDIA" type="button"
                                        @click="$refs.fileInput.click()" class="btn btn-outline-primary btn-sm px-4">
                                        {{ $t('eventForm.addMore') }}
                                    </button>

                                    <small class="text-muted d-block mt-2">
                                        {{ $t('eventForm.mediaCount', { count: form.media_items.length }) }} / {{
                                            MAX_MEDIA }}
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- الأزرار -->
                <div class="col-12 d-flex flex-column flex-sm-row justify-content-end gap-2 pt-3 pb-4">
                    <button type="button" class="btn btn-outline-secondary btn-md px-4 py-2 rounded-pill">
                        {{ $t('commons.cancel') }}
                    </button>
                    <button type="submit" :disabled="loading || !hasSelectedLocation"
                        class="btn btn-primary btn-md px-4 py-2 rounded-pill shadow">
                        {{ loading ? $t('commons.creating') : $t('commons.create') }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted, nextTick, computed } from "vue";
import maplibregl from "maplibre-gl";
import "maplibre-gl/dist/maplibre-gl.css";
import { LocationService } from "../../services/LocationService/LocationService";
import { CategoryService } from "../../services/CategoryService/CategoryService";
import { EventService } from "../../services/EventService/EventService";
import { TagService } from "../../services/TagService/TagService";

const MAX_MEDIA = 8;

const form = ref({
    title: "",
    description: "",
    city_id: "",
    sub_categorey_id: "",
    start_date: "",
    end_date: "",
    time: "",
    media_files: [],
    media_previews: [],
    media_items: [],
    latitude: null,
    longitude: null,
});

const countries = ref([]);
const cities = ref([]);
const categories = ref([]);
const subCategories = ref([]);
const selectedCountryId = ref("");
const selectedCategoryId = ref("");
const loading = ref(false);
const fileInput = ref(null);
const countrySearch = ref("");
const locatingUser = ref(false);
const locationError = ref(null);
const tags = ref([]);
const selectedTags = ref([]);
const loadingTags = ref(false);
const tagSearch = ref("");
const showTagsDropdown = ref(false);

const filteredCountries = computed(() => {
    if (!countrySearch.value) return countries.value;
    return countries.value.filter(c =>
        c.translation.name.toLowerCase().includes(countrySearch.value.toLowerCase())
    );
});

const hasSelectedLocation = computed(() =>
    Number.isFinite(form.value.latitude) && Number.isFinite(form.value.longitude)
);

function normalizeTagName(name) {
    return String(name || "")
        .trim()
        .replace(/\s+/g, " ");
}

const canAddNewTag = computed(() => {
    const name = normalizeTagName(tagSearch.value);

    if (!name) return false;

    const existsInAllTags = tags.value.some((tag) =>
        String(tag.name || "").toLowerCase() === name.toLowerCase()
    );

    const existsInSelected = selectedTags.value.some((tag) =>
        String(tag.name || "").toLowerCase() === name.toLowerCase()
    );

    return !existsInAllTags && !existsInSelected;
});

const filteredTags = computed(() => {
    const search = tagSearch.value.trim().toLowerCase();

    return tags.value
        .filter((tag) => {
            const alreadySelected = selectedTags.value.some(
                (selected) => !selected.isNew && String(selected.id) === String(tag.id)
            );

            if (alreadySelected) return false;

            if (!search) return true;

            return String(tag.name || "").toLowerCase().includes(search);
        });
});

async function fetchCountries() {
    try {
        const res = await LocationService.getAllCountries();
        countries.value = res || [];
        console.log("Countries loaded:", countries.value);
    } catch (err) {
        console.error("فشل تحميل الدول", err);
    }
}

async function loadCities() {
    if (!selectedCountryId.value) {
        cities.value = [];
        form.value.city_id = "";
        return;
    }
    try {
        const res = await LocationService.getCountryById(selectedCountryId.value);
        cities.value = res.data.data?.country?.cities || [];
        form.value.city_id = "";

    } catch (err) {
        console.error("فشل تحميل المدن", err);
        cities.value = [];
        alert("حدث خطأ أثناء تحميل المدن");
    } finally {
        loading.value = false;
    }
}

async function fetchCategories() {
    try {
        const res = await CategoryService.getCategories();
        categories.value = res.data.data || [];
    } catch (err) {
        console.error("فشل تحميل الفئات", err);
    }
}

async function fetchTags() {
    loadingTags.value = true;

    try {
        const res = await TagService.getTags();
        tags.value = res?.data?.data || res?.data || [];
    } catch (err) {
        console.error("Failed to load tags", err);
        tags.value = [];
    } finally {
        loadingTags.value = false;
    }
}

async function loadSubCategories() {
    subCategories.value = [];
    form.value.sub_categorey_id = "";
    if (!selectedCategoryId.value) return;
    try {
        const res = await CategoryService.getCategoryById(selectedCategoryId.value);
        subCategories.value = res.data.data?.sub_categories || [];
    } catch (err) {
        console.error("فشل تحميل التصنيفات الفرعية", err);
    }
}

const isTagSelected = (tagId) => {
    return selectedTags.value.some(
        (tag) => !tag.isNew && String(tag.id) === String(tagId)
    );
};

const toggleTag = (tag) => {
    const exists = selectedTags.value.some(
        (selected) => !selected.isNew && String(selected.id) === String(tag.id)
    );

    if (exists) {
        selectedTags.value = selectedTags.value.filter(
            (selected) => selected.isNew || String(selected.id) !== String(tag.id)
        );
        return;
    }

    if (selectedTags.value.length >= 4) {
        alert("You can select up to 4 tags only");
        return;
    }

    selectedTags.value = [
        ...selectedTags.value,
        {
            id: tag.id,
            name: tag.name,
            isNew: false,
        },
    ];
};

const addNewTag = () => {
    const name = normalizeTagName(tagSearch.value);

    if (!name) return;

    const exists = selectedTags.value.some(
        (tag) => String(tag.name || "").toLowerCase() === name.toLowerCase()
    );

    if (exists) {
        alert("This tag is already selected");
        return;
    }

    if (selectedTags.value.length >= 4) {
        alert("You can select up to 4 tags only");
        return;
    }

    selectedTags.value = [
        ...selectedTags.value,
        {
            id: `new-${Date.now()}`,
            name,
            isNew: true,
        },
    ];

    tagSearch.value = "";
};

const removeSelectedTag = (tag) => {
    selectedTags.value = selectedTags.value.filter(
        (selected) => String(selected.id) !== String(tag.id)
    );
};

const clearTags = () => {
    selectedTags.value = [];
    tagSearch.value = "";
};

async function handleMediaSelect(e) {
    const files = Array.from(e.target.files || []);
    await processMedia(files);
}

async function handleMediaDrop(e) {
    const files = Array.from(e.dataTransfer.files || []);
    await processMedia(files);
}

function clamp(value, min, max) {
    return Math.max(min, Math.min(max, value));
}

function normalize(value, min, max) {
    if (max === min) return 0;
    return clamp((value - min) / (max - min), 0, 1);
}

function getImageQualityMetrics(file, maxAnalyzeSize = 512) {
    return new Promise((resolve, reject) => {
        const image = new Image();
        const url = URL.createObjectURL(file);

        image.onload = () => {
            try {
                const originalWidth = image.naturalWidth;
                const originalHeight = image.naturalHeight;
                const megapixels = (originalWidth * originalHeight) / 1000000;
                const fileSizeMB = file.size / (1024 * 1024);

                const scale = Math.min(
                    1,
                    maxAnalyzeSize / Math.max(originalWidth, originalHeight)
                );

                const width = Math.max(1, Math.round(originalWidth * scale));
                const height = Math.max(1, Math.round(originalHeight * scale));

                const canvas = document.createElement("canvas");
                canvas.width = width;
                canvas.height = height;

                const ctx = canvas.getContext("2d", { willReadFrequently: true });
                ctx.drawImage(image, 0, 0, width, height);

                const { data } = ctx.getImageData(0, 0, width, height);

                const grayscale = new Float32Array(width * height);
                let brightnessSum = 0;

                for (let i = 0, p = 0; i < data.length; i += 4, p += 1) {
                    const r = data[i];
                    const g = data[i + 1];
                    const b = data[i + 2];

                    const gray = 0.299 * r + 0.587 * g + 0.114 * b;
                    grayscale[p] = gray;
                    brightnessSum += gray;
                }

                const pixelCount = width * height;
                const brightness = brightnessSum / pixelCount;

                let contrastSum = 0;
                for (let i = 0; i < grayscale.length; i += 1) {
                    const diff = grayscale[i] - brightness;
                    contrastSum += diff * diff;
                }

                const contrast = Math.sqrt(contrastSum / pixelCount);

                let laplacianCount = 0;
                let sharpnessMean = 0;
                const laplacianValues = [];

                for (let y = 1; y < height - 1; y += 1) {
                    for (let x = 1; x < width - 1; x += 1) {
                        const idx = y * width + x;

                        const center = grayscale[idx] * 4;
                        const left = grayscale[idx - 1];
                        const right = grayscale[idx + 1];
                        const top = grayscale[idx - width];
                        const bottom = grayscale[idx + width];

                        const laplacian = Math.abs(center - left - right - top - bottom);
                        laplacianValues.push(laplacian);
                        sharpnessMean += laplacian;
                        laplacianCount += 1;
                    }
                }

                sharpnessMean = laplacianCount ? sharpnessMean / laplacianCount : 0;

                let sharpnessVariance = 0;
                for (const value of laplacianValues) {
                    const diff = value - sharpnessMean;
                    sharpnessVariance += diff * diff;
                }

                sharpnessVariance = laplacianCount ? sharpnessVariance / laplacianCount : 0;

                const resolutionScore = normalize(megapixels, 0.5, 12);
                const sharpnessScore = normalize(sharpnessVariance, 40, 1200);
                const contrastScore = normalize(contrast, 20, 75);

                const brightnessScore = 1 - Math.abs(brightness - 128) / 128;
                const normalizedBrightnessScore = clamp(brightnessScore, 0, 1);

                const density = fileSizeMB / Math.max(megapixels, 0.1);
                const densityScore = normalize(density, 0.15, 2.5);

                const qualityScore =
                    resolutionScore * 0.30 +
                    sharpnessScore * 0.30 +
                    contrastScore * 0.15 +
                    normalizedBrightnessScore * 0.15 +
                    densityScore * 0.10;

                URL.revokeObjectURL(url);

                resolve({
                    width: originalWidth,
                    height: originalHeight,
                    megapixels: Number(megapixels.toFixed(2)),
                    fileSizeMB: Number(fileSizeMB.toFixed(2)),
                    brightness: Number(brightness.toFixed(2)),
                    contrast: Number(contrast.toFixed(2)),
                    sharpness: Number(sharpnessVariance.toFixed(2)),
                    resolutionScore: Math.round(resolutionScore * 100),
                    sharpnessScore: Math.round(sharpnessScore * 100),
                    contrastScore: Math.round(contrastScore * 100),
                    brightnessScore: Math.round(normalizedBrightnessScore * 100),
                    densityScore: Math.round(densityScore * 100),
                    qualityScore: Math.round(qualityScore * 100),
                });
            } catch (error) {
                URL.revokeObjectURL(url);
                reject(error);
            }
        };

        image.onerror = () => {
            URL.revokeObjectURL(url);
            reject(new Error("Unable to analyze image quality"));
        };

        image.src = url;
    });
}

function calculateSuggestedPriceFromQuality(metrics) {
    const score = metrics.qualityScore;
    const megapixels = metrics.megapixels;

    let price = 10;

    if (score >= 90) {
        price = 80;
    } else if (score >= 80) {
        price = 60;
    } else if (score >= 70) {
        price = 45;
    } else if (score >= 60) {
        price = 30;
    } else if (score >= 50) {
        price = 20;
    } else {
        price = 10;
    }

    if (megapixels >= 12) price += 15;
    else if (megapixels >= 8) price += 10;
    else if (megapixels >= 4) price += 5;

    return Math.max(10, Math.round(price));
}

function readFilePreview(file) {
    return new Promise((resolve, reject) => {
        const reader = new FileReader();

        reader.onload = (ev) => resolve(ev.target.result);
        reader.onerror = () => reject(new Error("Unable to read file preview"));
        reader.readAsDataURL(file);
    });
}

function syncMediaArrays() {
    form.value.media_files = form.value.media_items.map((item) => item.file);
    form.value.media_previews = form.value.media_items.map((item) => item.preview);
}

async function processMedia(newFiles) {
    const currentCount = form.value.media_items.length;
    const canAdd = MAX_MEDIA - currentCount;

    if (newFiles.length > canAdd) {
        alert(`يمكنك إضافة ${canAdd} ملف${canAdd === 1 ? "" : "ات"} فقط`);
        newFiles = newFiles.slice(0, canAdd);
    }

    for (const file of newFiles) {
        if (file.size > 20 * 1024 * 1024) {
            alert(`حجم الملف ${file.name} يتجاوز 5 ميجا`);
            continue;
        }

        const allowedTypes = [
            "image/png", "image/jpeg", "image/webp",
            "video/mp4", "video/webm", "video/ogg"
        ];

        if (!allowedTypes.includes(file.type)) {
            alert(`نوع الملف ${file.name} غير مدعوم`);
            continue;
        }

        try {
            const preview = await readFilePreview(file);

            if (file.type.startsWith("image/")) {
                const metrics = await getImageQualityMetrics(file);

                if (metrics.width < 720 || metrics.height < 720) {
                    alert(`Image ${file.name} quality is too low. Minimum resolution is 720px width and height.`);
                    continue;
                }

                const suggestedPrice = calculateSuggestedPriceFromQuality(metrics);

                form.value.media_items.push({
                    file,
                    preview,
                    type: "image",
                    width: metrics.width,
                    height: metrics.height,
                    suggested_price: suggestedPrice,
                    custom_price: suggestedPrice,
                    quality_score: metrics.qualityScore,
                    sharpness_score: metrics.sharpnessScore,
                    contrast_score: metrics.contrastScore,
                    brightness_score: metrics.brightnessScore,
                    resolution_score: metrics.resolutionScore,
                    density_score: metrics.densityScore,
                    megapixels: metrics.megapixels,
                    file_size_mb: metrics.fileSizeMB,
                    sharpness: metrics.sharpness,
                    contrast: metrics.contrast,
                    brightness: metrics.brightness,
                });
            } else {
                form.value.media_items.push({
                    file,
                    preview,
                    type: "video",
                    width: null,
                    height: null,
                    suggested_price: null,
                    custom_price: null,
                });
            }

            syncMediaArrays();
        } catch (err) {
            console.error("Failed to process media", err);
            alert(`تعذر قراءة الملف ${file.name}`);
        }
    }

    if (fileInput.value) fileInput.value.value = "";
}

function removeMedia(index) {
    form.value.media_items.splice(index, 1);
    syncMediaArrays();
}

async function createEvent() {
    if (
        !form.value.title?.trim() ||
        !form.value.description?.trim() ||
        !form.value.city_id ||
        !form.value.sub_categorey_id ||
        !form.value.start_date ||
        !hasSelectedLocation.value
    ) {
        return alert("برجاء ملء جميع الحقول المطلوبة (بما فيها الموقع على الخريطة)");
    }

    if (selectedTags.value.length === 0) {
        return alert("Please select or create at least one tag");
    }

    if (selectedTags.value.length > 4) {
        return alert("You can select up to 4 tags only");
    }

    if (form.value.media_items.length === 0) {
        return alert("يرجى رفع صورة أو فيديو واحد على الأقل");
    }

    const invalidPrice = form.value.media_items.some((item) =>
        item.type === "image" && (!item.custom_price || Number(item.custom_price) <= 0)
    );

    if (invalidPrice) {
        return alert("Please add a valid price for all images");
    }

    loading.value = true;
    const fd = new FormData();

    fd.append("title", form.value.title);
    fd.append("description", form.value.description);
    fd.append("city_id", form.value.city_id);
    fd.append("sub_categorey_id", form.value.sub_categorey_id);
    fd.append("start_date", form.value.start_date);

    if (form.value.end_date) fd.append("end_date", form.value.end_date);
    if (form.value.time) fd.append("time", form.value.time);

    fd.append("lattitude", form.value.latitude);
    fd.append("langitude", form.value.longitude);

    selectedTags.value.forEach((tag) => {
        if (tag.isNew) {
            fd.append("new_tags[]", tag.name);
        } else {
            fd.append("tags_id[]", tag.id);
        }
    });

    form.value.media_items.forEach((item) => {
        fd.append("urls[]", item.file);

        if (item.type === "image") {
            fd.append("media_prices[]", item.custom_price || item.suggested_price || 0);
            fd.append("media_widths[]", item.width);
            fd.append("media_heights[]", item.height);
            fd.append("media_quality_scores[]", item.quality_score || "");
            fd.append("media_sharpness_scores[]", item.sharpness_score || "");
            fd.append("media_contrast_scores[]", item.contrast_score || "");
            fd.append("media_brightness_scores[]", item.brightness_score || "");
            fd.append("media_file_sizes_mb[]", item.file_size_mb || "");
        } else {
            fd.append("media_prices[]", "");
            fd.append("media_widths[]", "");
            fd.append("media_heights[]", "");
            fd.append("media_quality_scores[]", "");
            fd.append("media_sharpness_scores[]", "");
            fd.append("media_contrast_scores[]", "");
            fd.append("media_brightness_scores[]", "");
            fd.append("media_file_sizes_mb[]", "");
        }
    });

    try {
        await EventService.createUser(fd);

        alert("تم إنشاء الحدث بنجاح!");
        window.location.href = "/";
    } catch (err) {
        console.error(err);
        alert("فشل إنشاء الحدث: " + (err.response?.data?.message || "خطأ غير معروف"));
    } finally {
        loading.value = false;
    }
}

const mapContainer = ref(null);
let map = null;
let marker = null;

const STYLE_URL = "https://tiles.openfreemap.org/styles/liberty";

onMounted(async () => {
    await Promise.all([fetchCountries(), fetchCategories(), fetchTags()]);
    await nextTick();

    initMap();
});

onUnmounted(() => {
    if (marker) {
        marker.remove();
        marker = null;
    }

    if (map) {
        map.remove();
        map = null;
    }
});

function initMap() {
    if (!mapContainer.value || map) return;

    const lang = localStorage.getItem("language") || "ar";
    const isAr = lang === "ar";
    const defaultLng = 31.2357;
    const defaultLat = 30.0444;

    map = new maplibregl.Map({
        container: mapContainer.value,
        style: STYLE_URL,
        center: [defaultLng, defaultLat],
        zoom: 6,
    });

    map.addControl(new maplibregl.NavigationControl());

    map.on("load", () => {
        patchLanguage(map, isAr);
        locateUserOnMap();
    });

    map.on("click", (e) => {
        const { lat, lng } = e.lngLat;
        setSelectedLocation(lat, lng, false);
    });
}

function setSelectedLocation(lat, lng, shouldFly = true) {
    form.value.latitude = lat;
    form.value.longitude = lng;
    locationError.value = null;

    if (!map) return;

    if (!marker) {
        marker = new maplibregl.Marker({ color: "#e53e3e" })
            .setLngLat([lng, lat])
            .addTo(map);
    } else {
        marker.setLngLat([lng, lat]);
    }

    if (shouldFly) {
        map.flyTo({
            center: [lng, lat],
            zoom: 14,
            essential: true,
        });
    }
}

function locateUserOnMap() {
    if (!navigator.geolocation) {
        locationError.value = "المتصفح لا يدعم تحديد الموقع";
        return;
    }

    locatingUser.value = true;
    locationError.value = null;

    navigator.geolocation.getCurrentPosition(
        (position) => {
            const lat = position.coords.latitude;
            const lng = position.coords.longitude;

            setSelectedLocation(lat, lng, true);
            locatingUser.value = false;
        },
        (error) => {
            console.warn("Geolocation error:", error);

            locationError.value = "تعذر تحديد موقعك الحالي، يمكنك اختيار الموقع يدويًا من الخريطة";
            locatingUser.value = false;
        },
        {
            enableHighAccuracy: true,
            timeout: 10000,
            maximumAge: 60000,
        }
    );
}

function patchLanguage(map, isAr) {
    const style = map.getStyle();
    if (!style?.layers) return;

    const langField = isAr ? "name:ar" : "name:en";
    const nameExpr = ["coalesce", ["get", langField], ["get", "name"]];

    style.layers.forEach(layer => {
        if (layer.type !== "symbol") return;
        if (!layer.layout?.["text-field"]) return;

        map.setLayoutProperty(layer.id, "text-field", nameExpr);

        if (isAr) {
            map.setLayoutProperty(layer.id, "text-writing-mode", ["horizontal"]);
        }
    });
}
</script>
