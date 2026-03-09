<script setup lang="ts">
import AdminLayout from '../../../layouts/AdminLayout.vue';
import { ref, onMounted } from 'vue';
import api from '../../../services/ApiClient';

interface User {
  id: number;
  name: string;
  email: string;
  role: string;
  image: string | null;
}

interface Comment {
  id: number;
  event_id: number;
  user_id: number;
  comment: string;
  created_at: string;
}

interface Report {
  id: number;
  comment_id: number;
  user_id: number;
  reason: string;
  created_at: string;
  user: User;
  comment: Comment;
}

interface PaginatedReports {
  current_page: number;
  data: Report[];
  last_page: number;
  total: number;
  per_page: number;
}

const reports = ref<Report[]>([]);
const pagination = ref<Omit<PaginatedReports, 'data'> | null>(null);
const currentPage = ref(1);
const loading = ref(false);
const deleteLoading = ref<number | null>(null);
const selectedReport = ref<Report | null>(null);
const showModal = ref(false);

const fetchReports = async (page = 1) => {
  loading.value = true;
  try {
    const response = await api.get(`/comments/reports/all?page=${page}`);
    const { data: pageData, ...meta } = response.data.data;
    reports.value = pageData;
    pagination.value = meta;
    currentPage.value = page;
  } catch (error) {
    console.error('Failed to fetch reports:', error);
  } finally {
    loading.value = false;
  }
};

const deleteReport = async (reportId: number, isFromModal = false) => {
  if (!confirm('هل أنت متأكد من حذف هذا التعليق')) return;

  const isDeletingComment = isFromModal;

  const targetId = isDeletingComment
    ? selectedReport.value?.comment_id ?? selectedReport.value?.comment.id
    : reportId;

  if (!targetId) {
    console.error('لم يتم العثور على معرف الهدف للحذف');
    return;
  }

  deleteLoading.value = reportId;

  try {
    if (isDeletingComment) {
      await api.delete(`/comments/${targetId}/delete`);
      reports.value = reports.value.filter(r => r.id !== reportId);
    } else {
      await api.delete(`/comments/reports/${reportId}/delete`);
      reports.value = reports.value.filter(r => r.id !== reportId);
    }
  } catch (error) {
    console.error('فشل في عملية الحذف:', error);
    alert('حدث خطأ أثناء الحذف، حاول مرة أخرى');
  } finally {
    deleteLoading.value = null;
  }
};

const showReport = (report: Report) => {
  selectedReport.value = report;
  showModal.value = true;
};

const closeModal = () => {
  showModal.value = false;
  selectedReport.value = null;
};

onMounted(() => fetchReports());
</script>

<template>
  <AdminLayout>
    <div class="p-6">
      <h3 class="text-2xl font-bold text-gray-800 mb-6">البلاغات عن التعليقات</h3>

      <!-- Loading -->
      <div v-if="loading" class="flex justify-center items-center py-20">
        <div class="animate-spin rounded-full h-10 w-10 border-4 border-blue-500 border-t-transparent"></div>
      </div>

      <!-- Table -->
      <div v-else class="bg-white rounded-xl shadow overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-4 py-3 text-right text-gray-600 font-semibold">#</th>
              <th class="px-4 py-3 text-right text-gray-600 font-semibold">المُبلِّغ</th>
              <th class="px-4 py-3 text-right text-gray-600 font-semibold">السبب</th>
              <th class="px-4 py-3 text-right text-gray-600 font-semibold">التعليق</th>
              <th class="px-4 py-3 text-right text-gray-600 font-semibold">التاريخ</th>
              <th class="px-4 py-3 text-center text-gray-600 font-semibold">الإجراءات</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <tr v-for="report in reports" :key="report.id" class="hover:bg-gray-50 transition">
              <td class="px-4 py-3 text-gray-500">{{ report.id }}</td>
              <td class="px-4 py-3">
                <div class="flex items-center gap-2">
                  <div class="w-8 h-8 rounded-full bg-blue-100 flex items-center justify-center text-blue-600 font-bold text-xs">
                    {{ report.user.name.charAt(0) }}
                  </div>
                  <div>
                    <p class="font-medium text-gray-800">{{ report.user.name }}</p>
                    <p class="text-xs text-gray-400">{{ report.user.email }}</p>
                  </div>
                </div>
              </td>
              <td class="px-4 py-3">
                <span class="px-2 py-1 rounded-full text-xs font-medium"
                  :class="{
                    'bg-red-100 text-red-600': report.reason === 'offensive',
                    'bg-yellow-100 text-yellow-600': report.reason === 'illegal',
                    'bg-gray-100 text-gray-600': !['offensive','illegal'].includes(report.reason)
                  }">
                  {{ report.reason }}
                </span>
              </td>
              <td class="px-4 py-3 text-gray-700 max-w-xs truncate">{{ report.comment.comment }}</td>
              <td class="px-4 py-3 text-gray-400 text-xs">
                {{ new Date(report.created_at).toLocaleDateString('ar-EG') }}
              </td>
              <td class="px-4 py-3">
                <div class="flex items-center justify-center gap-2">
                  <button
                    @click="showReport(report)"
                    class="flex items-center gap-1 px-3 py-1.5 bg-blue-50 text-blue-600 rounded-lg hover:bg-blue-100 transition text-xs font-medium"
                  >
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.477 0 8.268 2.943 9.542 7-1.274 4.057-5.065 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                    </svg>
                    عرض
                  </button>
                  <button
                    @click="deleteReport(report.id)"
                    :disabled="deleteLoading === report.id"
                    class="flex items-center gap-1 px-3 py-1.5 bg-red-50 text-red-600 rounded-lg hover:bg-red-100 transition text-xs font-medium disabled:opacity-50"
                  >
                    <svg v-if="deleteLoading !== report.id" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                    </svg>
                    <svg v-else class="w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24">
                      <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                      <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
                    </svg>
                    حذف
                  </button>
                </div>
              </td>
            </tr>
            <tr v-if="reports.length === 0">
              <td colspan="6" class="text-center py-12 text-gray-400">لا توجد بلاغات</td>
            </tr>
          </tbody>
        </table>

        <!-- Pagination -->
        <div v-if="pagination && pagination.last_page > 1" class="flex justify-center items-center gap-2 py-4 border-t">
          <button
            v-for="page in pagination.last_page"
            :key="page"
            @click="fetchReports(page)"
            :class="[
              'w-8 h-8 rounded-lg text-sm font-medium transition',
              currentPage === page ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'
            ]"
          >{{ page }}</button>
        </div>
      </div>
    </div>

    <!-- Modal -->
    <Teleport to="body">
      <div v-if="showModal && selectedReport" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm" @click.self="closeModal">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md mx-4 overflow-hidden">
          <div class="flex items-center justify-between px-6 py-4 border-b">
            <h4 class="text-lg font-bold text-gray-800">تفاصيل التقرير</h4>
            <button @click="closeModal" class="text-gray-400 hover:text-gray-600 transition">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
              </svg>
            </button>
          </div>
          <div class="px-6 py-5 space-y-4 text-sm text-right" dir="rtl">
            <div class="bg-gray-50 rounded-xl p-4 space-y-2">
              <p class="text-gray-500 text-xs font-medium">المُبلِّغ</p>
              <p class="font-semibold text-gray-800">{{ selectedReport.user.name }}</p>
              <p class="text-gray-400">{{ selectedReport.user.email }}</p>
            </div>
            <div class="bg-gray-50 rounded-xl p-4 space-y-2">
              <p class="text-gray-500 text-xs font-medium">التعليق المُبلَّغ عنه</p>
              <p class="text-gray-700">{{ selectedReport.comment.comment }}</p>
            </div>
            <div class="flex gap-3">
              <div class="flex-1 bg-gray-50 rounded-xl p-4">
                <p class="text-gray-500 text-xs font-medium mb-1">السبب</p>
                <span class="px-2 py-1 rounded-full text-xs font-medium"
                  :class="{
                    'bg-red-100 text-red-600': selectedReport.reason === 'offensive',
                    'bg-yellow-100 text-yellow-600': selectedReport.reason === 'illegal',
                    'bg-gray-100 text-gray-600': !['offensive','illegal'].includes(selectedReport.reason)
                  }">
                  {{ selectedReport.reason }}
                </span>
              </div>
              <div class="flex-1 bg-gray-50 rounded-xl p-4">
                <p class="text-gray-500 text-xs font-medium mb-1">التاريخ</p>
                <p class="text-gray-700">{{ new Date(selectedReport.created_at).toLocaleDateString('ar-EG') }}</p>
              </div>
            </div>
          </div>
          <div class="px-6 py-4 border-t flex justify-end gap-2">
            <button @click="closeModal" class="px-4 py-2 text-sm rounded-lg bg-gray-100 text-gray-600 hover:bg-gray-200 transition">
              إغلاق
            </button>
            <button
              @click="deleteReport(selectedReport.id, true); closeModal()"
              class="px-4 py-2 text-sm rounded-lg bg-red-600 text-white hover:bg-red-700 transition"
            >
              حذف التعليق
            </button>
          </div>
        </div>
      </div>
    </Teleport>
  </AdminLayout>
</template>
