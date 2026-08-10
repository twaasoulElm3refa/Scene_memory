<template>
    <div class="scemory-page wishlist-page container mx-auto px-4 py-8" dir="rtl">

        <h1 class="mb-8 text-center text-3xl font-bold text-gray-800 md:text-4xl">
            {{ $t('wishlist.title') }}
        </h1>

        <div v-if="loading" class="py-20 text-center text-xl text-gray-500">
            {{ $t('wishlist.loading') }}
        </div>

        <div v-else-if="wishlists.length === 0"
            class="rounded-xl bg-gray-50 py-16 text-center text-lg text-gray-600 shadow-sm">
            <p>{{ $t('wishlist.empty.main') }}</p>
            <p class="mt-2 text-sm">{{ $t('wishlist.empty.hint') }}</p>
        </div>

        <div v-else class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
            <div v-for="item in wishlists" :key="item.id"
                class="group flex h-full flex-col overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm transition-all duration-300 hover:-translate-y-1 hover:shadow-xl hover:shadow-indigo-100/40">
                <!-- الصورة -->
                <div class="relative aspect-[4/3] overflow-hidden">
                    <img :src="getImageUrl(item)"
                        :alt="item.first_image?.preview_url ? item.translation.title : $t('wishlist.fallback_image_alt')"
                        class="h-full w-full object-cover transition-transform duration-500 group-hover:scale-105"
                        loading="lazy" />
                    <div
                        class="absolute inset-0 bg-gradient-to-t from-black/40 via-transparent opacity-0 transition-opacity duration-300 group-hover:opacity-100">
                    </div>
                </div>

                <!-- المحتوى -->
                <div class="flex flex-1 flex-col p-5">
                    <!-- التصنيف (Badge) -->
                    <div class="mb-2">
                        <span v-if="item.sub_categorey?.translation.name"
                            class="inline-flex items-center rounded-full bg-indigo-100 px-3 py-1 text-xs font-medium text-indigo-800 ring-1 ring-inset ring-indigo-600/30">
                            {{ item.sub_categorey.translation.name }}
                        </span>
                        <span v-else
                            class="inline-flex items-center rounded-full bg-gray-100 px-3 py-1 text-xs font-medium text-gray-700">
                            {{ $t('wishlist.uncategorized') }}
                        </span>
                    </div>

                    <h3 class="mb-2 line-clamp-2 text-xl font-semibold text-gray-800 group-hover:text-indigo-700">
                        {{ item.translation.title || $t('wishlist.no_title') }}
                    </h3>

                    <p class="mb-4 line-clamp-3 flex-1 text-sm leading-relaxed text-gray-600">
                        {{ item.translation.description || '—' }}
                    </p>

                    <div class="mt-auto space-y-2.5 text-sm text-gray-700">
                        <div class="flex items-center gap-2">
                            <svg class="h-4 w-4 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                            <span><strong class="font-medium">{{ $t('wishlist.city_label') }}</strong> {{
                                item.city?.translation.name || $t('wishlist.not_specified') }}</span>
                        </div>

                        <div class="flex items-center gap-2">
                            <svg class="h-4 w-4 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                            <span><strong class="font-medium">{{ $t('wishlist.date_label') }}</strong> {{
                                formatDate(item.start_date) }} → {{ formatDate(item.end_date) }}</span>
                        </div>

                        <div v-if="item.time" class="flex items-center gap-2">
                            <svg class="h-4 w-4 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <span><strong class="font-medium">{{ $t('wishlist.time_label') }}</strong> {{ item.time
                            }}</span>
                        </div>
                    </div>

                    <!-- زر التفاصيل -->
                    <div class="mt-5 pt-4 border-t border-gray-100">
                        <router-link :to="{ name: 'single_event', params: { slug: item.slug } }"
                            class="inline-flex w-full items-center justify-center rounded-lg bg-indigo-600 px-5 py-2.5 text-sm font-medium text-white shadow-sm transition-all hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 disabled:opacity-50">
                            {{ $t('wishlist.view_details') }}
                            <svg class="mr-2 h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M9 5l7 7-7 7" />
                            </svg>
                        </router-link>
                    </div>

                    <!-- زر الحذف -->
                    <div class="mt-3">
                        <button @click="deleteFromWishlist(item.id)" :disabled="deletingItemId === item.id"
                            class="inline-flex w-full items-center justify-center rounded-lg border border-red-500 bg-white px-5 py-2.5 text-sm font-medium text-red-600 transition-all hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-red-400 focus:ring-offset-2 disabled:opacity-50">
                            <svg v-if="deletingItemId === item.id" class="mr-2 h-4 w-4 animate-spin"
                                xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                                    stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor"
                                    d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                                </path>
                            </svg>
                            {{ deletingItemId === item.id ? $t('wishlist.deleting') : $t('wishlist.remove') }}
                        </button>
                    </div>

                    <div class="mt-3 text-xs text-gray-500 text-center">
                        {{ $t('wishlist.added_at') }}: {{ formatDateTime(item.created_at) }}
                    </div>
                </div>
            </div>
        </div>

        <!-- Pagination -->
        <div v-if="pagination.last_page > 1" class="mt-10 flex items-center justify-center gap-6">
            <button :disabled="!pagination.prev_page_url" @click="changePage(currentPage - 1)"
                class="rounded-lg border border-gray-300 bg-white px-5 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50 disabled:opacity-50">
                {{ $t('wishlist.pagination.previous') }}
            </button>

            <span class="text-sm font-medium text-gray-700">
                {{ $t('wishlist.pagination.page_info', { current: currentPage, total: pagination.last_page }) }}
            </span>

            <button :disabled="!pagination.next_page_url" @click="changePage(currentPage + 1)"
                class="rounded-lg border border-gray-300 bg-white px-5 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50 disabled:opacity-50">
                {{ $t('wishlist.pagination.next') }}
            </button>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { WishlistService } from '../../services/WishlistService/WishlistService'

const wishlists = ref([])
const pagination = ref({})
const currentPage = ref(1)
const loading = ref(false)
const deletingItemId = ref(null)

const PLACEHOLDER_IMAGE = 'https://picsum.photos/seed/wishlist/600/400'


const fetchWishlists = async (page = 1) => {
    loading.value = true
    try {
        const response = await WishlistService.getMyWishlist(page)

        if (response.data.status === 'success') {
            wishlists.value = response.data.data.data
            console.log(wishlists.value)
            pagination.value = {
                current_page: response.data.data.current_page,
                last_page: response.data.data.last_page,
                prev_page_url: response.data.data.prev_page_url,
                next_page_url: response.data.data.next_page_url,
                total: response.data.data.total
            }
            currentPage.value = page
        }
    } catch (error) {
        console.error('Error fetching wishlists:', error)
    } finally {
        loading.value = false
    }
}

const getImageUrl = (item) => {
    if (item.first_image?.preview_url) {
        return `http://localhost:8000/storage/${item.first_image.preview_url}`
    }

    return `https://picsum.photos/seed/event-${item.id}/800/600`
}
const deleteFromWishlist = async (id) => {
    if (!confirm('هل أنت متأكد من إزالة هذا العنصر من قائمة المفضلة؟')) return

    deletingItemId.value = id

    try {
        const response = await WishlistService.deleteFromWishlist(id)
        if (response.data.status === 'success') {
            wishlists.value = wishlists.value.filter(item => item.id !== id)
            if (wishlists.value.length === 0 && currentPage.value > 1) {
                changePage(currentPage.value - 1)
            }
        }
    } catch (error) {
        console.error('Error deleting from wishlist:', error)
        let errorMessage = 'حدث خطأ أثناء الحذف، حاول مرة أخرى'

        if (error.response) {
            const res = error.response.data
            if (res?.message) {
                errorMessage = res.message
            } else if (res?.error) {
                errorMessage = res.error
            } else if (res?.errors && typeof res.errors === 'object') {
                const firstError = Object.values(res.errors)[0]
                errorMessage = Array.isArray(firstError) ? firstError[0] : firstError
            } else if (res?.data?.message) {
                errorMessage = res.data.message
            }
        } else if (error.request) {
            errorMessage = 'مشكلة في الاتصال بالخادم، تأكد من الإنترنت وحاول مرة أخرى'
        } else {
            errorMessage = error.message || 'خطأ غير متوقع'
        }

        alert(errorMessage)
    } finally {
        deletingItemId.value = null
    }
}

const formatDate = (dateString) => {
    if (!dateString) return '—';

    const language = localStorage.getItem('language') || 'ar';

    try {
        return new Date(dateString).toLocaleDateString(language, {
            year: 'numeric',
            month: 'long',
            day: 'numeric'
        });
    } catch (error) {
        return '—';
    }
};

const formatDateTime = (dateString) => {
    if (!dateString) return '—'
    return new Date(dateString).toLocaleString('ar-EG', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    })
}

const changePage = (page) => {
    if (page < 1 || page > pagination.value.last_page) return
    fetchWishlists(page)
}

onMounted(() => {
    fetchWishlists(1)
})
</script>

<style scoped>
.my-wishlists-container {
    max-width: 1100px;
    margin: 0 auto;
    padding: 1.5rem;
}

.page-title {
    font-size: 1.8rem;
    margin-bottom: 1.5rem;
    color: #2c3e50;
    text-align: center;
}

.wishlist-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    gap: 1.5rem;
}

.wishlist-card {
    background: white;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
    transition: transform 0.2s, box-shadow 0.2s;
}

.wishlist-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.12);
}

.card-image {
    height: 160px;
    background: #f0f2f5;
    position: relative;
}

.cover-image {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.placeholder-image {
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #94a3b8;
    font-size: 0.95rem;
    background: linear-gradient(135deg, #e2e8f0, #f1f5f9);
}

.card-content {
    padding: 1.25rem;
}

.title {
    font-size: 1.25rem;
    margin: 0 0 0.75rem;
    color: #1e293b;
}

.description {
    color: #64748b;
    margin-bottom: 1rem;
    line-height: 1.5;
}

.meta-info {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    font-size: 0.95rem;
    color: #475569;
    margin-bottom: 1rem;
}

.meta-info strong {
    color: #334155;
}

.created-at {
    font-size: 0.85rem;
    color: #94a3b8;
    text-align: left;
}

.pagination {
    margin-top: 2rem;
    text-align: center;
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 1.5rem;
}

.pagination button {
    padding: 0.6rem 1.2rem;
    border: 1px solid #cbd5e1;
    border-radius: 6px;
    background: white;
    cursor: pointer;
}

.pagination button:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.empty-state,
.loading {
    text-align: center;
    padding: 4rem 1rem;
    color: #64748b;
    font-size: 1.1rem;
}

.wishlist-page {
    max-width: 1200px;
    background:
        radial-gradient(circle at top left, rgba(48, 168, 255, 0.10), transparent 30rem),
        linear-gradient(180deg, #FFFFFF, #F8FAFC);
}

.wishlist-page h1,
.page-title {
    color: #06142A;
}

.wishlist-page .group,
.wishlist-card {
    border: 1px solid #E5EDF6;
    border-radius: 22px;
    box-shadow: 0 10px 35px rgba(13, 77, 151, 0.06);
}

.wishlist-page .group:hover,
.wishlist-card:hover {
    border-color: #CFE2F6;
    box-shadow: 0 18px 55px rgba(13, 77, 151, 0.12);
}

.pagination button {
    border-color: #DCE8F5;
    border-radius: 999px;
    color: #0D4D97;
}

.empty-state,
.loading {
    border: 1px dashed #CFE2F6;
    border-radius: 24px;
    background: #FFFFFF;
}
</style>
