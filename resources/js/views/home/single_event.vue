<template>
    <div v-if="cartAlert.show" class="fixed top-5 right-5 z-[9999] px-4 py-3 rounded-lg shadow-lg text-white transition"
        :class="cartAlert.type === 'success' ? 'bg-green-500' : 'bg-red-500'">
        {{ cartAlert.message }}
    </div>
    <div class="scemory-page single-event-page min-h-screen bg-gray-50">

        <div v-if="loading" class="text-center py-40">
            <div class="animate-spin rounded-full h-20 w-20 border-t-4 border-blue-600 mx-auto mb-8"></div>
            <p class="text-gray-700 text-2xl font-medium">
                {{ $t('event.loading') }}
            </p>
        </div>

        <div v-else-if="!event" class="text-center py-32 text-gray-600 text-2xl">
            {{ $t('event.not_found') }}
        </div>

        <div v-else>
            <!-- Hero -->
            <div class="relative">
                <component :is="heroMediaComponent" v-if="heroMedia" :src="getMediaUrl(heroMedia)"
                    class="w-full h-[300px] md:h-[400px] lg:h-[500px] object-cover" controls autoplay muted loop
                    playsinline />
                <img v-else :src="placeholderImage" :alt="event?.translation?.title || ''"
                    class="w-full h-[300px] md:h-[400px] lg:h-[500px] object-cover" />

                <div v-if="heroMedia && isMediaVideo(heroMedia)"
                    class="absolute inset-0 bg-black/30 flex items-center justify-center z-10 pointer-events-none">
                    <div
                        class="w-24 h-24 md:w-32 md:h-32 rounded-full bg-white/20 backdrop-blur-sm flex items-center justify-center shadow-2xl">
                        <span class="text-white text-5xl md:text-7xl drop-shadow-lg">▶</span>
                    </div>
                </div>

                <div class="absolute top-6 left-6 md:left-10 flex flex-wrap gap-3 z-20">
                    <span
                        class="bg-blue-100/90 backdrop-blur-sm text-blue-900 px-5 py-2 rounded-full text-base font-bold shadow-lg uppercase tracking-wider">
                        {{ event?.city?.translation?.name || $t('event.city_default') }}
                    </span>
                    <span v-if="event?.sub_categorey?.translation?.name"
                        class="bg-green-100/90 backdrop-blur-sm text-green-900 px-5 py-2 rounded-full text-base font-bold shadow-lg uppercase tracking-wider">
                        {{ event.sub_categorey.translation.name }}
                    </span>
                </div>
            </div>

            <!-- Content -->
            <div class="max-w-7xl mx-auto px-5 sm:px-8 lg:px-12 -mt-16 relative z-10">
                <div class="bg-white rounded-2xl shadow-2xl overflow-hidden">

                    <!-- ══════════════════════════════════════════════════════════ -->
                    <!-- Media Gallery — FULL WIDTH above the grid                  -->
                    <!-- ══════════════════════════════════════════════════════════ -->

                    <div class="p-8 md:p-12 border-b border-gray-100">
                        <div class="flex items-center justify-between mb-6">
                            <h2
                                class="text-2xl md:text-3xl font-bold text-gray-900 border-b border-gray-200 pb-4 flex-1">
                                {{ $t('event.media_gallery_title') }}
                            </h2>
                            <!-- <button v-if="isAuthenticated && Number(event?.user_id) === Number(currentUserId)" @click="showUploadModal = true"
                                class="flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-5 py-2.5 rounded-lg font-medium transition shadow-sm ml-4">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M12 4v16m8-8H4" />
                                </svg>
                                {{ $t('upload_media') }}
                            </button> -->
                        </div>

                        <!-- Grid -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                            <div v-for="(media, index) in eventImages" :key="media?.id || index"
                                class="aspect-[16/10] overflow-hidden rounded-2xl shadow hover:shadow-lg transition-shadow cursor-pointer relative group"
                                @click="openLightbox(index)">

                                <!-- IMAGE -->
                                <img v-if="!isMediaVideo(media)" :src="getMediaUrl(media) || placeholderImage"
                                    :alt="media?.title || media?.name || $t('event.media_alt')" @error="onMediaImageError"
                                    class="w-full h-full object-cover transition-transform duration-300 group-hover:scale-105"
                                    loading="lazy" />

                                <!-- VIDEO -->
                                <video v-else :src="getMediaUrl(media)" class="w-full h-full object-cover"
                                    autoplay muted loop playsinline preload="metadata">
                                </video>

                                <!-- DARK OVERLAY -->
                                <div class="absolute inset-0 bg-black/0 group-hover:bg-black/40 transition z-10"></div>

                                <!-- PRICE + CART -->
                                <div
                                    class="absolute bottom-0 left-0 right-0 z-20 bg-black/70 text-white p-3 flex items-center justify-between opacity-100 transition">
                                    <!-- PRICE -->
                                    <div class="text-sm font-bold">
                                        Price {{ formatPrice(getImagePrice(media)) }} $
                                    </div>
                                    <!-- ADD TO CART -->
                                    <button @click.stop="addToCart(media?.id)"
                                        class="bg-gray-500 hover:bg-gray-600 px-3 py-1 rounded text-sm font-semibold transition">
                                        Add
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ══════════════════════════════════════════════════════════ -->
                    <!-- COLLECTION PURCHASE SECTION                                -->
                    <!-- ══════════════════════════════════════════════════════════ -->
                    <div v-if="eventImages.length > 0"
                        class="p-8 md:p-12 border-b border-gray-100 bg-gradient-to-r from-blue-50 to-indigo-50">
                        <div class="max-w-2xl mx-auto">
                            <h3 class="text-2xl font-bold text-gray-900 mb-6 flex items-center gap-2">
                                <svg class="w-6 h-6 text-green-600" fill="currentColor" viewBox="0 0 24 24">
                                    <path
                                        d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z" />
                                </svg>
                                {{ $t('event.buy_collection_title') || 'Buy Full Collection' }}
                            </h3>

                            <!-- Collection Info -->
                            <div class="bg-white rounded-2xl shadow-lg p-6 mb-6">
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                                    <!-- Total Images -->
                                    <div class="text-center">
                                        <div class="text-3xl font-bold text-indigo-600 mb-2">{{ eventImages.length }}
                                        </div>
                                        <div class="text-sm text-gray-600">{{ $t('event.total_images') || 'Total Images'
                                        }}</div>
                                    </div>

                                    <!-- Price Info -->
                                    <div class="text-center border-l border-r border-gray-200">
                                        <div class="text-sm text-gray-500 line-through mb-2">
                                            ${{ formatPrice(collectionTotalPrice) }}
                                        </div>

                                        <div class="text-3xl font-bold text-green-600 mb-2">
                                            ${{ formatPrice(collectionDiscountedPrice) }}
                                        </div>
                                        <div class="text-sm text-green-600 font-semibold">{{ $t('event.save_discount')
                                            || '10% OFF' }}</div>
                                    </div>

                                    <!-- Savings -->
                                    <div class="text-center">
                                        <div class="text-2xl font-bold text-red-600 mb-2">${{
                                            formatPrice(collectionDiscountAmount) }}</div>
                                        <div class="text-sm text-gray-600">{{ $t('event.you_save') || 'You Save' }}
                                        </div>
                                    </div>
                                </div>

                                <!-- Add to Cart Button -->
                                <button @click="addCollectionToCart" :disabled="collectionLoading || eventImages.length === 0"
                                    class="w-full py-3 px-6 rounded-xl font-bold text-white text-lg transition-all duration-200 flex items-center justify-center gap-2"
                                    :class="{
                                        'bg-green-600 hover:bg-green-700': !collectionLoading && eventImages.length > 0,
                                        'bg-gray-400 cursor-not-allowed': collectionLoading || eventImages.length === 0,
                                    }">
                                    <svg v-if="!collectionLoading" class="w-5 h-5" fill="none" stroke="currentColor"
                                        viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" />
                                    </svg>
                                    <span v-if="collectionLoading">{{ $t('common.loading') || 'Loading...' }}</span>
                                    <span v-else>{{ $t('event.add_collection_to_cart') || 'Add Full Collection toCart' }}</span>
                                </button>

                                <!-- Alert Message -->
                                <p v-if="collectionAlert.show" class="mt-4 p-3 rounded-lg text-sm" :class="{
                                    'bg-green-50 text-green-700 border border-green-200': collectionAlert.type === 'success',
                                    'bg-red-50 text-red-700 border border-red-200': collectionAlert.type === 'error',
                                }">
                                    {{ collectionAlert.message }}
                                </p>
                            </div>

                            <!-- Additional Info -->
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                                <div class="flex gap-3 text-gray-600">
                                    <svg class="w-5 h-5 text-green-600 flex-shrink-0" fill="currentColor"
                                        viewBox="0 0 24 24">
                                        <path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z" />
                                    </svg>
                                    <span>{{ $t('event.collection_benefit_1') || '✓ Buy all images at once' }}</span>
                                </div>
                                <div class="flex gap-3 text-gray-600">
                                    <svg class="w-5 h-5 text-green-600 flex-shrink-0" fill="currentColor"
                                        viewBox="0 0 24 24">
                                        <path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z" />
                                    </svg>
                                    <span>{{ $t('event.collection_benefit_2') || 'Automatic 10% discount applied'
                                    }}</span>
                                </div>
                                <div class="flex gap-3 text-gray-600">
                                    <svg class="w-5 h-5 text-green-600 flex-shrink-0" fill="currentColor"
                                        viewBox="0 0 24 24">
                                        <path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z" />
                                    </svg>
                                    <span>{{ $t('event.collection_benefit_3') || 'Easy access in your downloads'
                                    }}</span>
                                </div>
                                <div class="flex gap-3 text-gray-600">
                                    <svg class="w-5 h-5 text-green-600 flex-shrink-0" fill="currentColor"
                                        viewBox="0 0 24 24">
                                        <path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z" />
                                    </svg>
                                    <span>{{ $t('event.collection_benefit_4') || 'Same secure PayPal checkout' }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ══════════════════════════════════════════════════════════ -->
                    <!-- Main + Sidebar Grid                                        -->
                    <!-- ══════════════════════════════════════════════════════════ -->
                    <div class="grid grid-cols-1 lg:grid-cols-4 gap-0">

                        <!-- Main Content (3/4) -->
                        <div class="lg:col-span-3 p-8 md:p-12 lg:p-16">

                            <!-- Title + Like -->
                            <div class="flex items-center justify-between mb-6">
                                <h1 class="text-3xl md:text-4xl lg:text-5xl font-bold text-gray-900 leading-tight">
                                    {{ event?.translation?.title || '' }}
                                </h1>
                                <button @click="toggleLike" :disabled="likeLoading || isLiked"
                                    class="flex items-center gap-2 px-4 py-2 rounded-full transition-all duration-200 ml-4 shrink-0"
                                    :class="{
                                        'bg-pink-50 text-pink-600 border border-pink-200 hover:bg-pink-100': isLiked,
                                        'bg-gray-100 text-gray-600 hover:bg-pink-50 hover:text-pink-600 border border-gray-300': !isLiked,
                                    }">
                                    <svg class="w-6 h-6 transition-transform" :class="{ 'scale-110': isLiked }"
                                        fill="currentColor" viewBox="0 0 24 24">
                                        <path fill-rule="evenodd" clip-rule="evenodd"
                                            d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z" />
                                    </svg>
                                    <span class="text-base font-semibold">{{ likesCount }}</span>
                                    <span v-if="likeLoading" class="text-sm animate-pulse">...</span>
                                </button>
                            </div>

                            <!-- Like Error -->
                            <p v-if="likeError"
                                class="mb-4 text-red-600 bg-red-50 border border-red-200 px-4 py-2 rounded-lg text-sm">
                                {{ likeError }}
                            </p>

                            <p class="text-lg md:text-xl text-gray-700 mb-10 leading-relaxed">
                                {{ event?.translation?.description || '' }}
                            </p>

                            <!-- About -->
                            <div class="mb-12">
                                <h2
                                    class="text-2xl md:text-3xl font-bold text-gray-900 mb-6 border-b border-gray-200 pb-4">
                                    {{ $t('event.about_event_title') }}
                                </h2>
                                <p class="text-gray-700 mb-8 text-lg leading-relaxed">
                                    {{ event?.translation?.des || '' }}
                                </p>
                            </div>

                            <!-- Comments Section -->
                            <div class="comments-section mt-12">
                                <h2
                                    class="text-2xl md:text-3xl font-bold text-gray-900 mb-8 border-b border-gray-200 pb-4">
                                    {{ $t('event.comments_title') }}
                                    ({{ event?.comments_count ?? event?.comments?.length ?? 0 }})
                                </h2>

                                <RouterLink v-if="isAuthenticated"
                                    :to="{ name: 'all_comments', params: { slug: event?.slug } }"
                                    class="inline-block mb-6 text-indigo-600 hover:text-indigo-800 font-medium">
                                    {{ $t('view_all_comments') }}
                                </RouterLink>

                                <!-- Comment List -->
                                <div v-for="comment in (event?.comments || [])" :key="comment?.id"
                                    class="comment-box mb-6 bg-gray-50 p-6 rounded-xl shadow-sm border border-gray-100 hover:shadow-md hover:border-indigo-100 transition-all duration-200">

                                    <p class="text-gray-700 text-sm leading-relaxed mb-3">
                                        {{ comment?.translation?.comment || comment?.comment || '' }}
                                    </p>

                                    <div v-if="comment?.images?.length"
                                        :class="['grid gap-2 mb-4', comment.images.length === 1 ? 'grid-cols-1 max-w-sm' : 'grid-cols-2']">
                                        <a v-for="image in comment.images.slice(0, 2)" :key="image.id"
                                            :href="getMediaUrl(image)" target="_blank" rel="noopener noreferrer"
                                            class="block overflow-hidden rounded-lg bg-gray-100">
                                            <img :src="getMediaUrl(image)" :alt="$t('commentsPage.attachmentAlt')"
                                                class="h-32 w-full object-cover" loading="lazy"
                                                @error="onMediaImageError" />
                                        </a>
                                    </div>

                                    <div class="flex items-center justify-between mb-4 text-xs text-gray-500">
                                        <div class="flex items-center gap-2">
                                            <div
                                                class="w-8 h-8 rounded-full bg-indigo-100 flex items-center justify-center text-indigo-700 font-bold">
                                                {{ comment?.user?.name?.charAt(0)?.toUpperCase() || '?' }}
                                            </div>
                                            <div>
                                                <p class="font-semibold text-gray-900 text-sm">
                                                    {{ comment?.user?.name || $t('event.visitor') }}
                                                </p>
                                                <span class="text-xs text-gray-500">
                                                    {{ formatCommentDate(comment?.created_at) }}
                                                </span>
                                            </div>
                                        </div>

                                        <div class="flex items-center gap-2">
                                            <!-- Reply Button -->
                                            <button v-if="isAuthenticated" @click="toggleReplyForm(comment?.id)"
                                                class="flex items-center gap-1 text-xs text-indigo-600 hover:text-indigo-800 transition-colors px-2 py-1 rounded-lg hover:bg-indigo-50">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                                                    viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6" />
                                                </svg>
                                                {{ $t('event.reply') }}
                                            </button>

                                            <!-- Delete Button -->
                                            <button v-if="comment?.user_id === currentUserId"
                                                @click="deleteComment(comment?.id)"
                                                class="flex items-center gap-1 text-xs text-red-500 hover:text-red-700 transition-colors px-2 py-1 rounded-lg hover:bg-red-50"
                                                :title="$t('event.delete_comment_title')">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" fill="none"
                                                    viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                </svg>
                                                {{ $t('event.delete') }}
                                            </button>

                                            <!-- Report Button -->
                                            <button @click="openReportModal(comment?.id)"
                                                class="flex items-center gap-1 text-xs text-gray-400 hover:text-red-500 transition-colors px-2 py-1 rounded-lg hover:bg-red-50"
                                                :title="$t('report.commentTitle')">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" fill="none"
                                                    viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        d="M3 3h18l-2 9H5L3 3z" />
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        d="M5 12v7h14v-7" />
                                                </svg>
                                                {{ $t('report.action') }}
                                            </button>
                                        </div>
                                    </div>

                                    <!-- Delete Comment Error -->
                                    <p v-if="comment && deleteCommentErrors[comment.id]"
                                        class="mb-2 text-red-600 bg-red-50 border border-red-200 px-3 py-1.5 rounded-lg text-xs">
                                        {{ deleteCommentErrors[comment.id] }}
                                    </p>

                                    <!-- Reactions -->
                                    <div class="flex items-center gap-2.5 flex-wrap mt-1" dir="rtl">
                                        <button @click="setReaction(comment?.id, 'support')"
                                            :disabled="comment && reactionLoading[comment.id]" :class="[
                                                'flex items-center gap-1.5 px-3.5 py-1.5 rounded-full text-xs font-medium border transition-all duration-150 min-w-[90px] justify-center',
                                                comment && commentReactions[comment.id] === 'support'
                                                    ? 'bg-emerald-600 border-emerald-600 text-white shadow-sm'
                                                    : 'bg-white border-gray-300 text-gray-700 hover:bg-emerald-50 hover:border-emerald-400',
                                                comment && reactionLoading[comment.id] ? 'opacity-60 cursor-not-allowed' : ''
                                            ]">
                                            <span class="text-[11px] font-bold">YES</span>
                                            {{ $t('commentsPage.reactions.support') }}
                                            <span
                                                class="text-[11px] font-semibold bg-white/30 px-1.5 py-0.5 rounded ml-1">
                                                {{ comment?.support_count ?? 0 }}
                                            </span>
                                        </button>

                                        <button @click="setReaction(comment?.id, 'neutral')"
                                            :disabled="comment && reactionLoading[comment.id]" :class="[
                                                'flex items-center gap-1.5 px-3.5 py-1.5 rounded-full text-xs font-medium border transition-all duration-150 min-w-[90px] justify-center',
                                                comment && commentReactions[comment.id] === 'neutral'
                                                    ? 'bg-amber-500 border-amber-500 text-white shadow-sm'
                                                    : 'bg-white border-gray-300 text-gray-700 hover:bg-amber-50 hover:border-amber-400',
                                                comment && reactionLoading[comment.id] ? 'opacity-60 cursor-not-allowed' : ''
                                            ]">
                                            <span class="text-[11px] font-bold">MID</span>
                                            {{ $t('commentsPage.reactions.neutral') }}
                                            <span
                                                class="text-[11px] font-semibold bg-white/30 px-1.5 py-0.5 rounded ml-1">
                                                {{ comment?.neutral_count ?? 0 }}
                                            </span>
                                        </button>

                                        <button @click="setReaction(comment?.id, 'exhibitions')"
                                            :disabled="comment && reactionLoading[comment.id]" :class="[
                                                'flex items-center gap-1.5 px-3.5 py-1.5 rounded-full text-xs font-medium border transition-all duration-150 min-w-[110px] justify-center',
                                                comment && commentReactions[comment.id] === 'exhibitions'
                                                    ? 'bg-rose-600 border-rose-600 text-white shadow-sm'
                                                    : 'bg-white border-gray-300 text-gray-700 hover:bg-rose-50 hover:border-rose-400',
                                                comment && reactionLoading[comment.id] ? 'opacity-60 cursor-not-allowed' : ''
                                            ]">
                                            <span class="text-[11px] font-bold">NO</span>
                                            {{ $t('commentsPage.reactions.oppose') }}
                                            <span
                                                class="text-[11px] font-semibold bg-white/30 px-1.5 py-0.5 rounded ml-1">
                                                {{ comment?.exhibitions_count ?? 0 }}
                                            </span>
                                        </button>
                                    </div>

                                    <!-- Reaction Error per comment -->
                                    <p v-if="comment && reactionErrors[comment.id]"
                                        class="mt-2 text-red-600 bg-red-50 border border-red-200 px-3 py-1.5 rounded-lg text-xs">
                                        {{ reactionErrors[comment.id] }}
                                    </p>

                                    <!-- Reply Form -->
                                    <div v-if="isAuthenticated && comment && replyingTo === comment.id" class="mt-6 pl-12">
                                        <form @submit.prevent="addReply(comment.id)" class="space-y-4">
                                            <textarea v-model="replyTexts[comment.id]" rows="2"
                                                class="w-full border border-gray-300 rounded-lg p-3 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                                                :placeholder="$t('event.reply_placeholder')"
                                                required></textarea>
                                            <p v-if="replyErrors[comment.id]"
                                                class="text-red-600 bg-red-50 border border-red-200 px-3 py-2 rounded-lg text-sm">
                                                {{ replyErrors[comment.id] }}
                                            </p>
                                            <div class="flex justify-end gap-3">
                                                <button type="button" @click="cancelReply(comment.id)"
                                                    class="px-4 py-2 text-gray-600 hover:bg-gray-100 rounded-lg">
                                                    {{ $t('common.cancel') }}
                                                </button>
                                                <button type="submit"
                                                    :disabled="replyLoading[comment.id] || !replyTexts[comment.id]?.trim()"
                                                    class="px-5 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2">
                                                    <span v-if="replyLoading[comment.id]">{{ $t('common.sending') }}</span>
                                                    <span v-else>{{ $t('event.send_reply') }}</span>
                                                </button>
                                            </div>
                                        </form>
                                    </div>

                                    <!-- Replies -->
                                    <div v-if="comment?.replies?.length > 0"
                                        class="mt-6 pl-12 border-l-2 border-indigo-200 space-y-6">
                                        <div v-for="reply in comment.replies" :key="reply?.id"
                                            class="bg-white p-4 rounded-lg shadow-sm">
                                            <div class="flex items-start gap-3">
                                                <div
                                                    class="w-8 h-8 rounded-full bg-gray-200 flex items-center justify-center text-gray-700 font-medium text-sm">
                                                    {{ reply?.user?.name?.charAt(0)?.toUpperCase() || '?' }}
                                                </div>
                                                <div class="flex-1">
                                                    <div class="flex items-center gap-2 mb-1">
                                                        <p class="font-medium text-gray-900">{{ reply?.user?.name ||
                                                            $t('event.visitor') }}</p>
                                                        <span class="text-xs text-gray-500">{{
                                                            formatCommentDate(reply?.created_at) }}</span>
                                                    </div>
                                                    <p class="text-gray-700">{{ reply?.comment || '' }}</p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Add Comment Form -->
                                <div v-if="isAuthenticated"
                                    class="comment-form mt-12 bg-white p-8 rounded-xl shadow-md border border-gray-100">
                                    <h3 class="text-xl font-bold text-gray-900 mb-6">
                                        {{ $t('event.add_comment_title') }}
                                    </h3>
                                    <form @submit.prevent="addComment">
                                        <textarea v-model="newComment" rows="4"
                                            class="w-full border border-gray-300 rounded-lg p-4 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                                            :placeholder="$t('event.comment_placeholder')"
                                            :disabled="commentLoading" required></textarea>
                                        <div class="mt-3 flex flex-wrap items-center gap-3">
                                            <input ref="commentImageInput" type="file"
                                                accept="image/jpeg,image/png,image/webp" multiple class="hidden"
                                                :disabled="commentLoading || commentImages.length >= MAX_COMMENT_IMAGES"
                                                @change="handleCommentImageSelection" />
                                            <button type="button" @click="commentImageInput?.click()"
                                                :disabled="commentLoading || commentImages.length >= MAX_COMMENT_IMAGES"
                                                class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50">
                                                <PhotoIcon class="h-5 w-5" aria-hidden="true" />
                                                {{ $t('event.comment_add_images', { count: commentImages.length, max: MAX_COMMENT_IMAGES }) }}
                                            </button>
                                            <span class="text-xs text-gray-500">{{ $t('event.comment_image_limit') }}</span>
                                        </div>
                                        <div v-if="commentImages.length" class="mt-3 grid max-w-xs grid-cols-2 gap-2">
                                            <div v-for="(item, index) in commentImages" :key="item.key"
                                                class="relative aspect-square overflow-hidden rounded-lg border border-gray-200 bg-gray-100">
                                                <img :src="item.previewUrl" :alt="$t('event.selected_comment_image_alt', { number: index + 1 })"
                                                    class="h-full w-full object-cover" />
                                                <button type="button" @click="removeCommentImage(index)"
                                                    class="absolute right-1 top-1 flex h-7 w-7 items-center justify-center rounded-full bg-black/65 text-white hover:bg-black"
                                                    :aria-label="$t('event.remove_selected_comment_image', { number: index + 1 })">
                                                    <XMarkIcon class="h-4 w-4" aria-hidden="true" />
                                                </button>
                                            </div>
                                        </div>
                                        <div class="mt-4 flex justify-end">
                                            <button type="submit" :disabled="commentLoading || !newComment.trim()"
                                                class="px-6 py-3 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 disabled:opacity-50 flex items-center gap-2">
                                                <span v-if="commentLoading">{{ $t('event.sending_comment') || ''
                                                }}</span>
                                                <span v-else>{{ $t('event.submit_comment') }}</span>
                                            </button>
                                        </div>
                                        <p v-if="commentError"
                                            class="mt-3 text-red-600 bg-red-50 border border-red-200 px-4 py-2 rounded-lg text-sm">
                                            {{ commentError }}
                                        </p>
                                        <p v-if="commentSuccess"
                                            class="mt-3 text-green-600 bg-green-50 border border-green-200 px-4 py-2 rounded-lg text-sm">
                                            {{ $t('event.comment_added_success') }}
                                        </p>
                                    </form>
                                </div>

                                <!-- Login prompt -->
                                <div v-else class="text-center py-12 bg-gray-50 rounded-xl">
                                    <p class="text-lg text-gray-700 mb-4">
                                        {{ $t('login_to_comment') }}
                                    </p>
                                    <RouterLink to="/auth"
                                        class="inline-block px-6 py-3 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700">
                                        {{ $t('login') }}
                                    </RouterLink>
                                </div>
                            </div>
                        </div>

                        <!-- Sidebar (1/4) — compact -->
                        <div
                            class="bg-gray-50/60 p-6 border-t lg:border-t-0 lg:border-l border-gray-200 lg:sticky lg:top-0 lg:h-screen lg:overflow-y-auto">
                            <div class="space-y-6">

                                <!-- Event Info -->
                                <div>
                                    <h3 class="text-base font-bold text-gray-900 mb-4">
                                        {{ $t('event.event_info_title') }}
                                    </h3>
                                    <div class="space-y-3 text-gray-700 text-sm">
                                        <div class="flex items-start gap-2">
                                            <span class="text-[11px] font-bold mt-0.5">FROM</span>
                                            <div>
                                                <p class="font-semibold text-xs text-gray-500">{{ $t('event.from_date')
                                                }}</p>
                                                <p class="font-medium">{{ formatDate(event?.start_date) }}</p>
                                            </div>
                                        </div>
                                        <div class="flex items-start gap-2">
                                            <span class="text-[11px] font-bold mt-0.5">TO</span>
                                            <div>
                                                <p class="font-semibold text-xs text-gray-500">{{ $t('event.to_date') }}
                                                </p>
                                                <p class="font-medium">{{ formatDate(event?.end_date) }}</p>
                                            </div>
                                        </div>
                                        <div class="flex items-start gap-2">
                                            <span class="text-[11px] font-bold mt-0.5">TIME</span>
                                            <div>
                                                <p class="font-semibold text-xs text-gray-500">{{ $t('event.time_label')
                                                }}</p>
                                                <p class="font-medium">{{ event?.time || $t('event.time_default') }}</p>
                                            </div>
                                        </div>
                                        <div class="flex items-start gap-2">
                                            <span class="text-[11px] font-bold mt-0.5">LOC</span>
                                            <div>
                                                <p class="font-semibold text-xs text-gray-500">{{ $t('event.location')
                                                }}</p>
                                                <p class="font-medium">{{ event?.city?.translation?.name ||
                                                    $t('event.city_default') }}</p>
                                            </div>
                                        </div>
                                        <div v-if="event?.user?.name" class="flex items-start gap-2">
                                            <span class="text-[11px] font-bold mt-0.5">USER</span>
                                            <div>
                                                <p class="font-semibold text-xs text-gray-500">{{ $t('event.organizer')
                                                }}</p>
                                                <p class="font-medium">{{ event.user.name }}</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Wishlist -->
                                <div class="pt-5 border-t border-gray-200 space-y-3">
                                    <button @click="addToWishlist" :disabled="wishlistLoading || isInWishlist"
                                        class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold text-sm py-3 px-5 rounded-lg transition shadow flex items-center justify-center gap-2"
                                        :class="{ 'opacity-70 cursor-not-allowed': wishlistLoading || isInWishlist }">
                                        <span v-if="wishlistLoading" class="animate-pulse">{{
                                            $t('event.adding_to_wishlist') }}</span>
                                        <template v-else>
                                            ♥
                                            {{ isInWishlist ? $t('event.already_in_wishlist') :
                                                $t('event.add_to_wishlist') }}
                                        </template>
                                    </button>
                                    <p v-if="wishlistError"
                                        class="text-red-600 text-center text-xs mt-1 bg-red-50 border border-red-200 p-2 rounded-lg">
                                        {{ wishlistError }}
                                    </p>
                                    <p v-if="wishlistSuccess"
                                        class="text-green-600 text-center text-xs mt-1 bg-green-50 border border-green-200 p-2 rounded-lg">
                                        {{ $t('event.wishlist_success') }}
                                    </p>
                                </div>

                            </div>
                        </div>

                    </div>
                </div>
            </div>

            <!-- Upload Modal -->
            <div v-if="showUploadModal" class="fixed inset-0 bg-black/70 z-50 flex items-center justify-center p-4"
                @click.self="showUploadModal = false">
                <div class="bg-white rounded-2xl shadow-2xl max-w-lg w-full max-h-[90vh] overflow-y-auto">
                    <div class="p-6 border-b">
                        <h3 class="text-2xl font-bold text-gray-900">
                            {{ $t('event.upload_media_to_event') }}
                        </h3>
                        <p class="text-gray-600 mt-1 text-sm">{{ $t('eventUpload.maxPerFile') }}</p>
                    </div>

                    <div class="p-6">
                        <div @dragover.prevent @drop.prevent="handleDrop"
                            class="border-2 border-dashed border-gray-300 rounded-xl p-8 text-center hover:border-indigo-500 transition-colors">
                            <input type="file" multiple accept="image/*,video/mp4,video/webm,video/quicktime"
                                class="hidden" id="mediaUploadInput" @change="handleFileChange" />
                            <label for="mediaUploadInput" class="cursor-pointer">
                                <div class="text-indigo-600 text-5xl mb-4">↑</div>
                                <p class="text-lg font-medium text-gray-700">
                                    {{ $t('eventUpload.dragFiles') }}
                                    <span class="text-indigo-600 hover:underline">{{ $t('eventUpload.clickToChoose') }}</span>
                                </p>
                                <p class="text-sm text-gray-500 mt-2">{{ $t('eventUpload.supportedFiles') }}</p>
                            </label>
                        </div>

                        <div v-if="selectedFiles.length > 0" class="mt-6">
                            <h4 class="font-semibold mb-3">{{ $t('eventUpload.selectedFiles', { count: selectedFiles.length }) }}</h4>
                            <ul class="space-y-2 max-h-48 overflow-y-auto pr-2">
                                <li v-for="(file, idx) in selectedFiles" :key="idx"
                                    class="flex items-center justify-between bg-gray-50 p-3 rounded-lg">
                                    <div class="flex items-center gap-3 truncate">
                                        <span class="text-gray-600">{{ file?.name }}</span>
                                        <span class="text-xs text-gray-500">({{ formatFileSize(file?.size) }})</span>
                                    </div>
                                    <button @click="removeFile(idx)"
                                        class="text-red-600 hover:text-red-800 text-sm">{{ $t('common.remove') }}</button>
                                </li>
                            </ul>
                        </div>

                        <p v-if="uploadError" class="mt-4 text-red-600 bg-red-50 border border-red-200 p-3 rounded-lg">
                            {{ uploadError }}
                        </p>
                        <p v-if="uploadSuccess"
                            class="mt-4 text-green-600 bg-green-50 border border-green-200 p-3 rounded-lg">
                            {{ uploadSuccess }}
                        </p>
                    </div>

                    <div class="p-6 border-t flex gap-3 justify-end">
                        <button @click="showUploadModal = false"
                            class="px-6 py-2.5 border border-gray-300 rounded-lg hover:bg-gray-50">{{ $t('common.cancel') }}</button>
                        <button @click="uploadFiles" :disabled="uploading || selectedFiles.length === 0"
                            class="px-6 py-2.5 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2">
                            <span v-if="uploading" class="animate-pulse">{{ $t('eventUpload.uploading') }}</span>
                            <template v-else>{{ $t('eventUpload.uploadFiles') }}</template>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Report Modal -->
    <Teleport to="body">
        <Transition name="modal">
            <div v-if="reportModal" class="fixed inset-0 z-50 flex items-center justify-center p-4" dir="rtl">
                <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" @click="closeReportModal"></div>
                <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-md p-6 z-10">

                    <!-- Success -->
                    <div v-if="reportSuccess" class="flex flex-col items-center py-6 gap-3 text-center">
                        <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center text-sm font-bold">OK
                        </div>
                        <h4 class="text-lg font-bold text-gray-800">{{ $t('report.successTitle') }}</h4>
                        <p class="text-sm text-gray-500">{{ $t('report.successMessage') }}</p>
                    </div>

                    <!-- Form -->
                    <template v-else>
                        <div class="flex items-center justify-between mb-5">
                            <div class="flex items-center gap-2">
                                <div
                                    class="w-8 h-8 bg-red-100 rounded-full flex items-center justify-center text-red-500">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none"
                                        viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="M12 9v4m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z" />
                                    </svg>
                                </div>
                                <h3 class="text-base font-bold text-gray-800">{{ $t('report.modalTitle') }}</h3>
                            </div>
                            <button @click="closeReportModal"
                                class="w-7 h-7 flex items-center justify-center rounded-full text-gray-400 hover:bg-gray-100 hover:text-gray-600 transition">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24"
                                    stroke="currentColor" stroke-width="2.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>

                        <p class="text-sm text-gray-500 mb-4">{{ $t('report.reasonPrompt') }}</p>

                        <div class="grid grid-cols-2 gap-2 mb-5">
                            <button v-for="reason in reportReasons" :key="reason.value"
                                @click="reportReason = reason.value" :class="[
                                    'flex items-center gap-2 px-3 py-2.5 rounded-xl border text-sm text-right transition-all duration-150',
                                    reportReason === reason.value
                                        ? 'bg-red-50 border-red-400 text-red-700 font-semibold shadow-sm'
                                        : 'bg-gray-50 border-gray-200 text-gray-700 hover:border-red-300 hover:bg-red-50/50'
                                ]">
                                <span class="text-base leading-none">{{ reason.icon }}</span>
                                <span class="leading-tight">{{ reason.label }}</span>
                            </button>
                        </div>

                        <p v-if="reportError"
                            class="text-xs text-red-500 mb-3 bg-red-50 border border-red-200 px-3 py-2 rounded-lg">
                            {{ reportError }}
                        </p>

                        <div class="flex gap-2 justify-end">
                            <button @click="closeReportModal"
                                class="px-4 py-2 rounded-xl border border-gray-200 text-sm text-gray-600 hover:bg-gray-50 transition">
                                {{ $t('common.cancel') }}
                            </button>
                            <button @click="submitReport" :disabled="reportLoading || !reportReason" :class="[
                                'px-5 py-2 rounded-xl text-sm font-semibold transition-all duration-150',
                                reportReason && !reportLoading
                                    ? 'bg-red-500 hover:bg-red-600 text-white shadow-sm'
                                    : 'bg-gray-200 text-gray-400 cursor-not-allowed'
                            ]">
                                <span v-if="reportLoading" class="flex items-center gap-1.5">
                                    <svg class="animate-spin w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" fill="none"
                                        viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                                            stroke-width="4" />
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z" />
                                    </svg>
                                    {{ $t('report.submitting') }}
                                </span>
                                <span v-else>{{ $t('report.submit') }}</span>
                            </button>
                        </div>
                    </template>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from "vue";
import { PhotoIcon, XMarkIcon } from "@heroicons/vue/24/outline";
import { useRoute } from "vue-router";
import { useI18n } from "vue-i18n";
import { EventService } from "@/services/singleEventService/singleEventService";
import CommentService, { extractErrorMessage } from "../../services/CommentService/CommentService";
import { CartService } from "@/services/CartService/CartService";
import { ReplyService } from "../../services/ReplyService/ReplyService";
import { AuthService } from "../../services/AuthService/AuthService";
import { LikeService } from "../../services/LikeService/LikeService";
import { WishlistService } from "../../services/WishlistService/WishlistService";
import { MediaRequestService } from "../../services/MediaRequestService/MediaRequestService";

const cartLoading = ref(false);
const collectionLoading = ref(false);
const collectionAlert = ref({
    show: false,
    type: '', // success | error
    message: ''
});

const route = useRoute();
const { t } = useI18n();
const slug = route.params?.slug;

// ─── State ────────────────────────────────────────────────────────────────────
const event = ref(null);
const loading = ref(true);
const lightboxOpen = ref(false);
const lightboxIndex = ref(0);
const newComment = ref("");
const commentImageInput = ref(null);
const commentImages = ref([]);
const commentLoading = ref(false);
const commentError = ref("");
const commentSuccess = ref(false);
const currentUserId = ref(null);
const likesCount = ref(0);
const isLiked = ref(false);
const likeLoading = ref(false);
const likeError = ref("");
const wishlistLoading = ref(false);
const isInWishlist = ref(false);
const wishlistError = ref("");
const wishlistSuccess = ref(false);
const isAuthenticated = ref(false);
const MAX_COMMENT_IMAGES = 2;
const MAX_COMMENT_IMAGE_SIZE = 5 * 1024 * 1024;
const COMMENT_IMAGE_TYPES = new Set(['image/jpeg', 'image/png', 'image/webp']);
const showUploadModal = ref(false);
const selectedFiles = ref([]);
const uploading = ref(false);
const uploadError = ref("");
const uploadSuccess = ref("");
const replyingTo = ref(null);
const replyTexts = ref({});
const replyLoading = ref({});
const replyErrors = ref({});
const deleteCommentErrors = ref({});

const refreshEvent = async () => {
    const response = await EventService.getSingleEvent(slug);
    event.value = response?.data?.data || response || null;
};

const ensureEventImagesArray = () => {
    if (!event.value) return;

    if (!Array.isArray(event.value.images)) {
        event.value.images = [];
    }
};

const ensureEventCommentsArray = () => {
    if (!event.value) return;

    if (!Array.isArray(event.value.comments)) {
        event.value.comments = [];
    }
};

const normalizeUploadedMedia = (payload) => {
    const data = payload?.data?.data || payload?.data || payload;

    const media =
        data?.media ||
        data?.images ||
        data?.image ||
        data?.files ||
        data?.uploaded_media ||
        [];

    if (Array.isArray(media)) {
        return media;
    }

    if (media) {
        return [media];
    }

    return [];
};

// ─── Reactions ────────────────────────────────────────────────────────────────
const commentReactions = ref({});
const reactionLoading = ref({});
const reactionErrors = ref({});

const reactionEndpointMap = {
    support: "support",
    exhibitions: "Exhibitions",
    neutral: "neutral",
};
const cartSuccess = ref(false);
const cartError = ref("");

const cartAlert = ref({
    show: false,
    type: "", // success | error
    message: ""
});

const showCartAlert = (type, message) => {
    cartAlert.value = {
        show: true,
        type,
        message
    };

    setTimeout(() => {
        cartAlert.value.show = false;
    }, 3000);
};

const placeholderImage = 'https://developers.elementor.com/docs/assets/img/elementor-placeholder-image.png';

const getBackendOrigin = () => {
    const apiUrl =
        import.meta.env?.VITE_API_URL ||
        import.meta.env?.VITE_API_BASE_URL ||
        '';

    if (!apiUrl) {
        return window.location.origin;
    }

    try {
        return new URL(apiUrl).origin;
    } catch {
        return window.location.origin;
    }
};

const getMediaRawPath = (mediaOrPath) => {
    if (!mediaOrPath) return '';

    if (typeof mediaOrPath === 'string') {
        return mediaOrPath;
    }

    return (
        mediaOrPath.image_url ||
        mediaOrPath.preview_url ||
        mediaOrPath.url ||
        mediaOrPath.path ||
        mediaOrPath.image ||
        mediaOrPath.file_path ||
        mediaOrPath.file ||
        mediaOrPath.src ||
        ''
    );
};

const getMediaUrl = (mediaOrPath) => {
    const rawPath = getMediaRawPath(mediaOrPath);

    if (!rawPath || typeof rawPath !== 'string') {
        return '';
    }

    const path = rawPath.replace(/\\/g, '/').trim();

    if (path.startsWith('http://') || path.startsWith('https://')) {
        return path;
    }

    const backendOrigin = getBackendOrigin();

    if (path.startsWith('/storage/')) {
        return `${backendOrigin}${path}`;
    }

    if (path.startsWith('storage/')) {
        return `${backendOrigin}/${path}`;
    }

    if (path.startsWith('public/')) {
        return `${backendOrigin}/storage/${path.replace(/^public\//, '')}`;
    }

    if (path.startsWith('/uploads/')) {
        return `${backendOrigin}${path}`;
    }

    if (path.startsWith('uploads/')) {
        return `${backendOrigin}/${path}`;
    }

    return `${backendOrigin}/storage/${path.replace(/^\/+/, '')}`;
};

const onMediaImageError = (e) => {
    if (e?.target) {
        e.target.src = placeholderImage;
    }
};

const addToCart = async (mediaId) => {
    if (!mediaId) {
        showCartAlert("error", t("cart.errors.itemNotIdentified"));
        return;
    }

    const token = localStorage.getItem("auth_token");

    if (!token) {
        showCartAlert("error", t("event.comment_login_required"));
        return;
    }

    cartLoading.value = true;

    try {
        await CartService.addToCart(mediaId);

        showCartAlert("success", t("cart.messages.added"));
    } catch (err) {
        showCartAlert("error", err?.message || t("cart.errors.addFailed"));
    } finally {
        cartLoading.value = false;
    }
};

// ─── Add Collection to Cart ────────────────────────────────────────────────────
const addCollectionToCart = async () => {
    const token = localStorage.getItem("auth_token");

    if (!token) {
        collectionAlert.value = {
            show: true,
            type: 'error',
            message: t("event.comment_login_required")
        };
        return;
    }

    if (!event.value?.id || eventImages.value.length === 0) {
        collectionAlert.value = {
            show: true,
            type: 'error',
            message: t("event.no_media")
        };
        return;
    }

    collectionLoading.value = true;
    collectionAlert.value.show = false;

    try {
        const response = await fetch(`/api/v1/collections/${event.value.id}/add-to-cart`, {
            method: 'POST',
            headers: {
                'Authorization': `Bearer ${token}`,
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
            },
        });

        const data = await response.json().catch(() => null);

        if (!response.ok) {
            throw new Error(data?.message || t("cart.errors.collectionAddFailed"));
        }

        const totalImages = data?.total_images || eventImages.value.length;

        collectionAlert.value = {
            show: true,
            type: 'success',
            message: t("cart.messages.collectionAddedDiscount", { count: totalImages })
        };

        setTimeout(() => {
            collectionAlert.value.show = false;
        }, 4000);

    } catch (err) {
        console.error('Collection Error:', err);
        collectionAlert.value = {
            show: true,
            type: 'error',
            message: err?.message || t("cart.errors.collectionAddFailed")
        };
    } finally {
        collectionLoading.value = false;
    }
};

const setReaction = async (commentId, type) => {
    if (!commentId || reactionLoading.value[commentId]) return;
    const comment = event.value?.comments?.find((c) => c?.id === commentId);
    if (!comment) return;

    const previousReaction = commentReactions.value[commentId];
    const isToggle = previousReaction === type;

    const snap = {
        support: comment.support_count ?? 0,
        exhibitions: comment.exhibitions_count ?? 0,
        neutral: comment.neutral_count ?? 0,
    };

    commentReactions.value[commentId] = isToggle ? null : type;

    if (isToggle) {
        comment[`${previousReaction}_count`] = Math.max(0, snap[previousReaction] - 1);
    } else {
        if (previousReaction) {
            comment[`${previousReaction}_count`] = Math.max(0, snap[previousReaction] - 1);
        }
        comment[`${type}_count`] = snap[type] + 1;
    }

    reactionLoading.value[commentId] = true;
    reactionErrors.value[commentId] = "";

    try {
        const endpoint = reactionEndpointMap[type];
        await CommentService.reactToComment(commentId, endpoint);
    } catch (err) {
        commentReactions.value[commentId] = previousReaction;
        comment.support_count = snap.support;
        comment.exhibitions_count = snap.exhibitions;
        comment.neutral_count = snap.neutral;
        reactionErrors.value[commentId] = extractErrorMessage(err);
        setTimeout(() => (reactionErrors.value[commentId] = ""), 5000);
    } finally {
        reactionLoading.value[commentId] = false;
    }
};

const toNumber = (value) => {
    if (value === null || value === undefined || value === '') {
        return 0;
    }

    if (typeof value === 'number') {
        return Number.isFinite(value) ? value : 0;
    }

    if (typeof value === 'string') {
        const cleaned = value.replace(/[^0-9.-]/g, '');
        const number = parseFloat(cleaned);
        return Number.isFinite(number) ? number : 0;
    }

    return 0;
};

const formatPrice = (value) => {
    return toNumber(value).toFixed(2);
};

const getImagePrice = (img) => {
    return toNumber(
        img?.price ??
        img?.amount ??
        img?.media_price ??
        img?.image_price ??
        img?.pivot?.price ??
        img?.original_price ??
        0
    );
};
// ─── Report ───────────────────────────────────────────────────────────────────
const reportModal = ref(false);
const reportCommentId = ref(null);
const reportReason = ref("");
const reportLoading = ref(false);
const reportSuccess = ref(false);
const reportError = ref("");

const reportReasons = computed(() => [
    { value: "spam", label: t("report.reasons.spam"), icon: "SP" },
    { value: "offensive", label: t("report.reasons.offensive"), icon: "OF" },
    { value: "inappropriate", label: t("report.reasons.inappropriate"), icon: "IN" },
    { value: "illegal", label: t("report.reasons.illegal"), icon: "LG" },
    { value: "untrue", label: t("report.reasons.untrue"), icon: "UN" },
    { value: "False information", label: t("report.reasons.falseInformation"), icon: "FI" },
    { value: "other", label: t("report.reasons.other"), icon: "OT" },
]);

const openReportModal = (commentId) => {
    reportCommentId.value = commentId;
    reportReason.value = "";
    reportSuccess.value = false;
    reportError.value = "";
    reportModal.value = true;
};

const closeReportModal = () => {
    reportModal.value = false;
    reportCommentId.value = null;
    reportReason.value = "";
    reportSuccess.value = false;
    reportError.value = "";
};

const submitReport = async () => {
    if (!reportReason.value) {
        reportError.value = t("report.errors.selectReason");
        return;
    }
    reportLoading.value = true;
    reportError.value = "";
    try {
        await CommentService.reportComment(reportCommentId.value, reportReason.value);
        reportSuccess.value = true;
        setTimeout(() => closeReportModal(), 2000);
    } catch (err) {
        reportError.value = extractErrorMessage(err);
    } finally {
        reportLoading.value = false;
    }
};

// ─── Reply ────────────────────────────────────────────────────────────────────
const toggleReplyForm = (commentId) => {
    if (!commentId) return;
    replyingTo.value = replyingTo.value === commentId ? null : commentId;
    if (!replyTexts.value[commentId]) replyTexts.value[commentId] = "";
    replyErrors.value[commentId] = "";
};

const cancelReply = (commentId) => {
    replyingTo.value = null;
    if (commentId) {
        replyTexts.value[commentId] = "";
        replyErrors.value[commentId] = "";
    }
};

const addReply = async (commentId) => {
    if (!commentId) return;
    const replyText = replyTexts.value[commentId]?.trim();
    if (!replyText) return;

    replyLoading.value[commentId] = true;
    replyErrors.value[commentId] = "";

    try {
        const response = await ReplyService.createReply(commentId, { comment: replyText });

        if (response?.data?.status === "success") {
            const newReply = {
                id: response.data?.data?.id || Date.now(),
                comment: replyText,
                comment_id: commentId,
                user_id: currentUserId.value,
                created_at: new Date().toISOString(),
                user: { name: t("commentsPage.you") },
            };

            ensureEventCommentsArray();
            const comment = event.value?.comments?.find((c) => c?.id === commentId);
            if (comment) {
                if (!comment.replies) comment.replies = [];
                comment.replies.push(newReply);
            }

            replyTexts.value[commentId] = "";
            replyingTo.value = null;
            if (event.value && event.value.comments_count !== undefined) event.value.comments_count++;
        }
    } catch (err) {
        replyErrors.value[commentId] = extractErrorMessage(err);
    } finally {
        replyLoading.value[commentId] = false;
    }
};

// ─── Auth / User ──────────────────────────────────────────────────────────────
const checkAuth = () => {
    isAuthenticated.value = !!localStorage.getItem("auth_token");
};

const fetchCurrentUser = async () => {
    try {
        const token = localStorage.getItem("auth_token");
        if (!token) return;
        const res = await AuthService.getProfile();
        currentUserId.value = res?.data?.data?.user?.id || res?.data?.id || null;
    } catch (err) {
        console.log("فشل جلب بيانات المستخدم", err);
    }
};

// ─── Likes ────────────────────────────────────────────────────────────────────
const fetchLikesInfo = async () => {
    if (!event.value?.id) return;
    try {
        const res = await LikeService.getLikes(event.value.id);
        if (res?.data?.status === "success" && res?.data?.data) {
            likesCount.value = res.data.data.count ?? 0;
            isLiked.value = !!res.data.data.liked;
        }
    } catch (err) {
        console.error("خطأ في جلب بيانات اللايكات", err);
    }
};

const toggleLike = async () => {
    if (likeLoading.value || isLiked.value) return;
    if (!event.value?.id) return;

    const token = localStorage.getItem("auth_token");
    if (!token) {
        likeError.value = t("event.comment_login_required");
        setTimeout(() => (likeError.value = ""), 4000);
        return;
    }

    likeLoading.value = true;
    likeError.value = "";

    try {
        const res = await LikeService.createLike(event.value.id);
        if (res?.data?.status === "success") {
            likesCount.value += 1;
            isLiked.value = true;
        }
    } catch (err) {
        likeError.value = extractErrorMessage(err);
        setTimeout(() => (likeError.value = ""), 5000);
    } finally {
        likeLoading.value = false;
    }
};

// ─── Wishlist ─────────────────────────────────────────────────────────────────
const addToWishlist = async () => {
    if (wishlistLoading.value || isInWishlist.value) return;
    if (!event.value?.id) return;

    const token = localStorage.getItem("auth_token");
    if (!token) {
        wishlistError.value = t("event.comment_login_required");
        setTimeout(() => (wishlistError.value = ""), 4000);
        return;
    }

    wishlistLoading.value = true;
    wishlistError.value = "";
    wishlistSuccess.value = false;

    try {
        const res = await WishlistService.addToWishlist(event.value.id);
        if (res?.data?.status === "success") {
            isInWishlist.value = true;
            wishlistSuccess.value = true;
            setTimeout(() => (wishlistSuccess.value = false), 4000);
        }
    } catch (err) {
        wishlistError.value = extractErrorMessage(err);
        setTimeout(() => (wishlistError.value = ""), 5000);
    } finally {
        wishlistLoading.value = false;
    }
};

// ─── Comments ─────────────────────────────────────────────────────────────────
const clearCommentImages = () => {
    commentImages.value.forEach((item) => URL.revokeObjectURL(item.previewUrl));
    commentImages.value = [];

    if (commentImageInput.value) {
        commentImageInput.value.value = "";
    }
};

const removeCommentImage = (index) => {
    const [removed] = commentImages.value.splice(index, 1);

    if (removed) {
        URL.revokeObjectURL(removed.previewUrl);
    }
};

const handleCommentImageSelection = (inputEvent) => {
    const files = Array.from(inputEvent.target.files || []);
    let validationMessage = "";

    commentError.value = "";

    for (const file of files) {
        if (commentImages.value.length >= MAX_COMMENT_IMAGES) {
            validationMessage = "A comment can have a maximum of 2 images.";
            break;
        }

        if (!COMMENT_IMAGE_TYPES.has(file.type)) {
            validationMessage = "Comment images must be JPG, JPEG, PNG, or WEBP files.";
            continue;
        }

        if (file.size > MAX_COMMENT_IMAGE_SIZE) {
            validationMessage = "Each comment image must not exceed 5 MB.";
            continue;
        }

        const duplicate = commentImages.value.some((item) => (
            item.file.name === file.name &&
            item.file.size === file.size &&
            item.file.lastModified === file.lastModified
        ));

        if (duplicate) {
            validationMessage = "That image is already selected.";
            continue;
        }

        commentImages.value.push({
            file,
            previewUrl: URL.createObjectURL(file),
            key: `${file.name}-${file.size}-${file.lastModified}`,
        });
    }

    inputEvent.target.value = "";
    commentError.value = validationMessage;
};

onUnmounted(clearCommentImages);

const addComment = async () => {
    if (!newComment.value.trim() || !event.value?.id) return;

    commentLoading.value = true;
    commentError.value = "";
    commentSuccess.value = false;

    try {
        const formData = new FormData();
        formData.append("comment", newComment.value.trim());
        commentImages.value.forEach((item) => {
            formData.append("images[]", item.file);
        });

        const response = await CommentService.createComment(event.value.id, formData);

        if (response?.data?.status === "success") {
            const newCommentData = response.data?.data;

            if (!newCommentData) {
                throw new Error("The server did not return the created comment.");
            }

            ensureEventCommentsArray();
            event.value.comments.unshift(newCommentData);
            newComment.value = "";
            clearCommentImages();
            commentSuccess.value = true;
            setTimeout(() => (commentSuccess.value = false), 4000);
        }
    } catch (err) {
        commentError.value = extractErrorMessage(err);
    } finally {
        commentLoading.value = false;
    }
};

const deleteComment = async (commentId) => {
    if (!commentId) return;
    if (!confirm(t("commentsPage.confirmDelete"))) return;

    deleteCommentErrors.value[commentId] = "";

    try {
        await CommentService.deleteComment(commentId);
        event.value.comments = (event.value?.comments || []).filter((c) => c?.id !== commentId);
        if (event.value && event.value.comments_count !== undefined) event.value.comments_count--;
    } catch (err) {
        deleteCommentErrors.value[commentId] = extractErrorMessage(err);
        setTimeout(() => (deleteCommentErrors.value[commentId] = ""), 5000);
    }
};

// ─── Media Upload ─────────────────────────────────────────────────────────────
const handleFileChange = (e) => addValidFiles(Array.from(e?.target?.files || []));
const handleDrop = (e) => addValidFiles(Array.from(e?.dataTransfer?.files || []));

const addValidFiles = (files) => {
    const MAX = 100 * 1024 * 1024;
    (files || []).forEach((file) => {
        if (!file) return;
        if (file.size > MAX) {
            uploadError.value = t("eventUpload.errors.fileTooLarge", { name: file.name });
            return;
        }
        if (!file.type?.startsWith("image/") && !file.type?.startsWith("video/")) {
            uploadError.value = t("eventUpload.errors.unsupportedType", { name: file.name });
            return;
        }
        selectedFiles.value.push(file);
    });
};

const removeFile = (i) => selectedFiles.value.splice(i, 1);

const formatFileSize = (bytes) => {
    const size = toNumber(bytes);
    if (size < 1024) return size + " B";
    if (size < 1024 * 1024) return (size / 1024).toFixed(1) + " KB";
    return (size / (1024 * 1024)).toFixed(1) + " MB";
};

const uploadFiles = async () => {
    if (!event.value?.id || !selectedFiles.value.length) return;

    uploading.value = true;
    uploadError.value = "";
    uploadSuccess.value = "";

    const formData = new FormData();
    selectedFiles.value.forEach((f) => formData.append("url[]", f));

    try {
        const res = await MediaRequestService.upload(event.value.id, formData);

        if (res?.data?.status === "success") {
            ensureEventImagesArray();

            const uploadedMedia = normalizeUploadedMedia(res);

            if (uploadedMedia.length > 0) {
                event.value.images.push(...uploadedMedia);
            } else {
                await refreshEvent();
            }

            uploadSuccess.value = t("eventUpload.success");
            selectedFiles.value = [];
            setTimeout(() => {
                showUploadModal.value = false;
                uploadSuccess.value = "";
            }, 1500);
        }
    } catch (err) {
        uploadError.value = extractErrorMessage(err);
    } finally {
        uploading.value = false;
    }
};

// ─── Computed ─────────────────────────────────────────────────────────────────
const eventImages = computed(() => {
    const images =
        event.value?.images ||
        event.value?.media ||
        event.value?.event_images ||
        [];

    return Array.isArray(images) ? images : [];
});

const hasMedia = computed(() => eventImages.value.length > 0);

const collectionTotalPrice = computed(() => {
    return eventImages.value.reduce((sum, img) => {
        return sum + getImagePrice(img);
    }, 0);
});

const collectionDiscountAmount = computed(() => {
    return collectionTotalPrice.value * 0.10;
});

const collectionDiscountedPrice = computed(() => {
    return Math.max(
        collectionTotalPrice.value - collectionDiscountAmount.value,
        0
    );
});

const heroMedia = computed(() => {
    return (
        eventImages.value.find((item) => {
            return item && !isMediaVideo(item) && getMediaUrl(item);
        }) || null
    );
});
const heroMediaComponent = computed(() =>
    isMediaVideo(heroMedia.value) ? "video" : "img"
);
const currentMedia = computed(() => eventImages.value[lightboxIndex.value] || null);

const isVideoUrl = (url) => {
    if (!url || typeof url !== 'string') return false;

    const cleanUrl = url.split('?')[0].toLowerCase();

    return ['.mp4', '.webm', '.ogg', '.mov', '.m4v'].some((ext) =>
        cleanUrl.endsWith(ext)
    );
};

const isMediaVideo = (media) => {
    if (!media) return false;

    if (media.video === true || media.type === 'video') {
        return true;
    }

    return isVideoUrl(getMediaRawPath(media));
};

// ─── Date Helpers ─────────────────────────────────────────────────────────────
const getLocale = () => {
    const lang = (localStorage.getItem("language") || "ar").toLowerCase();
    if (lang === "ar") return "ar-EG";
    if (lang === "fr") return "fr-FR";
    return "en-US";
};

const formatDate = (dateStr) => {
    if (!dateStr) return "—";
    try {
        return new Date(dateStr).toLocaleDateString(getLocale(), {
            weekday: "long", year: "numeric", month: "long", day: "numeric",
        });
    } catch { return "—"; }
};

const formatCommentDate = (dateStr) => {
    if (!dateStr) return "—";
    try {
        return new Date(dateStr).toLocaleString(getLocale(), {
            year: "numeric", month: "short", day: "numeric",
            hour: "numeric", minute: "2-digit",
        });
    } catch { return "—"; }
};

// ─── Lightbox Helpers ─────────────────────────────────────────────────────────
const openLightbox = (index) => {
    lightboxIndex.value = index;
    lightboxOpen.value = true;
};

// Navigation inside lightbox
const lightboxPrev = () => {
    const total = eventImages.value.length;
    if (!total) return;
    lightboxIndex.value = (lightboxIndex.value - 1 + total) % total;
};

const lightboxNext = () => {
    const total = eventImages.value.length;
    if (!total) return;
    lightboxIndex.value = (lightboxIndex.value + 1) % total;
};

// ─── Lifecycle ────────────────────────────────────────────────────────────────
onMounted(async () => {
    await fetchCurrentUser();
    checkAuth();
    if (!slug) { loading.value = false; return; }

    loading.value = true;
    try {
        await refreshEvent();
        await fetchLikesInfo();
    } catch (err) {
        console.error("خطأ في جلب الحدث:", err);
        event.value = null;
    } finally {
        loading.value = false;
    }
});
</script>

<style scoped>
.view-all-btn {
    background: #0d6efd;
    color: #fff;
    border: none;
    margin-bottom: 20px;
    padding: 8px 16px;
    border-radius: 6px;
    cursor: pointer;
    font-size: 14px;
    transition: transform 0.5s ease, box-shadow 0.5s ease, background 0.3s ease;
}

.view-all-btn:hover {
    transform: scale(1.45);
    box-shadow: 0 6px 15px rgba(0, 0, 0, 0.25);
    background: #084db4;
}

.comments-section {
    margin-bottom: 40px;
}

.comments-title {
    font-size: 20px;
    font-weight: 700;
    margin-bottom: 15px;
    border-bottom: 1px solid #e5e7eb;
    padding-bottom: 8px;
}

.comment-box {
    background: #f9fafb;
    padding: 8px 10px;
    border-radius: 8px;
    border: 1px solid #e5e7eb;
    margin-top: 10px;
    position: relative;
    transition: 0.2s ease;
}

.comment-box:hover {
    background: #f3f4f6;
}

.comment-content {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
}

.comment-header {
    display: flex;
    gap: 10px;
    align-items: center;
}

.comment-user {
    font-size: 13px;
    font-weight: 600;
    color: #1f2937;
}

.comment-date {
    font-size: 11px;
    color: #6b7280;
}

.comment-body {
    margin-top: 4px;
    font-size: 13px;
    color: #374151;
    line-height: 1.4;
}

.delete-btn {
    background: #fee2e2;
    color: #dc2626;
    border: none;
    width: 20px;
    /* أصغر */
    height: 20px;
    border-radius: 50%;
    cursor: pointer;
    font-size: 12px;
    font-weight: bold;
    transition: all 0.2s ease;
    margin-left: 6px;
}

.delete-btn:hover {
    background: #dc2626;
    color: #fff;
}

.comment-form {
    margin-top: 30px;
}

.form-title {
    font-size: 16px;
    font-weight: 700;
    margin-bottom: 10px;
}

.comment-textarea {
    width: 100%;
    padding: 8px;
    border-radius: 8px;
    border: 1px solid #d1d5db;
    font-size: 13px;
    resize: none;
    outline: none;
    transition: 0.2s;
}

.comment-textarea:focus {
    border-color: #3b82f6;
}

.form-actions {
    margin-top: 10px;
    display: flex;
    justify-content: flex-end;
}

.submit-btn {
    background: #2563eb;
    color: white;
    border: none;
    padding: 6px 14px;
    border-radius: 8px;
    font-size: 13px;
    cursor: pointer;
    transition: 0.2s;
}

.submit-btn:hover {
    background: #1d4ed8;
}

.submit-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.error-msg {
    color: #dc2626;
    font-size: 12px;
    margin-top: 6px;
}

.success-msg {
    color: #16a34a;
    font-size: 12px;
    margin-top: 6px;
}

.single-event-page {
    background:
        radial-gradient(circle at top left, rgba(48, 168, 255, 0.10), transparent 34rem),
        linear-gradient(180deg, #FFFFFF, #F8FAFC);
    color: #0F172A;
}

.single-event-page .bg-white,
.single-event-page .comment-form,
.single-event-page .comment-box {
    border: 1px solid #E5EDF6;
    border-radius: 22px;
    box-shadow: 0 10px 35px rgba(13, 77, 151, 0.06);
}

.single-event-page .bg-gray-50,
.single-event-page .bg-gray-50\/60 {
    background-color: #F8FAFC !important;
}

.single-event-page h1,
.single-event-page h2,
.single-event-page h3,
.single-event-page .comments-title {
    color: #06142A;
}

.single-event-page .text-indigo-600,
.single-event-page .text-blue-600 {
    color: #0D4D97 !important;
}

.single-event-page .bg-indigo-600,
.single-event-page .bg-blue-600,
.submit-btn,
.view-all-btn {
    background: linear-gradient(135deg, #0D4D97, #1677FF) !important;
}

.single-event-page textarea,
.comment-textarea {
    border-color: #DCE8F5;
    border-radius: 14px;
}

.single-event-page textarea:focus,
.comment-textarea:focus {
    border-color: #1677FF;
    box-shadow: 0 0 0 4px rgba(22, 119, 255, 0.10);
}

.view-all-btn:hover {
    transform: translateY(-1px);
    box-shadow: 0 14px 30px rgba(22, 119, 255, 0.20);
}
</style>
