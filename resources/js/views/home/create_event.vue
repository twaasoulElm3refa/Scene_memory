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
                  <input
                    v-model="form.title"
                    type="text"
                    class="form-control form-control-md rounded-3"
                    placeholder="مثال: رحلة نيلية ممتعة"
                    required
                  />
                </div>

                <div class="col-12">
                  <label class="form-label fw-medium">
                    الوصف <span class="text-danger">*</span>
                  </label>
                  <textarea
                    v-model="form.description"
                    class="form-control rounded-3"
                    rows="5"
                    placeholder="تفاصيل الحدث، الفعاليات، المكان، الأسعار..."
                    required
                    style="min-height: 120px"
                  ></textarea>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- 2. الموقع والتصنيف -->
        <div class="col-12">
          <div class="card shadow border-0 rounded-3">
            <div class="card-body p-4">
              <h2 class="card-title h4 fw-bold mb-3 d-flex align-items-center gap-2">
                <span class="text-primary fs-3 fw-bolder">②</span>
                الموقع والتصنيف
              </h2>

              <div class="row g-3">
                <div class="col-12 col-sm-6 col-lg-3">
                  <label class="form-label fw-medium"
                    >الدولة <span class="text-danger">*</span></label
                  >
                  <select
                    v-model="selectedCountryId"
                    @change="loadCities"
                    class="form-select form-select-md rounded-3"
                    required
                  >
                    <option value="" disabled>اختر الدولة</option>
                    <option v-for="c in countries" :key="c.id" :value="c.id">
                      {{ c.name }}
                    </option>
                  </select>
                </div>

                <div class="col-12 col-sm-6 col-lg-3">
                  <label class="form-label fw-medium"
                    >المدينة <span class="text-danger">*</span></label
                  >
                  <select
                    v-model="form.city_id"
                    :disabled="!selectedCountryId || cities.length === 0"
                    class="form-select form-select-md rounded-3"
                    required
                  >
                    <option value="" disabled>
                      {{ selectedCountryId ? "اختر المدينة" : "اختر الدولة أولاً" }}
                    </option>
                    <option v-for="city in cities" :key="city.id" :value="city.id">
                      {{ city.name }}
                    </option>
                  </select>
                </div>

                <div class="col-12 col-sm-6 col-lg-3">
                  <label class="form-label fw-medium"
                    >الفئة الرئيسية <span class="text-danger">*</span></label
                  >
                  <select
                    v-model="selectedCategoryId"
                    @change="loadSubCategories"
                    class="form-select form-select-md rounded-3"
                    required
                  >
                    <option value="" disabled>اختر الفئة</option>
                    <option v-for="cat in categories" :key="cat.id" :value="cat.id">
                      {{ cat.name }}
                    </option>
                  </select>
                </div>

                <div class="col-12 col-sm-6 col-lg-3">
                  <label class="form-label fw-medium"
                    >التصنيف الفرعي <span class="text-danger">*</span></label
                  >
                  <select
                    v-model="form.sub_category_id"
                    :disabled="!selectedCategoryId || subCategories.length === 0"
                    class="form-select form-select-md rounded-3"
                    required
                  >
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
                  <label class="form-label fw-medium"
                    >تاريخ البداية <span class="text-danger">*</span></label
                  >
                  <input
                    v-model="form.start_date"
                    type="date"
                    class="form-control rounded-3"
                    required
                  />
                </div>

                <div class="col-12 col-sm-4">
                  <label class="form-label fw-medium">تاريخ النهاية</label>
                  <input
                    v-model="form.end_date"
                    type="date"
                    class="form-control rounded-3"
                  />
                </div>

                <div class="col-12 col-sm-4">
                  <label class="form-label fw-medium">وقت البداية</label>
                  <input v-model="form.time" type="time" class="form-control rounded-3" />
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- 4. صورة الغلاف -->
        <div class="col-12">
          <div class="card shadow border-0 rounded-3">
            <div class="card-body p-4">
              <h2 class="card-title h4 fw-bold mb-3 d-flex align-items-center gap-2">
                <span class="text-primary fs-3 fw-bolder">④</span>
                صورة الغلاف
              </h2>

              <div
                @dragover.prevent
                @drop.prevent="handleImageDrop"
                class="border border-2 border-dashed border-secondary-subtle rounded-3 p-4 text-center bg-body-tertiary"
                style="min-height: 180px"
              >
                <input
                  ref="fileInput"
                  type="file"
                  accept="image/png,image/jpeg,image/webp"
                  hidden
                  @change="handleImageSelect"
                />

                <div v-if="!form.image_preview" class="py-4">
                  <div
                    class="mx-auto mb-3 bg-primary-subtle rounded-circle d-flex align-items-center justify-content-center"
                    style="width: 70px; height: 70px"
                  >
                    <svg
                      class="text-primary"
                      width="36"
                      height="36"
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
                  <p class="fs-5 fw-medium text-secondary mb-2">
                    اضغط أو اسحب الصورة هنا
                  </p>
                  <p class="text-muted small mb-3">PNG • JPG • WEBP | max 5MB</p>
                  <button
                    type="button"
                    @click="$refs.fileInput.click()"
                    class="btn btn-primary btn-md px-4 py-2 rounded-pill"
                  >
                    اختيار صورة
                  </button>
                </div>

                <div v-else class="py-3">
                  <img
                    :src="form.image_preview"
                    alt="معاينة"
                    class="img-fluid rounded-3 shadow mx-auto d-block"
                    style="max-height: 280px; object-fit: cover"
                  />
                  <button
                    type="button"
                    @click="clearImage"
                    class="btn btn-link text-danger mt-2 small"
                  >
                    إزالة الصورة
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- الأزرار -->
        <div
          class="col-12 d-flex flex-column flex-sm-row justify-content-end gap-2 pt-3 pb-4"
        >
          <button
            type="button"
            class="btn btn-outline-secondary btn-md px-4 py-2 rounded-pill"
          >
            إلغاء
          </button>
          <button
            type="button"
            class="btn btn-outline-secondary btn-md px-4 py-2 rounded-pill"
          >
            حفظ كمسودة
          </button>
          <button
            type="submit"
            :disabled="loading"
            class="btn btn-primary btn-md px-4 py-2 rounded-pill shadow"
          >
            {{ loading ? "جاري الإنشاء..." : "إنشاء الحدث" }}
          </button>
        </div>
      </form>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from "vue";
import axios from "axios";
const form = ref({
  title: "",
  description: "",
  city_id: "",
  sub_category_id: "",
  start_date: "",
  end_date: "",
  time: "",
  image: null,
  image_preview: null,
});

const countries = ref([]);
const cities = ref([]);
const categories = ref([]);
const subCategories = ref([]);

const selectedCountryId = ref("");
const selectedCategoryId = ref("");
const loading = ref(false);
const fileInput = ref(null);

onMounted(async () => {
  await Promise.all([fetchCountries(), fetchCategories()]);
});

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
  form.value.sub_category_id = "";

  if (!selectedCategoryId.value) return;

  try {
    const res = await axios.get(`/v1/categories/${selectedCategoryId.value}`);
    subCategories.value = res.data.data?.sub_categories || [];
  } catch (err) {
    console.error("فشل تحميل التصنيفات الفرعية", err);
  }
}

// Image logic remains the same
function handleImageSelect(e) {
  const file = e.target.files?.[0];
  if (file) processImage(file);
}

function handleImageDrop(e) {
  const file = e.dataTransfer.files?.[0];
  if (file) processImage(file);
}

function processImage(file) {
  if (file.size > 5 * 1024 * 1024) return alert("حجم الملف يتجاوز 5 ميجا");
  if (!["image/png", "image/jpeg", "image/webp"].includes(file.type))
    return alert("المسموح: PNG, JPG, WEBP فقط");

  form.value.image = file;
  const reader = new FileReader();
  reader.onload = (ev) => (form.value.image_preview = ev.target.result);
  reader.readAsDataURL(file);
}

function clearImage() {
  form.value.image = null;
  form.value.image_preview = null;
  fileInput.value && (fileInput.value.value = "");
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

  loading.value = true;

  const fd = new FormData();
  fd.append("title", form.value.title);
  fd.append("description", form.value.description);
  fd.append("city_id", form.value.city_id);
  fd.append("sub_categorey_id", form.value.sub_category_id); // ← typo kept as per backend
  fd.append("start_date", form.value.start_date);
  if (form.value.end_date) fd.append("end_date", form.value.end_date);
  if (form.value.time) fd.append("time", form.value.time);
  if (form.value.image) fd.append("image", form.value.image);

  try {
    await axios.post("/v1/user-dshboard/create/Event", fd, {
      headers: {
        "Content-Type": "multipart/form-data",
        Authorization: `Bearer ${localStorage.getItem("auth_token")}`,
        Accept: "application/json",
      },
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

function saveAsDraft() {
  alert("ميزة حفظ المسودة غير مفعلة بعد");
}
</script>
