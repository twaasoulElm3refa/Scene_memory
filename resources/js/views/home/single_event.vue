<template>
    <div class="min-h-screen bg-gray-50">

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
                <component :is="heroMediaComponent" v-if="heroMedia"
                    :src="getMediaUrl(heroMedia.full_url || heroMedia.preview_url)"
                    class="w-full h-[300px] md:h-[400px] lg:h-[500px] object-cover" controls autoplay muted loop
                    playsinline />
                <img v-else src="https://images.unsplash.com/..." :alt="event.translation.title"
                    class="w-full h-[300px] md:h-[400px] lg:h-[500px] object-cover" />

                <div v-if="heroMedia && (heroMedia.video || isVideoUrl(heroMedia.full_url))"
                    class="absolute inset-0 bg-black/30 flex items-center justify-center z-10 pointer-events-none">
                    <div
                        class="w-24 h-24 md:w-32 md:h-32 rounded-full bg-white/20 backdrop-blur-sm flex items-center justify-center shadow-2xl">
                        <span class="text-white text-5xl md:text-7xl drop-shadow-lg">▶</span>
                    </div>
                </div>

                <div class="absolute top-6 left-6 md:left-10 flex flex-wrap gap-3 z-20">
                    <span
                        class="bg-blue-100/90 backdrop-blur-sm text-blue-900 px-5 py-2 rounded-full text-base font-bold shadow-lg uppercase tracking-wider">
                        {{ event.city?.translation.name || $t('event.city_default') }}
                    </span>
                    <span v-if="event.sub_categorey?.translation.name"
                        class="bg-green-100/90 backdrop-blur-sm text-green-900 px-5 py-2 rounded-full text-base font-bold shadow-lg uppercase tracking-wider">
                        {{ event.sub_categorey.translation.name }}
                    </span>
                </div>
            </div>

            <!-- Content -->
            <div class="max-w-7xl mx-auto px-5 sm:px-8 lg:px-12 -mt-16 relative z-10">
                <div class="bg-white rounded-2xl shadow-2xl overflow-hidden">
                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-0">

                        <!-- Main -->
                        <div class="lg:col-span-2 p-8 md:p-12 lg:p-16">

                            <!-- Title + Like -->
                            <div class="flex items-center justify-between mb-6">
                                <h1 class="text-3xl md:text-4xl lg:text-5xl font-bold text-gray-900 leading-tight">
                                    {{ event.translation.title }}
                                </h1>
                                <button @click="toggleLike" :disabled="likeLoading || isLiked"
                                    class="flex items-center gap-2 px-5 py-2.5 rounded-full transition-all duration-200"
                                    :class="{
                                        'bg-pink-50 text-pink-600 border border-pink-200 hover:bg-pink-100': isLiked,
                                        'bg-gray-100 text-gray-600 hover:bg-pink-50 hover:text-pink-600 border border-gray-300': !isLiked,
                                    }">
                                    <svg class="w-7 h-7 transition-transform" :class="{ 'scale-110': isLiked }"
                                        fill="currentColor" viewBox="0 0 24 24">
                                        <path fill-rule="evenodd" clip-rule="evenodd"
                                            d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z" />
                                    </svg>
                                    <span class="text-lg font-semibold">{{ likesCount }}</span>
                                    <span v-if="likeLoading" class="text-sm animate-pulse">...</span>
                                </button>
                            </div>

                            <!-- Like Error -->
                            <p v-if="likeError"
                                class="mb-4 text-red-600 bg-red-50 border border-red-200 px-4 py-2 rounded-lg text-sm">
                                {{ likeError }}
                            </p>

                            <p class="text-lg md:text-xl text-gray-700 mb-10 leading-relaxed">
                                {{ event.translation.description }}
                            </p>

                            <!-- ═══════════════════════════════════════════════════════════════ -->
                            <!-- Media Gallery — 3 per row grid                                  -->
                            <!-- ═══════════════════════════════════════════════════════════════ -->
                            <div class="mb-12">
                                <div class="flex items-center justify-between mb-6">
                                    <h2
                                        class="text-2xl md:text-3xl font-bold text-gray-900 border-b border-gray-200 pb-4">
                                        {{ $t('event.media_gallery_title') }}
                                    </h2>
                                    <button v-if="isAuthenticated" @click="showUploadModal = true"
                                        class="flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-5 py-2.5 rounded-lg font-medium transition shadow-sm">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M12 4v16m8-8H4" />
                                        </svg>
                                        {{ $t('upload_media') || 'رفع وسائط' }}
                                    </button>
                                </div>

                                <!-- ✅ Grid: 3 columns -->
                                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                                    <div v-for="(media, index) in event.images" :key="media.id || index"
                                        class="aspect-[4/3] overflow-hidden rounded-xl shadow-sm hover:shadow-md transition-shadow cursor-pointer relative group"
                                        @click="openLightbox(index)">

                                        <!-- Image -->
                                        <img v-if="!media.video && !isVideoUrl(media.preview_url) && media.preview_url"
                                            :src="getMediaUrl(media.preview_url)"
                                            class="w-full h-full object-cover transition-transform duration-300 group-hover:scale-105"
                                            loading="lazy" />

                                        <!-- Video thumbnail -->
                                        <video
                                            v-else-if="media.video || isVideoUrl(media.preview_url || media.full_url)"
                                            :src="getMediaUrl(media.video || media.preview_url || media.full_url)"
                                            class="w-full h-full object-cover" muted loop playsinline
                                            preload="metadata">
                                        </video>

                                        <!-- Fallback -->
                                        <div v-else
                                            class="w-full h-full bg-gray-200 flex items-center justify-center text-gray-500">
                                            {{ $t('event.no_media') }}
                                        </div>

                                        <!-- Video overlay icon -->
                                        <div v-if="media.video || isVideoUrl(media.preview_url || media.full_url)"
                                            class="absolute inset-0 bg-black/20 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity duration-200">
                                            <div
                                                class="w-12 h-12 rounded-full bg-white/80 flex items-center justify-center shadow-lg">
                                                <span class="text-gray-800 text-xl">▶</span>
                                            </div>
                                        </div>

                                        <!-- Hover overlay for images -->
                                        <div v-else
                                            class="absolute inset-0 bg-black/0 group-hover:bg-black/20 transition-colors duration-200 flex items-center justify-center">
                                            <svg class="w-10 h-10 text-white opacity-0 group-hover:opacity-100 transition-opacity duration-200 drop-shadow-lg"
                                                fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0zM10 7v3m0 0v3m0-3h3m-3 0H7" />
                                            </svg>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- About -->
                            <div class="mb-12">
                                <h2
                                    class="text-2xl md:text-3xl font-bold text-gray-900 mb-6 border-b border-gray-200 pb-4">
                                    {{ $t('event.about_event_title') }}
                                </h2>
                                <p class="text-gray-700 mb-8 text-lg leading-relaxed">
                                    {{ event.translation.des }}
                                </p>
                            </div>

                            <!-- Comments Section -->
                            <div class="comments-section mt-12">
                                <h2
                                    class="text-2xl md:text-3xl font-bold text-gray-900 mb-8 border-b border-gray-200 pb-4">
                                    {{ $t('event.comments_title') }}
                                    ({{ event.comments_count ?? event.comments?.length ?? 0 }})
                                </h2>

                                <RouterLink v-if="isAuthenticated"
                                    :to="{ name: 'all_comments', params: { slug: event.slug } }"
                                    class="inline-block mb-6 text-indigo-600 hover:text-indigo-800 font-medium">
                                    {{ $t('view_all_comments') }}
                                </RouterLink>

                                <!-- Comment List -->
                                <div v-for="comment in event.comments" :key="comment.id"
                                    class="comment-box mb-6 bg-gray-50 p-6 rounded-xl shadow-sm border border-gray-100 hover:shadow-md hover:border-indigo-100 transition-all duration-200">

                                    <p class="text-gray-700 text-sm leading-relaxed mb-3">
                                        {{ comment.translation?.comment || comment.comment }}
                                    </p>

                                    <div class="flex items-center justify-between mb-4 text-xs text-gray-500">
                                        <div class="flex items-center gap-2">
                                            <div
                                                class="w-8 h-8 rounded-full bg-indigo-100 flex items-center justify-center text-indigo-700 font-bold">
                                                {{ comment.user?.name?.charAt(0)?.toUpperCase() || '?' }}
                                            </div>
                                            <div>
                                                <p class="font-semibold text-gray-900 text-sm">
                                                    {{ comment.user?.name || $t('event.visitor') }}
                                                </p>
                                                <span class="text-xs text-gray-500">
                                                    {{ formatCommentDate(comment.created_at) }}
                                                </span>
                                            </div>
                                        </div>

                                        <div class="flex items-center gap-2">
                                            <!-- Reply Button -->
                                            <button v-if="isAuthenticated" @click="toggleReplyForm(comment.id)"
                                                class="flex items-center gap-1 text-xs text-indigo-600 hover:text-indigo-800 transition-colors px-2 py-1 rounded-lg hover:bg-indigo-50">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                                                    viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6" />
                                                </svg>
                                                {{ $t('event.reply') || 'رد' }}
                                            </button>

                                            <!-- Delete Button -->
                                            <button v-if="comment.user_id === currentUserId"
                                                @click="deleteComment(comment.id)"
                                                class="flex items-center gap-1 text-xs text-red-500 hover:text-red-700 transition-colors px-2 py-1 rounded-lg hover:bg-red-50"
                                                :title="$t('event.delete_comment_title')">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" fill="none"
                                                    viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                </svg>
                                                {{ $t('event.delete') || 'حذف' }}
                                            </button>

                                            <!-- Report Button -->
                                            <button @click="openReportModal(comment.id)"
                                                class="flex items-center gap-1 text-xs text-gray-400 hover:text-red-500 transition-colors px-2 py-1 rounded-lg hover:bg-red-50"
                                                title="الإبلاغ عن هذا التعليق">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" fill="none"
                                                    viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        d="M3 3h18l-2 9H5L3 3z" />
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        d="M5 12v7h14v-7" />
                                                </svg>
                                                إبلاغ
                                            </button>
                                        </div>
                                    </div>

                                    <!-- Delete Comment Error -->
                                    <p v-if="deleteCommentErrors[comment.id]"
                                        class="mb-2 text-red-600 bg-red-50 border border-red-200 px-3 py-1.5 rounded-lg text-xs">
                                        {{ deleteCommentErrors[comment.id] }}
                                    </p>

                                    <!-- Reactions -->
                                    <div class="flex items-center gap-2.5 flex-wrap mt-1" dir="rtl">
                                        <button @click="setReaction(comment.id, 'support')"
                                            :disabled="reactionLoading[comment.id]" :class="[
                                                'flex items-center gap-1.5 px-3.5 py-1.5 rounded-full text-xs font-medium border transition-all duration-150 min-w-[90px] justify-center',
                                                commentReactions[comment.id] === 'support'
                                                    ? 'bg-emerald-600 border-emerald-600 text-white shadow-sm'
                                                    : 'bg-white border-gray-300 text-gray-700 hover:bg-emerald-50 hover:border-emerald-400',
                                                reactionLoading[comment.id] ? 'opacity-60 cursor-not-allowed' : ''
                                            ]">
                                            <span class="text-base">👍</span>
                                            موافق
                                            <span
                                                class="text-[11px] font-semibold bg-white/30 px-1.5 py-0.5 rounded ml-1">
                                                {{ comment.support_count ?? 0 }}
                                            </span>
                                        </button>

                                        <button @click="setReaction(comment.id, 'neutral')"
                                            :disabled="reactionLoading[comment.id]" :class="[
                                                'flex items-center gap-1.5 px-3.5 py-1.5 rounded-full text-xs font-medium border transition-all duration-150 min-w-[90px] justify-center',
                                                commentReactions[comment.id] === 'neutral'
                                                    ? 'bg-amber-500 border-amber-500 text-white shadow-sm'
                                                    : 'bg-white border-gray-300 text-gray-700 hover:bg-amber-50 hover:border-amber-400',
                                                reactionLoading[comment.id] ? 'opacity-60 cursor-not-allowed' : ''
                                            ]">
                                            <span class="text-base">😐</span>
                                            محايد
                                            <span
                                                class="text-[11px] font-semibold bg-white/30 px-1.5 py-0.5 rounded ml-1">
                                                {{ comment.neutral_count ?? 0 }}
                                            </span>
                                        </button>

                                        <button @click="setReaction(comment.id, 'exhibitions')"
                                            :disabled="reactionLoading[comment.id]" :class="[
                                                'flex items-center gap-1.5 px-3.5 py-1.5 rounded-full text-xs font-medium border transition-all duration-150 min-w-[110px] justify-center',
                                                commentReactions[comment.id] === 'exhibitions'
                                                    ? 'bg-rose-600 border-rose-600 text-white shadow-sm'
                                                    : 'bg-white border-gray-300 text-gray-700 hover:bg-rose-50 hover:border-rose-400',
                                                reactionLoading[comment.id] ? 'opacity-60 cursor-not-allowed' : ''
                                            ]">
                                            <span class="text-base">👎</span>
                                            غير موافق
                                            <span
                                                class="text-[11px] font-semibold bg-white/30 px-1.5 py-0.5 rounded ml-1">
                                                {{ comment.exhibitions_count ?? 0 }}
                                            </span>
                                        </button>
                                    </div>

                                    <!-- Reaction Error per comment -->
                                    <p v-if="reactionErrors[comment.id]"
                                        class="mt-2 text-red-600 bg-red-50 border border-red-200 px-3 py-1.5 rounded-lg text-xs">
                                        {{ reactionErrors[comment.id] }}
                                    </p>

                                    <!-- Reply Form -->
                                    <div v-if="isAuthenticated && replyingTo === comment.id" class="mt-6 pl-12">
                                        <form @submit.prevent="addReply(comment.id)" class="space-y-4">
                                            <textarea v-model="replyTexts[comment.id]" rows="2"
                                                class="w-full border border-gray-300 rounded-lg p-3 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                                                :placeholder="$t('event.reply_placeholder') || 'اكتب ردك هنا...'"
                                                required></textarea>
                                            <p v-if="replyErrors[comment.id]"
                                                class="text-red-600 bg-red-50 border border-red-200 px-3 py-2 rounded-lg text-sm">
                                                {{ replyErrors[comment.id] }}
                                            </p>
                                            <div class="flex justify-end gap-3">
                                                <button type="button" @click="cancelReply(comment.id)"
                                                    class="px-4 py-2 text-gray-600 hover:bg-gray-100 rounded-lg">
                                                    {{ $t('cancel') || 'إلغاء' }}
                                                </button>
                                                <button type="submit"
                                                    :disabled="replyLoading[comment.id] || !replyTexts[comment.id]?.trim()"
                                                    class="px-5 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2">
                                                    <span v-if="replyLoading[comment.id]">{{ $t('sending') || '' }}</span>
                                                    <span v-else>{{ $t('send_reply') || 'إرسال الرد' }}</span>
                                                </button>
                                            </div>
                                        </form>
                                    </div>

                                    <!-- Replies -->
                                    <div v-if="comment.replies?.length > 0"
                                        class="mt-6 pl-12 border-l-2 border-indigo-200 space-y-6">
                                        <div v-for="reply in comment.replies" :key="reply.id"
                                            class="bg-white p-4 rounded-lg shadow-sm">
                                            <div class="flex items-start gap-3">
                                                <div
                                                    class="w-8 h-8 rounded-full bg-gray-200 flex items-center justify-center text-gray-700 font-medium text-sm">
                                                    {{ reply.user?.name?.charAt(0)?.toUpperCase() || '?' }}
                                                </div>
                                                <div class="flex-1">
                                                    <div class="flex items-center gap-2 mb-1">
                                                        <p class="font-medium text-gray-900">{{ reply.user?.name ||
                                                            $t('event.visitor') }}</p>
                                                        <span class="text-xs text-gray-500">{{
                                                            formatCommentDate(reply.created_at) }}</span>
                                                    </div>
                                                    <p class="text-gray-700">{{ reply.comment }}</p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Add Comment Form -->
                                <div v-if="isAuthenticated"
                                    class="comment-form mt-12 bg-white p-8 rounded-xl shadow-md border border-gray-100">
                                    <h3 class="text-xl font-bold text-gray-900 mb-6">
                                        {{ $t('event.add_comment_title') || 'أضف تعليقًا' }}
                                    </h3>
                                    <form @submit.prevent="addComment">
                                        <textarea v-model="newComment" rows="4"
                                            class="w-full border border-gray-300 rounded-lg p-4 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                                            :placeholder="$t('event.comment_placeholder') || 'اكتب تعليقك هنا...'"
                                            :disabled="commentLoading" required></textarea>
                                        <div class="mt-4 flex justify-end">
                                            <button type="submit" :disabled="commentLoading || !newComment.trim()"
                                                class="px-6 py-3 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 disabled:opacity-50 flex items-center gap-2">
                                                <span v-if="commentLoading">{{ $t('event.sending_comment') || '' }}</span>
                                                <span v-else>{{ $t('event.submit_comment') || 'إرسال التعليق' }}</span>
                                            </button>
                                        </div>
                                        <p v-if="commentError"
                                            class="mt-3 text-red-600 bg-red-50 border border-red-200 px-4 py-2 rounded-lg text-sm">
                                            {{ commentError }}
                                        </p>
                                        <p v-if="commentSuccess"
                                            class="mt-3 text-green-600 bg-green-50 border border-green-200 px-4 py-2 rounded-lg text-sm">
                                            {{ $t('event.comment_added_success') || 'تم إضافة التعليق بنجاح' }}
                                        </p>
                                    </form>
                                </div>

                                <!-- Login prompt -->
                                <div v-else class="text-center py-12 bg-gray-50 rounded-xl">
                                    <p class="text-lg text-gray-700 mb-4">
                                        {{ $t('login_to_comment') || 'يجب عليك تسجيل الدخول لإضافة تعليق أو الرد' }}
                                    </p>
                                    <RouterLink to="/auth"
                                        class="inline-block px-6 py-3 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700">
                                        {{ $t('login') || 'تسجيل الدخول' }}
                                    </RouterLink>
                                </div>
                            </div>
                        </div>

                        <!-- Sidebar -->
                        <div
                            class="bg-gray-50/70 p-8 md:p-12 lg:p-16 border-t lg:border-t-0 lg:border-l border-gray-200 lg:sticky lg:top-0 lg:h-screen lg:overflow-y-auto">
                            <div class="space-y-10">
                                <div>
                                    <h3 class="text-2xl font-bold text-gray-900 mb-6">
                                        {{ $t('event.event_info_title') }}
                                    </h3>
                                    <div class="space-y-3 text-gray-800">
                                        <div class="flex items-center gap-2">
                                            <span class="text-3xl">📅</span>
                                            <div>
                                                <p class="font-semibold text-base">{{ $t('event.from_date') }}</p>
                                                <p class="text-lg">{{ formatDate(event.start_date) }}</p>
                                            </div>
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <span class="text-3xl">📅</span>
                                            <div>
                                                <p class="font-semibold text-base">{{ $t('event.to_date') }}</p>
                                                <p class="text-lg">{{ formatDate(event.end_date) }}</p>
                                            </div>
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <span class="text-3xl">🕒</span>
                                            <div>
                                                <p class="font-semibold text-base">{{ $t('event.time_label') }}</p>
                                                <p class="text-lg">{{ event.time || $t('event.time_default') }}</p>
                                            </div>
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <span class="text-3xl">📍</span>
                                            <div>
                                                <p class="font-semibold text-base">{{ $t('event.location') }}</p>
                                                <p class="text-lg">{{ event.city?.translation.name ||
                                                    $t('event.city_default') }}</p>
                                            </div>
                                        </div>
                                        <div v-if="event.user?.name" class="flex items-center gap-2">
                                            <span class="text-3xl">👤</span>
                                            <div>
                                                <p class="font-semibold text-base">{{ $t('event.organizer') }}</p>
                                                <p class="text-lg">{{ event.user.name }}</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="pt-8 border-t border-gray-200 space-y-4">
                                    <button @click="addToWishlist" :disabled="wishlistLoading || isInWishlist"
                                        class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold text-lg py-4 px-8 rounded transition shadow-lg flex items-center justify-center gap-2"
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
                                        class="text-red-600 text-center text-sm mt-2 bg-red-50 border border-red-200 p-3 rounded-lg">
                                        {{ wishlistError }}
                                    </p>
                                    <p v-if="wishlistSuccess"
                                        class="text-green-600 text-center text-sm mt-2 bg-green-50 border border-green-200 p-3 rounded-lg">
                                        {{ $t('event.wishlist_success') }}
                                    </p>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>
            </div>

            <!-- ═══════════════════════════════════════════════════════════════ -->
            <!-- Lightbox — يعرض preview_url أو full_url بشكل صح                -->
            <!-- ═══════════════════════════════════════════════════════════════ -->
            <div v-if="lightboxOpen" class="fixed inset-0 bg-black/95 z-50 flex items-center justify-center"
                @click="lightboxOpen = false">

                <!-- Navigation Prev -->
                <button v-if="event.images.length > 1" @click.stop="lightboxPrev"
                    class="absolute left-4 top-1/2 -translate-y-1/2 z-10 w-12 h-12 rounded-full bg-white/10 hover:bg-white/25 flex items-center justify-center text-white text-2xl transition">
                    ‹
                </button>

                <!-- Media -->
                <div class="relative max-w-[92vw] max-h-[92vh] flex items-center justify-center" @click.stop>

                    <!-- ✅ Image: use preview_url first, fallback to full_url -->
                    <img v-if="currentMedia && !isVideoUrl(currentMedia.full_url) && !isVideoUrl(currentMedia.preview_url) && !currentMedia.video"
                        :src="getMediaUrl(currentMedia.preview_url || currentMedia.full_url)"
                        class="max-w-full max-h-[88vh] object-contain rounded-lg shadow-2xl" />

                    <!-- ✅ Video: use full_url first (higher quality), fallback to preview_url -->
                    <video v-else-if="currentMedia"
                        :src="getMediaUrl(currentMedia.full_url || currentMedia.video || currentMedia.preview_url)"
                        class="max-w-full max-h-[88vh] rounded-lg shadow-2xl" controls autoplay @click.stop>
                    </video>

                    <!-- Counter -->
                    <div
                        class="absolute bottom-3 left-1/2 -translate-x-1/2 bg-black/60 text-white text-sm px-3 py-1 rounded-full">
                        {{ lightboxIndex + 1 }} / {{ event.images.length }}
                    </div>
                </div>

                <!-- Navigation Next -->
                <button v-if="event.images.length > 1" @click.stop="lightboxNext"
                    class="absolute right-4 top-1/2 -translate-y-1/2 z-10 w-12 h-12 rounded-full bg-white/10 hover:bg-white/25 flex items-center justify-center text-white text-2xl transition">
                    ›
                </button>

                <!-- Close -->
                <button
                    class="absolute top-4 right-4 text-white text-4xl font-bold drop-shadow-lg w-10 h-10 flex items-center justify-center hover:text-gray-300 transition"
                    @click="lightboxOpen = false">
                    ×
                </button>
            </div>

            <!-- Upload Modal -->
            <div v-if="showUploadModal" class="fixed inset-0 bg-black/70 z-50 flex items-center justify-center p-4"
                @click.self="showUploadModal = false">
                <div class="bg-white rounded-2xl shadow-2xl max-w-lg w-full max-h-[90vh] overflow-y-auto">
                    <div class="p-6 border-b">
                        <h3 class="text-2xl font-bold text-gray-900">
                            {{ $t('event.upload_media_to_event') || 'رفع صور / فيديو للحدث' }}
                        </h3>
                        <p class="text-gray-600 mt-1 text-sm">الحد الأقصى لكل ملف: 100 ميجابايت</p>
                    </div>

                    <div class="p-6">
                        <div @dragover.prevent @drop.prevent="handleDrop"
                            class="border-2 border-dashed border-gray-300 rounded-xl p-8 text-center hover:border-indigo-500 transition-colors">
                            <input type="file" multiple accept="image/*,video/mp4,video/webm,video/quicktime"
                                class="hidden" id="mediaUploadInput" @change="handleFileChange" />
                            <label for="mediaUploadInput" class="cursor-pointer">
                                <div class="text-indigo-600 text-5xl mb-4">↑</div>
                                <p class="text-lg font-medium text-gray-700">
                                    اسحب الملفات هنا أو
                                    <span class="text-indigo-600 hover:underline">اضغط للاختيار</span>
                                </p>
                                <p class="text-sm text-gray-500 mt-2">صور وفيديوهات (max 100MB لكل ملف)</p>
                            </label>
                        </div>

                        <div v-if="selectedFiles.length > 0" class="mt-6">
                            <h4 class="font-semibold mb-3">الملفات المختارة ({{ selectedFiles.length }})</h4>
                            <ul class="space-y-2 max-h-48 overflow-y-auto pr-2">
                                <li v-for="(file, idx) in selectedFiles" :key="idx"
                                    class="flex items-center justify-between bg-gray-50 p-3 rounded-lg">
                                    <div class="flex items-center gap-3 truncate">
                                        <span class="text-gray-600">{{ file.name }}</span>
                                        <span class="text-xs text-gray-500">({{ formatFileSize(file.size) }})</span>
                                    </div>
                                    <button @click="removeFile(idx)"
                                        class="text-red-600 hover:text-red-800 text-sm">حذف</button>
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
                            class="px-6 py-2.5 border border-gray-300 rounded-lg hover:bg-gray-50">إلغاء</button>
                        <button @click="uploadFiles" :disabled="uploading || selectedFiles.length === 0"
                            class="px-6 py-2.5 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2">
                            <span v-if="uploading" class="animate-pulse">جاري الرفع...</span>
                            <template v-else>رفع الملفات</template>
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
                        <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center text-3xl">✅
                        </div>
                        <h4 class="text-lg font-bold text-gray-800">تم إرسال البلاغ</h4>
                        <p class="text-sm text-gray-500">شكراً لك، سنراجع هذا التعليق قريباً.</p>
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
                                <h3 class="text-base font-bold text-gray-800">الإبلاغ عن تعليق</h3>
                            </div>
                            <button @click="closeReportModal"
                                class="w-7 h-7 flex items-center justify-center rounded-full text-gray-400 hover:bg-gray-100 hover:text-gray-600 transition">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24"
                                    stroke="currentColor" stroke-width="2.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>

                        <p class="text-sm text-gray-500 mb-4">اختر سبب الإبلاغ عن هذا التعليق:</p>

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
                            ⚠️ {{ reportError }}
                        </p>

                        <div class="flex gap-2 justify-end">
                            <button @click="closeReportModal"
                                class="px-4 py-2 rounded-xl border border-gray-200 text-sm text-gray-600 hover:bg-gray-50 transition">
                                إلغاء
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
                                    جاري الإرسال...
                                </span>
                                <span v-else>إرسال البلاغ</span>
                            </button>
                        </div>
                    </template>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<script setup>
import { ref, computed, onMounted } from "vue";
import { useRoute } from "vue-router";
import axios from "axios";
import { EventService } from "@/services/singleEventService";
import CommentService, { extractErrorMessage } from "../../services/CommentService";

const route = useRoute();
const slug = route.params.slug;

// ─── State ────────────────────────────────────────────────────────────────────
const event = ref(null);
const loading = ref(true);
const lightboxOpen = ref(false);
const lightboxIndex = ref(0);
const newComment = ref("");
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

// ─── Reactions ────────────────────────────────────────────────────────────────
const commentReactions = ref({});
const reactionLoading = ref({});
const reactionErrors = ref({});

const reactionEndpointMap = {
    support: "support",
    exhibitions: "Exhibitions",
    neutral: "neutral",
};

const STORAGE_URL = 'http://localhost:8000/storage/';

const getMediaUrl = (path) => {
    if (!path) return '';
    if (path.startsWith('http')) return path;
    return STORAGE_URL + path;
};

const setReaction = async (commentId, type) => {
    if (reactionLoading.value[commentId]) return;
    const comment = event.value?.comments?.find((c) => c.id === commentId);
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

// ─── Report ───────────────────────────────────────────────────────────────────
const reportModal = ref(false);
const reportCommentId = ref(null);
const reportReason = ref("");
const reportLoading = ref(false);
const reportSuccess = ref(false);
const reportError = ref("");

const reportReasons = [
    { value: "spam", label: "بريد مزعج", icon: "🚫" },
    { value: "offensive", label: "محتوى مسيء", icon: "😡" },
    { value: "inappropriate", label: "غير لائق", icon: "⚠️" },
    { value: "illegal", label: "محتوى غير قانوني", icon: "⚖️" },
    { value: "untrue", label: "محتوى كاذب", icon: "🤥" },
    { value: "False information", label: "معلومات مضللة", icon: "📛" },
    { value: "other", label: "سبب آخر", icon: "💬" },
];

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
        reportError.value = "يرجى اختيار سبب البلاغ";
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
    replyingTo.value = replyingTo.value === commentId ? null : commentId;
    if (!replyTexts.value[commentId]) replyTexts.value[commentId] = "";
    replyErrors.value[commentId] = "";
};

const cancelReply = (commentId) => {
    replyingTo.value = null;
    replyTexts.value[commentId] = "";
    replyErrors.value[commentId] = "";
};

const addReply = async (commentId) => {
    const replyText = replyTexts.value[commentId]?.trim();
    if (!replyText) return;

    replyLoading.value[commentId] = true;
    replyErrors.value[commentId] = "";

    try {
        const token = localStorage.getItem("auth_token");
        const response = await axios.post(
            `/v1/replies/reply/${commentId}`,
            { comment: replyText },
            {
                headers: {
                    Authorization: `Bearer ${token}`,
                    Accept: "application/json",
                },
            }
        );

        if (response.data.status === "success") {
            const newReply = {
                id: response.data.data?.id || Date.now(),
                comment: replyText,
                comment_id: commentId,
                user_id: currentUserId.value,
                created_at: new Date().toISOString(),
                user: { name: "أنت" },
            };

            const comment = event.value.comments.find((c) => c.id === commentId);
            if (comment) {
                if (!comment.replies) comment.replies = [];
                comment.replies.push(newReply);
            }

            replyTexts.value[commentId] = "";
            replyingTo.value = null;
            if (event.value.comments_count !== undefined) event.value.comments_count++;
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
        const res = await axios.get("/v1/users/profile", {
            headers: { Authorization: `Bearer ${token}` },
        });
        currentUserId.value = res.data?.data?.user?.id || res.data?.id;
    } catch (err) {
        console.log("فشل جلب بيانات المستخدم", err);
    }
};

// ─── Likes ────────────────────────────────────────────────────────────────────
const fetchLikesInfo = async () => {
    if (!event.value?.id) return;
    try {
        const token = localStorage.getItem("auth_token") || "";
        const res = await axios.get(`/v1/likes/${event.value.id}`, {
            headers: { Authorization: `Bearer ${token}` },
        });
        if (res.data.status === "success" && res.data.data) {
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
        likeError.value = "يرجى تسجيل الدخول أولاً";
        setTimeout(() => (likeError.value = ""), 4000);
        return;
    }

    likeLoading.value = true;
    likeError.value = "";

    try {
        const res = await axios.post(
            `/v1/likes/${event.value.id}/create`,
            {},
            {
                headers: {
                    Authorization: `Bearer ${token}`,
                    Accept: "application/json",
                },
            }
        );
        if (res.data.status === "success") {
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
        wishlistError.value = "يرجى تسجيل الدخول أولاً";
        setTimeout(() => (wishlistError.value = ""), 4000);
        return;
    }

    wishlistLoading.value = true;
    wishlistError.value = "";
    wishlistSuccess.value = false;

    try {
        const res = await axios.post(
            `/v1/Wishlist/${event.value.id}`,
            {},
            {
                headers: {
                    Authorization: `Bearer ${token}`,
                    Accept: "application/json",
                },
            }
        );
        if (res.data.status === "success") {
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
const addComment = async () => {
    if (!newComment.value.trim()) return;

    commentLoading.value = true;
    commentError.value = "";
    commentSuccess.value = false;

    try {
        const response = await axios.post(
            `/v1/comments/${event.value.id}/create`,
            { comment: newComment.value.trim() },
            {
                headers: {
                    Authorization: `Bearer ${localStorage.getItem("auth_token") || ""}`,
                    Accept: "application/json",
                },
            }
        );

        if (response.data.status === "success") {
            const newCommentData = response.data.data || {
                id: Date.now(),
                event_id: event.value.id,
                user_id: currentUserId.value,
                comment: newComment.value.trim(),
                created_at: new Date().toISOString(),
                user: { name: "أنت" },
            };

            event.value.comments.unshift(newCommentData);
            newComment.value = "";
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
    if (!confirm("هل أنت متأكد من حذف التعليق؟")) return;

    deleteCommentErrors.value[commentId] = "";

    try {
        const token = localStorage.getItem("auth_token");
        await axios.delete(`/v1/comments/${commentId}/delete`, {
            headers: {
                Authorization: `Bearer ${token}`,
                Accept: "application/json",
            },
        });
        event.value.comments = event.value.comments.filter((c) => c.id !== commentId);
        if (event.value.comments_count !== undefined) event.value.comments_count--;
    } catch (err) {
        deleteCommentErrors.value[commentId] = extractErrorMessage(err);
        setTimeout(() => (deleteCommentErrors.value[commentId] = ""), 5000);
    }
};

// ─── Media Upload ─────────────────────────────────────────────────────────────
const handleFileChange = (e) => addValidFiles(Array.from(e.target.files));
const handleDrop = (e) => addValidFiles(Array.from(e.dataTransfer.files));

const addValidFiles = (files) => {
    const MAX = 100 * 1024 * 1024;
    files.forEach((file) => {
        if (file.size > MAX) {
            uploadError.value = `الملف "${file.name}" أكبر من 100 ميجا`;
            return;
        }
        if (!file.type.startsWith("image/") && !file.type.startsWith("video/")) {
            uploadError.value = `نوع الملف "${file.name}" غير مدعوم`;
            return;
        }
        selectedFiles.value.push(file);
    });
};

const removeFile = (i) => selectedFiles.value.splice(i, 1);

const formatFileSize = (bytes) => {
    if (bytes < 1024) return bytes + " B";
    if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + " KB";
    return (bytes / (1024 * 1024)).toFixed(1) + " MB";
};

const uploadFiles = async () => {
    if (!event.value?.id || !selectedFiles.value.length) return;

    uploading.value = true;
    uploadError.value = "";
    uploadSuccess.value = "";

    const token = localStorage.getItem("auth_token");
    const formData = new FormData();
    selectedFiles.value.forEach((f) => formData.append("url[]", f));

    try {
        const res = await axios.post(
            `/v1/media-request/upload/${event.value.id}`,
            formData,
            {
                headers: {
                    Authorization: `Bearer ${token}`,
                    "Content-Type": "multipart/form-data",
                },
            }
        );

        if (res.data.status === "success") {
            const media = res.data.data?.media;
            if (Array.isArray(media)) media.forEach((m) => event.value.images.push(m));
            else if (media) event.value.images.push(media);

            uploadSuccess.value = "تم رفع الملفات بنجاح!";
            selectedFiles.value = [];
            setTimeout(() => {
                showUploadModal.value = false;
                uploadSuccess.value = "";
            }, 2200);
        }
    } catch (err) {
        uploadError.value = extractErrorMessage(err);
    } finally {
        uploading.value = false;
    }
};

// ─── Computed ─────────────────────────────────────────────────────────────────
const hasMedia = computed(() => event.value?.images?.length > 0);
const heroMedia = computed(() => event.value?.images?.[0] || null);
const heroMediaComponent = computed(() =>
    heroMedia.value?.video || isVideoUrl(heroMedia.value?.url) ? "video" : "img"
);
const currentMedia = computed(() => event.value?.images?.[lightboxIndex.value] || null);

const isVideoUrl = (url) => {
    if (!url) return false;
    return [".mp4", ".webm", ".ogg", ".mov"].some((ext) => url.toLowerCase().endsWith(ext));
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

// ✅ Navigation inside lightbox
const lightboxPrev = () => {
    const total = event.value?.images?.length ?? 0;
    lightboxIndex.value = (lightboxIndex.value - 1 + total) % total;
};

const lightboxNext = () => {
    const total = event.value?.images?.length ?? 0;
    lightboxIndex.value = (lightboxIndex.value + 1) % total;
};

// ─── Lifecycle ────────────────────────────────────────────────────────────────
onMounted(async () => {
    await fetchCurrentUser();
    checkAuth();
    if (!slug) { loading.value = false; return; }

    loading.value = true;
    try {
        const response = await EventService.getSingleEvent(slug);
        event.value = response.data?.data || response;
        console.log(event.value);
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
</style>
