<template>
  <div class="max-w-4xl mx-auto p-4 md:p-8">
    <div class="mb-8 text-center">
      <h1 class="text-3xl font-bold text-gray-800 mb-2">ملفي الشخصي</h1>
      <p class="text-gray-600">إدارة معلومات حسابك وتحديث كلمة المرور</p>
    </div>

    <div class="bg-white rounded-2xl shadow-xl overflow-hidden border border-gray-100">

      <div class="bg-gradient-to-r from-emerald-50 to-teal-50 p-6 border-b">
        <div class="flex flex-col md:flex-row md:items-center justify-between">
          <div class="flex items-center mb-4 md:mb-0">
            <div
              class="w-16 h-16 rounded-full bg-gradient-to-r from-emerald-500 to-teal-500 flex items-center justify-center text-white text-2xl font-bold mr-4 shadow-md">
              {{ getInitials(profile.user.name) }}
            </div>
            <div>
              <h2 class="text-xl font-bold text-gray-800">{{ profile.user.name }}</h2>
              <p class="text-gray-600">{{ profile.user.email }}</p>
              <div class="flex items-center mt-1">
                <span class="px-3 py-1 bg-emerald-100 text-emerald-800 text-sm font-medium rounded-full mr-2">
                  {{ profile.user.role }}
                </span>
                <span class="px-3 py-1 bg-blue-100 text-blue-800 text-sm font-medium rounded-full">
                  {{ profile.user.is_active ? 'مفعل' : 'غير مفعل' }}
                </span>
              </div>
            </div>
          </div>
          <div class="text-right">
            <p class="text-gray-600 text-sm">آخر دخول</p>
            <p class="text-gray-800 font-medium">{{ profile.user.last_login_at }}</p>
          </div>
        </div>
      </div>

      <div class="flex overflow-x-auto border-b">
        <button v-for="tab in tabs" :key="tab.id" @click="currentTab = tab.id" :class="[
          'flex-1 min-w-[150px] py-4 px-2 md:px-6 font-medium text-sm md:text-base transition-all duration-300',
          currentTab === tab.id
            ? 'border-b-2 border-emerald-500 text-emerald-600 bg-emerald-50/50'
            : 'text-gray-500 hover:text-emerald-500 hover:bg-gray-50'
        ]">
          <div class="flex items-center justify-center">
            <span class="ml-2">{{ tab.icon }}</span>
            <span>{{ tab.label }}</span>
          </div>
        </button>
      </div>

      <div class="p-6 md:p-8">
        <div v-if="currentTab === 1" class="fade-in">
          <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div class="bg-gray-50 rounded-xl p-6">
              <h3 class="text-lg font-bold text-gray-800 mb-4 flex items-center">
                <span class="ml-2"></span> معلومات الحساب
              </h3>
              <div class="space-y-4">
                <div class="flex justify-between items-center border-b pb-3">
                  <span class="text-gray-600">الاسم الكامل</span>
                  <span class="font-medium text-gray-800">{{ profile.user.name }}</span>
                </div>
                <div class="flex justify-between items-center border-b pb-3">
                  <span class="text-gray-600">البريد الإلكتروني</span>
                  <span class="font-medium text-gray-800">{{ profile.user.email }}</span>
                </div>
                <div class="flex justify-between items-center border-b pb-3">
                  <span class="text-gray-600">الدور</span>
                  <span class="font-medium text-gray-800">{{ profile.user.role }}</span>
                </div>
                <div class="flex justify-between items-center">
                  <span class="text-gray-600">حالة الحساب</span>
                  <span :class="profile.user.is_active ? 'text-emerald-600 font-medium' : 'text-red-600 font-medium'">
                    {{ profile.user.is_active ? 'مفعل' : 'غير مفعل' }}
                  </span>
                </div>
              </div>
            </div>

            <div class="bg-gray-50 rounded-xl p-6">
              <h3 class="text-lg font-bold text-gray-800 mb-4 flex items-center">
                <span class="ml-2"></span> معلومات النظام
              </h3>
              <div class="space-y-4">
                <div class="flex justify-between items-center border-b pb-3">
                  <span class="text-gray-600">معرف المستخدم</span>
                  <span class="font-medium text-gray-800">{{ profile.user.id }}</span>
                </div>
                <div class="flex justify-between items-center border-b pb-3">
                  <span class="text-gray-600">آخر دخول</span>
                  <span class="font-medium text-gray-800">{{ profile.user.last_login_at }}</span>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div v-if="currentTab === 2" class="fade-in">
          <div class="max-w-2xl mx-auto">
            <h3 class="text-xl font-bold text-gray-800 mb-6">تعديل معلومات الحساب</h3>
            <form @submit.prevent="updateProfile" class="space-y-6">
              <div class="bg-gray-50 p-6 rounded-xl">
                <label class="block font-bold text-gray-700 mb-2">الاسم الكامل</label>
                <input type="text" v-model="editData.name" placeholder="أدخل اسمك الكامل"
                  class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent transition"
                  required />
                <p class="text-sm text-gray-500 mt-2">الاسم الذي سيظهر في التطبيق</p>
              </div>

              <div class="bg-gray-50 p-6 rounded-xl">
                <label class="block font-bold text-gray-700 mb-2">البريد الإلكتروني</label>
                <input type="email" v-model="editData.email" placeholder="example@domain.com"
                  class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent transition"
                  required />
                <p class="text-sm text-gray-500 mt-2">سيتم استخدام هذا البريد لتلقي الإشعارات</p>
              </div>

              <div class="flex justify-end space-x-3 space-x-reverse">
                <button type="button" @click="resetEditForm"
                  class="px-6 py-3 border border-gray-300 text-gray-700 font-medium rounded-lg hover:bg-gray-50 transition">
                  إلغاء
                </button>
                <button type="submit" :disabled="isUpdatingProfile"
                  class="px-6 py-3 bg-gradient-to-r from-emerald-500 to-teal-500 text-white font-medium rounded-lg hover:from-emerald-600 hover:to-teal-600 transition flex items-center disabled:opacity-50">
                  <span v-if="isUpdatingProfile" class="ml-2">
                    <svg class="animate-spin h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none"
                      viewBox="0 0 24 24">
                      <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                      <path class="opacity-75" fill="currentColor"
                        d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                      </path>
                    </svg>
                  </span>
                  <span v-else>💾</span>
                  <span class="mr-2">{{ isUpdatingProfile ? 'جاري الحفظ...' : 'حفظ التغييرات' }}</span>
                </button>
              </div>
            </form>
          </div>
        </div>

        <div v-if="currentTab === 3" class="fade-in">
          <div class="max-w-2xl mx-auto">
            <h3 class="text-xl font-bold text-gray-800 mb-6">تغيير كلمة المرور</h3>
            <form @submit.prevent="updatePassword" class="space-y-6">
              <div class="bg-gray-50 p-6 rounded-xl">
                <label class="block font-bold text-gray-700 mb-2">كلمة المرور الحالية</label>
                <div class="relative">
                  <input :type="showCurrentPassword ? 'text' : 'password'" v-model="passwordData.current_password"
                    placeholder="أدخل كلمة المرور الحالية"
                    class="w-full border border-gray-300 rounded-lg px-4 py-3 pr-12 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent transition"
                    required />
                  <button type="button" @click="showCurrentPassword = !showCurrentPassword"
                    class="absolute inset-y-0 right-3 flex items-center text-gray-500">
                    {{ showCurrentPassword ? '👁️' : '👁️‍🗨️' }}
                  </button>

                </div>
              </div>

              <div class="bg-gray-50 p-6 rounded-xl">
                <label class="block font-bold text-gray-700 mb-2">كلمة المرور الجديدة</label>
                <div class="relative">
                  <input :type="showNewPassword ? 'text' : 'password'" v-model="passwordData.new_password"
                    placeholder="أدخل كلمة المرور الجديدة"
                    class="w-full border border-gray-300 rounded-lg px-4 py-3 pr-12 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent transition"
                    required />
                  <button type="button" @click="showNewPassword = !showNewPassword"
                    class="absolute inset-y-0 right-3 flex items-center text-gray-500 hover:text-emerald-600 transition">
                    {{ showNewPassword ? '👁️' : '👁️‍🗨️' }}
                  </button>
                </div>
                <p class="text-sm text-gray-500 mt-2">يجب أن تحتوي على 8 أحرف على الأقل</p>
              </div>

              <div class="bg-gray-50 p-6 rounded-xl">
                <label class="block font-bold text-gray-700 mb-2">تأكيد كلمة المرور الجديدة</label>
                <div class="relative">
                  <input :type="showConfirmPassword ? 'text' : 'password'" v-model="passwordData.confirm_password"
                    placeholder="أعد إدخال كلمة المرور الجديدة"
                    class="w-full border border-gray-300 rounded-lg px-4 py-3 pr-12 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent transition"
                    required />
                  <button type="button" @click="showConfirmPassword = !showConfirmPassword"
                    class="absolute inset-y-0 right-3 flex items-center text-gray-500 hover:text-emerald-600 transition">
                    {{ showConfirmPassword ? '👁️' : '👁️‍🗨️' }}
                  </button>
                </div>
              </div>

              <div class="flex justify-end space-x-3 space-x-reverse">
                <button type="button" @click="resetPasswordForm"
                  class="px-6 py-3 border border-gray-300 text-gray-700 font-medium rounded-lg hover:bg-gray-50 transition">
                  إلغاء
                </button>
                <button type="submit" :disabled="isUpdatingPassword"
                  class="px-6 py-3 bg-gradient-to-r from-emerald-500 to-teal-500 text-white font-medium rounded-lg hover:from-emerald-600 hover:to-teal-600 transition flex items-center disabled:opacity-50">
                  <span v-if="isUpdatingPassword" class="ml-2">
                    <svg class="animate-spin h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none"
                      viewBox="0 0 24 24">
                      <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                      <path class="opacity-75" fill="currentColor"
                        d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                      </path>
                    </svg>
                  </span>
                  <span v-else>🔒</span>
                  <span class="mr-2">{{ isUpdatingPassword ? 'جاري التحديث...' : 'تحديث كلمة المرور' }}</span>
                </button>
              </div>
            </form>
          </div>
        </div>
      </div>
    </div>

    <div v-if="statusMessage" class="mt-6 animate-slide-up">
      <div :class="[
        'p-4 rounded-xl border shadow-sm flex items-center',
        statusMessageType === 'success'
          ? 'bg-emerald-50 border-emerald-200 text-emerald-800'
          : 'bg-red-50 border-red-200 text-red-800'
      ]">
        <span class="ml-3 text-xl">
          {{ statusMessageType === 'success' ? '✅' : '⚠️' }}
        </span>
        <span class="flex-1">{{ statusMessage }}</span>
        <button @click="clearStatusMessage" class="text-gray-500 hover:text-gray-700">
          ✕
        </button>
      </div>
    </div>
  </div>
</template>

<script>
import { ref, onMounted, computed } from "vue";
import { getProfile, updateProfileAPI, updatePasswordAPI } from "@/services/userService";

export default {
  name: "UserProfile",
  setup() {
    const showCurrentPassword = ref(false);
    const showNewPassword = ref(false);
    const showConfirmPassword = ref(false);
    const isUpdatingProfile = ref(false);
    const isUpdatingPassword = ref(false);

    const profile = ref({
      user: {
        id: "",
        name: "",
        email: "",
        role: "",
        is_active: false,
        memory_enabled: false,
        last_login_at: "",
      },
      created_at: ""
    });

    const tabs = [
      { id: 1, label: "معلومات المستخدم", icon: ''},
      { id: 2, label: "تعديل البيانات", icon:'' },
      { id: 3, label: "تحديث كلمة المرور", icon: "" },
    ];

    const currentTab = ref(1);
    const statusMessage = ref("");
    const statusMessageType = ref("success");

    const editData = ref({
      name: "",
      email: "",
    });

    const originalEditData = ref({
      name: "",
      email: "",
    });

    const passwordData = ref({
      current_password: "",
      new_password: "",
      confirm_password: "",
    });

    const getInitials = (name) => {
      if (!name) return "U";
      return name
        .split(" ")
        .map(word => word[0])
        .join("")
        .toUpperCase()
        .substring(0, 2);
    };

    const formatDate = (dateString) => {
      console.log(dateString);
      if (!dateString) return "غير محدد";

      const cleaned = dateString.replace(/\.\d+Z$/, 'Z');

      const date = new Date(cleaned);

      if (isNaN(date.getTime())) return "غير محدد";

      return date.toLocaleDateString('ar-EG', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
      });
    };

    const fetchProfile = async () => {
      try {
        const data = await getProfile();
        profile.value = data;
        editData.value.name = data.user.name;
        editData.value.email = data.user.email;
        originalEditData.value.name = data.user.name;
        originalEditData.value.email = data.user.email;
      } catch (error) {
        console.error(error);
        showStatusMessage("غير مصرح لك بالوصول إلى هذه البيانات", "error");
      }
    };

    const updateProfile = async () => {
      if (editData.value.name === originalEditData.value.name &&
        editData.value.email === originalEditData.value.email) {
        showStatusMessage("لم تقم بإجراء أي تغييرات", "error");
        return;
      }

      isUpdatingProfile.value = true;
      try {
        const data = await updateProfileAPI(editData.value);
        showStatusMessage(data.message || "تم تحديث بياناتك بنجاح", "success");
        fetchProfile();
      } catch (error) {
        console.error(error);
        showStatusMessage("حدث خطأ أثناء تحديث البيانات", "error");
      } finally {
        isUpdatingProfile.value = false;
      }
    };

    const updatePassword = async () => {
      if (passwordData.value.new_password !== passwordData.value.confirm_password) {
        showStatusMessage("كلمة المرور الجديدة وتأكيدها غير متطابقين", "error");
        return;
      }

      if (passwordData.value.new_password.length < 8) {
        showStatusMessage("كلمة المرور الجديدة يجب أن تحتوي على 8 أحرف على الأقل", "error");
        return;
      }

      isUpdatingPassword.value = true;
      try {
        const data = await updatePasswordAPI(passwordData.value);
        showStatusMessage(data.message || "تم تحديث كلمة المرور بنجاح", "success");
        resetPasswordForm();
      } catch (error) {
        console.error(error);
        showStatusMessage("حدث خطأ أثناء تحديث كلمة المرور", "error");
      } finally {
        isUpdatingPassword.value = false;
      }
    };
    const resetEditForm = () => {
      editData.value.name = originalEditData.value.name;
      editData.value.email = originalEditData.value.email;
    };

    const resetPasswordForm = () => {
      passwordData.value.current_password = "";
      passwordData.value.new_password = "";
      passwordData.value.confirm_password = "";
    };

    const showStatusMessage = (message, type = "success") => {
      statusMessage.value = message;
      statusMessageType.value = type;
      setTimeout(() => {
        if (statusMessage.value === message) {
          statusMessage.value = "";
        }
      }, 5000);
    };

    const clearStatusMessage = () => {
      statusMessage.value = "";
    };

    onMounted(fetchProfile);

    return {
      profile,
      tabs,
      currentTab,
      editData,
      passwordData,
      statusMessage,
      statusMessageType,
      showCurrentPassword,
      showNewPassword,
      showConfirmPassword,
      isUpdatingProfile,
      isUpdatingPassword,
      updateProfile,
      updatePassword,
      resetEditForm,
      resetPasswordForm,
      clearStatusMessage,
      getInitials,
      formatDate,
    };
  },
};
</script>

<style scoped>
.fade-in {
  animation: fadeIn 0.3s ease-in-out;
}

@keyframes fadeIn {
  from {
    opacity: 0;
    transform: translateY(10px);
  }

  to {
    opacity: 1;
    transform: translateY(0);
  }
}

.animate-slide-up {
  animation: slideUp 0.3s ease-out;
}

@keyframes slideUp {
  from {
    opacity: 0;
    transform: translateY(20px);
  }

  to {
    opacity: 1;
    transform: translateY(0);
  }
}

/* تخصيص شريط التمرير للعلامات */
::-webkit-scrollbar {
  height: 6px;
}

::-webkit-scrollbar-track {
  background: #f1f1f1;
  border-radius: 10px;
}

::-webkit-scrollbar-thumb {
  background: #c1c1c1;
  border-radius: 10px;
}

::-webkit-scrollbar-thumb:hover {
  background: #a1a1a1;
}
</style>