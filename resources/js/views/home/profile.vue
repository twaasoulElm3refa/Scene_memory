<template>
    <div class="max-w-4xl mx-auto p-4 md:p-8">
        <!-- Header -->
        <div class="mb-8 text-center">
            <h1 class="text-3xl font-bold text-gray-800 mb-2">ملفي الشخصي</h1>
            <p class="text-gray-600">إدارة معلومات حسابك وتحديث كلمة المرور</p>
        </div>

        <!-- Card -->
        <div class="bg-white rounded-2xl shadow-xl overflow-hidden border border-gray-100">
            <!-- Profile Header -->
            <div class="bg-gradient-to-r from-emerald-50 to-teal-50 p-6 border-b">
                <div class="flex flex-col md:flex-row md:items-center justify-between">
                    <div class="flex items-center mb-4 md:mb-0">
                        <div
                            class="w-16 h-16 rounded-full bg-gradient-to-r from-emerald-500 to-teal-500 flex items-center justify-center text-white text-2xl font-bold mr-4 shadow-md">
                            {{ getInitials(profile.name) }}
                        </div>
                        <div>
                            <h2 class="text-xl font-bold text-gray-800">{{ profile.name || '—' }}</h2>
                            <p class="text-gray-600">{{ profile.email || '—' }}</p>
                            <div class="flex items-center mt-1 gap-2">
                                <span
                                    class="px-3 py-1 bg-emerald-100 text-emerald-800 text-sm font-medium rounded-full">
                                    {{ profile.role || '—' }}
                                </span>
                                <span class="px-3 py-1 bg-blue-100 text-blue-800 text-sm font-medium rounded-full">
                                    {{ profile.licenceType?.name ?? 'No Plan' }}
                                </span>
                            </div>
                        </div>
                    </div>
                    <div class="text-right">
                        <p class="text-gray-600 text-sm">آخر دخول</p>
                        <p class="text-gray-800 font-medium">
                            {{ formatDate(profile.last_login_at) }}
                        </p>
                    </div>
                </div>
            </div>

            <!-- Tabs -->
            <div class="flex overflow-x-auto border-b">
                <button v-for="tab in tabs" :key="tab.id" @click="currentTab = tab.id" :class="[
                    'flex-1 min-w-[150px] py-4 px-6 font-medium transition-colors',
                    currentTab === tab.id
                        ? 'border-b-2 border-emerald-500 text-emerald-600 bg-emerald-50/50'
                        : 'text-gray-500 hover:text-emerald-500 hover:bg-gray-50'
                ]">
                    {{ tab.label }}
                </button>
            </div>

            <!-- Content -->
            <div class="p-6 md:p-8">
                <!-- TAB 1: معلومات المستخدم -->
                <div v-if="currentTab === 1">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Account Info -->
                        <div class="bg-gray-50 rounded-xl p-6">
                            <h3 class="text-lg font-bold mb-4">معلومات الحساب</h3>
                            <div class="space-y-3">
                                <div class="flex justify-between border-b pb-2">
                                    <span class="text-gray-600">الاسم</span>
                                    <span class="font-medium">{{ profile.name || '—' }}</span>
                                </div>
                                <div class="flex justify-between border-b pb-2">
                                    <span class="text-gray-600">الإيميل</span>
                                    <span class="font-medium">{{ profile.email || '—' }}</span>
                                </div>
                                <div class="flex justify-between border-b pb-2">
                                    <span class="text-gray-600">الهاتف</span>
                                    <span class="font-medium">{{ profile.phone ?? 'غير متوفر' }}</span>
                                </div>
                                <div class="flex justify-between border-b pb-2">
                                    <span class="text-gray-600">الدولة</span>
                                    <span class="font-medium">{{ profile.country ?? 'غير متوفر' }}</span>
                                </div>
                                <div class="flex justify-between border-b pb-2">
                                    <span class="text-gray-600">المنصب</span>
                                    <span class="font-medium">{{ profile.position ?? 'غير متوفر' }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">تاريخ الميلاد</span>
                                    <span class="font-medium">{{ profile.date_of_birth ?
                                        formatDate(profile.date_of_birth) : 'غير متوفر' }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- System Info -->
                        <div class="bg-gray-50 rounded-xl p-6">
                            <h3 class="text-lg font-bold mb-4">معلومات النظام</h3>
                            <div class="space-y-3">
                                <div class="flex justify-between border-b pb-2">
                                    <span class="text-gray-600">ID</span>
                                    <span class="font-medium">{{ profile.id || '—' }}</span>
                                </div>
                                <div class="flex justify-between border-b pb-2">
                                    <span class="text-gray-600">الدور</span>
                                    <span class="font-medium">{{ profile.role || '—' }}</span>
                                </div>
                                <div class="flex justify-between border-b pb-2">
                                    <span class="text-gray-600">الحالة</span>
                                    <span class="font-medium text-green-600">نشط</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">النقاط</span>
                                    <span class="font-medium text-green-600">{{ profile.points || '—' }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Licence -->
                    <div class="mt-6 bg-emerald-50 p-6 rounded-xl border border-emerald-200">
                        <h3 class="font-bold mb-4 text-emerald-800">الباقة الحالية</h3>
                        <div class="space-y-3">
                            <div class="flex justify-between">
                                <span class="text-gray-600">نوع الباقة</span>
                                <span class="font-bold">{{ profile.licenceType?.name ?? 'غير متوفر' }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">السعر</span>
                                <span class="font-bold text-emerald-700">
                                    {{ profile.licenceType?.price ? profile.licenceType.price + ' $' : '—' }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- TAB 2: تعديل البيانات -->
                <div v-if="currentTab === 2">
                    <div class="max-w-2xl mx-auto">
                        <h3 class="text-xl font-bold mb-6">تعديل بيانات الحساب</h3>
                        <div class="space-y-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">الاسم الكامل</label>
                                <input v-model="editData.name" type="text"
                                    class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:border-emerald-500 transition"
                                    placeholder="أدخل اسمك الكامل" />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">البريد الإلكتروني</label>
                                <input v-model="editData.email" type="email"
                                    class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:border-emerald-500 transition"
                                    placeholder="example@email.com" />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">رقم الهاتف</label>
                                <input v-model="editData.phone" type="tel"
                                    class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:border-emerald-500 transition"
                                    placeholder="+20 123 456 789" />
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">الدولة</label>
                                    <input v-model="editData.country" type="text"
                                        class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:border-emerald-500 transition"
                                        placeholder="مصر" />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">المنصب / الوظيفة</label>
                                    <input v-model="editData.position" type="text"
                                        class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:border-emerald-500 transition"
                                        placeholder="مهندس برمجيات" />
                                </div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">تاريخ الميلاد</label>
                                <input v-model="editData.date_of_birth" type="date"
                                    class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:border-emerald-500 transition" />
                            </div>
                        </div>
                        <div class="mt-8 flex justify-center w-full">
                            <button @click="updateProfile" :disabled="isUpdatingProfile"
                                class="bg-emerald-600 hover:bg-emerald-700 text-white font-medium py-3.5 px-8 rounded-xl transition disabled:opacity-70">
                                {{ isUpdatingProfile ? 'جاري الحفظ...' : 'حفظ التغييرات' }}
                            </button>
                        </div>
                        <div v-if="statusMessage" :class="[
                            'mt-6 p-4 rounded-xl text-center font-medium',
                            statusMessageType === 'success' ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-700'
                        ]">
                            {{ statusMessage }}
                        </div>
                    </div>
                </div>

                <!-- TAB 3: تحديث كلمة المرور -->
                <div v-if="currentTab === 3">
                    <div class="max-w-2xl mx-auto">
                        <h3 class="text-xl font-bold mb-6">تحديث كلمة المرور</h3>
                        <div class="space-y-6">
                            <div class="relative">
                                <label class="block text-sm font-medium text-gray-700 mb-1">كلمة المرور الحالية</label>
                                <input v-model="passwordData.current_password"
                                    :type="showPassword ? 'text' : 'password'"
                                    class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:border-emerald-500 pr-12" />
                                <button type="button" @click="showPassword = !showPassword"
                                    class="absolute right-3 top-10 text-gray-500">
                                    {{ showPassword ? '🚫👁️' : '👁️' }}
                                </button>
                            </div>
                            <div class="relative">
                                <label class="block text-sm font-medium text-gray-700 mb-1">كلمة المرور الجديدة</label>
                                <input v-model="passwordData.new_password" :type="showNewPassword ? 'text' : 'password'"
                                    class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:border-emerald-500 pr-12" />
                                <button type="button" @click="showNewPassword = !showNewPassword"
                                    class="absolute right-3 top-10 text-gray-500">
                                    {{ showNewPassword ? '🚫👁️' : '👁️' }}
                                </button>
                            </div>
                            <div class="relative">
                                <label class="block text-sm font-medium text-gray-700 mb-1">تأكيد كلمة المرور</label>
                                <input v-model="passwordData.confirm_password"
                                    :type="showConfirmPassword ? 'text' : 'password'"
                                    class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:border-emerald-500 pr-12" />
                                <button type="button" @click="showConfirmPassword = !showConfirmPassword"
                                    class="absolute right-3 top-10 text-gray-500">
                                    {{ showConfirmPassword ? '🚫👁️' : '👁️' }}
                                </button>
                            </div>
                        </div>
                        <div class="mt-8 flex gap-4">
                            <button @click="updatePassword" :disabled="isUpdatingPassword"
                                class="flex-1 bg-emerald-600 hover:bg-emerald-700 text-white font-medium py-3.5 rounded-xl transition disabled:opacity-70">
                                {{ isUpdatingPassword ? 'جاري التحديث...' : 'تحديث كلمة المرور' }}
                            </button>
                        </div>
                        <div v-if="passwordStatusMessage" :class="[
                            'mt-6 p-4 rounded-xl text-center font-medium',
                            passwordStatusType === 'success' ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-700'
                        ]">
                            {{ passwordStatusMessage }}
                        </div>
                    </div>
                </div>

                <!-- TAB 4: المحفظة (Wallet) - المُحدث -->
                <div v-if="currentTab === 4">
                    <div class="max-w-3xl mx-auto">
                        <h3 class="text-xl font-bold mb-6 text-center">المحفظة</h3>

                        <!-- Wallet Card -->
                        <div
                            class="bg-gradient-to-r from-emerald-500 to-teal-600 text-white p-8 rounded-3xl shadow-lg mb-8">
                            <div class="flex justify-between items-start">
                                <div>
                                    <p class="text-sm opacity-90">الرصيد المتاح</p>
                                    <h2 class="text-5xl font-bold mt-3">
                                        {{ walletAmount }} <span class="text-2xl font-normal">$</span>
                                    </h2>
                                </div>
                                <div class="text-right">
                                    <span
                                        class="px-4 py-2 bg-white/20 backdrop-blur-sm rounded-2xl text-sm font-medium">
                                        {{ profile.wallet?.currency || 'USD' }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Actions -->
                        <div class="flex justify-center mb-8">
                            <button @click="deposit"
                                class="bg-emerald-600 hover:bg-emerald-700 text-white py-6 px-8 rounded font-medium transition flex items-center justify-center gap-2">
                                <span>شحن الرصيد</span>
                            </button>
                        </div>

                        <!-- Transactions -->
                        <div class="bg-gray-50 p-6 rounded-2xl">
                            <h4 class="font-bold mb-5 text-lg">آخر العمليات</h4>
                            <div v-if="transactions.length > 0" class="space-y-3">
                                <div v-for="tx in transactions" :key="tx.id"
                                    class="flex justify-between items-center bg-white p-4 rounded-xl shadow-sm">
                                    <div>
                                        <p class="font-medium">{{ tx.type === 'deposit' ? 'شحن' : 'سحب' }}</p>
                                        <p class="text-sm text-gray-500">{{ formatDate(tx.created_at) }}</p>
                                    </div>
                                    <span :class="[
                                        'font-bold text-lg',
                                        tx.type === 'deposit' ? 'text-green-600' : 'text-red-600'
                                    ]">
                                        {{ tx.type === 'deposit' ? '+' : '-' }} {{ tx.amount }} $
                                    </span>
                                </div>
                            </div>
                            <p v-else class="text-gray-500 text-center py-8">
                                لا توجد عمليات حتى الآن
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import { ref, onMounted, computed } from "vue";
import { getProfile, updateProfileAPI } from "@/services/userService/userService";
import { updatePasswordAPI } from "@/services/userService/userService";
import { useRouter, useRoute } from "vue-router";

export default {
    name: "UserProfile",
    setup() {
        const router = useRouter();
        const route = useRoute();
        // Profile Data
        const profile = ref({
            id: "",
            name: "",
            email: "",
            phone: null,
            country: null,
            position: null,
            points: 0,
            date_of_birth: null,
            role: "",
            last_login_at: null,
            licenceType: null,
            wallet: null,
        });

        // Edit Form Data
        const editData = ref({
            name: "",
            email: "",
            phone: "",
            country: "",
            position: "",
            date_of_birth: "",
        });

        const originalEditData = ref({});

        const tabs = [
            { id: 1, label: "معلومات المستخدم" },
            { id: 2, label: "تعديل البيانات" },
            { id: 3, label: "تحديث كلمة المرور" },
            { id: 4, label: 'المحفظة' }
        ];

        const currentTab = ref(1);
        const isUpdatingProfile = ref(false);
        const statusMessage = ref("");
        const statusMessageType = ref("success");

        const passwordData = ref({
            current_password: "",
            new_password: "",
            confirm_password: "",
        });

        const isUpdatingPassword = ref(false);
        const passwordStatusMessage = ref("");
        const passwordStatusType = ref("success");

        const showPassword = ref(false);
        const showNewPassword = ref(false);
        const showConfirmPassword = ref(false);

        // Wallet & Transactions
        const transactions = ref([]);

        // Computed Wallet Amount
        const walletAmount = computed(() => {
            if (!profile.value.wallet) return "0.00";
            return parseFloat(profile.value.wallet.amount || 0).toFixed(2);
        });

        const updatePassword = async () => {
            if (!passwordData.value.current_password || !passwordData.value.new_password || !passwordData.value.confirm_password) {
                passwordStatusMessage.value = "من فضلك املأ كل الحقول";
                passwordStatusType.value = "error";
                return;
            }
            if (passwordData.value.new_password !== passwordData.value.confirm_password) {
                passwordStatusMessage.value = "كلمة المرور غير متطابقة";
                passwordStatusType.value = "error";
                return;
            }

            isUpdatingPassword.value = true;
            try {
                const res = await updatePasswordAPI(passwordData.value);
                passwordStatusMessage.value = res.message || "تم تحديث كلمة المرور بنجاح";
                passwordStatusType.value = "success";
                passwordData.value = { current_password: "", new_password: "", confirm_password: "" };
            } catch (error) {
                passwordStatusMessage.value = error.response?.data?.message || "حدث خطأ أثناء تحديث كلمة المرور";
                passwordStatusType.value = "error";
            } finally {
                isUpdatingPassword.value = false;
            }
        };

        const getInitials = (name) => {
            if (!name) return "U";
            return name
                .split(" ")
                .map((word) => word.charAt(0))
                .join("")
                .toUpperCase()
                .substring(0, 2);
        };

        const fetchProfile = async () => {
            try {
                const res = await getProfile();
                let userData = res?.user || res?.data?.user || null;

                if (userData) {
                    profile.value = { ...userData };

                    editData.value = {
                        name: userData.name || "",
                        email: userData.email || "",
                        phone: userData.phone || "",
                        country: userData.country || "",
                        position: userData.position || "",
                        date_of_birth: userData.date_of_birth || "",
                    };

                    originalEditData.value = { ...editData.value };

                    // Initialize transactions if available in future
                    if (userData.transactions) {
                        transactions.value = userData.transactions;
                    }
                }
            } catch (error) {
                console.error("Error fetching profile:", error);
            }
        };

        const formatDate = (dateString) => {
            if (!dateString) return "غير متوفر";
            try {
                const date = new Date(dateString);
                if (isNaN(date.getTime())) return "غير متوفر";
                return date.toLocaleDateString("ar-EG", {
                    year: "numeric",
                    month: "long",
                    day: "numeric",
                });
            } catch {
                return "غير متوفر";
            }
        };

        const updateProfile = async () => {
            if (JSON.stringify(editData.value) === JSON.stringify(originalEditData.value)) {
                showStatusMessage("لم تقم بإجراء أي تغييرات", "error");
                return;
            }

            isUpdatingProfile.value = true;
            try {
                const payload = { ...editData.value };
                if (!payload.date_of_birth) payload.date_of_birth = null;

                const response = await updateProfileAPI(payload);
                showStatusMessage(response.message || "تم تحديث بياناتك بنجاح", "success");
                await fetchProfile();
            } catch (error) {
                showStatusMessage(error.response?.data?.message || "حدث خطأ أثناء تحديث البيانات", "error");
            } finally {
                isUpdatingProfile.value = false;
            }
        };

        const showStatusMessage = (message, type = "success") => {
            statusMessage.value = message;
            statusMessageType.value = type;
            setTimeout(() => {
                if (statusMessage.value === message) statusMessage.value = "";
            }, 5000);
        };

        const deposit = () => {
            const lang = route.params.lang || "en";
            router.push(`/${lang}/Deposit`);
        };

        const withdraw = () => {
            alert("سيتم فتح نافذة سحب الرصيد قريباً");
        };

        onMounted(fetchProfile);

        return {
            profile,
            editData,
            originalEditData,
            tabs,
            currentTab,
            isUpdatingProfile,
            statusMessage,
            statusMessageType,
            passwordData,
            isUpdatingPassword,
            passwordStatusMessage,
            passwordStatusType,
            showPassword,
            showNewPassword,
            showConfirmPassword,
            walletAmount,
            transactions,
            getInitials,
            formatDate,
            updateProfile,
            updatePassword,
            deposit,
            withdraw,
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
