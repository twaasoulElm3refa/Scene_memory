<template>
    <div class="min-vh-100 bg-light py-4">
        <div class="container px-3 px-md-4">
            <div class="mb-4">
                <h1 class="display-6 fw-bold text-dark">{{ tr("eventForm.title", "Create event") }}</h1>
            </div>

            <form @submit.prevent="createEvent" class="row g-3 g-md-4">
                <div class="col-12">
                    <div class="card shadow border-0 rounded-3">
                        <div class="card-body p-3 p-md-4">
                            <div class="row g-2">
                                <div v-for="step in wizardSteps" :key="step.id" class="col-12 col-md-4">
                                    <button
                                        type="button"
                                        class="btn w-100 text-start rounded-3 border d-flex align-items-center gap-3 p-3"
                                        :class="stepButtonClass(step.id)"
                                        :disabled="!canOpenStep(step.id)"
                                        @click="openStep(step.id)"
                                    >
                                        <span
                                            class="rounded-circle d-inline-flex align-items-center justify-content-center fw-bold"
                                            style="width: 34px; height: 34px"
                                            :class="stepNumberClass(step.id)"
                                        >
                                            {{ step.id }}
                                        </span>
                                        <span>
                                            <span class="d-block fw-semibold">{{ step.label }}</span>
                                            <small class="text-muted">{{ step.description }}</small>
                                        </span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div v-show="currentPhase === 1" class="col-12">
                    <div class="card shadow border-0 rounded-3">
                        <div class="card-body p-4">
                            <h2 class="card-title h4 fw-bold mb-3">Photography Type</h2>
                            <div class="row g-3">
                                <div class="col-12 col-md-6">
                                    <button
                                        type="button"
                                        class="btn w-100 text-start border rounded-3 p-4 h-100"
                                        :class="photographyCardClass('professional')"
                                        @click="selectPhotographyType('professional')"
                                    >
                                        <span class="d-block h5 fw-bold mb-2">Professional photography</span>
                                        <span class="text-muted">
                                            Requires backend quality approval, minimum 720px width and height, and sharpness checks.
                                        </span>
                                    </button>
                                </div>

                                <div class="col-12 col-md-6">
                                    <button
                                        type="button"
                                        class="btn w-100 text-start border rounded-3 p-4 h-100"
                                        :class="photographyCardClass('normal')"
                                        @click="selectPhotographyType('normal')"
                                    >
                                        <span class="d-block h5 fw-bold mb-2">Normal photography</span>
                                        <span class="text-muted">
                                            Valid images are accepted without professional resolution or sharpness constraints.
                                        </span>
                                    </button>
                                </div>
                            </div>

                            <div v-if="!isPhotographyPhaseValid" class="text-danger small mt-3">
                                Please select a photography type before continuing.
                            </div>
                        </div>
                    </div>
                </div>

                <div v-show="currentPhase === 2" class="col-12">
                    <div class="row g-3 g-md-4">
                        <div class="col-12">
                            <div class="card shadow border-0 rounded-3">
                                <div class="card-body p-4">
                                    <h2 class="card-title h4 fw-bold mb-3">
                                        {{ tr("eventForm.basicInfo", "Basic information") }}
                                    </h2>
                                    <div class="row g-3">
                                        <div class="col-12">
                                            <label class="form-label fw-medium">
                                                {{ tr("eventForm.eventTitle", "Event title") }}
                                                <span class="text-danger">*</span>
                                            </label>
                                            <input
                                                v-model="form.title"
                                                type="text"
                                                class="form-control form-control-md rounded-3"
                                                :placeholder="tr('eventForm.eventTitle', 'Event title')"
                                                required
                                            />
                                        </div>

                                        <div class="col-12">
                                            <label class="form-label fw-medium">
                                                {{ tr("eventForm.description", "Description") }}
                                                <span class="text-danger">*</span>
                                            </label>
                                            <textarea
                                                v-model="form.description"
                                                class="form-control rounded-3"
                                                rows="5"
                                                :placeholder="tr('eventForm.description', 'Description')"
                                                required
                                                style="min-height: 120px"
                                            ></textarea>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-12">
                            <div class="card shadow border-0 rounded-3">
                                <div class="card-body p-4">
                                    <h2 class="card-title h4 fw-bold mb-3">
                                        {{ tr("eventForm.locationCategory", "Location and category") }}
                                    </h2>

                                    <div class="row g-3 mb-4">
                                        <div class="col-12 col-sm-6 col-lg-3">
                                            <label class="form-label fw-medium">
                                                {{ tr("eventForm.country", "Country") }}
                                                <span class="text-danger">*</span>
                                            </label>
                                            <input
                                                v-model="countrySearch"
                                                type="text"
                                                class="form-control form-control-md rounded-3 mb-2"
                                                :placeholder="tr('eventForm.searchCountry', 'Search country')"
                                            />
                                            <select
                                                v-model="selectedCountryId"
                                                class="form-select form-select-md rounded-3"
                                                required
                                                @change="loadCities"
                                            >
                                                <option value="" disabled>{{ tr("eventForm.selectCountry", "Select country") }}</option>
                                                <option v-for="country in filteredCountries" :key="country.id" :value="country.id">
                                                    {{ translatedName(country) }}
                                                </option>
                                            </select>
                                        </div>

                                        <div class="col-12 col-sm-6 col-lg-3">
                                            <label class="form-label fw-medium">
                                                {{ tr("eventForm.city", "City") }}
                                                <span class="text-danger">*</span>
                                            </label>
                                            <select
                                                v-model="form.city_id"
                                                :disabled="!selectedCountryId || cities.length === 0"
                                                class="form-select form-select-md rounded-3"
                                                required
                                            >
                                                <option value="" disabled>
                                                    {{ selectedCountryId ? tr("eventForm.selectCity", "Select city") : tr("eventForm.selectCityFirst", "Select country first") }}
                                                </option>
                                                <option v-for="city in cities" :key="city.id" :value="city.id">
                                                    {{ translatedName(city) }}
                                                </option>
                                            </select>
                                        </div>

                                        <div class="col-12 col-sm-6 col-lg-3">
                                            <label class="form-label fw-medium">
                                                {{ tr("eventForm.mainCategory", "Main category") }}
                                                <span class="text-danger">*</span>
                                            </label>
                                            <select
                                                v-model="selectedCategoryId"
                                                class="form-select form-select-md rounded-3"
                                                required
                                                @change="loadSubCategories"
                                            >
                                                <option value="" disabled>{{ tr("commons.choose", "Choose") }}</option>
                                                <option v-for="category in categories" :key="category.id" :value="category.id">
                                                    {{ translatedName(category) }}
                                                </option>
                                            </select>
                                        </div>

                                        <div class="col-12 col-sm-6 col-lg-3">
                                            <label class="form-label fw-medium">
                                                {{ tr("eventForm.subCategory", "Sub category") }}
                                                <span class="text-danger">*</span>
                                            </label>
                                            <select
                                                v-model="form.sub_categorey_id"
                                                :disabled="!selectedCategoryId || subCategories.length === 0"
                                                class="form-select form-select-md rounded-3"
                                                required
                                            >
                                                <option value="" disabled>{{ tr("eventForm.selectSubFirst", "Select sub category") }}</option>
                                                <option v-for="sub in subCategories" :key="sub.id" :value="sub.id">
                                                    {{ translatedName(sub) }}
                                                </option>
                                            </select>
                                        </div>

                                        <div class="col-12">
                                            <label class="form-label fw-medium d-flex align-items-center justify-content-between">
                                                <span>Tags <span class="text-danger">*</span></span>
                                                <small class="text-muted">{{ selectedTags.length }} tags selected</small>
                                            </label>

                                            <div class="position-relative">
                                                <button
                                                    type="button"
                                                    class="form-control form-control-md rounded-3 text-start d-flex flex-wrap align-items-center gap-2"
                                                    :disabled="loadingTags"
                                                    @click="toggleTagsDropdown"
                                                >
                                                    <span v-if="loadingTags" class="text-muted">Loading tags...</span>

                                                    <template v-else-if="selectedTags.length">
                                                        <span
                                                            v-for="tag in selectedTags"
                                                            :key="tag.id"
                                                            class="badge rounded-pill d-inline-flex align-items-center gap-1"
                                                            :class="tag.isNew ? 'text-bg-success' : 'text-bg-primary'"
                                                        >
                                                            #{{ tag.name }}
                                                            <span role="button" class="ms-1" @click.stop="removeSelectedTag(tag)">x</span>
                                                        </span>
                                                    </template>

                                                    <span v-else class="text-muted">Select or create tags</span>
                                                </button>

                                                <div
                                                    v-if="showTagsDropdown && !loadingTags"
                                                    class="position-absolute z-3 w-100 mt-1 bg-white border rounded-3 shadow p-2"
                                                    style="max-height: 280px; overflow-y: auto"
                                                >
                                                    <input
                                                        v-model="tagSearch"
                                                        type="text"
                                                        class="form-control form-control-sm rounded-3 mb-2"
                                                        placeholder="Search or type new tag"
                                                        @keydown.enter.prevent="canAddNewTag && addNewTag()"
                                                    />

                                                    <button
                                                        v-if="canAddNewTag"
                                                        type="button"
                                                        class="btn btn-outline-success btn-sm w-100 mb-2 text-start"
                                                        @click="addNewTag"
                                                    >
                                                        + Add "{{ normalizeTagName(tagSearch) }}"
                                                    </button>

                                                    <button
                                                        v-if="selectedTags.length"
                                                        type="button"
                                                        class="btn btn-link btn-sm text-danger text-decoration-none px-0 mb-1"
                                                        @click="clearTags"
                                                    >
                                                        Clear selected tags
                                                    </button>

                                                    <label
                                                        v-for="tag in filteredTags"
                                                        :key="tag.id"
                                                        class="d-flex align-items-center gap-2 px-2 py-2 rounded-3"
                                                        style="cursor: pointer"
                                                    >
                                                        <input
                                                            class="form-check-input m-0"
                                                            type="checkbox"
                                                            :checked="isTagSelected(tag.id)"
                                                            @change="toggleTag(tag)"
                                                        />
                                                        <span>#{{ tag.name }}</span>
                                                    </label>

                                                    <div v-if="tagSearch.trim() && filteredTags.length === 0 && !canAddNewTag" class="text-muted small px-2 py-2">
                                                        No tags found
                                                    </div>
                                                </div>
                                            </div>

                                            <small class="text-muted d-block mt-1">
                                  You can select existing tags or create new ones.
                                            </small>
                                        </div>
                                    </div>

                                    <div class="mt-3">
                                        <label class="form-label fw-medium d-block mb-2">
                                            {{ tr("eventForm.selectLocationMap", "Select location on map") }}
                                            <span class="text-danger">*</span>
                                        </label>

                                        <div
                                            ref="mapContainer"
                                            style="height: 350px; border-radius: 12px; border: 1px solid #dee2e6"
                                        ></div>

                                        <div class="mt-2 small text-muted" v-if="locatingUser">
                                            Detecting your current location...
                                        </div>

                                        <div class="mt-2 small text-warning" v-if="locationError">
                                            {{ locationError }}
                                        </div>

                                        <div class="mt-2 small text-muted" v-if="hasSelectedLocation">
                                            {{ tr("eventForm.selectedCoords", "Selected coordinates") }}
                                            <strong>{{ tr("eventForm.lat", "Lat") }}: {{ form.latitude.toFixed(6) }}</strong>,
                                            <strong>{{ tr("eventForm.lng", "Lng") }}: {{ form.longitude.toFixed(6) }}</strong>
                                        </div>

                                        <div v-else class="mt-2 small text-danger">
                                            {{ tr("eventForm.pleaseSelectLocation", "Please select a location") }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-12">
                            <div class="card shadow border-0 rounded-3">
                                <div class="card-body p-4">
                                    <h2 class="card-title h4 fw-bold mb-3">
                                        {{ tr("eventForm.dates", "Dates") }}
                                    </h2>
                                    <div class="row g-3">
                                        <div class="col-12 col-sm-4">
                                            <label class="form-label fw-medium">
                                                {{ tr("eventForm.startDate", "Start date") }}
                                                <span class="text-danger">*</span>
                                            </label>
                                            <input v-model="form.start_date" type="date" class="form-control rounded-3" required />
                                        </div>
                                        <div class="col-12 col-sm-4">
                                            <label class="form-label fw-medium">{{ tr("eventForm.endDate", "End date") }}</label>
                                            <input v-model="form.end_date" type="date" class="form-control rounded-3" />
                                        </div>
                                        <div class="col-12 col-sm-4">
                                            <label class="form-label fw-medium">{{ tr("eventForm.startTime", "Start time") }}</label>
                                            <input v-model="form.time" type="time" class="form-control rounded-3" />
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div v-show="currentPhase === 3" class="col-12">
                    <PhotoUploadWizard
                        v-model="form.media_items"
                        :photography-type="form.photography_type"
                        :max-photos="MAX_MEDIA"
                        :available-tags="tags"
                        :loading-tags="loadingTags"
                    />

                    <div v-if="photosBlockingMessage" class="alert alert-warning mt-3 mb-0">
                        {{ photosBlockingMessage }}
                    </div>
                </div>

                <div class="col-12 d-flex flex-column flex-sm-row justify-content-between gap-2 pt-3 pb-4">
                    <button
                        type="button"
                        class="btn btn-outline-secondary btn-md px-4 py-2 rounded-pill"
                        :disabled="currentPhase === 1 || loading"
                        @click="previousPhase"
                    >
                        Previous
                    </button>

                    <div class="d-flex flex-column flex-sm-row gap-2 ms-sm-auto">
                        <button
                            v-if="currentPhase < 3"
                            type="button"
                            class="btn btn-primary btn-md px-4 py-2 rounded-pill shadow"
                            :disabled="!currentPhaseValid"
                            @click="goNext"
                        >
                            Next
                        </button>

                        <button
                            v-else
                            type="submit"
                            :disabled="loading || !canSubmit"
                            class="btn btn-primary btn-md px-4 py-2 rounded-pill shadow"
                        >
                            {{ loading ? tr("commons.creating", "Creating...") : tr("commons.create", "Create") }}
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</template>

<script setup>
import { computed, nextTick, onMounted, onUnmounted, ref } from "vue";
import { useI18n } from "vue-i18n";
import maplibregl from "maplibre-gl";
import "maplibre-gl/dist/maplibre-gl.css";
import PhotoUploadWizard from "../../components/media/PhotoUploadWizard.vue";
import { useFormWizard } from "../../composables/useFormWizard";
import { LocationService } from "../../services/LocationService/LocationService";
import { CategoryService } from "../../services/CategoryService/CategoryService";
import { EventService } from "../../services/EventService/EventService";
import { TagService } from "../../services/TagService/TagService";

const MAX_MEDIA = 8;

const { t, te } = useI18n();
const tr = (key, fallback) => (te(key) ? t(key) : fallback);

const form = ref({
    photography_type: "",
    title: "",
    description: "",
    city_id: "",
    sub_categorey_id: "",
    start_date: "",
    end_date: "",
    time: "",
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
const countrySearch = ref("");
const locatingUser = ref(false);
const locationError = ref(null);
const tags = ref([]);
const selectedTags = ref([]);
const loadingTags = ref(false);
const tagSearch = ref("");
const showTagsDropdown = ref(false);

const wizardSteps = [
    { id: 1, label: "Photography Type", description: "Choose mode" },
    { id: 2, label: "Details", description: "Main form data" },
    { id: 3, label: "Photos", description: "Upload and tag" },
];

const { currentPhase, goToPhase, nextPhase, previousPhase } = useFormWizard({
    totalPhases: 3,
    afterPhaseChange: (phase) => {
        if (phase === 2) {
            resizeMapForDetailsPhase();
        }
    },
});

const filteredCountries = computed(() => {
    const search = countrySearch.value.trim().toLowerCase();

    if (!search) return countries.value;

    return countries.value.filter((country) =>
        translatedName(country).toLowerCase().includes(search)
    );
});

const hasSelectedLocation = computed(() =>
    Number.isFinite(form.value.latitude) && Number.isFinite(form.value.longitude)
);

const filteredTags = computed(() => {
    const search = tagSearch.value.trim().toLowerCase();

    if (!search) return [];

    return tags.value.filter((tag) => {
        const alreadySelected = selectedTags.value.some(
            (selected) => !selected.isNew && String(selected.id) === String(tag.id)
        );

        if (alreadySelected) return false;

        return String(tag.name || "").toLowerCase().includes(search);
    });
});

const canAddNewTag = computed(() => {
    const name = normalizeTagName(tagSearch.value);

    if (!name) return false;

    const existsInAllTags = tags.value.some(
        (tag) => String(tag.name || "").toLowerCase() === name.toLowerCase()
    );

    const existsInSelected = selectedTags.value.some(
        (tag) => String(tag.name || "").toLowerCase() === name.toLowerCase()
    );

    return !existsInAllTags && !existsInSelected;
});

const isPhotographyPhaseValid = computed(() =>
    ["professional", "normal"].includes(form.value.photography_type)
);

const isDetailsPhaseValid = computed(() =>
    Boolean(
        form.value.title?.trim() &&
        form.value.description?.trim() &&
        form.value.city_id &&
        form.value.sub_categorey_id &&
        form.value.start_date &&
        hasSelectedLocation.value &&
        selectedTags.value.length > 0
    )
);

const photosBlockingMessage = computed(() => {
    const photos = form.value.media_items;

    if (photos.length === 0) return "Please upload at least one photo.";

    if (photos.some((item) => !item.description?.trim())) {
        return "Every photo must have a description.";
    }

    if (photos.some((item) => !Array.isArray(item.tags) || item.tags.length === 0)) {
        return "Every photo must have at least one tag.";
    }

    if (photos.some((item) => !item.custom_price || Number(item.custom_price) <= 0)) {
        return "Please add a valid price for every photo.";
    }

    if (photos.some((item) => item.status === "checking")) {
        return "Please wait until all photo checks finish.";
    }

    const rejected = photos.find((item) => item.status === "rejected");

    if (rejected) {
        return rejected.errors?.length
            ? rejected.errors.join("; ")
            : "Rejected photos must be fixed before creating the event.";
    }

    if (form.value.photography_type === "professional" && photos.some((item) => item.status !== "accepted")) {
        return "All professional photos must be at least 720px by 720px and pass backend quality validation.";
    }

    return "";
});

const isPhotosPhaseValid = computed(() => photosBlockingMessage.value === "");

const currentPhaseValid = computed(() => {
    if (currentPhase.value === 1) return isPhotographyPhaseValid.value;
    if (currentPhase.value === 2) return isDetailsPhaseValid.value;
    return isPhotosPhaseValid.value;
});

const canSubmit = computed(() =>
    isPhotographyPhaseValid.value &&
    isDetailsPhaseValid.value &&
    isPhotosPhaseValid.value
);

async function goNext() {
    if (!currentPhaseValid.value) return;
    await nextPhase();
}

function selectPhotographyType(type) {
    form.value.photography_type = type;
}

function photographyCardClass(type) {
    return form.value.photography_type === type
        ? "border-primary bg-primary-subtle shadow-sm"
        : "bg-white";
}

function canOpenStep(stepId) {
    if (stepId <= currentPhase.value) return true;
    if (stepId === 2) return isPhotographyPhaseValid.value;
    if (stepId === 3) return isPhotographyPhaseValid.value && isDetailsPhaseValid.value;
    return false;
}

async function openStep(stepId) {
    if (canOpenStep(stepId)) {
        await goToPhase(stepId);
    }
}

function stepButtonClass(stepId) {
    if (stepId === currentPhase.value) return "border-primary bg-primary-subtle";
    if (stepId < currentPhase.value) return "border-success bg-white";
    return "bg-white";
}

function stepNumberClass(stepId) {
    if (stepId === currentPhase.value) return "bg-primary text-white";
    if (stepId < currentPhase.value) return "bg-success text-white";
    return "bg-light text-muted";
}

async function fetchCountries() {
    try {
        const res = await LocationService.getAllCountries();
        countries.value = res || [];
    } catch (err) {
        console.error("Failed to load countries", err);
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
        console.error("Failed to load cities", err);
        cities.value = [];
        alert("An error occurred while loading cities.");
    }
}

async function fetchCategories() {
    try {
        const res = await CategoryService.getCategories();
        categories.value = res.data.data || [];
    } catch (err) {
        console.error("Failed to load categories", err);
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
        console.error("Failed to load sub categories", err);
    }
}

function translatedName(item) {
    return item?.translation?.name || item?.name || "";
}

function normalizeTagName(name) {
    return String(name || "").trim().replace(/\s+/g, " ");
}

function isTagSelected(tagId) {
    return selectedTags.value.some(
        (tag) => !tag.isNew && String(tag.id) === String(tagId)
    );
}

function toggleTagsDropdown() {
    showTagsDropdown.value = !showTagsDropdown.value;

    if (showTagsDropdown.value) {
        tagSearch.value = "";
    }
}

function toggleTag(tag) {
    const exists = selectedTags.value.some(
        (selected) => !selected.isNew && String(selected.id) === String(tag.id)
    );

    if (exists) {
        selectedTags.value = selectedTags.value.filter(
            (selected) => selected.isNew || String(selected.id) !== String(tag.id)
        );
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
}

function addNewTag() {
    const name = normalizeTagName(tagSearch.value);

    if (!name) return;

    selectedTags.value = [
        ...selectedTags.value,
        {
            id: `new-${Date.now()}`,
            name,
            isNew: true,
        },
    ];

    tagSearch.value = "";
}

function removeSelectedTag(tag) {
    selectedTags.value = selectedTags.value.filter(
        (selected) => String(selected.id) !== String(tag.id)
    );
}

function clearTags() {
    selectedTags.value = [];
    tagSearch.value = "";
}

function appendPhotoFields(fd, item) {
    const metrics = item.metrics || {};
    const validationMessage = item.errors?.length
        ? item.errors.join("; ")
        : item.validationMessage || "";

    fd.append("urls[]", item.file);
    fd.append("photo_descriptions[]", item.description.trim());
    fd.append("photo_tags_json[]", JSON.stringify({
        tags_id: (item.tags || [])
            .filter((tag) => !tag.isNew)
            .map((tag) => tag.id),
        new_tags: (item.tags || [])
            .filter((tag) => tag.isNew)
            .map((tag) => tag.name),
    }));
    fd.append("photo_widths[]", metrics.width ?? "");
    fd.append("photo_heights[]", metrics.height ?? "");
    fd.append("photo_quality_scores[]", metrics.quality_score ?? "");
    fd.append("photo_sharpness_scores[]", metrics.sharpness_score ?? "");
    fd.append("photo_blur_scores[]", metrics.blur_score ?? "");
    fd.append("photo_validation_statuses[]", item.status || "");
    fd.append("photo_validation_messages[]", validationMessage);

    fd.append("media_prices[]", item.custom_price || item.suggested_price || 0);
    fd.append("media_widths[]", metrics.width ?? "");
    fd.append("media_heights[]", metrics.height ?? "");
    fd.append("media_quality_scores[]", metrics.quality_score ?? "");
    fd.append("media_sharpness_scores[]", metrics.sharpness_score ?? "");
    fd.append("media_contrast_scores[]", "");
    fd.append("media_brightness_scores[]", "");
    fd.append("media_file_sizes_mb[]", metrics.file_size_mb ?? "");
}

async function createEvent() {
    if (!canSubmit.value) {
        return alert(photosBlockingMessage.value || "Please complete all required fields.");
    }

    loading.value = true;
    const fd = new FormData();

    fd.append("photography_type", form.value.photography_type);
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

    form.value.media_items.forEach((item) => appendPhotoFields(fd, item));

    try {
        await EventService.createUser(fd);

        alert("Event created successfully!");
        window.location.href = "/";
    } catch (err) {
        console.error(err);
        alert("Failed to create event: " + (err.response?.data?.message || "Unknown error"));
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

function resizeMapForDetailsPhase() {
    nextTick(() => {
        if (!map) {
            initMap();
            return;
        }

        requestAnimationFrame(() => map?.resize());
    });
}

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
        map.resize();
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
        locationError.value = "Your browser does not support geolocation.";
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

            locationError.value = "Unable to detect your current location. You can select the location manually on the map.";
            locatingUser.value = false;
        },
        {
            enableHighAccuracy: true,
            timeout: 10000,
            maximumAge: 60000,
        }
    );
}

function patchLanguage(activeMap, isAr) {
    const style = activeMap.getStyle();

    if (!style?.layers) return;

    const langField = isAr ? "name:ar" : "name:en";
    const nameExpr = ["coalesce", ["get", langField], ["get", "name"]];

    style.layers.forEach((layer) => {
        if (layer.type !== "symbol") return;
        if (!layer.layout?.["text-field"]) return;

        activeMap.setLayoutProperty(layer.id, "text-field", nameExpr);

        if (isAr) {
            activeMap.setLayoutProperty(layer.id, "text-writing-mode", ["horizontal"]);
        }
    });
}
</script>
