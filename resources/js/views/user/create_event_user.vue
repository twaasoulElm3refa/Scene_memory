<template>
  <UserLayout>
    <div class="min-vh-100 bg-light py-5">
      <div class="container-fluid px-4 px-md-5">
        <!-- العنوان الرئيسي -->
        <div class="mb-5">
          <h1 class="display-5 fw-bold text-dark">إضافة حدث جديد</h1>
        </div>

        <form @submit.prevent="createEvent" class="row g-4 g-lg-5">
          <!-- 1. المعلومات الأساسية -->
          <div class="col-12">
            <div class="card shadow-lg border-0 rounded-4">
              <div class="card-body p-4 p-md-5">
                <h2 class="card-title h3 fw-bold mb-4 d-flex align-items-center gap-3">
                  <span class="text-primary fs-1 fw-bolder">①</span>
                  المعلومات الأساسية
                </h2>

                <div class="row g-4">
                  <div class="col-12">
                    <label class="form-label fs-5 fw-medium text-dark">
                      عنوان الحدث <span class="text-danger fs-4">*</span>
                    </label>
                    <input
                      v-model="form.title"
                      type="text"
                      class="form-control form-control-lg rounded-3 shadow-sm"
                      placeholder="مثال: رحلة نيلية ممتعة"
                      required
                    />
                  </div>

                  <div class="col-12">
                    <label class="form-label fs-5 fw-medium text-dark">
                      الوصف <span class="text-danger fs-4">*</span>
                    </label>
                    <textarea
                      v-model="form.description"
                      class="form-control form-control-lg rounded-3 shadow-sm"
                      rows="6"
                      placeholder="اكتب تفاصيل الحدث، الفعاليات، المكان، الأسعار إن وجدت، وما يمكن توقعه..."
                      required
                      style="min-height: 160px"
                    ></textarea>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- 2. الموقع والتصنيف -->
          <div class="col-12">
            <div class="card shadow-lg border-0 rounded-4">
              <div class="card-body p-4 p-md-5">
                <h2 class="card-title h3 fw-bold mb-4 d-flex align-items-center gap-3">
                  <span class="text-primary fs-1 fw-bolder">②</span>
                  الموقع والتصنيف
                </h2>

                <div class="row g-4">
                  <div class="col-12 col-md-6 col-lg-3">
                    <label class="form-label fs-5 fw-medium text-dark">
                      الدولة <span class="text-danger fs-4">*</span>
                    </label>
                    <select
                      v-model="selectedCountryId"
                      @change="loadCities"
                      class="form-select form-select-lg rounded-3 shadow-sm"
                      required
                    >
                      <option value="" disabled>اختر الدولة</option>
                      <option v-for="c in countries" :key="c.id" :value="c.id">
                        {{ c.name }}
                      </option>
                    </select>
                  </div>

                  <div class="col-12 col-md-6 col-lg-3">
                    <label class="form-label fs-5 fw-medium text-dark">
                      المدينة <span class="text-danger fs-4">*</span>
                    </label>
                    <select
                      v-model="form.city_id"
                      :disabled="!selectedCountryId || cities.length === 0"
                      class="form-select form-select-lg rounded-3 shadow-sm"
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

                  <div class="col-12 col-md-6 col-lg-3">
                    <label class="form-label fs-5 fw-medium text-dark">
                      الفئة الرئيسية <span class="text-danger fs-4">*</span>
                    </label>
                    <select
                      v-model="selectedCategoryId"
                      @change="loadSubCategories"
                      class="form-select form-select-lg rounded-3 shadow-sm"
                      required
                    >
                      <option value="" disabled>اختر الفئة</option>
                      <option v-for="cat in categories" :key="cat.id" :value="cat.id">
                        {{ cat.name }}
                      </option>
                    </select>
                  </div>

                  <div class="col-12 col-md-6 col-lg-3">
                    <label class="form-label fs-5 fw-medium text-dark">
                      التصنيف الفرعي <span class="text-danger fs-4">*</span>
                    </label>
                    <select
                      v-model="form.sub_category_id"
                      :disabled="!selectedCategoryId || subCategories.length === 0"
                      class="form-select form-select-lg rounded-3 shadow-sm"
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
            <div class="card shadow-lg border-0 rounded-4">
              <div class="card-body p-4 p-md-5">
                <h2 class="card-title h3 fw-bold mb-4 d-flex align-items-center gap-3">
                  <span class="text-primary fs-1 fw-bolder">③</span>
                  المواعيد
                </h2>

                <div class="row g-4">
                  <div class="col-12 col-sm-4">
                    <label class="form-label fs-5 fw-medium text-dark">
                      تاريخ البداية <span class="text-danger fs-4">*</span>
                    </label>
                    <input
                      v-model="form.start_date"
                      type="date"
                      class="form-control form-control-lg rounded-3 shadow-sm"
                      required
                    />
                  </div>

                  <div class="col-12 col-sm-4">
                    <label class="form-label fs-5 fw-medium text-dark"
                      >تاريخ النهاية</label
                    >
                    <input
                      v-model="form.end_date"
                      type="date"
                      class="form-control form-control-lg rounded-3 shadow-sm"
                    />
                  </div>

                  <div class="col-12 col-sm-4">
                    <label class="form-label fs-5 fw-medium text-dark">وقت البداية</label>
                    <input
                      v-model="form.time"
                      type="time"
                      class="form-control form-control-lg rounded-3 shadow-sm"
                    />
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- 4. صورة الغلاف -->
          <div class="col-12">
            <div class="card shadow-lg border-0 rounded-4">
              <div class="card-body p-4 p-md-5">
                <h2 class="card-title h3 fw-bold mb-4 d-flex align-items-center gap-3">
                  <span class="text-primary fs-1 fw-bolder">④</span>
                  صورة الغلاف
                </h2>

                <div
                  @dragover.prevent
                  @drop.prevent="handleImageDrop"
                  class="border border-3 border-dashed border-secondary-subtle rounded-4 p-5 text-center bg-body-tertiary hover-border-primary transition cursor-pointer"
                >
                  <input
                    ref="fileInput"
                    type="file"
                    accept="image/png,image/jpeg,image/webp"
                    hidden
                    @change="handleImageSelect"
                  />

                  <div v-if="!form.image_preview" class="py-5">
                    <div
                      class="mx-auto mb-4 bg-primary-subtle rounded-circle d-flex align-items-center justify-content-center"
                      style="width: 100px; height: 100px"
                    >
                      <svg
                        class="text-primary"
                        width="50"
                        height="50"
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
                    <p class="fs-4 fw-medium text-secondary mb-2">
                      اضغط للرفع أو اسحب وأفلت الصورة هنا
                    </p>
                    <p class="text-muted mb-4">
                      PNG • JPG • WEBP | الحد الأقصى 5 ميجا بايت
                    </p>
                    <button
                      type="button"
                      @click="$refs.fileInput.click()"
                      class="btn btn-primary btn-lg px-5 py-3 rounded-pill shadow"
                    >
                      اختيار صورة
                    </button>
                  </div>

                  <div v-else class="py-4">
                    <img
                      :src="form.image_preview"
                      alt="معاينة صورة الحدث"
                      class="img-fluid rounded-4 shadow-lg mx-auto d-block"
                      style="max-height: 400px; object-fit: cover"
                    />
                    <button
                      type="button"
                      @click="clearImage"
                      class="btn btn-link text-danger fs-5 mt-3"
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
            class="col-12 d-flex flex-column flex-sm-row justify-content-end gap-3 pt-4 pb-5"
          >
            <button
              type="button"
              class="btn btn-outline-secondary btn-lg px-5 py-3 rounded-pill"
            >
              إلغاء
            </button>

            <button
              type="button"
              class="btn btn-outline-secondary btn-lg px-5 py-3 rounded-pill"
            >
              حفظ كمسودة
            </button>

            <button
              type="submit"
              :disabled="loading"
              class="btn btn-primary btn-lg px-5 py-3 rounded-pill shadow"
            >
              {{ loading ? "جاري الإنشاء..." : "إنشاء الحدث" }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </UserLayout>
</template>

<script setup>
import { ref, onMounted } from "vue";
import axios from "axios";
import UserLayout from "../../layouts/user/UserLayout.vue";
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

<style>
.hover-border-primary:hover {
  border-color: var(--bs-primary) !important;
}
.transition {
  transition: border-color 0.25s ease;
}
</style>
