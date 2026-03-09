<template>
  <AdminLayout>
    <div class="max-w-2xl mx-auto py-8 px-4 sm:px-6 lg:px-8">
      <div class="mb-8">
        <h1 class="text-2xl font-bold text-gray-900">إضافة تصنيف فرعي</h1>
        <p class="mt-2 text-gray-600">
          إضافة تصنيف فرعي جديد تحت الفئة الرئيسية:
          <span class="font-medium text-gray-900">{{
            parentCategoryName || "جاري التحميل..."
          }}</span>
        </p>
      </div>

      <div class="bg-white shadow-sm border border-gray-200 rounded-xl overflow-hidden">
        <form @submit.prevent="submitForm" class="p-6 space-y-6">
          <div>
            <label for="name" class="block text-sm font-medium text-gray-700 mb-1">
              اسم التصنيف الفرعي <span class="text-red-500">*</span>
            </label>
            <input
              v-model="form.name"
              type="text"
              id="name"
              class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none transition-all"
              placeholder="مثال: دورات تدريبية، ورش عمل..."
              :class="{ 'border-red-500': form.errors.name }"
              required
            />
            <p v-if="form.errors.name" class="mt-1.5 text-sm text-red-600">
              {{ form.errors.name }}
            </p>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">
              الصورة (اختياري)
            </label>
            <div class="mt-1 flex items-center gap-4">
              <label
                class="cursor-pointer px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg border border-gray-300 transition-colors flex items-center gap-2"
              >
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                </svg>
                اختيار صورة
                <input
                  type="file"
                  accept="image/*"
                  class="hidden"
                  @change="handleImageChange"
                />
              </label>

              <div v-if="form.imagePreview" class="w-20 h-20 rounded-lg overflow-hidden border border-gray-200">
                <img :src="form.imagePreview" alt="معاينة الصورة" class="w-full h-full object-cover" />
              </div>

              <p v-else-if="form.imageName" class="text-sm text-gray-600">
                {{ form.imageName }}
              </p>

              <p v-else class="text-sm text-gray-500">لم يتم اختيار صورة</p>
            </div>
          </div>

          <div class="flex items-center justify-end gap-4 pt-4 border-t border-gray-200">
            <button
              type="button"
              @click="$router.back()"
              class="px-6 py-2.5 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg transition-colors"
              :disabled="form.processing"
            >
              إلغاء
            </button>
            <button
              type="submit"
              class="px-6 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition-colors disabled:opacity-60 flex items-center gap-2"
              :disabled="form.processing"
            >
              <span v-if="form.processing">جاري الحفظ...</span>
              <span v-else>إضافة التصنيف</span>
            </button>
          </div>
        </form>
      </div>
    </div>
  </AdminLayout>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import AdminLayout from '../../../layouts/AdminLayout.vue'

// استيراد الـ service
import { categoryService } from '@/services/admin/categories/categoryService'

const route = useRoute()
const router = useRouter()

const parentCategoryName = ref('')

const form = ref({
  name: '',
  image: null,
  imagePreview: null,
  imageName: '',
  category_id: null,
  processing: false,
  errors: {}
})

onMounted(() => {
  const categoryId = route.params.id
  if (categoryId) {
    form.value.category_id = Number(categoryId)
    fetchParentCategoryName(categoryId)
  } else {
    router.back()
  }
})

async function fetchParentCategoryName(id) {
  const result = await categoryService.getCategoryWithSubs(id)

  if (result.success) {
    parentCategoryName.value = result.data?.name || 'غير معروف'
  } else {
    console.error('فشل جلب اسم الفئة الأم:', result.error)
    parentCategoryName.value = 'حدث خطأ'
  }
}

function handleImageChange(e) {
  const file = e.target.files?.[0]
  if (!file) return

  form.value.image = file
  form.value.imageName = file.name

  const reader = new FileReader()
  reader.onload = (event) => {
    form.value.imagePreview = event.target.result
  }
  reader.readAsDataURL(file)
}

async function submitForm() {
  form.value.processing = true
  form.value.errors = {}

  try {
    const result = await categoryService.createSubCategoryWithImage({
      category_id: form.value.category_id,
      name: form.value.name,
      image: form.value.image
    })

    if (result.success) {
      router.push(`/admin/categories/${form.value.category_id}`)
    } else {
      if (result.error?.type === 'validation') {
        form.value.errors = result.error.messages || {}
      } else {
        alert(result.error?.message || 'حدث خطأ أثناء إضافة التصنيف الفرعي')
      }
    }
  } catch (err) {
    console.error(err)
    alert('حدث خطأ غير متوقع')
  } finally {
    form.value.processing = false
  }
}
</script>
