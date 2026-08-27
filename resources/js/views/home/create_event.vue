<template>
    <div class="scemory-page create-event-page min-vh-100 py-4">
        <div class="container px-3 px-md-4">
            <div class="mb-4">
                <h1 class="display-6 fw-bold text-dark">
                    {{ props.admin ? $t("eventForm.admin.title") : tr("eventForm.title", "Create event") }}
                </h1>
                <p class="text-muted small mb-0 mt-2">
                    {{ $t("eventForm.minimumRequirementsHint") }}
                </p>
            </div>

            <form @submit.prevent="createEvent" class="row g-3 g-md-4">
                <div class="col-12">
                    <div class="card shadow border-0 rounded-3">
                        <div class="card-body p-3 p-md-4">
                            <div class="row g-2">
                                <div v-for="step in wizardSteps" :key="step.id" class="col-12 col-md-6">
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
                                            <span class="d-block fw-semibold">{{ $t(step.label) }}</span>
                                            <small class="text-muted">{{ $t(step.description) }}</small>
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
                            <!-- is_real: {{ $t('eventForm.eventType') }} -->
                            <h2 class="card-title h4 fw-bold mb-3">{{ $t('eventForm.eventType') }}</h2>
                            <div class="row g-2 g-md-3 mb-4">
                                <div class="col-12 col-md-6">
                                    <label
                                        class="event-type-option d-flex align-items-start gap-3 border rounded-3 px-3 py-3 h-100"
                                        :class="form.is_real === true ? 'border-primary bg-primary-subtle shadow-sm' : 'border-secondary-subtle bg-white'"
                                    >
                                        <input
                                            v-model="form.is_real"
                                            class="event-type-input"
                                            type="radio"
                                            name="event_type"
                                            :value="true"
                                        />
                                        <span class="event-type-radio flex-shrink-0 mt-1"></span>
                                        <span>
                                            <span class="d-block fw-semibold lh-sm">{{ $t('eventForm.publicOfficialEvent') }}</span>
                                            <small class="text-muted d-block mt-1">
                                                {{ $t('eventForm.publicOfficialDescription') }}
                                            </small>
                                        </span>
                                    </label>
                                </div>

                                <div class="col-12 col-md-6">
                                    <label
                                        class="event-type-option d-flex align-items-start gap-3 border rounded-3 px-3 py-3 h-100"
                                        :class="form.is_real === false ? 'border-primary bg-primary-subtle shadow-sm' : 'border-secondary-subtle bg-white'"
                                    >
                                        <input
                                            v-model="form.is_real"
                                            class="event-type-input"
                                            type="radio"
                                            name="event_type"
                                            :value="false"
                                        />
                                        <span class="event-type-radio flex-shrink-0 mt-1"></span>
                                        <span>
                                            <span class="d-block fw-semibold lh-sm">{{ $t('eventForm.personalEvent') }}</span>
                                            <small class="text-muted d-block mt-1">
                                                {{ $t('eventForm.personalEventDescription') }}
                                            </small>
                                        </span>
                                    </label>
                                </div>
                            </div>

                            <h2 class="card-title h4 fw-bold mb-3">{{ $t('eventForm.photographyType') }}</h2>
                            <div class="row g-3">
                                <div class="col-12 col-md-6">
                                    <button
                                        type="button"
                                        class="btn w-100 text-start border rounded-3 p-4 h-100"
                                        :class="photographyCardClass('professional')"
                                        @click="selectPhotographyType('professional')"
                                    >
                                        <span class="d-block h5 fw-bold mb-2">{{ $t('photoUpload.professionalPhotography') }}</span>
                                        <span class="text-muted">
                                            {{ $t('eventForm.professional720Notice') }}
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
                                        <span class="d-block h5 fw-bold mb-2">{{ $t('photoUpload.normalPhotography') }}</span>
                                        <span class="text-muted">
                                            {{ $t('eventForm.normalPhotoNotice') }}
                                        </span>
                                    </button>
                                </div>
                            </div>

                            <div v-if="stepOneBlockingMessage && !form.photography_type" class="text-danger small mt-3">
                                {{ stepOneBlockingMessage }}
                            </div>
                        </div>
                    </div>

                    <div v-if="form.photography_type" class="mt-3">
                        <PhotoUploadWizard
                            v-model="form.media_items"
                            :photography-type="form.photography_type"
                            :max-photos="MAX_MEDIA"
                            :available-tags="tags"
                            :loading-tags="loadingTags"
                        />

                        <div v-if="stepOneBlockingMessage" class="alert alert-warning mt-3 mb-0">
                            {{ stepOneBlockingMessage }}
                        </div>
                    </div>
                </div>

                <div v-show="currentPhase === 2" class="col-12">
                    <div class="required-details-notice alert alert-warning border-0 rounded-4 shadow-sm mb-3" role="alert">
                        <strong class="d-block fs-6 text-dark mb-2">
                            {{ $t("eventForm.errors.requiredFields") }}
                        </strong>

                        <ul class="mb-0 ps-3 fw-semibold text-dark">
                            <li>{{ tr("eventForm.eventTitle", "Event title") }}</li>
                            <li>{{ tr("eventForm.description", "Description") }}</li>
                            <li>{{ tr("eventForm.country", "Country") }}</li>
                            <li>{{ tr("eventForm.city", "City") }}</li>
                            <li>{{ tr("eventForm.mainCategory", "Main category") }}</li>
                            <li>{{ tr("eventForm.subCategory", "Sub category") }}</li>
                        </ul>
                    </div>

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
                                                aria-required="true"
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
                                                aria-required="true"
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
                                                aria-required="true"
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
                                                aria-required="true"
                                            >
                                                <option value="" disabled>{{ tr("eventForm.selectSubFirst", "Select sub category") }}</option>
                                                <option v-for="sub in subCategories" :key="sub.id" :value="sub.id">
                                                    {{ translatedName(sub) }}
                                                </option>
                                            </select>
                                        </div>

                                        <div class="col-12">
                                            <label class="form-label fw-medium d-flex align-items-center justify-content-between">
                                                <span>{{ $t('eventForm.tags') }}</span>
                                                <small class="text-muted">{{ selectedTags.length }} {{ $t('eventForm.tagsSelected') }}</small>
                                            </label>

                                            <div class="position-relative">
                                                <button
                                                    type="button"
                                                    class="form-control form-control-md rounded-3 text-start d-flex flex-wrap align-items-center gap-2"
                                                    :disabled="loadingTags"
                                                    @click="toggleTagsDropdown"
                                                >
                                                    <span v-if="loadingTags" class="text-muted">{{ $t('homeAudit.tags.loading') }}</span>

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

                                                    <span v-else class="text-muted">{{ $t('eventForm.selectOrCreateTags') }}</span>
                                                </button>

                                                <div
                                                    v-if="showTagsDropdown && !loadingTags"
                                                    class="position-absolute z-3 w-100 mt-1 bg-white border rounded-3 shadow p-2"
                                                    style="max-height: 280px; overflow-y: auto"
                                                >
                                                    <input
                                                        ref="eventTagSearchInput"
                                                        v-model="tagSearch"
                                                        type="text"
                                                        class="form-control form-control-sm rounded-3 mb-2"
                                                        :placeholder="$t('eventForm.searchOrTypeTag')"
                                                        @keydown.enter.prevent="canAddNewTag && addNewTag()"
                                                        @keydown.escape="closeTagsDropdown"
                                                    />

                                                    <template v-if="hasTagSearch">
                                                        <button
                                                            v-if="canAddNewTag"
                                                            type="button"
                                                            class="btn btn-outline-success btn-sm w-100 mb-2 text-start"
                                                            @click="addNewTag"
                                                        >
                                                            {{ $t('eventForm.addTag', { tag: normalizeTagName(tagSearch) }) }}
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

                                                        <div
                                                            v-if="filteredTags.length === 0 && !canAddNewTag"
                                                            class="text-muted small px-2 py-2"
                                                        >
                                                            {{ $t('homeAudit.tags.none') }}
                                                        </div>
                                                    </template>

                                                    <button
                                                        v-if="selectedTags.length"
                                                        type="button"
                                                        class="btn btn-link btn-sm text-danger text-decoration-none px-0 mt-1"
                                                        @click="clearTags"
                                                    >
                                                        {{ $t('homeAudit.tags.clearSelected') }}
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
                                                        {{ $t('homeAudit.tags.none') }}
                                                    </div>
                                                </div>
                                            </div>

                                            <small class="text-muted d-block mt-1">
                                  {{ $t('eventForm.tagHelper') }}
                                            </small>

                                            <div v-if="props.admin" class="d-flex flex-column flex-sm-row align-items-sm-center gap-2 mt-3">
                                                <button
                                                    type="button"
                                                    class="btn btn-outline-primary rounded-pill px-4"
                                                    :disabled="aiTagsLoading || !canGenerateAiTags"
                                                    @click="generateAiTags"
                                                >
                                                    {{ aiTagsLoading ? $t("eventForm.admin.generatingTags") : $t("eventForm.admin.generateTags") }}
                                                </button>
                                                <small class="text-muted">{{ $t("eventForm.admin.generateTagsHelp") }}</small>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="mt-3">
                                        <label class="form-label fw-medium d-block mb-2">
                                            {{ tr("eventForm.selectLocationMap", "Select location on map") }}
                                        </label>

                                        <div
                                            ref="mapContainer"
                                            style="height: 350px; border-radius: 12px; border: 1px solid #dee2e6"
                                        ></div>

                                        <div class="mt-2 small text-muted" v-if="locatingUser">
                                            {{ $t('eventForm.detectingLocation') }}
                                        </div>

                                        <div class="mt-2 small text-warning" v-if="locationError">
                                            {{ locationError }}
                                        </div>

                                        <div class="mt-2 small text-muted" v-if="hasSelectedLocation">
                                            {{ tr("eventForm.selectedCoords", "Selected coordinates") }}
                                            <strong>{{ tr("eventForm.lat", "Lat") }}: {{ form.latitude.toFixed(6) }}</strong>,
                                            <strong>{{ tr("eventForm.lng", "Lng") }}: {{ form.longitude.toFixed(6) }}</strong>
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
                                            </label>
                                            <input v-model="form.start_date" type="date" class="form-control rounded-3" />
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

                        <div v-if="props.admin" class="col-12">
                            <div class="card shadow border-0 rounded-3">
                                <div class="card-body p-4">
                                    <div class="form-check form-switch d-flex align-items-start gap-3 p-0 m-0">
                                        <input
                                            id="admin-event-trending"
                                            v-model="form.is_trending"
                                            class="form-check-input ms-0 mt-1 flex-shrink-0"
                                            type="checkbox"
                                            role="switch"
                                        />
                                        <label class="form-check-label" for="admin-event-trending">
                                            <span class="d-block fw-semibold text-dark">{{ $t("eventForm.admin.trending") }}</span>
                                            <small class="text-muted">{{ $t("eventForm.admin.trendingHelp") }}</small>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-12 d-flex flex-column flex-sm-row justify-content-between gap-2 pt-3 pb-4">
                    <button
                        type="button"
                        class="btn btn-outline-secondary btn-md px-4 py-2 rounded-pill"
                        :disabled="currentPhase === 1 || loading"
                        @click="previousPhase"
                    >
                        {{ $t('common.previous') }}
                    </button>

                    <div class="d-flex flex-column flex-sm-row gap-2 ms-sm-auto">
                        <button
                            v-if="currentPhase < 2"
                            type="button"
                            class="btn btn-primary btn-md px-4 py-2 rounded-pill shadow"
                            :disabled="!currentPhaseValid"
                            @click="goNext"
                        >
                            {{ $t('common.next') }}
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

const props = defineProps({
    admin: {
        type: Boolean,
        default: false,
    },
    historical: {
        type: Boolean,
        default: false,
    },
});

const MAX_MEDIA = 8;

const { t, te } = useI18n();
const tr = (key, fallback) => (te(key) ? t(key) : fallback);

const form = ref({
    // is_real: {{ $t('eventForm.eventType') }}
    is_real: false,
    photography_type: "normal",
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
    is_trending: false,
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
const aiTagsLoading = ref(false);
const tagSearch = ref("");
const showTagsDropdown = ref(false);
const eventTagSearchInput = ref(null);

const wizardSteps = [
    { id: 1, label: "eventForm.steps.photographyPhotos", description: "eventForm.steps.chooseModeUpload" },
    { id: 2, label: "eventForm.steps.eventDetails", description: "eventForm.steps.mainFormData" },
];

const { currentPhase, goToPhase, nextPhase, previousPhase } = useFormWizard({
    totalPhases: 2,
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

const hasTagSearch = computed(() =>
    String(tagSearch.value || "").trim().length > 0
);

const filteredTags = computed(() => {
    const search = String(tagSearch.value || "").trim().toLowerCase();

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

const canGenerateAiTags = computed(() =>
    Boolean(
        form.value.title?.trim() &&
        form.value.media_items.some((item) => item.file instanceof File && item.file.type.startsWith("image/"))
    )
);

// is_real: {{ $t('eventForm.eventType') }}
const isEventTypeValid = computed(() =>
    form.value.is_real === true || form.value.is_real === false
);

const isPhotographyPhaseValid = computed(() => isEventTypeValid.value);

const isDetailsPhaseValid = computed(() => {
    const hasBasicInfo = Boolean(
        form.value.title?.trim() &&
        form.value.description?.trim()
    );

    const hasRequiredSelections = Boolean(
        selectedCountryId.value &&
        form.value.city_id &&
        selectedCategoryId.value &&
        form.value.sub_categorey_id
    );

    return hasBasicInfo && hasRequiredSelections;
});

const photosBlockingMessage = computed(() => {
    const media = form.value.media_items;

    if (media.length === 0) return t("eventForm.errors.mediaRequired");

    const invalid = media.find((item) => item.invalidFile);
    if (invalid) return invalid.errors?.join("; ") || t("photoUpload.allowedTypes");

    return "";
});

const isPhotosPhaseValid = computed(() => photosBlockingMessage.value === "");

const stepOneBlockingMessage = computed(() => {
    return photosBlockingMessage.value;
});

const currentPhaseValid = computed(() => {
    if (currentPhase.value === 1) return isPhotographyPhaseValid.value && isPhotosPhaseValid.value;
    if (currentPhase.value === 2) return isDetailsPhaseValid.value;
    return false;
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

// is_real: {{ $t('eventForm.eventType') }}
function selectEventType(isReal) {
    form.value.is_real = isReal;
}

// is_real: {{ $t('eventForm.eventType') }}
function eventTypeCardClass(isReal) {
    return form.value.is_real === isReal
        ? "border-primary bg-primary-subtle shadow-sm"
        : "bg-white";
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
    if (stepId === 2) return isPhotographyPhaseValid.value && isPhotosPhaseValid.value;
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
        alert(t("eventForm.errors.loadCities"));
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

function toggleTagsDropdown() {
    showTagsDropdown.value = !showTagsDropdown.value;

    if (showTagsDropdown.value) {
        tagSearch.value = "";

        nextTick(() => {
            eventTagSearchInput.value?.focus();
        });
    }
}

function closeTagsDropdown() {
    showTagsDropdown.value = false;
    tagSearch.value = "";
}

function isTagSelected(tagId) {
    return selectedTags.value.some(
        (tag) => !tag.isNew && String(tag.id) === String(tagId)
    );
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

function tagOptionFromName(name) {
    const normalizedName = normalizeTagName(name);

    if (!normalizedName) return null;

    const existingTag = tags.value.find(
        (tag) => normalizeTagName(tag.name).toLowerCase() === normalizedName.toLowerCase()
    );

    return existingTag
        ? { id: existingTag.id, name: existingTag.name, isNew: false }
        : { id: `ai-${Date.now()}-${Math.random()}`, name: normalizedName, isNew: true };
}

function mergeSuggestedTags(currentTags, suggestedNames, maxTags = 10) {
    const merged = Array.isArray(currentTags) ? [...currentTags] : [];

    suggestedNames.forEach((name) => {
        const option = tagOptionFromName(name);

        if (!option || merged.length >= maxTags) return;

        const exists = merged.some(
            (tag) => normalizeTagName(tag.name).toLowerCase() === option.name.toLowerCase()
        );

        if (!exists) merged.push(option);
    });

    return merged;
}

async function generateAiTags() {
    if (!props.admin || !canGenerateAiTags.value || aiTagsLoading.value) return;

    aiTagsLoading.value = true;
    const fd = new FormData();
    fd.append("title", form.value.title.trim());
    fd.append("description", form.value.description?.trim() || "");

    const currentLanguage = String(localStorage.getItem("language") || "en").toLowerCase();
    const supportedAiLanguages = ["ar", "en", "fr", "ru", "zh"];
    fd.append("language", supportedAiLanguages.includes(currentLanguage) ? currentLanguage : "en");

    form.value.media_items
        .filter((item) => item.file instanceof File && item.file.type.startsWith("image/"))
        .slice(0, 5)
        .forEach((item) => fd.append("images[]", item.file));

    try {
        const response = await TagService.generateImageTags(fd);
        const result = response?.data?.data || {};
        selectedTags.value = mergeSuggestedTags(selectedTags.value, result.event_tags || []);

        const suggestionsByImage = new Map(
            (result.images || []).map((image) => [Number(image.image_index) - 1, image.tags || []])
        );

        form.value.media_items = form.value.media_items.map((item, index) => ({
            ...item,
            tags: mergeSuggestedTags(item.tags, suggestionsByImage.get(index) || []),
        }));
    } catch (err) {
        alert(err.response?.data?.message || t("eventForm.admin.generateTagsError"));
    } finally {
        aiTagsLoading.value = false;
    }
}

function appendPhotoFields(fd, item) {
    const metrics = item.metrics || {};
    const validationMessage = item.errors?.length
        ? item.errors.join("; ")
        : item.validationMessage || "";

    fd.append("urls[]", item.file);

    /*
     * Photo description/tags may be empty for Admin-created events.
     * Always send a safe empty value instead of calling .trim()
     * on an undefined/null description.
     */
    fd.append(
        "photo_descriptions[]",
        String(item.description || "").trim()
    );

    fd.append("photo_tags_json[]", JSON.stringify({
        tags_id: Array.isArray(item.tags)
            ? item.tags
                .filter((tag) => !tag.isNew)
                .map((tag) => tag.id)
            : [],
        new_tags: Array.isArray(item.tags)
            ? item.tags
                .filter((tag) => tag.isNew)
                .map((tag) => tag.name)
            : [],
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
        return alert(photosBlockingMessage.value || t("eventForm.errors.requiredFields"));
    }

    loading.value = true;
    const fd = new FormData();

    // is_real: {{ $t('eventForm.eventType') }}
    fd.append("is_real", form.value.is_real ? "1" : "0");
    if (props.historical) fd.append("is_historical", "1");
    fd.append("photography_type", form.value.photography_type);
    fd.append("title", form.value.title);
    fd.append("description", form.value.description);
    if (form.value.city_id) fd.append("city_id", form.value.city_id);
    if (form.value.sub_categorey_id) fd.append("sub_categorey_id", form.value.sub_categorey_id);
    if (form.value.start_date) fd.append("start_date", form.value.start_date);
    if (form.value.end_date) fd.append("end_date", form.value.end_date);
    if (form.value.time) fd.append("time", form.value.time);

    if (hasSelectedLocation.value) {
        fd.append("lattitude", form.value.latitude);
        fd.append("langitude", form.value.longitude);
    }

    if (props.admin) {
        fd.append("is_trending", form.value.is_trending ? "1" : "0");
    }

    selectedTags.value.forEach((tag) => {
        if (tag.isNew) {
            fd.append("new_tags[]", tag.name);
        } else {
            fd.append("tags_id[]", tag.id);
        }
    });

    form.value.media_items.forEach((item) => appendPhotoFields(fd, item));

    try {
        const createRequest = props.admin
            ? EventService.create
            : props.historical
                ? EventService.createHistoricUser
                : EventService.createUser;

        await createRequest(fd);

        alert(t(props.admin
            ? "eventForm.admin.created"
            : props.historical
                ? "eventForm.success.historicalCreated"
                : "eventForm.success.created"));
        window.location.href = props.admin ? "/admin/events" : "/";
    } catch (err) {
        console.error(err);
        const errorKey = props.historical
            ? "eventForm.errors.historicalCreateFailed"
            : "eventForm.errors.createFailed";

        alert(t(errorKey) + " " + (err.response?.data?.message || t("common.unknownError")));
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

            locationError.value = t("eventForm.errors.locationDetect");
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

<style scoped>
.event-type-option {
    cursor: pointer;
    transition: border-color 0.2s ease, background-color 0.2s ease, box-shadow 0.2s ease, transform 0.2s ease;
}

.event-type-option:hover {
    border-color: var(--bs-primary) !important;
    box-shadow: 0 0.25rem 0.75rem rgba(var(--bs-primary-rgb), 0.08);
}

.event-type-input {
    position: absolute;
    opacity: 0;
    pointer-events: none;
}

.event-type-radio {
    width: 1.15rem;
    height: 1.15rem;
    border: 2px solid var(--bs-secondary-color);
    border-radius: 50%;
    background: var(--bs-body-bg);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    transition: border-color 0.2s ease, background-color 0.2s ease, box-shadow 0.2s ease;
}

.event-type-radio::after {
    content: "";
    width: 0.45rem;
    height: 0.45rem;
    border-radius: 50%;
    background: var(--bs-primary);
    opacity: 0;
    transform: scale(0.5);
    transition: opacity 0.2s ease, transform 0.2s ease;
}

.event-type-input:checked + .event-type-radio {
    border-color: var(--bs-primary);
    box-shadow: 0 0 0 0.2rem rgba(var(--bs-primary-rgb), 0.12);
}

.event-type-input:checked + .event-type-radio::after {
    opacity: 1;
    transform: scale(1);
}

.required-details-notice {
    border: 1px solid rgba(245, 158, 11, 0.28) !important;
    background:
        linear-gradient(135deg, rgba(255, 248, 230, 0.98), rgba(255, 252, 242, 0.98));
    box-shadow: 0 10px 28px rgba(146, 94, 12, 0.08) !important;
}

.required-details-notice ul {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 0.35rem 1.5rem;
}

@media (max-width: 575.98px) {
    .required-details-notice ul {
        grid-template-columns: 1fr;
    }
}

.create-event-page {
    background:
        radial-gradient(circle at top left, rgba(48, 168, 255, 0.12), transparent 32rem),
        linear-gradient(180deg, #FFFFFF, #F8FAFC);
}

.create-event-page .container {
    max-width: 1280px;
}

.create-event-page h1 {
    color: #06142A !important;
    letter-spacing: 0;
}

.create-event-page .card {
    border: 1px solid #E5EDF6 !important;
    border-radius: 24px !important;
    box-shadow: 0 10px 35px rgba(13, 77, 151, 0.06) !important;
}

.create-event-page .btn {
    border-radius: 16px !important;
}

.create-event-page .btn-primary,
.create-event-page button[type="submit"] {
    border: 0 !important;
    background: linear-gradient(135deg, #0D4D97, #1677FF) !important;
    box-shadow: 0 14px 30px rgba(22, 119, 255, 0.18);
}

.create-event-page .form-control,
.create-event-page .form-select {
    min-height: 46px;
    border-color: #DCE8F5;
    border-radius: 14px;
}

.create-event-page .form-control:focus,
.create-event-page .form-select:focus {
    border-color: #1677FF;
    box-shadow: 0 0 0 4px rgba(22, 119, 255, 0.10);
}

.event-type-option {
    border-color: #DCE8F5 !important;
    border-radius: 18px !important;
}

.event-type-option:hover {
    border-color: #1677FF !important;
    background: #F4F8FC;
    box-shadow: 0 10px 30px rgba(13, 77, 151, 0.08);
}

.event-type-input:checked + .event-type-radio {
    border-color: #1677FF;
    box-shadow: 0 0 0 0.22rem rgba(22, 119, 255, 0.12);
}

.event-type-radio::after {
    background: #1677FF;
}
</style>
