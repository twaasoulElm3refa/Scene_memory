<template>
  <AdminLayout>
    <div class="p-6 max-w-4xl mx-auto">
      <form @submit.prevent="createEvent" class="space-y-6">
        <!-- 1. Basic Information -->
        <section class="bg-white shadow-md rounded-xl p-7 border border-gray-100">
          <h2 class="text-2xl font-semibold mb-7 flex items-center gap-3 text-gray-800">
            <span class="text-blue-600 text-3xl">①</span> المعلومات الأساسية
          </h2>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-7">
            <div class="md:col-span-2">
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

            <div class="md:col-span-2">
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

        <!-- 2. Location & Category -->
        <section class="bg-white shadow-md rounded-xl p-7 border border-gray-100">
          <h2 class="text-2xl font-semibold mb-7 flex items-center gap-3 text-gray-800">
            <span class="text-blue-600 text-3xl">②</span> الموقع والتصنيف
          </h2>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-7">
            <!-- Country with Search -->
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

            <!-- City with Search -->
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
              <select
                v-model="form.city_id"
                size="6"
                class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-base h-48 overflow-y-auto disabled:bg-gray-100 disabled:text-gray-400"
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

            <!-- Category -->
            <div>
              <label class="block text-base font-medium text-gray-800 mb-2">
                الفئة الرئيسية <span class="text-red-600">*</span>
              </label>
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

            <!-- Sub-category -->
            <div>
              <label class="block text-base font-medium text-gray-800 mb-2">
                التصنيف الفرعي <span class="text-red-600">*</span>
              </label>
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
        </section>

        <!-- 3. Scheduling -->
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

        <!-- 4. Cover Images (Multiple) -->
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
              <div
                class="mx-auto w-20 h-20 bg-blue-100 rounded-full flex items-center justify-center"
              >
                <svg
                  class="w-10 h-10 text-blue-600"
                  fill="none"
                  stroke="currentColor"
                  viewBox="0 0 24 24"
                >
                  <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"
                  />
                </svg>
              </div>
              <p class="text-lg font-medium text-gray-700">
                اضغط للرفع أو اسحب وأفلت الصور هنا
              </p>
              <p class="text-sm text-gray-500">
                PNG • JPG • WEBP | الحد الأقصى 5 ميجا لكل صورة
              </p>
              <button
                type="button"
                @click="$refs.fileInput.click()"
                class="mt-4 px-8 py-3 bg-blue-600 text-white font-medium rounded-lg hover:bg-blue-700 transition shadow-sm"
              >
                اختيار الصور
              </button>
            </div>

            <!-- Preview Area -->
            <div v-else class="space-y-6">
              <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-4">
                <div
                  v-for="(preview, index) in form.url_previews"
                  :key="index"
                  class="relative group"
                >
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

        <!-- Actions -->
        <div class="flex flex-col sm:flex-row justify-end gap-4 pt-8">
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
import axios from "axios";
import AdminLayout from "../../../layouts/AdminLayout.vue";

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
});

const countries = ref([]);
const cities = ref([]);
const categories = ref([]);
const subCategories = ref([]);

const selectedCountryId = ref("");
const selectedCategoryId = ref("");
const loading = ref(false);
const fileInput = ref(null);

// Search fields
const countrySearch = ref("");
const citySearch = ref("");

// Filtered lists
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

onMounted(async () => {
  await Promise.all([fetchCountries(), fetchCategories()]);
});

async function fetchCountries() {
  try {
    const res = await axios.get("/v1/countries/all/get");
    countries.value = res.data.data || [];
  } catch (err) {
    console.error("فشل تحميل الدول", err);
  }
}

async function loadCities() {
  cities.value = [];
  form.value.city_id = "";
  citySearch.value = ""; // reset search when country changes

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
  form.value.sub_category_id = "";

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
  if (
    !form.value.title?.trim() ||
    !form.value.description?.trim() ||
    !form.value.city_id ||
    !form.value.sub_category_id ||
    !form.value.start_date
  ) {
    return alert("برجاء ملء جميع الحقول المطلوبة");
  }

  if (form.value.urls.length === 0) {
    return alert("يرجى رفع صورة واحدة على الأقل");
  }

  loading.value = true;

  const fd = new FormData();
  fd.append("title", form.value.title);
  fd.append("description", form.value.description);
  fd.append("city_id", form.value.city_id);
  fd.append("sub_categorey_id", form.value.sub_category_id); // note: typo in original → sub_category_id
  fd.append("start_date", form.value.start_date);
  form.value.end_date && fd.append("end_date", form.value.end_date);
  if (form.value.time) fd.append("time", form.value.time);

  form.value.urls.forEach((file) => {
    fd.append("urls[]", file);
  });

  try {
    await axios.post("/v1/events/create", fd, {
      headers: { "Content-Type": "multipart/form-data" },
    });
    window.location.href = "/admin/events";
    alert("تم إنشاء الحدث بنجاح!");
  } catch (err) {
    console.error(err);
    alert("فشل إنشاء الحدث: " + (err.response?.data?.message || "خطأ غير معروف"));
  } finally {
    loading.value = false;
  }
}
</script>
