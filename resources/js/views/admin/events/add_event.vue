<template>
  <AdminLayout>
    <div class="p-6 max-w-4xl mx-auto">
      <form @submit.prevent="createEvent" class="space-y-8">
        <!-- 1. المعلومات الأساسية -->
        <section class="bg-white shadow-md rounded-xl p-7 border border-gray-100">
          <h2 class="text-2xl font-semibold mb-7 flex items-center gap-3 text-gray-800">
            <span class="text-blue-600 text-3xl">①</span> المعلومات الأساسية
          </h2>

          <div class="grid grid-cols-1 gap-7">
            <div>
              <label class="block text-base font-medium text-gray-800 mb-2">
                عنوان الحدث <span class="text-red-600">*</span>
              </label>
              <input
                v-model="form.title"
                type="text"
                placeholder="مثال: رحلة نيلية ممتعة"
                class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 py-3 px-4 text-base"
                required
              />
            </div>

            <div>
              <label class="block text-base font-medium text-gray-800 mb-2">
                الوصف <span class="text-red-600">*</span>
              </label>
              <textarea
                v-model="form.description"
                rows="5"
                placeholder="اكتب تفاصيل الحدث، الفعاليات، المكان، الأسعار إن وجدت، وما يمكن توقعه..."
                class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 py-3 px-4 text-base"
                required
              ></textarea>
            </div>
          </div>
        </section>

        <!-- 2. الموقع والتصنيف + الخريطة -->
        <section class="bg-white shadow-md rounded-xl p-7 border border-gray-100">
          <h2 class="text-2xl font-semibold mb-7 flex items-center gap-3 text-gray-800">
            <span class="text-blue-600 text-3xl">②</span> الموقع والتصنيف
          </h2>

          <!-- رسائل الخطأ العامة لهذا القسم -->
          <div v-if="errors.general" class="mb-6 p-4 bg-red-50 border border-red-200 text-red-700 rounded-lg">
            {{ errors.general }}
          </div>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-7">
            <!-- الدولة -->
            <div>
              <label class="block text-base font-medium text-gray-800 mb-2">
                الدولة <span class="text-red-600">*</span>
              </label>
              <input
                v-model="countrySearch"
                type="text"
                placeholder="ابحث عن دولة..."
                class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 py-3 px-4 text-base mb-2"
                @input="filterCountries"
              />

              <div v-if="errors.countries" class="mb-2 p-3 bg-red-50 border border-red-200 text-red-600 text-sm rounded">
                {{ errors.countries }}
              </div>

              <select
                v-model="selectedCountryId"
                @change="loadCities"
                size="6"
                class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-base h-48 overflow-y-auto"
                required
              >
                <option value="" disabled>اختر الدولة</option>
                <option v-for="c in filteredCountries" :key="c.id" :value="c.id">
                  {{ c.name }}
                </option>
              </select>
            </div>

            <!-- المدينة -->
            <div>
              <label class="block text-base font-medium text-gray-800 mb-2">
                المدينة <span class="text-red-600">*</span>
              </label>
              <input
                v-model="citySearch"
                type="text"
                placeholder="ابحث عن مدينة..."
                class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 py-3 px-4 text-base mb-2"
                :disabled="!selectedCountryId || cities.length === 0"
                @input="filterCities"
              />

              <div v-if="errors.cities" class="mb-2 p-3 bg-red-50 border border-red-200 text-red-600 text-sm rounded">
                {{ errors.cities }}
              </div>

              <select
                v-model="form.city_id"
                size="6"
                class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-base h-48 overflow-y-auto"
                :class="{ 'disabled:bg-gray-100 disabled:text-gray-400': !selectedCountryId || cities.length === 0 }"
                :disabled="!selectedCountryId || cities.length === 0"
                required
              >
                <option value="" disabled>
                  {{ selectedCountryId ? "اختر المدينة" : "اختر الدولة أولاً" }}
                </option>
                <option v-for="city in filteredCities" :key="city.id" :value="city.id">
                  {{ city.name }}
                </option>
              </select>
            </div>

            <!-- الفئة الرئيسية -->
            <div>
              <label class="block text-base font-medium text-gray-800 mb-2">
                الفئة الرئيسية <span class="text-red-600">*</span>
              </label>

              <div v-if="errors.categories" class="mb-2 p-3 bg-red-50 border border-red-200 text-red-600 text-sm rounded">
                {{ errors.categories }}
              </div>

              <select
                v-model="selectedCategoryId"
                @change="loadSubCategories"
                class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 py-3 px-4 text-base"
                required
              >
                <option value="" disabled>اختر الفئة</option>
                <option v-for="cat in categories" :key="cat.id" :value="cat.id">
                  {{ cat.name }}
                </option>
              </select>
            </div>

            <!-- التصنيف الفرعي -->
            <div>
              <label class="block text-base font-medium text-gray-800 mb-2">
                التصنيف الفرعي <span class="text-red-600">*</span>
              </label>

              <div v-if="errors.subCategories" class="mb-2 p-3 bg-red-50 border border-red-200 text-red-600 text-sm rounded">
                {{ errors.subCategories }}
              </div>

              <select
                v-model="form.sub_category_id"
                :disabled="!selectedCategoryId || subCategories.length === 0"
                class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 py-3 px-4 text-base disabled:bg-gray-100 disabled:text-gray-400"
                required
              >
                <option value="" disabled>
                  {{ selectedCategoryId ? "اختر التصنيف الفرعي" : "اختر الفئة أولاً" }}
                </option>
                <option v-for="sub in subCategories" :key="sub.id" :value="sub.id">
                  {{ sub.name }}
                </option>
              </select>
            </div>
          </div>

          <!-- الخريطة -->
          <div class="mt-10">
            <label class="block text-base font-medium text-gray-800 mb-3">
              حدد الموقع الدقيق على الخريطة (اختياري)
            </label>
            <div
              class="border border-gray-300 rounded-lg overflow-hidden"
              style="height: 400px"
            >
              <l-map
                ref="map"
                :zoom="zoom"
                :center="center"
                @click="onMapClick"
                style="height: 100%"
              >
                <l-tile-layer
                  url="https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png"
                  attribution='© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
                />
                <l-marker v-if="markerPosition" :lat-lng="markerPosition" />
              </l-map>
            </div>
            <p v-if="form.lattitude && form.langitude" class="mt-2 text-sm text-gray-600">
              الإحداثيات المحددة: خط العرض {{ form.lattitude }} | خط الطول {{ form.langitude }}
            </p>
          </div>
        </section>

        <!-- 3. المواعيد -->
        <section class="bg-white shadow-md rounded-xl p-7 border border-gray-100">
          <h2 class="text-2xl font-semibold mb-7 flex items-center gap-3 text-gray-800">
            <span class="text-blue-600 text-3xl">③</span> المواعيد
          </h2>

          <div class="grid grid-cols-1 md:grid-cols-3 gap-7">
            <div>
              <label class="block text-base font-medium text-gray-800 mb-2">
                تاريخ البداية <span class="text-red-600">*</span>
              </label>
              <input
                v-model="form.start_date"
                type="date"
                class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 py-3 px-4 text-base"
                required
              />
            </div>

            <div>
              <label class="block text-base font-medium text-gray-800 mb-2">
                تاريخ النهاية
              </label>
              <input
                v-model="form.end_date"
                type="date"
                class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 py-3 px-4 text-base"
              />
            </div>

            <div>
              <label class="block text-base font-medium text-gray-800 mb-2">
                وقت البداية
              </label>
              <input
                v-model="form.time"
                type="time"
                class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 py-3 px-4 text-base"
              />
            </div>
          </div>
        </section>

        <!-- 4. صور الحدث -->
        <section class="bg-white shadow-md rounded-xl p-7 border border-gray-100">
          <h2 class="text-2xl font-semibold mb-7 flex items-center gap-3 text-gray-800">
            <span class="text-blue-600 text-3xl">④</span> صور الحدث
          </h2>

          <div
            @dragover.prevent
            @drop.prevent="handleImageDrop"
            class="border-2 border-dashed border-gray-400 rounded-xl p-10 text-center hover:border-blue-500 transition-all cursor-pointer bg-gray-50"
          >
            <input
              ref="fileInput"
              type="file"
              accept="image/png,image/jpeg,image/webp"
              multiple
              hidden
              @change="handleImageSelect"
            />

            <div v-if="form.urls.length === 0" class="space-y-4 py-8">
              <div class="mx-auto w-20 h-20 bg-blue-100 rounded-full flex items-center justify-center">
                <svg class="w-10 h-10 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                </svg>
              </div>
              <p class="text-lg font-medium text-gray-700">اضغط للرفع أو اسحب وأفلت الصور هنا</p>
              <p class="text-sm text-gray-500">PNG • JPG • WEBP | الحد الأقصى 5 ميجا لكل صورة</p>
              <button
                type="button"
                @click="$refs.fileInput.click()"
                class="mt-4 px-8 py-3 bg-blue-600 text-white font-medium rounded-lg hover:bg-blue-700 transition shadow-sm"
              >
                اختيار الصور
              </button>
            </div>

            <div v-else class="space-y-6">
              <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-4">
                <div v-for="(preview, index) in form.url_previews" :key="index" class="relative group">
                  <img
                    :src="preview"
                    :alt="`صورة الحدث ${index + 1}`"
                    class="w-full h-40 object-cover rounded-lg shadow border border-gray-200"
                  />
                  <button
                    type="button"
                    @click="removeImage(index)"
                    class="absolute top-2 right-2 bg-red-600 text-white rounded-full w-7 h-7 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity shadow-md"
                  >
                    ×
                  </button>
                </div>
              </div>

              <button
                type="button"
                @click="$refs.fileInput.click()"
                class="mt-4 px-6 py-2 bg-gray-600 text-white font-medium rounded-lg hover:bg-gray-700 transition"
              >
                إضافة المزيد من الصور
              </button>
            </div>
          </div>
        </section>

        <!-- أزرار الإجراءات -->
        <div class="flex flex-col sm:flex-row justify-end gap-4 pt-6">
          <button
            type="button"
            class="px-8 py-3 border border-gray-300 rounded-lg text-gray-700 font-medium hover:bg-gray-50 transition"
          >
            إلغاء
          </button>
          <button
            type="submit"
            :disabled="loading"
            class="px-10 py-3 bg-blue-600 text-white font-medium rounded-lg hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed transition shadow-md"
          >
            {{ loading ? "جاري الإنشاء..." : "إنشاء الحدث" }}
          </button>
        </div>
      </form>
    </div>
  </AdminLayout>
</template>

<script setup>
import { ref, onMounted, computed } from "vue";
import AdminLayout from "../../../layouts/AdminLayout.vue";
import { LMap, LTileLayer, LMarker } from "@vue-leaflet/vue-leaflet";
import "leaflet/dist/leaflet.css";
import { LocationService } from "../../../services/LocationService/LocationService";
import { CategoryService } from "../../../services/CategoryService/CategoryService";
import { EventService } from "../../../services/EventService/EventService";
import L from "leaflet";
import iconUrl from "leaflet/dist/images/marker-icon.png";
import iconShadow from "leaflet/dist/images/marker-shadow.png";

// Fix Leaflet default icon issue in Vue/Vite
delete L.Icon.Default.prototype._getIconUrl;

L.Icon.Default.mergeOptions({
  iconUrl,
  shadowUrl: iconShadow,
});
const form = ref({
  title: "",
  description: "",
  city_id: "",
  sub_category_id: "",
  start_date: "",
  end_date: "",
  time: "",
  urls: [],
  url_previews: [],
  lattitude: null,
  langitude: null,
});

const countries = ref([]);
const cities = ref([]);
const categories = ref([]);
const subCategories = ref([]);

const selectedCountryId = ref("");
const selectedCategoryId = ref("");
const loading = ref(false);
const fileInput = ref(null);

// البحث
const countrySearch = ref("");
const citySearch = ref("");

// حالات الخطأ
const errors = ref({
  general: "",
  countries: "",
  cities: "",
  categories: "",
  subCategories: "",
});

const filteredCountries = computed(() => {
  if (!countrySearch.value.trim()) return countries.value;
  const search = countrySearch.value.toLowerCase().trim();
  return countries.value.filter((c) => c.name.toLowerCase().includes(search));
});

const filteredCities = computed(() => {
  if (!citySearch.value.trim()) return cities.value;
  const search = citySearch.value.toLowerCase().trim();
  return cities.value.filter((c) => c.name.toLowerCase().includes(search));
});

// الخريطة
const zoom = ref(13);
const center = ref([30.04, 31.24]); // القاهرة افتراضياً
const markerPosition = ref(null);

function onMapClick(e) {
  const lat = e.latlng.lat;
  const lng = e.latlng.lng;
  markerPosition.value = [lat, lng];
  form.value.lattitude = lat.toFixed(6);
  form.value.langitude = lng.toFixed(6);
}

onMounted(async () => {
  await Promise.all([fetchCountries(), fetchCategories()]);
});

async function fetchCountries() {
  errors.value.countries = "";
  try {
    const res = await LocationService.getCountriesAll();
    countries.value = res.data.data || [];
  } catch (err) {
    console.error("فشل تحميل الدول", err);
    errors.value.countries = "تعذر تحميل قائمة الدول، تحقق من الاتصال بالإنترنت أو حاول لاحقاً";
  }
}

async function loadCities() {
  errors.value.cities = "";
  cities.value = [];
  form.value.city_id = "";
  citySearch.value = "";

  if (!selectedCountryId.value) return;

  try {
    const res = await LocationService.getCountryById(selectedCountryId.value);
    cities.value = res.data.data?.country?.cities || [];
  } catch (err) {
    console.error("فشل تحميل المدن", err);
    errors.value.cities = "تعذر تحميل المدن لهذه الدولة، حاول اختيار دولة أخرى أو أعد المحاولة";
  }
}

async function fetchCategories() {
  errors.value.categories = "";
  try {
    const res = await CategoryService.getCategories();
    categories.value = res.data.data || [];
  } catch (err) {
    console.error("فشل تحميل الفئات", err);
    errors.value.categories = "تعذر تحميل قائمة الفئات الرئيسية، تحقق من الاتصال وحاول مرة أخرى";
  }
}

async function loadSubCategories() {
  errors.value.subCategories = "";
  subCategories.value = [];
  form.value.sub_category_id = "";

  if (!selectedCategoryId.value) return;

  try {
    const res = await CategoryService.getCategoryById(selectedCategoryId.value);
    subCategories.value = res.data.data?.sub_categories || [];
  } catch (err) {
    console.error("فشل تحميل التصنيفات الفرعية", err);
    errors.value.subCategories = "تعذر تحميل التصنيفات الفرعية، حاول اختيار فئة أخرى أو أعد المحاولة";
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
  const validFiles = newFiles.filter((file) => {
    if (file.size > 5 * 1024 * 1024) {
      alert(`حجم الملف ${file.name} يتجاوز 5 ميجا`);
      return false;
    }
    if (!["image/png", "image/jpeg", "image/webp"].includes(file.type)) {
      alert(`نوع الملف ${file.name} غير مدعوم (مسموح: PNG, JPG, WEBP)`);
      return false;
    }
    return true;
  });

  validFiles.forEach((file) => {
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
  errors.value.general = "";

  if (
    !form.value.title?.trim() ||
    !form.value.description?.trim() ||
    !form.value.city_id ||
    !form.value.sub_category_id ||
    !form.value.start_date
  ) {
    errors.value.general = "برجاء ملء جميع الحقول المطلوبة (*) بشكل صحيح";
    return;
  }

  if (form.value.urls.length === 0) {
    errors.value.general = "يرجى رفع صورة واحدة على الأقل للحدث";
    return;
  }

  loading.value = true;

  const fd = new FormData();
  fd.append("title", form.value.title);
  fd.append("description", form.value.description);
  fd.append("city_id", form.value.city_id);
  fd.append("sub_categorey_id", form.value.sub_category_id); // ملاحظة: ربما خطأ إملائي في API ← sub_category_id
  fd.append("start_date", form.value.start_date);
  if (form.value.end_date) fd.append("end_date", form.value.end_date);
  if (form.value.time) fd.append("time", form.value.time);

  if (form.value.lattitude) fd.append("lattitude", form.value.lattitude);
  if (form.value.langitude) fd.append("langitude", form.value.langitude);

  form.value.urls.forEach((file) => {
    fd.append("urls[]", file);
  });

  try {
    await EventService.create(fd);
    alert("تم إنشاء الحدث بنجاح!");
    window.location.href = "/admin/events";
  } catch (err) {
    console.error("خطأ أثناء إنشاء الحدث", err);
    errors.value.general =
      err.response?.data?.message ||
      "حدث خطأ أثناء إنشاء الحدث، تحقق من البيانات وحاول مرة أخرى";
  } finally {
    loading.value = false;
  }
}
</script>

<style>
@import "leaflet/dist/leaflet.css";
</style>
