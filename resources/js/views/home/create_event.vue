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
                            </div>

                            <div class="mt-3">
                                <label class="form-label fw-medium d-block mb-2">
                                    {{ $t('eventForm.selectLocationMap') }} <span class="text-danger">*</span>
                                </label>

                                <div ref="mapContainer"
                                    style="height: 350px; border-radius: 12px; border: 1px solid #dee2e6">
                                </div>

                                <div class="mt-2 small text-muted" v-if="form.latitude && form.longitude">
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
                                <div v-if="form.media_previews.length === 0" class="py-4">
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
                                        <div v-for="(preview, index) in form.media_previews" :key="index" class="col">
                                            <div class="position-relative">
                                                <!-- صورة -->
                                                <img v-if="isImage(preview)" :src="preview" :alt="'media ' + index"
                                                    class="img-fluid rounded-3 shadow"
                                                    style="height: 140px; object-fit: cover; width: 100%" />
                                                <!-- فيديو -->
                                                <video v-else :src="preview" class="rounded-3 shadow w-100"
                                                    style="height: 140px; object-fit: cover;" controls muted></video>

                                                <button type="button" @click="removeMedia(index)"
                                                    class="btn btn-sm btn-danger position-absolute top-0 end-0 m-1 rounded-circle shadow-sm"
                                                    :title="$t('eventForm.remove')"
                                                    style="width: 28px; height: 28px; line-height: 1; font-size: 1.1rem; padding: 0;">
                                                    ×
                                                </button>

                                                <!-- علامة فيديو -->
                                                <span v-if="!isImage(preview)"
                                                    class="position-absolute bottom-0 start-50 translate-middle-x bg-dark text-white px-2 py-1 rounded-top small fw-bold"
                                                    style="font-size: 0.75rem;">
                                                    فيديو
                                                </span>
                                            </div>
                                        </div>
                                    </div>

                                    <button v-if="form.media_files.length < MAX_MEDIA" type="button"
                                        @click="$refs.fileInput.click()" class="btn btn-outline-primary btn-sm px-4">
                                        {{ $t('eventForm.addMore') }}
                                    </button>

                                    <small class="text-muted d-block mt-2">
                                        {{ $t('eventForm.mediaCount', { count: form.media_files.length }) }} / {{
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
                    <button type="submit" :disabled="loading || !form.latitude || !form.longitude"
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
import axios from "axios";
import maplibregl from "maplibre-gl";
import "maplibre-gl/dist/maplibre-gl.css";

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
const zoom = ref(6);
const center = ref([30.0444, 31.2357]);
const mapRef = ref(null);
const countrySearch = ref("");

axios.interceptors.request.use(
    (config) => {
        const lang = localStorage.getItem("language") || "ar";
        config.headers["Accept-Language"] = lang;
        return config;
    },
    (error) => Promise.reject(error)
);

onMounted(async () => {
    await Promise.all([fetchCountries(), fetchCategories()]);
    nextTick(() => {
        if (mapRef.value?.leafletObject) {
            mapRef.value.leafletObject.invalidateSize();
        }
    });
});

const filteredCountries = computed(() => {
    if (!countrySearch.value) return countries.value;
    return countries.value.filter(c =>
        c.translation.name.toLowerCase().includes(countrySearch.value.toLowerCase())
    );
});

onUnmounted(() => {
    if (mapRef.value?.leafletObject) {
        mapRef.value.leafletObject.remove();
    }
});

function onMapClick(e) {
    form.value.latitude = e.latlng.lat;
    form.value.longitude = e.latlng.lng;
}

async function fetchCountries() {
    try {
        const res = await axios.get("/v1/countries");
        countries.value = res.data.data || [];
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
        const res = await axios.get(`/v1/countries/${selectedCountryId.value}`);
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
        const res = await axios.get("/v1/categories");
        categories.value = res.data.data || [];
    } catch (err) {
        console.error("فشل تحميل الفئات", err);
    }
}

async function loadSubCategories() {
    subCategories.value = [];
    form.value.sub_categorey_id = "";
    if (!selectedCategoryId.value) return;
    try {
        const res = await axios.get(`/v1/categories/${selectedCategoryId.value}`);
        subCategories.value = res.data.data?.sub_categories || [];
    } catch (err) {
        console.error("فشل تحميل التصنيفات الفرعية", err);
    }
}

function handleMediaSelect(e) {
    const files = Array.from(e.target.files || []);
    processMedia(files);
}

function handleMediaDrop(e) {
    const files = Array.from(e.dataTransfer.files || []);
    processMedia(files);
}

function processMedia(newFiles) {
    const currentCount = form.value.media_files.length;
    const canAdd = MAX_MEDIA - currentCount;

    if (newFiles.length > canAdd) {
        alert(`يمكنك إضافة ${canAdd} ملف${canAdd === 1 ? "" : "ات"} فقط`);
        newFiles = newFiles.slice(0, canAdd);
    }

    newFiles.forEach((file) => {
        if (file.size > 5 * 1024 * 1024) {
            alert(`حجم الملف ${file.name} يتجاوز 5 ميجا`);
            return;
        }
        const allowedTypes = [
            "image/png", "image/jpeg", "image/webp",
            "video/mp4", "video/webm", "video/ogg"
        ];
        if (!allowedTypes.includes(file.type)) {
            alert(`نوع الملف ${file.name} غير مدعوم`);
            return;
        }

        form.value.media_files.push(file);

        const reader = new FileReader();
        reader.onload = (ev) => {
            form.value.media_previews.push(ev.target.result);
        };
        reader.readAsDataURL(file);
    });
    if (fileInput.value) fileInput.value.value = "";
}

function removeMedia(index) {
    form.value.media_files.splice(index, 1);
    form.value.media_previews.splice(index, 1);
}

function isImage(previewUrl) {
    return previewUrl.startsWith("data:image/");
}

async function createEvent() {
    if (
        !form.value.title?.trim() ||
        !form.value.description?.trim() ||
        !form.value.city_id ||
        !form.value.sub_categorey_id ||
        !form.value.start_date ||
        !form.value.latitude ||
        !form.value.longitude
    ) {
        return alert("برجاء ملء جميع الحقول المطلوبة (بما فيها الموقع على الخريطة)");
    }

    if (form.value.media_files.length === 0) {
        return alert("يرجى رفع صورة أو فيديو واحد على الأقل");
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
    form.value.media_files.forEach((file) => {
        fd.append("urls[]", file);
    });

    try {
        await axios.post("/v1/events/create/user", fd, {
            headers: { "Content-Type": "multipart/form-data" },
        });

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

onMounted(() => {
    initMap();
});

function initMap() {
    const lang = localStorage.getItem("language") || "ar";
    const isAr = lang === "ar";

    map = new maplibregl.Map({
        container: mapContainer.value,
        style: STYLE_URL,
        center: [31.2357, 30.0444],
        zoom: 6,
    });

    map.addControl(new maplibregl.NavigationControl());

    map.on("load", () => {
        patchLanguage(map, isAr);
    });

    map.on("click", (e) => {
        const { lat, lng } = e.lngLat;

        form.value.latitude = lat;
        form.value.longitude = lng;

        if (!marker) {
            marker = new maplibregl.Marker({ color: "#e53e3e" })
                .setLngLat([lng, lat])
                .addTo(map);
        } else {
            marker.setLngLat([lng, lat]);
        }
    });
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
