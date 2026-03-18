<template>
    <div class="min-vh-100 bg-light py-4">
        <div class="container px-3 px-md-4">

            <div class="mb-4">
                <h1 class="display-6 fw-bold text-dark">
                    {{ $t('eventForm.title') }}
                </h1>
            </div>

            <form @submit.prevent="createEvent" class="row g-3 g-md-4">

                <!-- ================= BASIC INFO ================= -->
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

                                    <input v-model="form.title" type="text" class="form-control rounded-3"
                                        :placeholder="$t('eventForm.eventTitle') + ' ' + $t('commons.choose')"
                                        required />
                                </div>

                                <div class="col-12">
                                    <label class="form-label fw-medium">
                                        {{ $t('eventForm.description') }} <span class="text-danger">*</span>
                                    </label>

                                    <textarea v-model="form.description" class="form-control rounded-3" rows="5"
                                        style="min-height: 120px"
                                        :placeholder="$t('eventForm.description') + '، ' + $t('eventForm.basicInfo').toLowerCase() + '، ' + $t('eventForm.locationCategory').toLowerCase() + '...'"
                                        required></textarea>
                                </div>
                            </div>

                        </div>
                    </div>
                </div>

                <!-- ================= LOCATION ================= -->
                <div class="col-12">
                    <div class="card shadow border-0 rounded-3">
                        <div class="card-body p-4">

                            <h2 class="card-title h4 fw-bold mb-3 d-flex align-items-center gap-2">
                                <span class="text-primary fs-3 fw-bolder">②</span>
                                {{ $t('eventForm.locationCategory') }}
                            </h2>

                            <div class="row g-3 mb-4">

                                <!-- Country -->
                                <div class="col-12 col-sm-6 col-lg-3">
                                    <label class="form-label fw-medium">
                                        {{ $t('eventForm.country') }} <span class="text-danger">*</span>
                                    </label>

                                    <input v-model="countrySearch" type="text" class="form-control rounded-3 mb-2"
                                        :placeholder="$t('eventForm.searchCountry')" />

                                    <select v-model="selectedCountryId" @change="loadCities"
                                        class="form-select rounded-3" required>
                                        <option value="" disabled>{{ $t('eventForm.selectCountry') }}</option>
                                        <option v-for="c in filteredCountries" :key="c.id" :value="c.id">
                                            {{ c.translation.name }}
                                        </option>
                                    </select>
                                </div>

                                <!-- City -->
                                <div class="col-12 col-sm-6 col-lg-3">
                                    <label class="form-label fw-medium">
                                        {{ $t('eventForm.city') }} <span class="text-danger">*</span>
                                    </label>

                                    <select v-model="form.city_id" :disabled="!selectedCountryId || cities.length === 0"
                                        class="form-select rounded-3" required>
                                        <option value="" disabled>
                                            {{ selectedCountryId ? $t('eventForm.selectCity') :
                                                $t('eventForm.selectCityFirst') }}
                                        </option>

                                        <option v-for="city in cities" :key="city.id" :value="city.id">
                                            {{ city.translation.name }}
                                        </option>
                                    </select>
                                </div>

                                <!-- Category -->
                                <div class="col-12 col-sm-6 col-lg-3">
                                    <label class="form-label fw-medium">
                                        {{ $t('eventForm.mainCategory') }} <span class="text-danger">*</span>
                                    </label>

                                    <select v-model="selectedCategoryId" @change="loadSubCategories"
                                        class="form-select rounded-3" required>
                                        <option value="" disabled>{{ $t('commons.choose') }}</option>

                                        <option v-for="cat in categories" :key="cat.id" :value="cat.id">
                                            {{ cat.translation.name }}
                                        </option>
                                    </select>
                                </div>

                                <!-- Sub Category -->
                                <div class="col-12 col-sm-6 col-lg-3">
                                    <label class="form-label fw-medium">
                                        {{ $t('eventForm.subCategory') }} <span class="text-danger">*</span>
                                    </label>

                                    <select v-model="form.sub_categorey_id"
                                        :disabled="!selectedCategoryId || subCategories.length === 0"
                                        class="form-select rounded-3" required>
                                        <option value="" disabled>{{ $t('eventForm.selectSubFirst') }}</option>

                                        <option v-for="sub in subCategories" :key="sub.id" :value="sub.id">
                                            {{ sub.translation.name }}
                                        </option>
                                    </select>
                                </div>
                            </div>

                            <!-- MAP -->
                            <div class="mt-3">
                                <label class="form-label fw-medium d-block mb-2">
                                    {{ $t('eventForm.selectLocationMap') }} <span class="text-danger">*</span>
                                </label>

                                <div ref="mapContainer"
                                    style="height: 350px; border-radius: 12px; border: 1px solid #dee2e6"></div>

                                <div class="mt-2 small text-muted" v-if="form.latitude && form.longitude">
                                    <strong>Lat:</strong> {{ form.latitude.toFixed(6) }} ,
                                    <strong>Lng:</strong> {{ form.longitude.toFixed(6) }}
                                </div>

                                <div v-else class="mt-2 small text-danger">
                                    {{ $t('eventForm.pleaseSelectLocation') }}
                                </div>
                            </div>

                        </div>
                    </div>
                </div>

                <!-- ================= DATES ================= -->
                <div class="col-12">
                    <div class="card shadow border-0 rounded-3">
                        <div class="card-body p-4">

                            <h2 class="card-title h4 fw-bold mb-3">
                                {{ $t('eventForm.dates') }}
                            </h2>

                            <div class="row g-3">
                                <div class="col-12 col-sm-4">
                                    <input v-model="form.start_date" type="date" class="form-control" required />
                                </div>

                                <div class="col-12 col-sm-4">
                                    <input v-model="form.end_date" type="date" class="form-control" />
                                </div>

                                <div class="col-12 col-sm-4">
                                    <input v-model="form.time" type="time" class="form-control" />
                                </div>
                            </div>

                        </div>
                    </div>
                </div>

                <!-- ================= MEDIA ================= -->
                <div class="col-12">
                    <div class="card shadow border-0 rounded-3">
                        <div class="card-body p-4">

                            <h2 class="h4 fw-bold mb-3">
                                {{ $t('eventForm.media') || 'صور وفيديوهات الحدث' }}
                            </h2>

                            <div class="text-center p-4 border rounded-3">

                                <p class="text-muted">
                                    {{ $t('eventForm.mediaFormatsMax') || 'PNG, JPG, WEBP, MP4 (الحد الأقصى 8 ملفات)' }}
                                </p>

                                <input ref="fileInput" type="file" multiple hidden @change="handleMediaSelect" />

                                <button type="button" @click="$refs.fileInput.click()" class="btn btn-primary">
                                    اختر ملفات
                                </button>

                            </div>

                        </div>
                    </div>
                </div>

                <!-- ================= BUTTONS ================= -->
                <div class="col-12 d-flex justify-content-end gap-2">
                    <button type="submit" class="btn btn-primary">
                        {{ $t('commons.create') }}
                    </button>
                </div>

            </form>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from "vue";
import axios from "axios";
import maplibregl from "maplibre-gl";
import "maplibre-gl/dist/maplibre-gl.css";

/* ================== STATE ================== */

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
const countrySearch = ref("");

/* ================== MAP ================== */

const mapContainer = ref(null);
let map = null;
let marker = null;

const STYLE_URL = "https://tiles.openfreemap.org/styles/liberty";

/* ================== LIFECYCLE ================== */

onMounted(async () => {
    await Promise.all([fetchCountries(), fetchCategories()]);
    initMap();
});

onUnmounted(() => {
    if (map) map.remove();
});

/* ================== COMPUTED ================== */

const filteredCountries = computed(() => {
    if (!countrySearch.value) return countries.value;

    return countries.value.filter((c) =>
        c.translation.name
            .toLowerCase()
            .includes(countrySearch.value.toLowerCase())
    );
});

/* ================== AXIOS ================== */

axios.interceptors.request.use((config) => {
    const lang = localStorage.getItem("language") || "ar";
    config.headers["Accept-Language"] = lang;
    return config;
});

/* ================== MAP FUNCTIONS ================== */

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
        patchLanguage(isAr);
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

function patchLanguage(isAr) {
    const style = map.getStyle();
    if (!style?.layers) return;

    const langField = isAr ? "name:ar" : "name:en";
    const nameExpr = ["coalesce", ["get", langField], ["get", "name"]];

    style.layers.forEach((layer) => {
        if (layer.type !== "symbol") return;
        if (!layer.layout?.["text-field"]) return;

        map.setLayoutProperty(layer.id, "text-field", nameExpr);

        if (isAr) {
            map.setLayoutProperty(layer.id, "text-writing-mode", ["horizontal"]);
        }
    });
}

/* ================== API ================== */

async function fetchCountries() {
    try {
        const res = await axios.get("/v1/countries");
        countries.value = res.data.data || [];
    } catch (err) {
        console.error("فشل تحميل الدول", err);
    }
}

async function loadCities() {
    cities.value = [];
    form.value.city_id = "";

    if (!selectedCountryId.value) return;

    try {
        const res = await axios.get(`/v1/countries/${selectedCountryId.value}`);
        cities.value = res.data.data?.country?.cities || [];
    } catch (err) {
        console.error("فشل تحميل المدن", err);
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

/* ================== MEDIA ================== */

const MAX_MEDIA = 8;

function isVideo(previewUrl) {
    return previewUrl.startsWith("data:video/");
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
        alert(`يمكنك إضافة ${canAdd} ملف فقط`);
        newFiles = newFiles.slice(0, canAdd);
    }

    newFiles.forEach((file) => {
        const maxSize = file.type.startsWith("video/")
            ? 50 * 1024 * 1024
            : 5 * 1024 * 1024;

        if (file.size > maxSize) {
            alert(`حجم الملف ${file.name} كبير جدًا`);
            return;
        }

        const allowedTypes = [
            "image/png",
            "image/jpeg",
            "image/webp",
            "video/mp4",
            "video/quicktime",
            "video/x-m4v",
        ];

        if (!allowedTypes.includes(file.type)) {
            alert(`نوع الملف غير مدعوم`);
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

/* ================== SUBMIT ================== */

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
        return alert("برجاء ملء جميع الحقول المطلوبة");
    }

    if (form.value.media_files.length === 0) {
        return alert("ارفع صورة أو فيديو واحد على الأقل");
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
        await axios.post("/v1/events/historic/user", fd, {
            headers: { "Content-Type": "multipart/form-data" },
        });

        alert("تم إنشاء الحدث بنجاح!");
        window.location.href = "/";
    } catch (err) {
        console.error(err);
        alert("فشل إنشاء الحدث");
    } finally {
        loading.value = false;
    }
}
</script>
