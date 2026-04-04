<template>
    <div class="p-6">

        <h1 class="text-2xl font-bold mb-6">My Downloads</h1>

        <!-- Loading -->
        <div v-if="loading" class="text-center text-gray-500">
            Loading...
        </div>

        <!-- Error -->
        <div v-if="error" class="text-red-500 text-center">
            حصل خطأ في تحميل البيانات
        </div>

        <!-- DATA -->
        <div v-if="downloads.length" class="grid grid-cols-2 md:grid-cols-4 gap-4">

            <div
                v-for="item in downloads"
                :key="item.id"
                class="bg-white shadow rounded-xl overflow-hidden"
            >

                <!-- MEDIA -->
                <div class="h-40 bg-gray-100 relative">

                    <!-- IMAGE -->
                    <img
                        v-if="isImage(item.full_url)"
                        :src="getUrl(item.full_url)"
                        class="w-full h-full object-cover"
                    />

                    <!-- VIDEO -->
                    <video
                        v-else
                        class="w-full h-full object-cover"
                        controls
                        playsinline
                    >
                        <source :src="getUrl(item.full_url)" />
                    </video>

                </div>

                <!-- INFO -->
                <div class="p-3">

                    <div class="text-sm text-gray-600">
                        💰 {{ item.price }} $
                    </div>

                    <div class="text-xs text-gray-400">
                        {{ item.width }} x {{ item.height }}
                    </div>

                    <!-- DOWNLOAD -->
                    <button
                        @click="downloadFile(item.full_url)"
                        class="block w-full mt-2 text-center bg-blue-500 text-white py-1 rounded-lg hover:bg-blue-600"
                    >
                        Download
                    </button>

                </div>

            </div>

        </div>

        <!-- EMPTY -->
        <div v-else-if="!loading" class="text-center text-gray-500">
            لا يوجد تحميلات
        </div>

    </div>
</template>

<script setup>
import { ref, onMounted } from "vue";
import downloadService from "@/services/downloadService";

const downloads = ref([]);
const loading = ref(true);
const error = ref(false);

/* ---------------- URL ---------------- */
const getUrl = (path) => {
    if (!path) return "";
    return `http://127.0.0.1:8000/storage/${path}`;
};

/* ---------------- CHECK TYPE ---------------- */
const isImage = (path) => {
    if (!path) return false;

    return (
        path.includes(".jpg") ||
        path.includes(".jpeg") ||
        path.includes(".png") ||
        path.includes(".webp") ||
        path.includes(".gif")
    );
};

/* ---------------- FETCH ---------------- */
const fetchDownloads = async () => {
    try {
        loading.value = true;
        const res = await downloadService.getDownloads();

        downloads.value = res.data || [];
    } catch (err) {
        error.value = true;
        console.error(err);
    } finally {
        loading.value = false;
    }
};

/* ---------------- DOWNLOAD FILE ---------------- */
const downloadFile = async (url) => {
    try {
        const fullUrl = getUrl(url);

        const response = await fetch(fullUrl);
        const blob = await response.blob();

        const link = document.createElement("a");
        link.href = window.URL.createObjectURL(blob);

        const filename = url.split("/").pop();
        link.download = filename;

        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);

        window.URL.revokeObjectURL(link.href);

    } catch (err) {
        console.error("Download failed", err);
    }
};

/* ---------------- INIT ---------------- */
onMounted(() => {
    fetchDownloads();
});
</script>
