<template>
    <AdminLayout>
        <!-- Page Title & Tabs -->
        <div class="mb-6">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-2xl font-bold text-gray-800">Plans, Media Tiers & Quota Management</h2>
                <div class="flex gap-2" v-if="activeTab === 'plans'">
                    <a href="/admin/plans/create"
                        class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-semibold flex items-center gap-2 shadow-sm text-decoration-none">
                        <span>+ Add Plan</span>
                    </a>
                </div>
                <div class="flex gap-2" v-else-if="activeTab === 'tiers'">
                    <button @click="openTierModal()"
                        class="bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-2 rounded-lg text-sm font-semibold flex items-center gap-2 shadow-sm">
                        <span>+ Add Media Tier</span>
                    </button>
                </div>
            </div>

            <!-- Tabs Navigation -->
            <div class="flex border-b border-gray-200">
                <button @click="activeTab = 'plans'"
                    :class="['px-6 py-3 font-semibold text-sm transition-colors border-b-2', activeTab === 'plans' ? 'border-blue-600 text-blue-600 bg-blue-50/50' : 'border-transparent text-gray-500 hover:text-gray-700']">
                    Subscription Plans & Quotas
                </button>
                <button @click="activeTab = 'tiers'"
                    :class="['px-6 py-3 font-semibold text-sm transition-colors border-b-2', activeTab === 'tiers' ? 'border-emerald-600 text-emerald-600 bg-emerald-50/50' : 'border-transparent text-gray-500 hover:text-gray-700']">
                    Media Tiers Configuration
                </button>
                <button @click="activeTab = 'reports'"
                    :class="['px-6 py-3 font-semibold text-sm transition-colors border-b-2', activeTab === 'reports' ? 'border-purple-600 text-purple-600 bg-purple-50/50' : 'border-transparent text-gray-500 hover:text-gray-700']">
                    Credit Reports & Analytics
                </button>
                <button @click="activeTab = 'financials'"
                    :class="['px-6 py-3 font-semibold text-sm transition-colors border-b-2', activeTab === 'financials' ? 'border-amber-600 text-amber-700 bg-amber-50/50' : 'border-transparent text-gray-500 hover:text-gray-700']">
                    Financial Assumptions
                </button>
            </div>
        </div>

        <!-- ═════════════════════════════════════════════════════════════════════ -->
        <!-- TAB 1: PLANS & QUOTAS                                               -->
        <!-- ═════════════════════════════════════════════════════════════════════ -->
        <div v-if="activeTab === 'plans'">
            <div v-if="loading" class="text-center py-12 text-gray-500">Loading plans...</div>
            <div v-else class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left font-semibold text-gray-700">ID</th>
                            <th class="px-4 py-3 text-left font-semibold text-gray-700">Plan Name</th>
                            <th class="px-4 py-3 text-left font-semibold text-gray-700">Access Tier</th>
                            <th class="px-4 py-3 text-left font-semibold text-gray-700">Photo Quota</th>
                            <th class="px-4 py-3 text-left font-semibold text-gray-700">Video Quota</th>
                            <th class="px-4 py-3 text-left font-semibold text-gray-700">Price</th>
                            <th class="px-4 py-3 text-left font-semibold text-gray-700">Financial Status</th>
                            <th class="px-4 py-3 text-left font-semibold text-gray-700">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <tr v-for="plan in plans" :key="plan.id" class="hover:bg-gray-50 transition">
                            <td class="px-4 py-3 font-mono text-gray-500">#{{ plan.id }}</td>
                            <td class="px-4 py-3 font-bold text-gray-900">{{ plan.translation?.name || plan.name }}</td>
                            <td class="px-4 py-3">
                                <span v-if="plan.media_tier" class="px-2.5 py-1 bg-blue-100 text-blue-800 rounded-full font-medium text-xs">
                                    {{ plan.media_tier.name }} (Rank {{ plan.media_tier.rank }})
                                </span>
                                <span v-else class="text-gray-400 text-xs">None</span>
                            </td>
                            <td class="px-4 py-3 font-semibold text-blue-600">{{ plan.photo_quota || 0 }} photos</td>
                            <td class="px-4 py-3 font-semibold text-purple-600">{{ plan.video_quota || 0 }} videos</td>
                            <td class="px-4 py-3 font-bold text-gray-800">${{ plan.price }}</td>
                            <td class="px-4 py-3"><span class="px-2 py-1 rounded text-xs font-bold" :class="plan.financial_status === 'safe' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'">{{ plan.financial_status || 'unconfigured' }}</span></td>
                            <td class="px-4 py-3">
                                <div class="flex gap-2">
                                    <router-link :to="`/admin/plans/${plan.id}`"
                                        class="p-1.5 bg-gray-100 hover:bg-gray-200 rounded text-gray-700" title="View Plan">
                                        👁️
                                    </router-link>
                                    <button class="p-1.5 bg-yellow-100 hover:bg-yellow-200 rounded text-yellow-800"
                                        @click="openEditModal(plan)" title="Edit Plan">
                                        ✏️
                                    </button>
                                    <button class="p-1.5 bg-red-100 hover:bg-red-200 rounded text-red-800"
                                        @click="deletePlan(plan.id)" title="Delete Plan">
                                        🗑️
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div v-if="activeTab === 'financials'" class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <div class="flex items-start justify-between gap-4 mb-6">
                <div>
                    <h3 class="text-lg font-bold text-gray-900">Versioned financial assumptions</h3>
                    <p class="text-sm text-gray-500">All values are explicit. Saving creates a new version and revalidates plan definitions without changing sold grants.</p>
                </div>
                <span class="px-3 py-1 rounded-full text-xs font-bold" :class="financialStatus.is_complete ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'">
                    {{ financialStatus.is_complete ? 'COMPLETE' : 'INCOMPLETE' }}
                </span>
            </div>
            <div v-if="financialStatus.missing?.length" class="mb-4 p-3 rounded bg-red-50 text-red-800 text-sm">Missing: {{ financialStatus.missing.join(', ') }}</div>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <label class="text-sm">Currency<input v-model="financialForm.currency" maxlength="3" class="mt-1 w-full border rounded px-3 py-2" /></label>
                <label class="text-sm">Minimum profit ($)<input v-model.number="financialForm.minimum_profit" type="number" min="0" step="0.01" class="mt-1 w-full border rounded px-3 py-2" /></label>
                <label class="text-sm">Minimum margin (bps)<input v-model.number="financialForm.minimum_margin_bps" type="number" min="0" max="10000" class="mt-1 w-full border rounded px-3 py-2" /></label>
                <label class="text-sm">Operating cost (bps)<input v-model.number="financialForm.operating_cost_bps" type="number" min="0" max="10000" class="mt-1 w-full border rounded px-3 py-2" /></label>
                <label class="text-sm">Fixed operating cost ($)<input v-model.number="financialForm.fixed_operating_cost" type="number" min="0" step="0.01" class="mt-1 w-full border rounded px-3 py-2" /></label>
                <label class="text-sm">Risk reserve (bps)<input v-model.number="financialForm.risk_reserve_bps" type="number" min="0" max="10000" class="mt-1 w-full border rounded px-3 py-2" /></label>
                <label class="text-sm">Tax (bps)<input v-model.number="financialForm.tax_bps" type="number" min="0" max="10000" class="mt-1 w-full border rounded px-3 py-2" /></label>
                <label class="text-sm">Photo allocation (bps)<input v-model.number="financialForm.photo_allocation_bps" type="number" min="0" max="10000" class="mt-1 w-full border rounded px-3 py-2" /></label>
            </div>
            <h4 class="font-bold mt-6 mb-2">Payment fees</h4>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div v-for="fee in financialForm.payment_fees" :key="fee.payment_method" class="border rounded p-3">
                    <div class="font-semibold capitalize mb-2">{{ fee.payment_method }}</div>
                    <div class="grid grid-cols-2 gap-2">
                        <label class="text-xs">Percent (bps)<input v-model.number="fee.percentage_bps" type="number" min="0" class="mt-1 w-full border rounded px-2 py-1" /></label>
                        <label class="text-xs">Fixed fee ($)<input v-model.number="fee.fixed_fee" type="number" min="0" step="0.01" class="mt-1 w-full border rounded px-2 py-1" /></label>
                    </div>
                </div>
            </div>
            <h4 class="font-bold mt-6 mb-2">Tier distribution (must total 10,000 bps)</h4>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                <label v-for="tier in mediaTiers.filter((item) => item.is_active && item.is_plan_eligible)" :key="tier.id" class="text-sm">{{ tier.name }}
                    <input v-model.number="financialForm.tier_distribution[tier.id]" type="number" min="0" max="10000" class="mt-1 w-full border rounded px-3 py-2" />
                </label>
            </div>
            <div v-if="financialError" class="mt-4 p-3 bg-red-50 text-red-800 rounded text-sm">{{ financialError }}</div>
            <div v-if="financialMessage" class="mt-4 p-3 bg-green-50 text-green-800 rounded text-sm">{{ financialMessage }}</div>
            <button @click="saveFinancials" class="mt-5 px-5 py-2 bg-amber-600 hover:bg-amber-700 text-white font-semibold rounded-lg">Save New Version & Revalidate</button>
        </div>

        <!-- ═════════════════════════════════════════════════════════════════════ -->
        <!-- TAB 2: MEDIA TIERS CONFIGURATION                                    -->
        <!-- ═════════════════════════════════════════════════════════════════════ -->
        <div v-if="activeTab === 'tiers'">
            <div v-if="loadingTiers" class="text-center py-12 text-gray-500">Loading media tiers...</div>
            <div v-else class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left font-semibold text-gray-700">Rank</th>
                            <th class="px-4 py-3 text-left font-semibold text-gray-700">Tier Name</th>
                            <th class="px-4 py-3 text-left font-semibold text-gray-700">Photo Price Range</th>
                            <th class="px-4 py-3 text-left font-semibold text-gray-700">Photo Payout Cap</th>
                            <th class="px-4 py-3 text-left font-semibold text-gray-700">Video Price Range</th>
                            <th class="px-4 py-3 text-left font-semibold text-gray-700">Video Payout Cap</th>
                            <th class="px-4 py-3 text-left font-semibold text-gray-700">Plan Eligible</th>
                            <th class="px-4 py-3 text-left font-semibold text-gray-700">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <tr v-for="tier in mediaTiers" :key="tier.id" class="hover:bg-gray-50 transition">
                            <td class="px-4 py-3 font-bold text-gray-900">#{{ tier.rank }}</td>
                            <td class="px-4 py-3 font-bold text-emerald-800">{{ tier.name }}</td>
                            <td class="px-4 py-3">${{ tier.min_photo_price }} - ${{ tier.max_photo_price }}</td>
                            <td class="px-4 py-3 font-semibold text-blue-600">${{ tier.max_photo_payout_cap }}</td>
                            <td class="px-4 py-3">${{ tier.min_video_price }} - ${{ tier.max_video_price }}</td>
                            <td class="px-4 py-3 font-semibold text-purple-600">${{ tier.max_video_payout_cap }}</td>
                            <td class="px-4 py-3">
                                <span :class="['px-2 py-0.5 rounded-full text-xs font-semibold', tier.is_plan_eligible ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600']">
                                    {{ tier.is_plan_eligible ? 'Yes' : 'No' }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex gap-2">
                                    <button class="p-1.5 bg-yellow-100 hover:bg-yellow-200 rounded text-yellow-800"
                                        @click="openTierModal(tier)" title="Edit Tier">
                                        ✏️
                                    </button>
                                    <button class="p-1.5 bg-red-100 hover:bg-red-200 rounded text-red-800"
                                        @click="deleteTier(tier.id)" title="Delete Tier">
                                        🗑️
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- ═════════════════════════════════════════════════════════════════════ -->
        <!-- TAB 3: CREDIT REPORTS & ANALYTICS                                   -->
        <!-- ═════════════════════════════════════════════════════════════════════ -->
        <div v-if="activeTab === 'reports'">
            <div v-if="loadingReports" class="text-center py-12 text-gray-500">Loading credit reports...</div>
            <div v-else>
                <!-- Overview Cards -->
                <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
                    <div class="bg-white rounded-xl p-6 border border-gray-200 shadow-sm">
                        <div class="text-xs font-bold text-gray-500 uppercase mb-2">Total Plan Grants Issued</div>
                        <div class="text-3xl font-extrabold text-gray-900">{{ reportsData.grants?.total_grants_issued || 0 }}</div>
                    </div>
                    <div class="bg-white rounded-xl p-6 border border-gray-200 shadow-sm">
                        <div class="text-xs font-bold text-blue-600 uppercase mb-2">Photo Credits Issued / Redeemed</div>
                        <div class="text-2xl font-extrabold text-blue-900">
                            {{ reportsData.grants?.photos?.redeemed || 0 }}
                            <span class="text-sm font-normal text-gray-500">/ {{ reportsData.grants?.photos?.granted || 0 }}</span>
                        </div>
                        <div class="text-xs text-gray-500 mt-1">{{ reportsData.grants?.photos?.remaining || 0 }} outstanding</div>
                    </div>
                    <div class="bg-white rounded-xl p-6 border border-gray-200 shadow-sm">
                        <div class="text-xs font-bold text-purple-600 uppercase mb-2">Video Credits Issued / Redeemed</div>
                        <div class="text-2xl font-extrabold text-purple-900">
                            {{ reportsData.grants?.videos?.redeemed || 0 }}
                            <span class="text-sm font-normal text-gray-500">/ {{ reportsData.grants?.videos?.granted || 0 }}</span>
                        </div>
                        <div class="text-xs text-gray-500 mt-1">{{ reportsData.grants?.videos?.remaining || 0 }} outstanding</div>
                    </div>
                    <div class="bg-white rounded-xl p-6 border border-gray-200 shadow-sm">
                        <div class="text-xs font-bold text-emerald-600 uppercase mb-2">Recent Creator Payouts</div>
                        <div class="text-2xl font-extrabold text-emerald-900">${{ reportsData.financials?.recent_creator_payouts_sample || 0 }}</div>
                        <div class="text-xs text-gray-500 mt-1">Savings: ${{ reportsData.financials?.recent_reference_difference_sample || 0 }}</div>
                    </div>
                </div>

                <!-- Recent Redemptions Table -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-200 font-bold text-gray-800">
                        Recent Media Redemptions Ledger
                    </div>
                    <div v-if="reportsData.recent_redemptions && reportsData.recent_redemptions.length > 0" class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left font-semibold text-gray-700">Date</th>
                                    <th class="px-4 py-3 text-left font-semibold text-gray-700">User ID</th>
                                    <th class="px-4 py-3 text-left font-semibold text-gray-700">Media ID</th>
                                    <th class="px-4 py-3 text-left font-semibold text-gray-700">Type</th>
                                    <th class="px-4 py-3 text-left font-semibold text-gray-700">Listing Price</th>
                                    <th class="px-4 py-3 text-left font-semibold text-gray-700">Creator Payout</th>
                                    <th class="px-4 py-3 text-left font-semibold text-gray-700">Savings</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <tr v-for="item in reportsData.recent_redemptions" :key="item.id" class="hover:bg-gray-50 transition">
                                    <td class="px-4 py-3 text-xs text-gray-500">{{ formatDate(item.created_at) }}</td>
                                    <td class="px-4 py-3">#{{ item.user_id }}</td>
                                    <td class="px-4 py-3">#{{ item.media_id || '—' }}</td>
                                    <td class="px-4 py-3 capitalize">{{ item.media_type }}</td>
                                    <td class="px-4 py-3 font-semibold">${{ item.snapshot?.listing_price || '—' }}</td>
                                    <td class="px-4 py-3 font-bold text-emerald-600">${{ item.snapshot?.creator_payout || 0 }}</td>
                                    <td class="px-4 py-3 text-xs text-gray-600">${{ item.snapshot?.reference_price_difference || 0 }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div v-else class="text-center py-8 text-gray-500">No redemptions recorded yet.</div>
                </div>
            </div>
        </div>

        <!-- ═════════════════════════════════════════════════════════════════════ -->
        <!-- MODAL: EDIT PLAN                                                    -->
        <!-- ═════════════════════════════════════════════════════════════════════ -->
        <div v-if="showModal" class="fixed inset-0 bg-black/60 flex items-center justify-center z-50 p-4">
            <div class="bg-white rounded-2xl shadow-xl w-full max-w-lg p-6 max-h-[90vh] overflow-y-auto">
                <h3 class="text-lg font-bold mb-4 text-gray-900">Edit Subscription Plan</h3>

                <div class="mb-4">
                    <label class="block text-xs font-semibold text-gray-700 uppercase mb-1">Plan Name</label>
                    <input v-model="editPlan.name" type="text" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
                </div>
                <div class="mb-4">
                    <label class="block text-xs font-semibold text-gray-700 uppercase mb-1">Price ($ USD)</label>
                    <input v-model.number="editPlan.price" type="number" step="0.01" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
                </div>
                <div class="mb-4">
                    <label class="block text-xs font-semibold text-gray-700 uppercase mb-1">Access Media Tier</label>
                    <select v-model="editPlan.media_tier_id" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option :value="null">-- Select Tier --</option>
                        <option v-for="t in mediaTiers" :key="t.id" :value="t.id">
                            {{ t.name }} (Rank {{ t.rank }})
                        </option>
                    </select>
                </div>
                <div class="grid grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 uppercase mb-1">Photo Quota</label>
                        <input v-model.number="editPlan.photo_quota" type="number" min="0" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 uppercase mb-1">Video Quota</label>
                        <input v-model.number="editPlan.video_quota" type="number" min="0" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
                    </div>
                </div>

                <div v-if="editPlan.tier_limits?.length" class="mb-4 border rounded-lg overflow-hidden">
                    <div class="px-3 py-2 bg-gray-50 text-xs font-bold uppercase">Tier ceilings (shared quota constraints)</div>
                    <div v-for="limit in editPlan.tier_limits" :key="limit.media_tier_id" class="grid grid-cols-3 gap-2 items-center px-3 py-2 border-t text-sm">
                        <span class="font-semibold">{{ mediaTiers.find((tier) => tier.id === limit.media_tier_id)?.name }}</span>
                        <input v-model.number="limit.photo_maximum" type="number" min="0" class="border rounded px-2 py-1" aria-label="Photo tier maximum" />
                        <input v-model.number="limit.video_maximum" type="number" min="0" class="border rounded px-2 py-1" aria-label="Video tier maximum" />
                    </div>
                </div>

                <div v-if="editPlanError" class="p-3 bg-red-50 border border-red-200 text-red-700 rounded-lg text-xs mb-4">
                    {{ editPlanError }}
                </div>

                <div class="flex justify-end gap-3 pt-4 border-t border-gray-200">
                    <button class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg text-sm font-semibold" @click="closeModal">
                        Cancel
                    </button>
                    <button class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-sm font-semibold" @click="updatePlan">
                        Save Plan
                    </button>
                </div>
            </div>
        </div>

        <!-- ═════════════════════════════════════════════════════════════════════ -->
        <!-- MODAL: ADD / EDIT MEDIA TIER                                        -->
        <!-- ═════════════════════════════════════════════════════════════════════ -->
        <div v-if="showTierModal" class="fixed inset-0 bg-black/60 flex items-center justify-center z-50 p-4">
            <div class="bg-white rounded-2xl shadow-xl w-full max-w-lg p-6 max-h-[90vh] overflow-y-auto">
                <h3 class="text-lg font-bold mb-4 text-gray-900">
                    {{ tierForm.id ? 'Edit Media Tier' : 'Add New Media Tier' }}
                </h3>

                <div class="grid grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 uppercase mb-1">Tier Name</label>
                        <input v-model="tierForm.name" type="text" placeholder="e.g. Pro" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 uppercase mb-1">Rank (Access Level)</label>
                        <input v-model.number="tierForm.rank" type="number" min="1" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" />
                    </div>
                </div>

                <div class="border-t border-gray-200 pt-3 mb-4">
                    <div class="font-bold text-xs uppercase text-blue-800 mb-2">Photo Price & Payout Rules</div>
                    <div class="grid grid-cols-3 gap-3">
                        <div>
                            <label class="block text-xs text-gray-600 mb-1">Min Price ($)</label>
                            <input v-model.number="tierForm.min_photo_price" type="number" step="0.01" class="w-full border border-gray-300 rounded-lg px-2 py-1.5 text-xs" />
                        </div>
                        <div>
                            <label class="block text-xs text-gray-600 mb-1">Max Price ($)</label>
                            <input v-model.number="tierForm.max_photo_price" type="number" step="0.01" class="w-full border border-gray-300 rounded-lg px-2 py-1.5 text-xs" />
                        </div>
                        <div>
                            <label class="block text-xs text-gray-600 mb-1">Payout Cap ($)</label>
                            <input v-model.number="tierForm.max_photo_payout_cap" type="number" step="0.01" class="w-full border border-gray-300 rounded-lg px-2 py-1.5 text-xs font-bold text-blue-600" />
                        </div>
                    </div>
                </div>

                <div class="border-t border-gray-200 pt-3 mb-4">
                    <div class="font-bold text-xs uppercase text-purple-800 mb-2">Video Price & Payout Rules</div>
                    <div class="grid grid-cols-3 gap-3">
                        <div>
                            <label class="block text-xs text-gray-600 mb-1">Min Price ($)</label>
                            <input v-model.number="tierForm.min_video_price" type="number" step="0.01" class="w-full border border-gray-300 rounded-lg px-2 py-1.5 text-xs" />
                        </div>
                        <div>
                            <label class="block text-xs text-gray-600 mb-1">Max Price ($)</label>
                            <input v-model.number="tierForm.max_video_price" type="number" step="0.01" class="w-full border border-gray-300 rounded-lg px-2 py-1.5 text-xs" />
                        </div>
                        <div>
                            <label class="block text-xs text-gray-600 mb-1">Payout Cap ($)</label>
                            <input v-model.number="tierForm.max_video_payout_cap" type="number" step="0.01" class="w-full border border-gray-300 rounded-lg px-2 py-1.5 text-xs font-bold text-purple-600" />
                        </div>
                    </div>
                </div>

                <div class="flex items-center gap-4 mb-4 pt-2">
                    <label class="flex items-center gap-2 text-sm text-gray-700">
                        <input v-model="tierForm.is_plan_eligible" type="checkbox" class="rounded text-emerald-600" />
                        <span>Eligible for Plan Redemption</span>
                    </label>
                    <label class="flex items-center gap-2 text-sm text-gray-700">
                        <input v-model="tierForm.is_active" type="checkbox" class="rounded text-emerald-600" />
                        <span>Active</span>
                    </label>
                </div>

                <div v-if="tierError" class="p-3 bg-red-50 border border-red-200 text-red-700 rounded-lg text-xs mb-4">
                    {{ tierError }}
                </div>

                <div class="flex justify-end gap-3 pt-4 border-t border-gray-200">
                    <button class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg text-sm font-semibold" @click="closeTierModal">
                        Cancel
                    </button>
                    <button class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-sm font-semibold" @click="saveTier">
                        Save Tier
                    </button>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>

<script setup>
import { ref, onMounted, watch } from 'vue';
import AdminLayout from "../../../layouts/AdminLayout.vue";
import PlanService from '@/services/admin/licenses/planService';
import dayjs from 'dayjs';

const activeTab = ref('plans');
const plans = ref([]);
const mediaTiers = ref([]);
const reportsData = ref({});

const loading = ref(false);
const loadingTiers = ref(false);
const loadingReports = ref(false);
const financialStatus = ref({ is_complete: false, missing: [] });
const financialError = ref('');
const financialMessage = ref('');
const financialForm = ref({
    currency: 'USD', minimum_profit: null, minimum_margin_bps: null,
    operating_cost_bps: null, fixed_operating_cost: null, risk_reserve_bps: null,
    tax_bps: null, photo_allocation_bps: null, tier_distribution: {},
    payment_fees: [
        { payment_method: 'wallet', percentage_bps: null, fixed_fee: null, is_enabled: true },
        { payment_method: 'paypal', percentage_bps: null, fixed_fee: null, is_enabled: true },
    ],
});

// Modal Plan Edit
const showModal = ref(false);
const editPlan = ref({});
const editPlanError = ref('');

// Modal Tier
const showTierModal = ref(false);
const tierError = ref('');
const tierForm = ref({
    id: null,
    name: '',
    rank: 1,
    min_photo_price: 0,
    max_photo_price: 5,
    max_photo_payout_cap: 3,
    min_video_price: 0,
    max_video_price: 15,
    max_video_payout_cap: 10,
    is_plan_eligible: true,
    is_active: true,
});

const formatDate = (date) => {
    if (!date) return '—';
    return dayjs(date).format('DD-MM-YYYY');
};

const fetchPlans = async () => {
    loading.value = true;
    try {
        const response = await PlanService.getAll();
        plans.value = response.data || [];
    } catch (err) {
        console.error('Error fetching plans:', err);
    } finally {
        loading.value = false;
    }
};

const fetchTiers = async () => {
    loadingTiers.value = true;
    try {
        const response = await PlanService.getMediaTiers();
        mediaTiers.value = response.data || [];
    } catch (err) {
        console.error('Error fetching tiers:', err);
    } finally {
        loadingTiers.value = false;
    }
};

const fetchReports = async () => {
    loadingReports.value = true;
    try {
        const response = await PlanService.getCreditReports();
        reportsData.value = response.data || {};
    } catch (err) {
        console.error('Error fetching reports:', err);
    } finally {
        loadingReports.value = false;
    }
};

const fetchFinancials = async () => {
    try {
        const response = await PlanService.getFinancialSettings();
        financialStatus.value = response;
        const settings = response.settings;
        if (settings) {
            financialForm.value = {
                currency: settings.currency,
                minimum_profit: Number(settings.minimum_profit_minor) / 100,
                minimum_margin_bps: settings.minimum_margin_bps,
                operating_cost_bps: settings.operating_cost_bps,
                fixed_operating_cost: Number(settings.fixed_operating_cost_minor) / 100,
                risk_reserve_bps: settings.risk_reserve_bps,
                tax_bps: settings.tax_bps,
                photo_allocation_bps: settings.photo_allocation_bps,
                tier_distribution: { ...(settings.tier_distribution || {}) },
                payment_fees: (settings.payment_fees || []).map((fee) => ({
                    payment_method: fee.payment_method,
                    percentage_bps: fee.percentage_bps,
                    fixed_fee: Number(fee.fixed_fee_minor) / 100,
                    is_enabled: fee.is_enabled,
                })),
            };
        } else {
            const eligible = response.tiers || mediaTiers.value.filter((tier) => tier.is_active && tier.is_plan_eligible);
            const share = eligible.length ? Math.floor(10000 / eligible.length) : 0;
            financialForm.value.tier_distribution = Object.fromEntries(eligible.map((tier, index) => [tier.id, share + (index === 0 ? 10000 - share * eligible.length : 0)]));
        }
    } catch (err) {
        financialError.value = err.data?.message || 'Unable to load financial assumptions.';
    }
};

const saveFinancials = async () => {
    financialError.value = '';
    financialMessage.value = '';
    try {
        const response = await PlanService.saveFinancialSettings(financialForm.value);
        financialMessage.value = `Saved version ${response.settings.version}. Plans were revalidated; sold grants were unchanged.`;
        await Promise.all([fetchFinancials(), fetchPlans()]);
    } catch (err) {
        financialError.value = err.data?.message || Object.values(err.data?.errors || {}).flat().join(' ') || 'Unable to save financial assumptions.';
    }
};

watch(activeTab, (tab) => {
    if (tab === 'plans') fetchPlans();
    if (tab === 'tiers') fetchTiers();
    if (tab === 'reports') fetchReports();
    if (tab === 'financials') fetchFinancials();
});

// Plan Modal logic
const openEditModal = (plan) => {
    editPlanError.value = '';
    editPlan.value = {
        id: plan.id,
        name: plan.translation?.name || plan.name,
        price: Number(plan.price),
        media_tier_id: plan.media_tier_id,
        photo_quota: plan.photo_quota || 0,
        video_quota: plan.video_quota || 0,
        tier_limits: (plan.tier_limits || []).map((limit) => ({
            media_tier_id: limit.media_tier_id,
            photo_maximum: limit.photo_maximum,
            video_maximum: limit.video_maximum,
        })),
    };
    showModal.value = true;
};

const closeModal = () => {
    showModal.value = false;
    editPlan.value = {};
    editPlanError.value = '';
};

const updatePlan = async () => {
    editPlanError.value = '';
    try {
        await PlanService.update(editPlan.value.id, editPlan.value);
        closeModal();
        fetchPlans();
    } catch (err) {
        editPlanError.value = err.data?.message || err.message || 'Error updating plan';
    }
};

const deletePlan = async (id) => {
    if (!confirm('Are you sure you want to delete this plan?')) return;
    try {
        await PlanService.delete(id);
        fetchPlans();
    } catch (err) {
        console.error('Error deleting plan:', err);
    }
};

// Tier Modal logic
const openTierModal = (tier = null) => {
    tierError.value = '';
    if (tier) {
        tierForm.value = { ...tier };
    } else {
        tierForm.value = {
            id: null,
            name: '',
            rank: (mediaTiers.value.length || 0) + 1,
            min_photo_price: 0,
            max_photo_price: 5,
            max_photo_payout_cap: 3,
            min_video_price: 0,
            max_video_price: 15,
            max_video_payout_cap: 10,
            is_plan_eligible: true,
            is_active: true,
        };
    }
    showTierModal.value = true;
};

const closeTierModal = () => {
    showTierModal.value = false;
    tierError.value = '';
};

const saveTier = async () => {
    tierError.value = '';
    try {
        await PlanService.saveMediaTier(tierForm.value);
        closeTierModal();
        fetchTiers();
    } catch (err) {
        tierError.value = err.data?.message || err.message || 'Error saving tier';
    }
};

const deleteTier = async (id) => {
    if (!confirm('Are you sure you want to delete this media tier?')) return;
    try {
        await PlanService.deleteMediaTier(id);
        fetchTiers();
    } catch (err) {
        alert(err.data?.message || 'Error deleting tier');
    }
};

onMounted(() => {
    fetchPlans();
    fetchTiers();
    fetchFinancials();
});
</script>
