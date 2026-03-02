<script setup lang="ts">
import { ref, onMounted } from 'vue'
import AdminLayout from '../../../layouts/AdminLayout.vue'
import axios from 'axios'

interface FooterData {
  id: number
  logo: string
  twitter: string
  facebook: string
  instagram: string
  google_play: string
  app_store: string
  created_at: string
  updated_at: string
}

const footer = ref<FooterData | null>(null)
const loading = ref(false)
const error = ref<string | null>(null)

const showModal = ref(false)
const form = ref({
  twitter: '',
  facebook: '',
  instagram: '',
  google_play: '',
  app_store: '',
})

const apiBase = '/v1'

onMounted(async () => {
  await fetchFooter()
})

async function fetchFooter() {
  loading.value = true
  error.value = null

  try {
    const res = await axios.get(`${apiBase}/footer`)
    if (res.data.status === 'success') {
      footer.value = res.data.data

      form.value = {
        twitter: res.data.data.twitter || '',
        facebook: res.data.data.facebook || '',
        instagram: res.data.data.instagram || '',
        google_play: res.data.data.google_play || '',
        app_store: res.data.data.app_store || '',
      }
    } else {
      error.value = res.data.message || 'حدث خطأ أثناء جلب البيانات'
    }
  } catch (err: any) {
    error.value = err.response?.data?.message || 'فشل الاتصال بالسيرفر'
  } finally {
    loading.value = false
  }
}

async function updateFooter() {
  if (!footer.value) return

  loading.value = true
  error.value = null

  try {
    const res = await axios.post(`${apiBase}/footer/update`, form.value)

    if (res.data.status === 'success') {
      footer.value = { ...footer.value, ...res.data.data }
      closeModal()
      alert('تم حفظ التعديلات بنجاح ✓')
    } else {
      error.value = res.data.message || 'فشل الحفظ'
    }
  } catch (err: any) {
    error.value = err.response?.data?.message || 'حدث خطأ أثناء الحفظ'
  } finally {
    loading.value = false
  }
}

function openModal() {
  showModal.value = true
}

function closeModal() {
  showModal.value = false
  error.value = null
}
</script>

<template>
  <AdminLayout>
    <div class="p-6">
      <div class="flex items-center justify-between mb-6">
        <h2 class="text-2xl font-bold text-gray-800">إدارة الفوتر</h2>
        <button
          @click="openModal"
          class="px-5 py-2.5 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition shadow-sm flex items-center gap-2"
          :disabled="loading || !footer"
        >
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
          </svg>
          تعديل بيانات الفوتر
        </button>
      </div>

      <div v-if="loading" class="text-center py-12">
        <div class="animate-spin inline-block w-8 h-8 border-4 border-indigo-500 border-t-transparent rounded-full"></div>
        <p class="mt-3 text-gray-600">جاري التحميل...</p>
      </div>

      <div v-else-if="error" class="bg-red-50 border border-red-200 text-red-700 p-4 rounded-lg mb-6">
        {{ error }}
      </div>

      <div v-else-if="footer" class="bg-white shadow-sm rounded-xl overflow-hidden border border-gray-200">
        <div class="p-6 border-b border-gray-100">
          <h3 class="text-lg font-semibold text-gray-800 mb-4">بيانات الفوتر الحالية</h3>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
              <label class="block text-sm font-medium text-gray-600 mb-1">الشعار (Logo)</label>
              <div class="mt-1">
                <img
                  v-if="footer.logo"
                  :src="`/storage/${footer.logo}`"
                  alt="Footer Logo"
                  class="h-16 w-auto object-contain rounded border bg-gray-50"
                />
                <span v-else class="text-gray-400">لا يوجد شعار</span>
              </div>
            </div>

            <div class="space-y-4">
              <div>
                <label class="block text-sm font-medium text-gray-600">تويتر</label>
                <p class="mt-1 text-gray-900 break-all">{{ footer.twitter || '—' }}</p>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-600">فيسبوك</label>
                <p class="mt-1 text-gray-900 break-all">{{ footer.facebook || '—' }}</p>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-600">إنستغرام</label>
                <p class="mt-1 text-gray-900 break-all">{{ footer.instagram || '—' }}</p>
              </div>
            </div>

            <div class="space-y-4">
              <div>
                <label class="block text-sm font-medium text-gray-600">Google Play</label>
                <p class="mt-1 text-gray-900 break-all">{{ footer.google_play || '—' }}</p>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-600">App Store</label>
                <p class="mt-1 text-gray-900 break-all">{{ footer.app_store || '—' }}</p>
              </div>
            </div>
          </div>
        </div>

        <div class="px-6 py-4 bg-gray-50 text-right">
          <p class="text-sm text-gray-500">
            تم الإنشاء: {{ new Date(footer.created_at).toLocaleDateString('ar-EG') }}
            • آخر تحديث: {{ new Date(footer.updated_at).toLocaleDateString('ar-EG') }}
          </p>
        </div>
      </div>

      <div
        v-if="showModal"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-40 backdrop-blur-sm"
      >
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-lg mx-4 overflow-hidden">
          <div class="px-6 py-5 border-b border-gray-200">
            <h3 class="text-xl font-bold text-gray-900">تعديل بيانات الفوتر</h3>
          </div>

          <div class="p-6 space-y-5">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">رابط تويتر</label>
              <input
                v-model="form.twitter"
                type="url"
                class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none"
                placeholder="https://twitter.com/..."
              />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">رابط فيسبوك</label>
              <input
                v-model="form.facebook"
                type="url"
                class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none"
                placeholder="https://facebook.com/..."
              />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">رابط إنستغرام</label>
              <input
                v-model="form.instagram"
                type="url"
                class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none"
                placeholder="https://instagram.com/..."
              />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">رابط Google Play</label>
              <input
                v-model="form.google_play"
                type="url"
                class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none"
                placeholder="https://play.google.com/..."
              />
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">رابط App Store</label>
              <input
                v-model="form.app_store"
                type="url"
                class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none"
                placeholder="https://apps.apple.com/..."
              />
            </div>

            <div v-if="error" class="bg-red-50 border border-red-200 text-red-700 p-3 rounded-lg text-sm">
              {{ error }}
            </div>
          </div>

          <div class="px-6 py-4 bg-gray-50 flex justify-end gap-3 border-t">
            <button
              @click="closeModal"
              class="px-5 py-2.5 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition"
              :disabled="loading"
            >
              إلغاء
            </button>
            <button
              @click="updateFooter"
              class="px-6 py-2.5 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition shadow-sm flex items-center gap-2"
              :disabled="loading"
            >
              <span v-if="loading" class="animate-spin inline-block w-4 h-4 border-2 border-white border-t-transparent rounded-full"></span>
              {{ loading ? 'جاري الحفظ...' : 'حفظ التعديلات' }}
            </button>
          </div>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>
