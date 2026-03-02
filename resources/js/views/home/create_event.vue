<template>
    <div class="min-vh-100 bg-light py-4">
        <div class="container px-3 px-md-4">
            <!-- العنوان -->
            <div class="mb-4">
                <h1 class="display-6 fw-bold text-dark">إضافة حدث جديد</h1>
            </div>

            <form @submit.prevent="createEvent" class="row g-3 g-md-4">
                <!-- 1. المعلومات الأساسية -->
                <div class="col-12">
                    <div class="card shadow border-0 rounded-3">
                        <div class="card-body p-4">
                            <h2 class="card-title h4 fw-bold mb-3 d-flex align-items-center gap-2">
                                <span class="text-primary fs-3 fw-bolder">①</span>
                                المعلومات الأساسية
                            </h2>

                            <div class="row g-3">
                                <div class="col-12">
                                    <label class="form-label fw-medium">
                                        عنوان الحدث <span class="text-danger">*</span>
                                    </label>
                                    <input v-model="form.title" type="text"
                                        class="form-control form-control-md rounded-3"
                                        placeholder="مثال: رحلة نيلية ممتعة" required />
                                </div>

                                <div class="col-12">
                                    <label class="form-label fw-medium">
                                        الوصف <span class="text-danger">*</span>
                                    </label>
                                    <textarea v-model="form.description" class="form-control rounded-3" rows="5"
                                        placeholder="تفاصيل الحدث، الفعاليات، المكان، الأسعار..." required
                                        style="min-height: 120px"></textarea>
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
                                الموقع والتصنيف
                            </h2>

                            <div class="row g-3 mb-4">
                                <div class="col-12 col-sm-6 col-lg-3">
                                    <label class="form-label fw-medium">الدولة <span
                                            class="text-danger">*</span></label>
                                    <!-- حقل البحث -->
                                    <input v-model="countrySearch" type="text"
                                        class="form-control form-control-md rounded-3 mb-2"
                                        placeholder="ابحث عن دولة..." />

                                    <select v-model="selectedCountryId" @change="loadCities"
                                        class="form-select form-select-md rounded-3" required>
                                        <option value="" disabled>اختر الدولة</option>
                                        <option v-for="c in filteredCountries" :key="c.id" :value="c.id">
                                            {{ c.translation.name }}
                                        </option>
                                    </select>
                                </div>

                                <div class="col-12 col-sm-6 col-lg-3">
                                    <label class="form-label fw-medium">المدينة <span
                                            class="text-danger">*</span></label>
                                    <select v-model="form.city_id" :disabled="!selectedCountryId || cities.length === 0"
                                        class="form-select form-select-md rounded-3" required>
                                        <option value="" disabled>
                                            {{ selectedCountryId ? "اختر المدينة" : "اختر الدولة أولاً" }}
                                        </option>
                                        <option v-for="city in cities" :key="city.id" :value="city.id">
                                            {{ city.name }}
                                        </option>
                                    </select>
                                </div>

                                <div class="col-12 col-sm-6 col-lg-3">
                                    <label class="form-label fw-medium">الفئة الرئيسية <span
                                            class="text-danger">*</span></label>
                                    <select v-model="selectedCategoryId" @change="loadSubCategories"
                                        class="form-select form-select-md rounded-3" required>
                                        <option value="" disabled>اختر الفئة</option>
                                        <option v-for="cat in categories" :key="cat.id" :value="cat.id">
                                            {{ cat.name }}
                                        </option>
                                    </select>
                                </div>

                                <div class="col-12 col-sm-6 col-lg-3">
                                    <label class="form-label fw-medium">التصنيف الفرعي <span
                                            class="text-danger">*</span></label>
                                    <select v-model="form.sub_categorey_id"
                                        :disabled="!selectedCategoryId || subCategories.length === 0"
                                        class="form-select form-select-md rounded-3" required>
                                        <option value="" disabled>
                                            {{
                                                selectedCategoryId ? "اختر التصنيف الفرعي" : "اختر الفئة أولاً"
                                            }}
                                        </option>
                                        <option v-for="sub in subCategories" :key="sub.id" :value="sub.id">
                                            {{ sub.name }}
                                        </option>
                                    </select>
                                </div>
                            </div>

                            <!-- الخريطة -->
                            <div class="mt-3">
                                <label class="form-label fw-medium d-block mb-2">
                                    اختر الموقع على الخريطة <span class="text-danger">*</span>
                                </label>

                                <l-map ref="mapRef" :zoom="zoom" :center="center"
                                    style="height: 350px; border-radius: 12px; border: 1px solid #dee2e6"
                                    @click="onMapClick">
                                    <l-tile-layer url="https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png"
                                        attribution='© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>' />

                                    <l-marker v-if="form.latitude && form.longitude"
                                        :lat-lng="[form.latitude, form.longitude]">
                                        <l-tooltip :permanent="true" direction="top">
                                            الموقع المختار
                                        </l-tooltip>
                                    </l-marker>
                                </l-map>

                                <div class="mt-2 small text-muted" v-if="form.latitude && form.longitude">
                                    الإحداثيات المختارة:
                                    <strong>Lat: {{ form.latitude.toFixed(6) }}</strong> ,
                                    <strong>Lng: {{ form.longitude.toFixed(6) }}</strong>
                                </div>
                                <div v-else class="mt-2 small text-danger">
                                    من فضلك اختر موقعاً على الخريطة
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
                                المواعيد
                            </h2>

                            <div class="row g-3">
                                <div class="col-12 col-sm-4">
                                    <label class="form-label fw-medium">تاريخ البداية <span
                                            class="text-danger">*</span></label>
                                    <input v-model="form.start_date" type="date" class="form-control rounded-3"
                                        required />
                                </div>

                                <div class="col-12 col-sm-4">
                                    <label class="form-label fw-medium">تاريخ النهاية</label>
                                    <input v-model="form.end_date" type="date" class="form-control rounded-3" />
                                </div>

                                <div class="col-12 col-sm-4">
                                    <label class="form-label fw-medium">وقت البداية</label>
                                    <input v-model="form.time" type="time" class="form-control rounded-3" />
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 4. صور الحدث (متعددة) -->
                <div class="col-12">
                    <div class="card shadow border-0 rounded-3">
                        <div class="card-body p-4">
                            <h2 class="card-title h4 fw-bold mb-3 d-flex align-items-center gap-2">
                                <span class="text-primary fs-3 fw-bolder">④</span>
                                صور الحدث
                            </h2>

                            <div @dragover.prevent @drop.prevent="handleImageDrop"
                                class="border border-2 border-dashed border-secondary-subtle rounded-3 p-4 text-center bg-body-tertiary"
                                style="min-height: 220px">
                                <input ref="fileInput" type="file" accept="image/png,image/jpeg,image/webp" multiple
                                    hidden @change="handleImageSelect" />

                                <!-- حالة بدون صور -->
                                <div v-if="form.url_previews.length === 0" class="py-4">
                                    <div class="mx-auto mb-3 bg-primary-subtle rounded-circle d-flex align-items-center justify-content-center"
                                        style="width: 70px; height: 70px">
                                        <svg class="text-primary" width="36" height="36" fill="none"
                                            stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                                        </svg>
                                    </div>
                                    <p class="fs-5 fw-medium text-secondary mb-2">اضغط أو اسحب الصور هنا</p>
                                    <p class="text-muted small mb-3">PNG • JPG • WEBP | max 5MB لكل صورة</p>
                                    <button type="button" @click="$refs.fileInput.click()"
                                        class="btn btn-primary btn-md px-4 py-2 rounded-pill">
                                        اختيار صور
                                    </button>
                                </div>

                                <!-- عرض الصور بعد الرفع -->
                                <div v-else class="py-3">
                                    <div class="row row-cols-2 row-cols-sm-3 row-cols-md-4 g-3 mb-3">
                                        <div v-for="(preview, index) in form.url_previews" :key="index" class="col">
                                            <div class="position-relative">
                                                <img :src="preview" alt="معاينة" class="img-fluid rounded-3 shadow"
                                                    style="height: 140px; object-fit: cover; width: 100%" />
                                                <button type="button" @click="removeImage(index)"
                                                    class="btn btn-sm btn-danger position-absolute top-0 end-0 m-1 rounded-circle shadow-sm"
                                                    style="
                            width: 28px;
                            height: 28px;
                            line-height: 1;
                            font-size: 1.1rem;
                            padding: 0;
                          ">
                                                    ×
                                                </button>
                                            </div>
                                        </div>
                                    </div>

                                    <button v-if="form.urls.length < 8" type="button" @click="$refs.fileInput.click()"
                                        class="btn btn-outline-primary btn-sm px-4">
                                        إضافة المزيد
                                    </button>

                                    <small class="text-muted d-block mt-2">
                                        {{ form.urls.length }} / 8 صور
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- الأزرار -->
                <div class="col-12 d-flex flex-column flex-sm-row justify-content-end gap-2 pt-3 pb-4">
                    <button type="button" class="btn btn-outline-secondary btn-md px-4 py-2 rounded-pill">
                        إلغاء
                    </button>
                    <button type="button" class="btn btn-outline-secondary btn-md px-4 py-2 rounded-pill">
                        حفظ كمسودة
                    </button>
                    <button type="submit" :disabled="loading || !form.latitude || !form.longitude"
                        class="btn btn-primary btn-md px-4 py-2 rounded-pill shadow">
                        {{ loading ? "جاري الإنشاء..." : "إنشاء الحدث" }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted, nextTick } from "vue";
import { computed } from "vue";
import axios from "axios";
import "leaflet/dist/leaflet.css";
import { LMap, LTileLayer, LMarker, LTooltip } from "@vue-leaflet/vue-leaflet";

const form = ref({
    title: "",
    description: "",
    city_id: "",
    sub_categorey_id: "",
    start_date: "",
    end_date: "",
    time: "",
    urls: [],
    url_previews: [],
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

const MAX_IMAGES = 8;

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

    return countries.value.filter((c) =>
        c.translation.name
            .toLowerCase()
            .includes(countrySearch.value.toLowerCase())
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
    cities.value = [];
    form.value.city_id = "";

    if (!selectedCountryId.value) return;

    try {
        const res = await axios.get(`/v1/countries/${selectedCountryId.value}`);
        cities.value = res.data.data?.countries?.cities || [];
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

function handleImageSelect(e) {
    const files = Array.from(e.target.files || []);
    processImages(files);
}

function handleImageDrop(e) {
    const files = Array.from(e.dataTransfer.files || []);
    processImages(files);
}

function processImages(newFiles) {
    const currentCount = form.value.urls.length;
    const canAdd = MAX_IMAGES - currentCount;

    if (newFiles.length > canAdd) {
        alert(`يمكنك إضافة ${canAdd} صور${canAdd === 1 ? "ة" : ""} فقط`);
        newFiles = newFiles.slice(0, canAdd);
    }

    newFiles.forEach((file) => {
        if (file.size > 5 * 1024 * 1024) {
            alert(`حجم الصورة ${file.name} يتجاوز 5 ميجا`);
            return;
        }
        if (!["image/png", "image/jpeg", "image/webp"].includes(file.type)) {
            alert(`نوع الملف ${file.name} غير مدعوم (PNG, JPG, WEBP فقط)`);
            return;
        }

        form.value.urls.push(file);

        const reader = new FileReader();
        reader.onload = (ev) => {
            form.value.url_previews.push(ev.target.result);
        };
        reader.readAsDataURL(file);
    });

    if (fileInput.value) fileInput.value.value = "";
}

function removeImage(index) {
    form.value.urls.splice(index, 1);
    form.value.url_previews.splice(index, 1);
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

    if (form.value.urls.length === 0) {
        return alert("يرجى رفع صورة واحدة على الأقل");
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

    form.value.urls.forEach((file) => {
        fd.append("urls[]", file);
    });

    for (let [key, value] of fd.entries()) {
        if (value instanceof File) {
        } else {
        }
    }

    try {
        await axios.post("/v1/events/create/user", fd, {
            headers: {
                "Content-Type": "multipart/form-data",
                Authorization: `Bearer ${localStorage.getItem("auth_token")}`,
                Accept: "application/json",
            },
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
</script>
