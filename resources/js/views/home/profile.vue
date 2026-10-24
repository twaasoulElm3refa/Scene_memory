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

                <WeeklyStatsCard class="mt-5" :stats="weeklyStats" @refresh="fetchProfile" />
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
                <ProfileGiftsEarnings v-if="currentTab === 5" />

                <!-- TAB 6: أرصدتي للوسائط (My Media Credits) -->
                <div v-if="currentTab === 6">
                    <!-- Summary Cards -->
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                        <div class="bg-gradient-to-br from-blue-50 to-indigo-50 border border-blue-100 rounded-2xl p-6 shadow-sm">
                            <div class="flex items-center justify-between mb-3">
                                <span class="text-sm font-semibold text-blue-800">{{ $t('mediaCredits.photosAvailable') }}</span>
                                <div class="w-10 h-10 rounded-full bg-blue-500 text-white flex items-center justify-center shadow">
                                    <i class="bi bi-camera-fill text-lg"></i>
                                </div>
                            </div>
                            <div class="text-3xl font-extrabold text-blue-900">{{ creditStats.total_photos }}</div>
                            <p class="text-xs text-blue-600 mt-2">{{ $t('mediaCredits.photoQuotaDesc') }}</p>
                        </div>

                        <div class="bg-gradient-to-br from-purple-50 to-pink-50 border border-purple-100 rounded-2xl p-6 shadow-sm">
                            <div class="flex items-center justify-between mb-3">
                                <span class="text-sm font-semibold text-purple-800">{{ $t('mediaCredits.videosAvailable') }}</span>
                                <div class="w-10 h-10 rounded-full bg-purple-500 text-white flex items-center justify-center shadow">
                                    <i class="bi bi-camera-reels-fill text-lg"></i>
                                </div>
                            </div>
                            <div class="text-3xl font-extrabold text-purple-900">{{ creditStats.total_videos }}</div>
                            <p class="text-xs text-purple-600 mt-2">{{ $t('mediaCredits.videoQuotaDesc') }}</p>
                        </div>

                        <div class="bg-gradient-to-br from-emerald-50 to-teal-50 border border-emerald-100 rounded-2xl p-6 shadow-sm flex flex-col justify-between">
                            <div>
                                <div class="flex items-center justify-between mb-3">
                                    <span class="text-sm font-semibold text-emerald-800">{{ $t('mediaCredits.activeTier') }}</span>
                                    <span class="px-3 py-1 bg-emerald-600 text-white text-xs font-bold rounded-full uppercase">
                                        {{ creditStats.highest_tier || $t('mediaCredits.noTier') }}
                                    </span>
                                </div>
                                <div class="text-lg font-bold text-emerald-900">
                                    {{ creditStats.grants?.length || 0 }} {{ $t('mediaCredits.activeGrants') }}
                                </div>
                            </div>
                            <div class="mt-4 flex gap-2">
                                <router-link :to="`/${routeLang}/plans`" class="flex-1 text-center py-2 px-3 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold rounded-lg transition">
                                    {{ $t('mediaCredits.getMoreCredits') }}
                                </router-link>
                                <router-link :to="`/${routeLang}/all_events`" class="flex-1 text-center py-2 px-3 bg-white border border-emerald-600 text-emerald-700 hover:bg-emerald-50 text-xs font-bold rounded-lg transition">
                                    {{ $t('mediaCredits.browseMarketplace') }}
                                </router-link>
                            </div>
                        </div>
                    </div>

                    <!-- Active Grants Section -->
                    <div class="bg-gray-50 rounded-2xl p-6 mb-8 border border-gray-100">
                        <h3 class="text-lg font-bold text-gray-800 mb-4 flex items-center gap-2">
                            <i class="bi bi-shield-check text-emerald-600"></i>
                            {{ $t('mediaCredits.activeGrants') }}
                        </h3>
                        <div v-if="creditStats.grants && creditStats.grants.length > 0" class="overflow-x-auto">
                            <table class="min-w-full text-sm">
                                <thead>
                                    <tr class="text-left text-gray-500 border-b">
                                        <th class="pb-3">{{ $t('mediaCredits.plan') }}</th>
                                        <th class="pb-3">{{ $t('mediaCredits.tier') }}</th>
                                        <th class="pb-3">{{ $t('mediaCredits.photos') }}</th>
                                        <th class="pb-3">{{ $t('mediaCredits.videos') }}</th>
                                        <th class="pb-3">{{ $t('mediaCredits.payoutCap') }}</th>
                                        <th class="pb-3">{{ $t('mediaCredits.grantedOn') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200">
                                    <tr v-for="grant in creditStats.grants" :key="grant.id" class="hover:bg-white transition">
                                        <td class="py-3 font-semibold text-gray-800">{{ grant.plan?.name || grant.tier_name }}</td>
                                        <td class="py-3">
                                            <span class="px-2 py-0.5 bg-blue-100 text-blue-800 rounded font-medium text-xs">
                                                {{ grant.tier_name }} (Rank {{ grant.tier_rank }})
                                            </span>
                                        </td>
                                        <td class="py-3">
                                            <span class="font-bold text-blue-600">{{ grant.remaining_photos }}</span>
                                            <span class="text-gray-400 text-xs"> / {{ grant.photo_count }}</span>
                                        </td>
                                        <td class="py-3">
                                            <span class="font-bold text-purple-600">{{ grant.remaining_videos }}</span>
                                            <span class="text-gray-400 text-xs"> / {{ grant.video_count }}</span>
                                        </td>
                                        <td class="py-3 text-xs text-gray-600">
                                            ${{ grant.payout_cap_photo }} / ${{ grant.payout_cap_video }}
                                        </td>
                                        <td class="py-3 text-xs text-gray-500">{{ formatDate(grant.created_at) }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <p v-else class="text-gray-500 text-center py-6">
                            {{ $t('mediaCredits.noGrants') }}
                        </p>
                    </div>

                    <!-- Redemption History Ledger -->
                    <div class="bg-gray-50 rounded-2xl p-6 border border-gray-100">
                        <h3 class="text-lg font-bold text-gray-800 mb-4 flex items-center gap-2">
                            <i class="bi bi-clock-history text-indigo-600"></i>
                            {{ $t('mediaCredits.redemptionHistory') }}
                        </h3>
                        <div v-if="creditHistory && creditHistory.length > 0" class="divide-y divide-gray-200">
                            <div v-for="item in creditHistory" :key="item.id" class="py-3 flex items-center justify-between">
                                <div class="flex items-center gap-3">
                                    <div class="w-9 h-9 rounded-full flex items-center justify-center"
                                        :class="item.media_type === 'video' ? 'bg-purple-100 text-purple-600' : 'bg-blue-100 text-blue-600'">
                                        <i :class="item.media_type === 'video' ? 'bi bi-camera-reels' : 'bi bi-camera'"></i>
                                    </div>
                                    <div>
                                        <p class="font-semibold text-gray-800 mb-0">
                                            {{ item.snapshot?.media_title || item.snapshot?.plan_name || (item.media_type === 'video' ? 'Video' : 'Photo') }}
                                        </p>
                                        <p class="text-xs text-gray-500 mb-0">
                                            {{ formatDate(item.created_at) }} •
                                            <span class="capitalize">{{ item.action }}</span>
                                        </p>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <span :class="item.quantity < 0 ? 'text-red-600 font-bold' : 'text-emerald-600 font-bold'">
                                        {{ item.quantity > 0 ? '+' : '' }}{{ item.quantity }}
                                    </span>
                                    <div v-if="item.action === 'redemption'" class="text-xs text-emerald-600 font-medium">
                                        {{ $t('mediaCredits.redeemed') }}
                                    </div>
                                </div>
                            </div>
                        </div>
                        <p v-else class="text-gray-500 text-center py-6">
                            {{ $t('mediaCredits.noRedemptions') }}
                        </p>
                    </div>
                </div>

                <!-- TAB 7: الحساب والخصوصية (Account & Privacy / Data Deletion) -->
                <div v-if="currentTab === 7" class="space-y-8">
                    <!-- Danger Zone Card -->
                    <div class="bg-red-50/50 border border-red-200 rounded-2xl p-6 sm:p-8 shadow-sm">
                        <div class="flex items-start gap-4 mb-6">
                            <div class="w-12 h-12 rounded-xl bg-red-100 text-red-600 flex items-center justify-center text-2xl flex-shrink-0">
                                <i class="bi bi-shield-x"></i>
                            </div>
                            <div>
                                <h3 class="text-xl font-bold text-red-900 mb-1">
                                    {{ $t('dataDeletion.profile.cardTitle') }}
                                </h3>
                                <p class="text-sm text-red-700 leading-relaxed mb-0">
                                    {{ $t('dataDeletion.profile.cardDesc') }}
                                </p>
                            </div>
                        </div>

                        <!-- Warning List -->
                        <div class="bg-white/80 border border-red-100 rounded-xl p-5 mb-6">
                            <h4 class="text-sm font-bold text-red-800 mb-3 flex items-center gap-2">
                                <i class="bi bi-exclamation-triangle-fill"></i>
                                {{ $t('dataDeletion.profile.warningHeader') }}
                            </h4>
                            <ul class="space-y-2 text-sm text-gray-700 list-disc list-inside">
                                <li v-for="(point, idx) in warningPoints" :key="idx">
                                    {{ point }}
                                </li>
                            </ul>
                        </div>

                        <!-- Active Deletion Status Box (if exists) -->
                        <div v-if="activeDeletionRequest && activeDeletionRequest.status !== 'cancelled' && activeDeletionRequest.status !== 'failed'"
                             class="bg-white border border-amber-200 rounded-xl p-6 mb-6">
                            <div class="flex items-center justify-between mb-3">
                                <span class="text-sm font-semibold text-gray-600">
                                    {{ $t('dataDeletion.statusResult.reference') }}:
                                    <strong class="font-mono text-gray-900">{{ activeDeletionRequest.reference }}</strong>
                                </span>
                                <span :class="[
                                    'px-3 py-1 rounded-full text-xs font-bold uppercase',
                                    activeDeletionRequest.status === 'completed' ? 'bg-emerald-100 text-emerald-800' :
                                    activeDeletionRequest.status === 'under_review' ? 'bg-amber-100 text-amber-800' :
                                    'bg-blue-100 text-blue-800'
                                ]">
                                    {{ $t('dataDeletion.statusResult.statuses.' + activeDeletionRequest.status, activeDeletionRequest.status) }}
                                </span>
                            </div>

                            <p v-if="activeDeletionRequest.financial_obligations_detected" class="text-sm text-amber-700 mb-3">
                                <i class="bi bi-info-circle-fill me-1"></i>
                                {{ activeDeletionRequest.financial_hold_reason || $t('dataDeletion.statusResult.financialNotice') }}
                            </p>

                            <div class="flex items-center gap-3 mt-4">
                                <button v-if="['pending_confirmation', 'pending', 'under_review'].includes(activeDeletionRequest.status)"
                                        @click="handleCancelDeletion"
                                        :disabled="isCancellingDeletion"
                                        type="button"
                                        class="px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50 transition">
                                    {{ isCancellingDeletion ? '...' : $t('dataDeletion.profile.cancelButton') }}
                                </button>
                                <button v-if="activeDeletionRequest.status === 'pending_confirmation'"
                                        @click="openDeleteModal"
                                        type="button"
                                        class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-lg text-sm font-medium transition">
                                    {{ $t('dataDeletion.profile.confirmDeleteButton') }}
                                </button>
                            </div>
                        </div>

                        <!-- Initiate Deletion Trigger Button (when no active request) -->
                        <div v-else>
                            <button @click="openDeleteModal"
                                    type="button"
                                    class="px-6 py-3 bg-red-600 hover:bg-red-700 text-white rounded-xl font-bold shadow transition flex items-center gap-2">
                                <i class="bi bi-trash3-fill"></i>
                                <span>{{ $t('dataDeletion.profile.deleteButton') }}</span>
                            </button>
                        </div>

                        <!-- Link to Public Instructions Page -->
                        <div class="mt-6 pt-4 border-t border-red-200/60 flex items-center justify-between text-xs text-gray-500">
                            <span>Scemory Platform & Meta Compliance</span>
                            <RouterLink :to="'/' + (locale.value || locale || 'en') + '/data-deletion'" class="text-red-700 hover:underline font-semibold flex items-center gap-1">
                                <span>{{ $t('footer.dataDeletion') }}</span>
                                <i class="bi bi-box-arrow-up-right"></i>
                            </RouterLink>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Account & Data Modal -->
    <div v-if="isDeleteModalOpen" class="fixed inset-0 z-50 overflow-y-auto bg-black/60 flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl max-w-lg w-full p-6 sm:p-8 shadow-2xl border border-gray-100 relative">
            <button @click="closeDeleteModal" class="absolute top-4 end-4 text-gray-400 hover:text-gray-600 text-xl">
                <i class="bi bi-x-lg"></i>
            </button>

            <div class="flex items-center gap-3 text-red-600 mb-4">
                <div class="w-10 h-10 rounded-full bg-red-100 flex items-center justify-center text-xl">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                </div>
                <h3 class="text-lg font-bold text-gray-900 mb-0">
                    {{ $t('dataDeletion.profile.modalTitle') }}
                </h3>
            </div>

            <p class="text-sm text-gray-600 mb-5 leading-relaxed">
                {{ $t('dataDeletion.profile.modalDesc') }}
            </p>

            <div v-if="deletionErrorMessage" class="p-3 mb-4 rounded-lg bg-red-50 text-red-700 text-sm border border-red-200">
                {{ deletionErrorMessage }}
            </div>

            <div class="space-y-4">
                <!-- Typed Confirmation: DELETE -->
                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase mb-1">
                        {{ $t('dataDeletion.profile.typeDeletePrompt') }}
                    </label>
                    <input v-model="deleteConfirmPhrase"
                           type="text"
                           class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:border-red-500 focus:outline-none font-mono"
                           :placeholder="$t('dataDeletion.profile.typeDeletePlaceholder')" />
                </div>

                <!-- Credential Check (Password or Code) -->
                <div v-if="!activeDeletionRequest?.requires_code">
                    <label class="block text-xs font-bold text-gray-700 uppercase mb-1">
                        {{ $t('dataDeletion.profile.passwordPrompt') }}
                    </label>
                    <input v-model="deletePassword"
                           type="password"
                           class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:border-red-500 focus:outline-none"
                           :placeholder="$t('dataDeletion.profile.passwordPlaceholder')" />
                </div>

                <div v-if="activeDeletionRequest?.requires_code">
                    <div class="flex justify-between items-center mb-1">
                        <label class="block text-xs font-bold text-gray-700 uppercase">
                            {{ $t('dataDeletion.profile.codePrompt') }}
                        </label>
                        <button @click="handleResendCode" type="button" class="text-xs text-blue-600 hover:underline">
                            {{ $t('dataDeletion.profile.resendCode') }}
                        </button>
                    </div>
                    <input v-model="deleteCode"
                           type="text"
                           maxlength="6"
                           class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:border-red-500 focus:outline-none font-mono text-center tracking-widest text-lg"
                           :placeholder="$t('dataDeletion.profile.codePlaceholder')" />
                </div>

                <!-- Reason (Optional) -->
                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase mb-1">
                        Reason (Optional)
                    </label>
                    <textarea v-model="deleteReason"
                              rows="2"
                              class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:border-red-500 focus:outline-none text-sm"
                              placeholder="Why are you leaving? (optional)"></textarea>
                </div>
            </div>

            <div class="flex items-center justify-end gap-3 mt-6 pt-4 border-t border-gray-100">
                <button @click="closeDeleteModal"
                        type="button"
                        class="px-4 py-2.5 rounded-xl border border-gray-300 text-sm font-medium text-gray-700 hover:bg-gray-50 transition">
                    {{ $t('dataDeletion.profile.cancelModalButton') }}
                </button>
                <button @click="handleInitiateOrConfirmDeletion"
                        :disabled="deleteConfirmPhrase.trim().toUpperCase() !== 'DELETE' || isSubmittingDeletion"
                        type="button"
                        class="px-5 py-2.5 rounded-xl bg-red-600 hover:bg-red-700 disabled:opacity-50 disabled:cursor-not-allowed text-sm font-bold text-white transition flex items-center gap-2">
                    <span v-if="isSubmittingDeletion" class="spinner-border spinner-border-sm" role="status"></span>
                    <span>{{ isSubmittingDeletion ? $t('dataDeletion.profile.processing') : $t('dataDeletion.profile.confirmDeleteButton') }}</span>
                </button>
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
import WeeklyStatsCard from "@/components/profile/WeeklyStatsCard.vue";
import ProfileGiftsEarnings from "@/components/profile/ProfileGiftsEarnings.vue";
import { PlanService } from "@/services/planService/planService";
import {
    submitDataDeletionRequest,
    confirmDataDeletionRequest,
    getDataDeletionStatus,
    resendDataDeletionCode,
    cancelDataDeletionRequest,
} from "@/services/userDataDeletionService";
import { clearAuthToken, clearGoogleOAuthVerifier } from "@/services/authTokenStorage";

export default {
    name: "UserProfile",
    components: { WeeklyStatsCard, ProfileGiftsEarnings },
    setup() {
        const router = useRouter();
        const route = useRoute();
        const { t, tm, rt, locale } = useI18n();

        const warningPoints = computed(() => {
            try {
                const points = tm("dataDeletion.profile.warningPoints");
                if (Array.isArray(points)) {
                    return points.map((p) => (typeof p === "string" ? p : (typeof rt === "function" ? rt(p) : String(p))));
                }
            } catch (e) {
                console.error("Error reading warning points:", e);
            }
            return [];
        });

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
        const weeklyStats = ref({
            weekly_points: 0,
            weekly_rank: null,
            week_start: "", week_end: "",
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

        const routeLang = computed(() => (route.params.lang || "en").toLowerCase());

        const creditStats = ref({
            total_photos: 0,
            total_videos: 0,
            highest_tier: null,
            grants: []
        });
        const creditHistory = ref([]);

        const fetchCreditsData = async () => {
            try {
                const [statsRes, historyRes] = await Promise.all([
                    PlanService.getUserCredits(),
                    PlanService.getCreditHistory()
                ]);
                if (statsRes.data?.data) {
                    creditStats.value = statsRes.data.data;
                }
                if (historyRes.data?.data) {
                    creditHistory.value = historyRes.data.data.data || historyRes.data.data || [];
                }
            } catch (e) {
                console.error("Error fetching credit data:", e);
            }
        };

        const tabs = computed(() => [
            { id: 1, label: t("profilePage.tabs.userInfo") },
            { id: 2, label: t("profilePage.tabs.editData") },
            { id: 3, label: t("profilePage.tabs.password") },
            { id: 4, label: t("profilePage.tabs.wallet") },
            { id: 5, label: t("gifts.history") },
            { id: 6, label: t("mediaCredits.myCredits") },
            { id: 7, label: t("profilePage.tabs.privacy") }
        ]);

        const currentTab = ref(
            route.query.tab === 'privacy' ? 7 :
            route.query.tab === 'credits' ? 6 :
            (route.query.tab === 'gifts' ? 5 : 1)
        );
        watch(() => route.query.tab, (value) => {
            if (value === 'privacy') currentTab.value = 7;
            else if (value === 'credits') currentTab.value = 6;
            else if (value === 'gifts') currentTab.value = 5;
        });

        watch(currentTab, (val) => {
            if (val === 6) {
                fetchCreditsData();
            } else if (val === 7) {
                fetchDeletionStatus();
            }
        });
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
                    weeklyStats.value = {
                        ...weeklyStats.value,
                        ...(res?.weekly_statistics || {}),
                    };

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

        // Data Deletion State
        const activeDeletionRequest = ref(null);
        const isDeleteModalOpen = ref(false);
        const deleteConfirmPhrase = ref("");
        const deletePassword = ref("");
        const deleteCode = ref("");
        const deleteReason = ref("");
        const deletionErrorMessage = ref("");
        const isSubmittingDeletion = ref(false);
        const isCancellingDeletion = ref(false);

        const fetchDeletionStatus = async () => {
            try {
                const res = await getDataDeletionStatus();
                activeDeletionRequest.value = res?.data || null;
            } catch (err) {
                console.error("Failed to fetch deletion status:", err);
            }
        };

        const openDeleteModal = () => {
            isDeleteModalOpen.value = true;
            deleteConfirmPhrase.value = "";
            deletePassword.value = "";
            deleteCode.value = "";
            deleteReason.value = "";
            deletionErrorMessage.value = "";
        };

        const closeDeleteModal = () => {
            isDeleteModalOpen.value = false;
            deletionErrorMessage.value = "";
        };

        const handleInitiateOrConfirmDeletion = async () => {
            if (deleteConfirmPhrase.value.trim().toUpperCase() !== "DELETE") {
                deletionErrorMessage.value = t("dataDeletion.profile.typeDeletePrompt");
                return;
            }

            isSubmittingDeletion.value = true;
            deletionErrorMessage.value = "";

            try {
                if (!activeDeletionRequest.value || activeDeletionRequest.value.status === 'cancelled' || activeDeletionRequest.value.status === 'failed') {
                    const submitRes = await submitDataDeletionRequest({
                        reason: deleteReason.value,
                        confirmation_phrase: "DELETE"
                    });
                    activeDeletionRequest.value = submitRes.data;

                    if (submitRes.data.requires_code && !deleteCode.value) {
                        isSubmittingDeletion.value = false;
                        return;
                    }
                }

                if (activeDeletionRequest.value.requires_code && !deleteCode.value) {
                    deletionErrorMessage.value = t("dataDeletion.profile.codePrompt");
                    isSubmittingDeletion.value = false;
                    return;
                }

                const confirmRes = await confirmDataDeletionRequest({
                    password: deletePassword.value || undefined,
                    code: deleteCode.value || undefined,
                    confirmation_phrase: "DELETE"
                });

                const updated = confirmRes.data;
                activeDeletionRequest.value = updated;

                if (updated.status === 'completed') {
                    await clearAuthToken();
                    await clearGoogleOAuthVerifier();
                    localStorage.removeItem("admin_token");
                    localStorage.removeItem("admin_user");
                    localStorage.removeItem("user_role");
                    localStorage.removeItem("licence_name");
                    localStorage.removeItem("is_profile_filled");
                    closeDeleteModal();
                    router.push(`/${locale.value}/home`);
                } else if (updated.status === 'under_review') {
                    closeDeleteModal();
                }
            } catch (err) {
                const apiError = err?.response?.data?.message || err?.response?.data?.errors?.password?.[0] || err?.response?.data?.errors?.code?.[0] || err?.message;
                deletionErrorMessage.value = apiError || "An error occurred while processing your request.";
            } finally {
                isSubmittingDeletion.value = false;
            }
        };

        const handleCancelDeletion = async () => {
            isCancellingDeletion.value = true;
            try {
                const res = await cancelDataDeletionRequest();
                activeDeletionRequest.value = res.data;
            } catch (err) {
                console.error("Failed to cancel deletion request:", err);
            } finally {
                isCancellingDeletion.value = false;
            }
        };

        const handleResendCode = async () => {
            try {
                await resendDataDeletionCode();
                deletionErrorMessage.value = "";
            } catch (err) {
                deletionErrorMessage.value = err?.response?.data?.message || "Failed to resend code.";
            }
        };

        onMounted(() => {
            fetchProfile();
            fetchCreditsData();
            if (currentTab.value === 7) {
                fetchDeletionStatus();
            }
            document.addEventListener("click", handleClickOutsideCountry);
        });

        onUnmounted(() => {
            document.removeEventListener("click", handleClickOutsideCountry);
        });

        return {
            route,
            routeLang,
            creditStats,
            creditHistory,
            fetchCreditsData,
            profile,
            weeklyStats,
            fetchProfile,
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
            locale,
            activeDeletionRequest,
            isDeleteModalOpen,
            deleteConfirmPhrase,
            deletePassword,
            deleteCode,
            deleteReason,
            deletionErrorMessage,
            isSubmittingDeletion,
            isCancellingDeletion,
            openDeleteModal,
            closeDeleteModal,
            handleInitiateOrConfirmDeletion,
            handleCancelDeletion,
            handleResendCode,
            fetchDeletionStatus,
            warningPoints,
            tm,
            rt,
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
