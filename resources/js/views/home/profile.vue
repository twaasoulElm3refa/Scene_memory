<template>
    <div class="scemory-page profile-page max-w-4xl mx-auto p-4 md:p-8">
        <!-- Header -->
        <div class="mb-8 text-center">
            <h1 class="text-3xl font-bold text-gray-800 mb-2">{{ $t('profilePage.title') }}</h1>
            <p class="text-gray-600">{{ $t('profilePage.subtitle') }}</p>
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
                                    {{ profile.licenceType?.name ?? $t('profilePage.noPlan') }}
                                </span>
                            </div>
                        </div>
                    </div>
                    <div class="text-right">
                        <p class="text-gray-600 text-sm">{{ $t('profilePage.lastLogin') }}</p>
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
                            <h3 class="text-lg font-bold mb-4">{{ $t('profilePage.accountInfo') }}</h3>
                            <div class="space-y-3">
                                <div class="flex justify-between border-b pb-2">
                                    <span class="text-gray-600">{{ $t('profilePage.fields.name') }}</span>
                                    <span class="font-medium">{{ profile.name || '—' }}</span>
                                </div>
                                <div class="flex justify-between border-b pb-2">
                                    <span class="text-gray-600">{{ $t('profilePage.fields.email') }}</span>
                                    <span class="font-medium">{{ profile.email || '—' }}</span>
                                </div>
                                <div class="flex justify-between border-b pb-2">
                                    <span class="text-gray-600">{{ $t('profilePage.fields.phone') }}</span>
                                    <span class="font-medium">{{ profile.phone ?? $t('common.notSpecified') }}</span>
                                </div>
                                <div class="flex justify-between border-b pb-2">
                                    <span class="text-gray-600">{{ $t('profilePage.fields.country') }}</span>
                                    <span class="font-medium">{{ profile.country ?? $t('common.notSpecified') }}</span>
                                </div>
                                <div class="flex justify-between border-b pb-2">
                                    <span class="text-gray-600">{{ $t('profilePage.fields.position') }}</span>
                                    <span class="font-medium">{{ profile.position ?? $t('common.notSpecified') }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">{{ $t('profilePage.fields.birthDate') }}</span>
                                    <span class="font-medium">{{ profile.date_of_birth ?
                                        formatDate(profile.date_of_birth) : $t('common.notSpecified') }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- System Info -->
                        <div class="bg-gray-50 rounded-xl p-6">
                            <h3 class="text-lg font-bold mb-4">{{ $t('profilePage.systemInfo') }}</h3>
                            <div class="space-y-3">
                                <div class="flex justify-between border-b pb-2">
                                    <span class="text-gray-600">ID</span>
                                    <span class="font-medium">{{ profile.id || '—' }}</span>
                                </div>
                                <div class="flex justify-between border-b pb-2">
                                    <span class="text-gray-600">{{ $t('profilePage.fields.role') }}</span>
                                    <span class="font-medium">{{ profile.role || '—' }}</span>
                                </div>
                                <div class="flex justify-between border-b pb-2">
                                    <span class="text-gray-600">{{ $t('profilePage.fields.status') }}</span>
                                    <span class="font-medium text-green-600">{{ $t('profilePage.status.active') }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">{{ $t('profilePage.fields.points') }}</span>
                                    <span class="font-medium text-green-600">{{ profile.points || '—' }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Licence -->
                    <div class="mt-6 bg-emerald-50 p-6 rounded-xl border border-emerald-200">
                        <h3 class="font-bold mb-4 text-emerald-800">{{ $t('profilePage.currentPlan') }}</h3>
                        <div class="space-y-3">
                            <div class="flex justify-between">
                                <span class="text-gray-600">{{ $t('profilePage.planType') }}</span>
                                <span class="font-bold">{{ profile.licenceType?.name ?? $t('common.notSpecified') }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">{{ $t('profilePage.fields.price') }}</span>
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
                        <h3 class="text-xl font-bold mb-6">{{ $t('profilePage.editAccount') }}</h3>
                        <div class="space-y-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">{{ $t('profilePage.fields.fullName') }}</label>
                                <input v-model="editData.name" type="text"
                                    class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:border-emerald-500 transition"
                                    :placeholder="$t('profilePage.placeholders.fullName')" />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">{{ $t('profilePage.fields.email') }}</label>
                                <input v-model="editData.email" type="email"
                                    class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:border-emerald-500 transition"
                                    placeholder="example@email.com" />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">{{ $t('profilePage.fields.phoneNumber') }}</label>
                                <input v-model="editData.phone" type="tel"
                                    class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:border-emerald-500 transition"
                                    placeholder="+20 123 456 789" />
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div class="relative" ref="countryDropdownRef" dir="rtl">
                                    <label class="block text-sm font-medium text-gray-700 mb-1">
                                        {{ $t('profilePage.fields.country') }}
                                    </label>

                                    <div class="relative">
                                        <input v-model="countrySearch" type="text"
                                            class="w-full px-4 py-3 pr-10 border border-gray-300 rounded-xl focus:outline-none focus:border-emerald-500 transition"
                                            :placeholder="$t('profilePage.placeholders.countrySearch')" autocomplete="off"
                                            @focus="isCountryDropdownOpen = true"
                                            @input="isCountryDropdownOpen = true" />

                                        <button type="button"
                                            class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-emerald-600"
                                            @click.stop="isCountryDropdownOpen = !isCountryDropdownOpen">
                                            ▼
                                        </button>
                                    </div>

                                    <div v-if="isCountryDropdownOpen"
                                        class="absolute z-50 mt-2 w-full max-h-64 overflow-y-auto bg-white border border-gray-200 rounded-xl shadow-xl">
                                        <button v-for="country in filteredCountries" :key="country.id" type="button"
                                            class="w-full px-4 py-3 text-right hover:bg-emerald-50 flex items-center justify-between gap-3 border-b last:border-b-0"
                                            @click="selectCountry(country)">
                                            <span class="font-medium text-gray-800">
                                                {{ country.name }}
                                            </span>

                                            <span
                                                class="text-xs font-bold text-emerald-700 bg-emerald-100 px-2 py-1 rounded-full">
                                                {{ country.code }}
                                            </span>
                                        </button>

                                        <div v-if="filteredCountries.length === 0"
                                            class="px-4 py-4 text-center text-gray-500">
                                            {{ $t('common.noResults') }}
                                        </div>
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ $t('profilePage.fields.positionJob') }}</label>
                                    <input v-model="editData.position" type="text"
                                        class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:border-emerald-500 transition"
                                        :placeholder="$t('profilePage.placeholders.position')" />
                                </div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">{{ $t('profilePage.fields.birthDate') }}</label>
                                <input v-model="editData.date_of_birth" type="date"
                                    class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:border-emerald-500 transition" />
                            </div>
                        </div>
                        <div class="mt-8 flex justify-center w-full">
                            <button @click="updateProfile" :disabled="isUpdatingProfile"
                                class="bg-emerald-600 hover:bg-emerald-700 text-white font-medium py-3.5 px-8 rounded-xl transition disabled:opacity-70">
                                {{ isUpdatingProfile ? $t('profilePage.saving') : $t('profilePage.saveChanges') }}
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
                        <h3 class="text-xl font-bold mb-6">{{ $t('profilePage.password.title') }}</h3>
                        <div class="space-y-6">
                            <div class="relative">
                                <label class="block text-sm font-medium text-gray-700 mb-1">{{ $t('profilePage.password.current') }}</label>
                                <input v-model="passwordData.current_password"
                                    :type="showPassword ? 'text' : 'password'"
                                    class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:border-emerald-500 pr-12" />
                                <button type="button" @click="showPassword = !showPassword"
                                    class="absolute right-3 top-10 text-gray-500">
                                    {{ showPassword ? $t('common.hide') : $t('common.show') }}
                                </button>
                            </div>
                            <div class="relative">
                                <label class="block text-sm font-medium text-gray-700 mb-1">{{ $t('profilePage.password.new') }}</label>
                                <input v-model="passwordData.new_password" :type="showNewPassword ? 'text' : 'password'"
                                    class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:border-emerald-500 pr-12" />
                                <button type="button" @click="showNewPassword = !showNewPassword"
                                    class="absolute right-3 top-10 text-gray-500">
                                    {{ showNewPassword ? $t('common.hide') : $t('common.show') }}
                                </button>
                            </div>
                            <div class="relative">
                                <label class="block text-sm font-medium text-gray-700 mb-1">{{ $t('profilePage.password.confirm') }}</label>
                                <input v-model="passwordData.confirm_password"
                                    :type="showConfirmPassword ? 'text' : 'password'"
                                    class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:border-emerald-500 pr-12" />
                                <button type="button" @click="showConfirmPassword = !showConfirmPassword"
                                    class="absolute right-3 top-10 text-gray-500">
                                    {{ showConfirmPassword ? $t('common.hide') : $t('common.show') }}
                                </button>
                            </div>
                        </div>
                        <div class="mt-8 flex gap-4">
                            <button @click="updatePassword" :disabled="isUpdatingPassword"
                                class="flex-1 bg-emerald-600 hover:bg-emerald-700 text-white font-medium py-3.5 rounded-xl transition disabled:opacity-70">
                                {{ isUpdatingPassword ? $t('profilePage.password.updating') : $t('profilePage.password.update') }}
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
                        <h3 class="text-xl font-bold mb-6 text-center">{{ $t('profilePage.wallet.title') }}</h3>

                        <!-- Wallet Card -->
                        <div
                            class="bg-gradient-to-r from-emerald-500 to-teal-600 text-white p-8 rounded-3xl shadow-lg mb-8">
                            <div class="flex justify-between items-start">
                                <div>
                                    <p class="text-sm opacity-90">{{ $t('profilePage.wallet.availableBalance') }}</p>
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
                                <span>{{ $t('profilePage.wallet.deposit') }}</span>
                            </button>
                        </div>

                        <!-- Transactions -->
                        <div class="bg-gray-50 p-6 rounded-2xl">
                            <h4 class="font-bold mb-5 text-lg">{{ $t('profilePage.wallet.recentTransactions') }}</h4>
                            <div v-if="transactions.length > 0" class="space-y-3">
                                <div v-for="tx in transactions" :key="tx.id"
                                    class="flex justify-between items-center bg-white p-4 rounded-xl shadow-sm">
                                    <div>
                                        <p class="font-medium">{{ tx.type === 'deposit' ? $t('profilePage.wallet.depositType') : $t('profilePage.wallet.withdrawType') }}</p>
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
                                {{ $t('profilePage.wallet.noTransactions') }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import { ref, onMounted, onUnmounted, computed, watch } from "vue";
import { getProfile, updateProfileAPI } from "@/services/userService/userService";
import { updatePasswordAPI } from "@/services/userService/userService";
import { useRouter, useRoute } from "vue-router";
import { useI18n } from "vue-i18n";

export default {
    name: "UserProfile",
    setup() {
        const router = useRouter();
        const route = useRoute();
        const { t, locale } = useI18n();
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

        const countries = ref([
            { id: 1, name: 'Afghanistan', code: 'AF', slug: 'afghanistan' },
            { id: 2, name: 'Albania', code: 'AL', slug: 'albania' },
            { id: 3, name: 'Algeria', code: 'DZ', slug: 'algeria' },
            { id: 4, name: 'Andorra', code: 'AD', slug: 'andorra' },
            { id: 5, name: 'Angola', code: 'AO', slug: 'angola' },
            { id: 6, name: 'Antigua and Barbuda', code: 'AG', slug: 'antigua-and-barbuda' },
            { id: 7, name: 'Argentina', code: 'AR', slug: 'argentina' },
            { id: 8, name: 'Armenia', code: 'AM', slug: 'armenia' },
            { id: 9, name: 'Australia', code: 'AU', slug: 'australia' },
            { id: 10, name: 'Austria', code: 'AT', slug: 'austria' },
            { id: 11, name: 'Azerbaijan', code: 'AZ', slug: 'azerbaijan' },
            { id: 12, name: 'Bahamas', code: 'BS', slug: 'bahamas' },
            { id: 13, name: 'Bahrain', code: 'BH', slug: 'bahrain' },
            { id: 14, name: 'Bangladesh', code: 'BD', slug: 'bangladesh' },
            { id: 15, name: 'Barbados', code: 'BB', slug: 'barbados' },
            { id: 16, name: 'Belarus', code: 'BY', slug: 'belarus' },
            { id: 17, name: 'Belgium', code: 'BE', slug: 'belgium' },
            { id: 18, name: 'Belize', code: 'BZ', slug: 'belize' },
            { id: 19, name: 'Benin', code: 'BJ', slug: 'benin' },
            { id: 20, name: 'Bhutan', code: 'BT', slug: 'bhutan' },
            { id: 21, name: 'Bolivia', code: 'BO', slug: 'bolivia' },
            { id: 22, name: 'Bosnia and Herzegovina', code: 'BA', slug: 'bosnia-and-herzegovina' },
            { id: 23, name: 'Botswana', code: 'BW', slug: 'botswana' },
            { id: 24, name: 'Brazil', code: 'BR', slug: 'brazil' },
            { id: 25, name: 'Brunei', code: 'BN', slug: 'brunei' },
            { id: 26, name: 'Bulgaria', code: 'BG', slug: 'bulgaria' },
            { id: 27, name: 'Burkina Faso', code: 'BF', slug: 'burkina-faso' },
            { id: 28, name: 'Burundi', code: 'BI', slug: 'burundi' },
            { id: 29, name: 'Cabo Verde', code: 'CV', slug: 'cabo-verde' },
            { id: 30, name: 'Cambodia', code: 'KH', slug: 'cambodia' },
            { id: 31, name: 'Cameroon', code: 'CM', slug: 'cameroon' },
            { id: 32, name: 'Canada', code: 'CA', slug: 'canada' },
            { id: 33, name: 'Central African Republic', code: 'CF', slug: 'central-african-republic' },
            { id: 34, name: 'Chad', code: 'TD', slug: 'chad' },
            { id: 35, name: 'Chile', code: 'CL', slug: 'chile' },
            { id: 36, name: 'China', code: 'CN', slug: 'china' },
            { id: 37, name: 'Colombia', code: 'CO', slug: 'colombia' },
            { id: 38, name: 'Comoros', code: 'KM', slug: 'comoros' },
            { id: 39, name: 'Congo', code: 'CG', slug: 'congo' },
            { id: 40, name: 'Democratic Republic of the Congo', code: 'CD', slug: 'democratic-republic-of-the-congo' },
            { id: 41, name: 'Costa Rica', code: 'CR', slug: 'costa-rica' },
            { id: 42, name: 'Croatia', code: 'HR', slug: 'croatia' },
            { id: 43, name: 'Cuba', code: 'CU', slug: 'cuba' },
            { id: 44, name: 'Cyprus', code: 'CY', slug: 'cyprus' },
            { id: 45, name: 'Czech Republic', code: 'CZ', slug: 'czech-republic' },
            { id: 46, name: 'Denmark', code: 'DK', slug: 'denmark' },
            { id: 47, name: 'Djibouti', code: 'DJ', slug: 'djibouti' },
            { id: 48, name: 'Dominica', code: 'DM', slug: 'dominica' },
            { id: 49, name: 'Dominican Republic', code: 'DO', slug: 'dominican-republic' },
            { id: 50, name: 'Ecuador', code: 'EC', slug: 'ecuador' },
            { id: 51, name: 'Egypt', code: 'EG', slug: 'egypt' },
            { id: 52, name: 'El Salvador', code: 'SV', slug: 'el-salvador' },
            { id: 53, name: 'Equatorial Guinea', code: 'GQ', slug: 'equatorial-guinea' },
            { id: 54, name: 'Eritrea', code: 'ER', slug: 'eritrea' },
            { id: 55, name: 'Estonia', code: 'EE', slug: 'estonia' },
            { id: 56, name: 'Ethiopia', code: 'ET', slug: 'ethiopia' },
            { id: 57, name: 'Fiji', code: 'FJ', slug: 'fiji' },
            { id: 58, name: 'Finland', code: 'FI', slug: 'finland' },
            { id: 59, name: 'France', code: 'FR', slug: 'france' },
            { id: 60, name: 'Gabon', code: 'GA', slug: 'gabon' },
            { id: 61, name: 'Gambia', code: 'GM', slug: 'gambia' },
            { id: 62, name: 'Georgia', code: 'GE', slug: 'georgia' },
            { id: 63, name: 'Germany', code: 'DE', slug: 'germany' },
            { id: 64, name: 'Ghana', code: 'GH', slug: 'ghana' },
            { id: 65, name: 'Greece', code: 'GR', slug: 'greece' },
            { id: 66, name: 'Grenada', code: 'GD', slug: 'grenada' },
            { id: 67, name: 'Guatemala', code: 'GT', slug: 'guatemala' },
            { id: 68, name: 'Guinea', code: 'GN', slug: 'guinea' },
            { id: 69, name: 'Guinea-Bissau', code: 'GW', slug: 'guinea-bissau' },
            { id: 70, name: 'Guyana', code: 'GY', slug: 'guyana' },
            { id: 71, name: 'Haiti', code: 'HT', slug: 'haiti' },
            { id: 72, name: 'Honduras', code: 'HN', slug: 'honduras' },
            { id: 73, name: 'Hungary', code: 'HU', slug: 'hungary' },
            { id: 74, name: 'Iceland', code: 'IS', slug: 'iceland' },
            { id: 75, name: 'India', code: 'IN', slug: 'india' },
            { id: 76, name: 'Indonesia', code: 'ID', slug: 'indonesia' },
            { id: 77, name: 'Iran', code: 'IR', slug: 'iran' },
            { id: 78, name: 'Iraq', code: 'IQ', slug: 'iraq' },
            { id: 79, name: 'Ireland', code: 'IE', slug: 'ireland' },
            { id: 80, name: 'Italy', code: 'IT', slug: 'italy' },
            { id: 81, name: 'Jamaica', code: 'JM', slug: 'jamaica' },
            { id: 82, name: 'Japan', code: 'JP', slug: 'japan' },
            { id: 83, name: 'Jordan', code: 'JO', slug: 'jordan' },
            { id: 84, name: 'Kazakhstan', code: 'KZ', slug: 'kazakhstan' },
            { id: 85, name: 'Kenya', code: 'KE', slug: 'kenya' },
            { id: 86, name: 'North Korea', code: 'KP', slug: 'north-korea' },
            { id: 87, name: 'South Korea', code: 'KR', slug: 'south-korea' },
            { id: 88, name: 'Kuwait', code: 'KW', slug: 'kuwait' },
            { id: 89, name: 'Kyrgyzstan', code: 'KG', slug: 'kyrgyzstan' },
            { id: 90, name: 'Laos', code: 'LA', slug: 'laos' },
            { id: 91, name: 'Latvia', code: 'LV', slug: 'latvia' },
            { id: 92, name: 'Lebanon', code: 'LB', slug: 'lebanon' },
            { id: 93, name: 'Lesotho', code: 'LS', slug: 'lesotho' },
            { id: 94, name: 'Liberia', code: 'LR', slug: 'liberia' },
            { id: 95, name: 'Libya', code: 'LY', slug: 'libya' },
            { id: 96, name: 'Liechtenstein', code: 'LI', slug: 'liechtenstein' },
            { id: 97, name: 'Lithuania', code: 'LT', slug: 'lithuania' },
            { id: 98, name: 'Luxembourg', code: 'LU', slug: 'luxembourg' },
            { id: 99, name: 'Madagascar', code: 'MG', slug: 'madagascar' },
            { id: 100, name: 'Malawi', code: 'MW', slug: 'malawi' },
            { id: 101, name: 'Malaysia', code: 'MY', slug: 'malaysia' },
            { id: 102, name: 'Maldives', code: 'MV', slug: 'maldives' },
            { id: 103, name: 'Mali', code: 'ML', slug: 'mali' },
            { id: 104, name: 'Malta', code: 'MT', slug: 'malta' },
            { id: 105, name: 'Marshall Islands', code: 'MH', slug: 'marshall-islands' },
            { id: 106, name: 'Mauritania', code: 'MR', slug: 'mauritania' },
            { id: 107, name: 'Mauritius', code: 'MU', slug: 'mauritius' },
            { id: 108, name: 'Mexico', code: 'MX', slug: 'mexico' },
            { id: 109, name: 'Micronesia', code: 'FM', slug: 'micronesia' },
            { id: 110, name: 'Moldova', code: 'MD', slug: 'moldova' },
            { id: 111, name: 'Monaco', code: 'MC', slug: 'monaco' },
            { id: 112, name: 'Mongolia', code: 'MN', slug: 'mongolia' },
            { id: 113, name: 'Montenegro', code: 'ME', slug: 'montenegro' },
            { id: 114, name: 'Morocco', code: 'MA', slug: 'morocco' },
            { id: 115, name: 'Mozambique', code: 'MZ', slug: 'mozambique' },
            { id: 116, name: 'Myanmar', code: 'MM', slug: 'myanmar' },
            { id: 117, name: 'Namibia', code: 'NA', slug: 'namibia' },
            { id: 118, name: 'Nauru', code: 'NR', slug: 'nauru' },
            { id: 119, name: 'Nepal', code: 'NP', slug: 'nepal' },
            { id: 120, name: 'Netherlands', code: 'NL', slug: 'netherlands' },
            { id: 121, name: 'New Zealand', code: 'NZ', slug: 'new-zealand' },
            { id: 122, name: 'Nicaragua', code: 'NI', slug: 'nicaragua' },
            { id: 123, name: 'Niger', code: 'NE', slug: 'niger' },
            { id: 124, name: 'Nigeria', code: 'NG', slug: 'nigeria' },
            { id: 125, name: 'North Macedonia', code: 'MK', slug: 'north-macedonia' },
            { id: 126, name: 'Norway', code: 'NO', slug: 'norway' },
            { id: 127, name: 'Oman', code: 'OM', slug: 'oman' },
            { id: 128, name: 'Pakistan', code: 'PK', slug: 'pakistan' },
            { id: 129, name: 'Palau', code: 'PW', slug: 'palau' },
            { id: 130, name: 'Palestine', code: 'PS', slug: 'palestine' },
            { id: 131, name: 'Panama', code: 'PA', slug: 'panama' },
            { id: 132, name: 'Papua New Guinea', code: 'PG', slug: 'papua-new-guinea' },
            { id: 133, name: 'Paraguay', code: 'PY', slug: 'paraguay' },
            { id: 134, name: 'Peru', code: 'PE', slug: 'peru' },
            { id: 135, name: 'Philippines', code: 'PH', slug: 'philippines' },
            { id: 136, name: 'Poland', code: 'PL', slug: 'poland' },
            { id: 137, name: 'Portugal', code: 'PT', slug: 'portugal' },
            { id: 138, name: 'Qatar', code: 'QA', slug: 'qatar' },
            { id: 139, name: 'Romania', code: 'RO', slug: 'romania' },
            { id: 140, name: 'Russia', code: 'RU', slug: 'russia' },
            { id: 141, name: 'Rwanda', code: 'RW', slug: 'rwanda' },
            { id: 142, name: 'Saint Kitts and Nevis', code: 'KN', slug: 'saint-kitts-and-nevis' },
            { id: 143, name: 'Saint Lucia', code: 'LC', slug: 'saint-lucia' },
            { id: 144, name: 'Saint Vincent and the Grenadines', code: 'VC', slug: 'saint-vincent-and-the-grenadines' },
            { id: 145, name: 'Samoa', code: 'WS', slug: 'samoa' },
            { id: 146, name: 'San Marino', code: 'SM', slug: 'san-marino' },
            { id: 147, name: 'Sao Tome and Principe', code: 'ST', slug: 'sao-tome-and-principe' },
            { id: 148, name: 'Saudi Arabia', code: 'SA', slug: 'saudi-arabia' },
            { id: 149, name: 'Senegal', code: 'SN', slug: 'senegal' },
            { id: 150, name: 'Serbia', code: 'RS', slug: 'serbia' },
            { id: 151, name: 'Seychelles', code: 'SC', slug: 'seychelles' },
            { id: 152, name: 'Sierra Leone', code: 'SL', slug: 'sierra-leone' },
            { id: 153, name: 'Singapore', code: 'SG', slug: 'singapore' },
            { id: 154, name: 'Slovakia', code: 'SK', slug: 'slovakia' },
            { id: 155, name: 'Slovenia', code: 'SI', slug: 'slovenia' },
            { id: 156, name: 'Solomon Islands', code: 'SB', slug: 'solomon-islands' },
            { id: 157, name: 'Somalia', code: 'SO', slug: 'somalia' },
            { id: 158, name: 'South Africa', code: 'ZA', slug: 'south-africa' },
            { id: 159, name: 'South Sudan', code: 'SS', slug: 'south-sudan' },
            { id: 160, name: 'Spain', code: 'ES', slug: 'spain' },
            { id: 161, name: 'Sri Lanka', code: 'LK', slug: 'sri-lanka' },
            { id: 162, name: 'Sudan', code: 'SD', slug: 'sudan' },
            { id: 163, name: 'Suriname', code: 'SR', slug: 'suriname' },
            { id: 164, name: 'Sweden', code: 'SE', slug: 'sweden' },
            { id: 165, name: 'Switzerland', code: 'CH', slug: 'switzerland' },
            { id: 166, name: 'Syria', code: 'SY', slug: 'syria' },
            { id: 167, name: 'Tajikistan', code: 'TJ', slug: 'tajikistan' },
            { id: 168, name: 'Tanzania', code: 'TZ', slug: 'tanzania' },
            { id: 169, name: 'Thailand', code: 'TH', slug: 'thailand' },
            { id: 170, name: 'Timor-Leste', code: 'TL', slug: 'timor-leste' },
            { id: 171, name: 'Togo', code: 'TG', slug: 'togo' },
            { id: 172, name: 'Tonga', code: 'TO', slug: 'tonga' },
            { id: 173, name: 'Trinidad and Tobago', code: 'TT', slug: 'trinidad-and-tobago' },
            { id: 174, name: 'Tunisia', code: 'TN', slug: 'tunisia' },
            { id: 175, name: 'Turkey', code: 'TR', slug: 'turkey' },
            { id: 176, name: 'Turkmenistan', code: 'TM', slug: 'turkmenistan' },
            { id: 177, name: 'Tuvalu', code: 'TV', slug: 'tuvalu' },
            { id: 178, name: 'Uganda', code: 'UG', slug: 'uganda' },
            { id: 179, name: 'Ukraine', code: 'UA', slug: 'ukraine' },
            { id: 180, name: 'United Arab Emirates', code: 'AE', slug: 'united-arab-emirates' },
            { id: 181, name: 'United Kingdom', code: 'GB', slug: 'united-kingdom' },
            { id: 182, name: 'United States of America', code: 'US', slug: 'united-states-of-america' },
            { id: 183, name: 'Uruguay', code: 'UY', slug: 'uruguay' },
            { id: 184, name: 'Uzbekistan', code: 'UZ', slug: 'uzbekistan' },
            { id: 185, name: 'Vanuatu', code: 'VU', slug: 'vanuatu' },
            { id: 186, name: 'Venezuela', code: 'VE', slug: 'venezuela' },
            { id: 187, name: 'Vietnam', code: 'VN', slug: 'vietnam' },
            { id: 188, name: 'Yemen', code: 'YE', slug: 'yemen' },
            { id: 189, name: 'Zambia', code: 'ZM', slug: 'zambia' },
            { id: 190, name: 'Zimbabwe', code: 'ZW', slug: 'zimbabwe' },
            { id: 191, name: 'Vatican City', code: 'VA', slug: 'vatican-city' },
            { id: 192, name: 'Eswatini', code: 'SZ', slug: 'eswatini' },
        ]);
        const countrySearch = ref("");
        const isCountryDropdownOpen = ref(false);
        const countryDropdownRef = ref(null);

        const filteredCountries = computed(() => {
            const search = String(countrySearch.value || "").trim().toLowerCase();

            if (!search) return countries.value;

            return countries.value.filter((country) => {
                return (
                    country.name.toLowerCase().includes(search) ||
                    country.code.toLowerCase().includes(search) ||
                    country.slug.toLowerCase().includes(search)
                );
            });
        });

        const selectCountry = (country) => {
            editData.value.country = country.name;
            countrySearch.value = country.name;
            isCountryDropdownOpen.value = false;
        };

        const handleClickOutsideCountry = (event) => {
            if (!countryDropdownRef.value) return;

            if (!countryDropdownRef.value.contains(event.target)) {
                isCountryDropdownOpen.value = false;
            }
        };

        watch(countrySearch, (value) => {
            const selectedCountry = countries.value.find(
                (country) => country.name.toLowerCase() === String(value || "").toLowerCase()
            );

            editData.value.country = selectedCountry ? selectedCountry.name : "";
        });

        const originalEditData = ref({});

        const tabs = computed(() => [
            { id: 1, label: t("profilePage.tabs.userInfo") },
            { id: 2, label: t("profilePage.tabs.editData") },
            { id: 3, label: t("profilePage.tabs.password") },
            { id: 4, label: t("profilePage.tabs.wallet") }
        ]);

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
                passwordStatusMessage.value = t("profilePage.messages.fillAllFields");
                passwordStatusType.value = "error";
                return;
            }
            if (passwordData.value.new_password !== passwordData.value.confirm_password) {
                passwordStatusMessage.value = t("profilePage.messages.passwordMismatch");
                passwordStatusType.value = "error";
                return;
            }

            isUpdatingPassword.value = true;
            try {
                const res = await updatePasswordAPI(passwordData.value);
                passwordStatusMessage.value = res.message || t("profilePage.messages.passwordUpdated");
                passwordStatusType.value = "success";
                passwordData.value = { current_password: "", new_password: "", confirm_password: "" };
            } catch (error) {
                passwordStatusMessage.value = error.response?.data?.message || t("profilePage.messages.passwordUpdateFailed");
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
                    countrySearch.value = userData.country || "";

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
            if (!dateString) return t("common.notSpecified");
            try {
                const date = new Date(dateString);
                if (isNaN(date.getTime())) return t("common.notSpecified");
                return date.toLocaleDateString(locale.value || "en", {
                    year: "numeric",
                    month: "long",
                    day: "numeric",
                });
            } catch {
                return t("common.notSpecified");
            }
        };

        const updateProfile = async () => {
            const selectedCountry = countries.value.find(
                (country) => country.name === editData.value.country
            );

            if (!selectedCountry && countrySearch.value) {
                showStatusMessage(t("profilePage.messages.chooseCountry"), "error");
                return;
            }

            if (JSON.stringify(editData.value) === JSON.stringify(originalEditData.value)) {
                showStatusMessage(t("profilePage.messages.noChanges"), "error");
                return;
            }

            isUpdatingProfile.value = true;
            try {
                const payload = { ...editData.value };
                if (!payload.date_of_birth) payload.date_of_birth = null;

                const response = await updateProfileAPI(payload);
                showStatusMessage(response.message || t("profilePage.messages.profileUpdated"), "success");
                await fetchProfile();
            } catch (error) {
                showStatusMessage(error.response?.data?.message || t("profilePage.messages.profileUpdateFailed"), "error");
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
            alert(t("profilePage.wallet.withdrawComingSoon"));
        };

        onMounted(() => {
            fetchProfile();
            document.addEventListener("click", handleClickOutsideCountry);
        });

        onUnmounted(() => {
            document.removeEventListener("click", handleClickOutsideCountry);
        });

        return {
            profile,
            editData,
            originalEditData,
            countries,
            countrySearch,
            isCountryDropdownOpen,
            countryDropdownRef,
            filteredCountries,
            tabs,
            t,
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
            selectCountry,
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

.profile-page {
    background:
        radial-gradient(circle at top left, rgba(48, 168, 255, 0.10), transparent 30rem),
        linear-gradient(180deg, #FFFFFF, #F8FAFC);
    max-width: 1100px;
}

.profile-page h1,
.profile-page h2,
.profile-page h3 {
    color: #06142A;
}

.profile-page .bg-white {
    border: 1px solid #E5EDF6;
    border-radius: 24px;
    box-shadow: 0 10px 35px rgba(13, 77, 151, 0.06);
}

.profile-page .from-emerald-50,
.profile-page .to-teal-50 {
    --tw-gradient-from: #F4F8FC !important;
    --tw-gradient-to: #EEF5FC !important;
}

.profile-page .from-emerald-500,
.profile-page .to-teal-500 {
    --tw-gradient-from: #0D4D97 !important;
    --tw-gradient-to: #1677FF !important;
}

.profile-page input,
.profile-page select,
.profile-page textarea {
    border-color: #DCE8F5;
    border-radius: 14px;
}

.profile-page input:focus,
.profile-page select:focus,
.profile-page textarea:focus {
    border-color: #1677FF;
    box-shadow: 0 0 0 4px rgba(22, 119, 255, 0.10);
}
</style>
