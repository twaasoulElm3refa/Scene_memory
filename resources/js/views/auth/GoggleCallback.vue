<template>
  <div class="flex items-center justify-center h-screen">
    <div v-if="loading" class="flex flex-col items-center gap-3">
      <!-- Circular Spinner -->
      <svg
        class="animate-spin h-12 w-12 text-blue-600"
        xmlns="http://www.w3.org/2000/svg"
        fill="none"
        viewBox="0 0 24 24"
      >
        <circle
          class="opacity-25"
          cx="12"
          cy="12"
          r="10"
          stroke="currentColor"
          stroke-width="4"
        ></circle>
        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
      </svg>
      <span class="text-gray-700">جارٍ تسجيل الدخول عبر Google...</span>
    </div>

    <div v-else-if="error" class="text-red-600 font-semibold text-center">
      {{ error }}
    </div>
  </div>
</template>

<script setup>
import { onMounted, ref } from "vue";
import { useRoute, useRouter } from "vue-router";
import axios from "axios";

const route = useRoute();
const router = useRouter();
const loading = ref(true);
const error = ref(null);

onMounted(async () => {
  const code = route.query.code;
  if (!code) {
    error.value = "لم يتم استقبال الكود";
    loading.value = false;
    return;
  }

  try {
    const response = await axios.get(`/v1/users/google-callback`, { params: { code } });
    localStorage.setItem("auth_token", response.data.token);
    window.dispatchEvent(new Event("login"));
    if (response.data.role === "admin") {
      router.push("/admin");
    } else {
      router.push("/");
    }
  } catch (err) {
    console.error(err);
    error.value = "فشل تسجيل الدخول عبر Google";
  } finally {
    loading.value = false;
  }
});
</script>

<style scoped></style>
