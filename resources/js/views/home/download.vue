<template>
  <div class="scemory-page downloads-page">

    <!-- Header -->
    <div class="page-header">
      <div class="header-left">
        <h1 class="page-title">{{ $t('downloads.title') }}</h1>
        <transition name="badge-pop">
          <span v-if="downloads.length" class="count-badge">
            {{ $t('downloads.fileCount', { count: downloads.length }) }}
          </span>
        </transition>
      </div>
    </div>

    <!-- Loading Skeleton -->
    <transition name="fade">
      <div v-if="loading" class="media-grid">
        <div v-for="n in 8" :key="n" class="media-card skeleton-card">
          <div class="skeleton-thumb"></div>
          <div class="card-body">
            <div class="skeleton-line short"></div>
            <div class="skeleton-line full"></div>
          </div>
        </div>
      </div>
    </transition>

    <!-- Error -->
    <transition name="fade-up">
      <div v-if="error && !loading" class="state-container">
        <div class="state-icon error-icon">
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none">
            <path d="M12 9v4m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"
              stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
          </svg>
        </div>
        <p class="state-text">{{ $t('downloads.loadError') }}</p>
        <button class="retry-btn" @click="fetchDownloads">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none">
            <path d="M1 4v6h6M23 20v-6h-6" stroke="currentColor" stroke-width="2.5"
              stroke-linecap="round" stroke-linejoin="round"/>
            <path d="M20.49 9A9 9 0 005.64 5.64L1 10m22 4l-4.64 4.36A9 9 0 013.51 15"
              stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
          </svg>
          {{ $t('common.tryAgain') }}
        </button>
      </div>
    </transition>

    <!-- Grid -->
    <transition name="fade">
      <div v-if="!loading && downloads.length" class="media-grid">
        <transition-group name="card-stagger" appear>
          <div
            v-for="(item, index) in downloads"
            :key="item.id"
            class="media-card"
            :style="{ '--i': index }"
            :class="{
              'is-downloading': downloadingId === item.id,
              'is-done': doneIds.has(item.id)
            }"
          >
            <!-- Thumbnail -->
            <div class="media-thumb">
              <img
                v-if="isImage(item.preview_url)"
                :src="getUrl(item.preview_url)"
                :alt="$t('downloads.fileAlt', { id: item.id })"
                class="thumb-img"
                loading="lazy"
              />
              <video
                v-else
                class="thumb-img"
                playsinline
                preload="metadata"
              >
                <source :src="getUrl(item.preview_url)" />
              </video>

              <!-- Type badge -->
              <span class="type-badge">
                <svg v-if="isImage(item.preview_url)" width="10" height="10" viewBox="0 0 24 24" fill="currentColor">
                  <path d="M21 19V5a2 2 0 00-2-2H5a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2zM8.5 13.5l2.5 3 3.5-4.5 4.5 6H5l3.5-4.5z"/>
                </svg>
                <svg v-else width="10" height="10" viewBox="0 0 24 24" fill="currentColor">
                  <path d="M15 10l4.553-2.277A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14v-4zM3 8a2 2 0 012-2h8a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2V8z"/>
                </svg>
                {{ isImage(item.preview_url) ? $t('downloads.imageType') : $t('downloads.videoType') }}
              </span>

              <!-- Done checkmark overlay -->
              <transition name="check-pop">
                <div v-if="doneIds.has(item.id)" class="done-overlay">
                  <div class="check-circle">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none">
                      <path d="M20 6L9 17l-5-5" stroke="white" stroke-width="2.5"
                        stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                  </div>
                </div>
              </transition>

              <!-- Hover overlay -->
              <div class="thumb-overlay">
                <button class="preview-btn" @click="openFile(item.preview_url)">
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none">
                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"
                      stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                    <circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="2"/>
                  </svg>
                  {{ $t('downloads.preview') }}
                </button>
              </div>
            </div>

            <!-- Card Body -->
            <div class="card-body">
              <div class="card-meta">
                <span class="meta-dim">{{ item.width }} × {{ item.height }}</span>
                <span class="meta-price">${{ item.price }}</span>
              </div>

              <!-- Progress bar -->
              <div class="progress-wrap" :class="{ visible: downloadingId === item.id }">
                <div class="progress-bar"></div>
              </div>

              <!-- {{ $t('downloads.download') }} Button -->
              <button
                class="download-btn"
                :class="{
                  'is-loading': downloadingId === item.id,
                  'is-done': doneIds.has(item.id)
                }"
                :disabled="downloadingId === item.id || doneIds.has(item.id)"
                @click="downloadFile(item)"
              >
                <transition name="icon-swap" mode="out-in">
                  <span v-if="downloadingId === item.id" key="spin" class="btn-spinner"></span>
                  <svg v-else-if="doneIds.has(item.id)" key="check" width="15" height="15"
                    viewBox="0 0 24 24" fill="none" class="btn-svg">
                    <path d="M20 6L9 17l-5-5" stroke="currentColor" stroke-width="2.5"
                      stroke-linecap="round" stroke-linejoin="round"/>
                  </svg>
                  <svg v-else key="dl" width="15" height="15" viewBox="0 0 24 24"
                    fill="none" class="btn-svg">
                    <path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4M7 10l5 5 5-5M12 15V3"
                      stroke="currentColor" stroke-width="2.2"
                      stroke-linecap="round" stroke-linejoin="round"/>
                  </svg>
                </transition>

                <span class="btn-label">
                  <transition name="text-swap" mode="out-in">
                    <span v-if="downloadingId === item.id" key="loading">{{ $t('downloads.downloading') }}</span>
                    <span v-else-if="doneIds.has(item.id)" key="done">{{ $t('downloads.downloaded') }}</span>
                    <span v-else key="idle">{{ $t('downloads.download') }}</span>
                  </transition>
                </span>
              </button>

            </div>
          </div>
        </transition-group>
      </div>
    </transition>

    <!-- Empty
    <transition name="fade-up">
      <div v-else-if="!loading && !error" class="state-container">
        <div class="empty-illustration">
          <svg width="80" height="80" viewBox="0 0 80 80" fill="none">
            <circle cx="40" cy="40" r="36" fill="#f1f5f9"/>
            <path d="M52 44v8a2 2 0 01-2 2H30a2 2 0 01-2-2v-8" stroke="#94a3b8"
              stroke-width="2.2" stroke-linecap="round"/>
            <path d="M40 26v18m0 0l-6-6m6 6l6-6" stroke="#94a3b8"
              stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/>
          </svg>
        </div>
        <p class="state-text">{{ $t('downloads.empty') }}</p>
        <p class="state-sub">{{ $t('downloads.emptyHint') }}</p>
      </div>
    </transition> -->

    <!-- Toast -->
    <transition name="toast-slide">
      <div v-if="toast.show" class="toast" :class="toast.type">
        <svg v-if="toast.type === 'success'" width="16" height="16" viewBox="0 0 24 24" fill="none">
          <path d="M22 11.08V12a10 10 0 11-5.93-9.14" stroke="currentColor" stroke-width="2.2"
            stroke-linecap="round"/>
          <path d="M22 4L12 14.01l-3-3" stroke="currentColor" stroke-width="2.2"
            stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
        <svg v-else width="16" height="16" viewBox="0 0 24 24" fill="none">
          <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2.2"/>
          <path d="M12 8v4m0 4h.01" stroke="currentColor" stroke-width="2.2"
            stroke-linecap="round"/>
        </svg>
        {{ toast.message }}
      </div>
    </transition>

  </div>
</template>

<script setup>
import { ref, onMounted, reactive } from "vue";
import { useI18n } from "vue-i18n";
import downloadService from "@/services/downloadService/downloadService";

const { t } = useI18n();
const downloads     = ref([]);
const loading       = ref(true);
const error         = ref(false);
const downloadingId = ref(null);
const doneIds       = ref(new Set());

const toast = reactive({ show: false, message: "", type: "success" });

let toastTimer = null;

/* ── Toast ── */
const showToast = (message, type = "success") => {
  clearTimeout(toastTimer);
  toast.message = message;
  toast.type    = type;
  toast.show    = true;
  toastTimer    = setTimeout(() => (toast.show = false), 3000);
};

/* ── URL ── */
const getUrl = (path) => {
  if (!path) return "";
  if (path.startsWith("http://") || path.startsWith("https://") || path.startsWith("/")) return path;
  return `/storage/${path}`;
};

/* ── {{ $t('downloads.download') }} URL — goes through Laravel to force download ── */

/* ── Type check ── */
const isImage = (path) => {
  if (!path) return false;
  return /\.(jpe?g|png|webp|gif|avif|svg)(\?|$)/i.test(path);
};

/* ── Filename ── */
const getFilename = (path) => {
  if (!path) return "download";
  return path.split("/").pop().split("?")[0] || "download";
};

/* ── Fetch ── */
const fetchDownloads = async () => {
  try {
    loading.value = true;
    error.value   = false;
    const res     = await downloadService.getDownloads();
    downloads.value = res.data || [];
  } catch (err) {
    error.value = true;
    console.error(err);
  } finally {
    loading.value = false;
  }
};

/* ── {{ $t('downloads.preview') }} ── */
const openFile = (url) => {
  window.open(getUrl(url), "_blank");
};

/* ── {{ $t('downloads.download') }} ── */
const downloadFile = async (item) => {
  if (downloadingId.value === item.id || doneIds.value.has(item.id)) return;

  downloadingId.value = item.id;
  const filename = `scemory-media-${item.id}`;

  try {
    // Option 1: via Laravel endpoint (recommended — avoids CORS)
    const response = await downloadService.downloadFile(item.id);
    const blob      = response.data;
    const objectUrl = URL.createObjectURL(blob);
    const anchor    = document.createElement("a");
    anchor.href     = objectUrl;
    anchor.download = filename;
    document.body.appendChild(anchor);
    anchor.click();
    document.body.removeChild(anchor);
    URL.revokeObjectURL(objectUrl);

    doneIds.value = new Set([...doneIds.value, item.id]);
    showToast(t("downloads.downloadSuccess", { filename }), "success");

  } catch (err) {
    console.error('Download failed:', err);
    showToast(t('downloads.downloadFailed'), 'error');
  } finally {
    downloadingId.value = null;
  }
};

onMounted(fetchDownloads);
</script>

<style scoped>
/* ── Page ── */
.downloads-page {
  padding: 2rem 1.5rem;
  max-width: 1100px;
  margin: 0 auto;
  font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
  position: relative;
}

.page-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 2rem;
}

.header-left {
  display: flex;
  align-items: center;
  gap: 12px;
}

.page-title {
  font-size: 1.6rem;
  font-weight: 700;
  color: #0f172a;
  margin: 0;
}

.count-badge {
  background: #ede9fe;
  color: #6366f1;
  font-size: 0.75rem;
  font-weight: 700;
  padding: 4px 12px;
  border-radius: 999px;
}

/* ── States ── */
.state-container {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 5rem 1rem;
  gap: 12px;
}

.state-icon {
  width: 60px;
  height: 60px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
}

.error-icon { background: #fef2f2; color: #ef4444; }

.state-text { font-size: 1rem; color: #475569; margin: 0; font-weight: 500; }
.state-sub  { font-size: 0.85rem; color: #94a3b8; margin: 0; }

.empty-illustration { margin-bottom: 4px; }

.retry-btn {
  display: flex;
  align-items: center;
  gap: 7px;
  margin-top: 8px;
  padding: 9px 22px;
  background: #ef4444;
  color: white;
  border: none;
  border-radius: 10px;
  font-size: 0.85rem;
  font-weight: 600;
  cursor: pointer;
  transition: background 0.2s, transform 0.15s;
}
.retry-btn:hover { background: #dc2626; transform: scale(1.03); }

/* ── Spinner ── */
.spinner {
  width: 36px;
  height: 36px;
  border: 3px solid #e2e8f0;
  border-top-color: #6366f1;
  border-radius: 50%;
  animation: spin 0.7s linear infinite;
}
@keyframes spin { to { transform: rotate(360deg); } }

/* ── Grid ── */
.media-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
  gap: 1.25rem;
}

/* ── Skeleton ── */
.skeleton-card { pointer-events: none; }

.skeleton-thumb {
  height: 170px;
  background: linear-gradient(90deg, #f1f5f9 25%, #e2e8f0 50%, #f1f5f9 75%);
  background-size: 200% 100%;
  animation: shimmer 1.4s infinite;
}

.skeleton-line {
  height: 12px;
  border-radius: 6px;
  margin-bottom: 10px;
  background: linear-gradient(90deg, #f1f5f9 25%, #e2e8f0 50%, #f1f5f9 75%);
  background-size: 200% 100%;
  animation: shimmer 1.4s infinite;
}
.skeleton-line.short  { width: 50%; }
.skeleton-line.full   { width: 100%; height: 32px; border-radius: 8px; }

@keyframes shimmer {
  0%   { background-position:  200% 0; }
  100% { background-position: -200% 0; }
}

/* ── Card ── */
.media-card {
  background: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 16px;
  overflow: hidden;
  transition: transform 0.25s cubic-bezier(.34,1.56,.64,1), box-shadow 0.25s;
}
.media-card:hover {
  transform: translateY(-4px) scale(1.01);
  box-shadow: 0 16px 40px rgba(99,102,241,0.1);
}
.media-card.is-downloading { opacity: 0.8; }
.media-card.is-done { border-color: #a7f3d0; }

/* ── Thumbnail ── */
.media-thumb {
  position: relative;
  height: 170px;
  background: #f8fafc;
  overflow: hidden;
}

.thumb-img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
  transition: transform 0.4s ease;
}
.media-thumb:hover .thumb-img { transform: scale(1.05); }

.type-badge {
  position: absolute;
  top: 10px;
  left: 10px;
  display: flex;
  align-items: center;
  gap: 4px;
  background: rgba(15,23,42,0.6);
  color: #fff;
  font-size: 0.6rem;
  font-weight: 700;
  letter-spacing: 0.08em;
  padding: 3px 8px;
  border-radius: 6px;
  backdrop-filter: blur(6px);
}

/* Done overlay */
.done-overlay {
  position: absolute;
  inset: 0;
  background: rgba(16,185,129,0.35);
  display: flex;
  align-items: center;
  justify-content: center;
}

.check-circle {
  width: 52px;
  height: 52px;
  background: #10b981;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  box-shadow: 0 0 0 8px rgba(16,185,129,0.2);
}

/* Hover overlay */
.thumb-overlay {
  position: absolute;
  inset: 0;
  background: rgba(15,23,42,0.42);
  display: flex;
  align-items: center;
  justify-content: center;
  opacity: 0;
  transition: opacity 0.22s;
}
.media-thumb:hover .thumb-overlay { opacity: 1; }

.preview-btn {
  display: flex;
  align-items: center;
  gap: 7px;
  background: rgba(255,255,255,0.18);
  color: #fff;
  border: 1px solid rgba(255,255,255,0.45);
  backdrop-filter: blur(8px);
  padding: 8px 18px;
  border-radius: 9px;
  font-size: 0.8rem;
  font-weight: 600;
  cursor: pointer;
  transition: background 0.15s, transform 0.15s;
}
.preview-btn:hover { background: rgba(255,255,255,0.28); transform: scale(1.04); }

/* ── Card body ── */
.card-body {
  padding: 14px 14px 16px;
}

.card-meta {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 10px;
}

.meta-dim   { font-size: 0.75rem; color: #94a3b8; font-weight: 500; }
.meta-price { font-size: 0.9rem; font-weight: 700; color: #0f172a; }

/* Progress bar */
.progress-wrap {
  height: 3px;
  background: #e2e8f0;
  border-radius: 99px;
  margin-bottom: 10px;
  overflow: hidden;
  opacity: 0;
  transition: opacity 0.2s;
}
.progress-wrap.visible { opacity: 1; }

.progress-bar {
  height: 100%;
  width: 0%;
  background: linear-gradient(90deg, #6366f1, #8b5cf6);
  border-radius: 99px;
  animation: progress-indeterminate 1.4s ease-in-out infinite;
}

@keyframes progress-indeterminate {
  0%   { transform: translateX(-100%) scaleX(0.5); }
  50%  { transform: translateX(50%)   scaleX(1);   }
  100% { transform: translateX(200%)  scaleX(0.5); }
}

/* ── {{ $t('downloads.download') }} button ── */
.download-btn {
  width: 100%;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  padding: 10px 0;
  border-radius: 10px;
  border: none;
  background: #6366f1;
  color: #fff;
  font-size: 0.85rem;
  font-weight: 600;
  cursor: pointer;
  transition: background 0.2s, transform 0.15s;
  position: relative;
  overflow: hidden;
}

/* ripple */
.download-btn::after {
  content: "";
  position: absolute;
  inset: 0;
  background: radial-gradient(circle, rgba(255,255,255,0.25) 0%, transparent 70%);
  transform: scale(0);
  opacity: 0;
  transition: transform 0.5s, opacity 0.5s;
}
.download-btn:active::after {
  transform: scale(2.5);
  opacity: 1;
  transition: 0s;
}

.download-btn:hover:not(:disabled) {
  background: #4f46e5;
  transform: translateY(-1px);
}
.download-btn:active:not(:disabled) { transform: scale(0.97); }
.download-btn:disabled { cursor: not-allowed; }

.download-btn.is-loading { background: #818cf8; }
.download-btn.is-done    { background: #10b981; }

.btn-svg { display: block; }

.btn-spinner {
  width: 15px;
  height: 15px;
  border: 2px solid rgba(255,255,255,0.35);
  border-top-color: #fff;
  border-radius: 50%;
  animation: spin 0.65s linear infinite;
  flex-shrink: 0;
}

/* ── Toast ── */
.toast {
  position: fixed;
  bottom: 28px;
  right: 28px;
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 12px 20px;
  border-radius: 12px;
  font-size: 0.85rem;
  font-weight: 600;
  z-index: 9999;
  box-shadow: 0 8px 30px rgba(0,0,0,0.12);
}
.toast.success { background: #10b981; color: #fff; }
.toast.error   { background: #ef4444; color: #fff; }

/* ─────────────── Vue Transitions ─────────────── */

/* fade */
.fade-enter-active, .fade-leave-active { transition: opacity 0.3s; }
.fade-enter-from,  .fade-leave-to      { opacity: 0; }

/* fade-up */
.fade-up-enter-active { transition: opacity 0.4s, transform 0.4s; }
.fade-up-leave-active { transition: opacity 0.25s, transform 0.25s; }
.fade-up-enter-from   { opacity: 0; transform: translateY(16px); }
.fade-up-leave-to     { opacity: 0; transform: translateY(-8px); }

/* badge pop */
.badge-pop-enter-active { transition: all 0.35s cubic-bezier(.34,1.56,.64,1); }
.badge-pop-enter-from   { opacity: 0; transform: scale(0.5); }

/* card stagger */
.card-stagger-enter-active {
  transition: opacity 0.4s, transform 0.4s cubic-bezier(.34,1.4,.64,1);
  transition-delay: calc(var(--i) * 0.055s);
}
.card-stagger-enter-from  { opacity: 0; transform: translateY(24px) scale(0.95); }
.card-stagger-leave-active { transition: opacity 0.2s, transform 0.2s; }
.card-stagger-leave-to    { opacity: 0; transform: scale(0.9); }

/* icon swap */
.icon-swap-enter-active, .icon-swap-leave-active { transition: all 0.2s; }
.icon-swap-enter-from { opacity: 0; transform: translateY(6px) scale(0.7); }
.icon-swap-leave-to   { opacity: 0; transform: translateY(-6px) scale(0.7); }

/* text swap */
.text-swap-enter-active, .text-swap-leave-active { transition: all 0.2s; }
.text-swap-enter-from { opacity: 0; transform: translateY(5px); }
.text-swap-leave-to   { opacity: 0; transform: translateY(-5px); }

/* check pop */
.check-pop-enter-active { transition: all 0.4s cubic-bezier(.34,1.56,.64,1); }
.check-pop-enter-from   { opacity: 0; transform: scale(0); }
.check-pop-leave-active { transition: opacity 0.2s; }
.check-pop-leave-to     { opacity: 0; }

/* toast slide */
.toast-slide-enter-active { transition: all 0.35s cubic-bezier(.34,1.56,.64,1); }
.toast-slide-leave-active { transition: all 0.25s ease-in; }
.toast-slide-enter-from   { opacity: 0; transform: translateX(60px); }
.toast-slide-leave-to     { opacity: 0; transform: translateX(60px); }

.downloads-page {
  max-width: 1200px;
  padding: 3rem 1.5rem;
  background:
    radial-gradient(circle at top left, rgba(48, 168, 255, 0.10), transparent 30rem),
    linear-gradient(180deg, #FFFFFF, #F8FAFC);
}

.page-header {
  padding: 1.5rem;
  border: 1px solid #DCE8F5;
  border-radius: 24px;
  background: linear-gradient(135deg, #FFFFFF, #F4F8FC);
  box-shadow: 0 10px 35px rgba(13, 77, 151, 0.06);
}

.page-title {
  color: #06142A;
}

.count-badge {
  background: #EAF4FF;
  color: #0D4D97;
}

.media-card {
  border: 1px solid #E5EDF6;
  border-radius: 22px;
  background: #FFFFFF;
  box-shadow: 0 10px 35px rgba(13, 77, 151, 0.06);
}

.spinner {
  border-top-color: #1677FF;
}

.retry-btn {
  border-radius: 999px;
  background: linear-gradient(135deg, #0D4D97, #1677FF);
}
</style>
