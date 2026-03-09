<template>
  <AdminLayout>
    <div class="p-6 space-y-8">
      <!-- Stats Cards Row -->
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        <!-- Total Events -->
        <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-100">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm text-gray-500 font-medium">Total Events</p>
              <p class="text-3xl font-bold mt-1">{{ stats.events.toLocaleString() }}</p>
            </div>
            <div class="bg-blue-100 text-blue-600 p-3 rounded-lg">
              <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"
                />
              </svg>
            </div>
          </div>
          <p class="mt-4 text-sm">
            <span class="text-green-600 font-medium">+12%</span>
            <span class="text-gray-500"> from last month</span>
          </p>
        </div>
        <!-- Active Users -->
        <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-100">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm text-gray-500 font-medium">Active Users</p>
              <p class="text-3xl font-bold mt-1">{{ formatNumber(stats.users) }}</p>
            </div>
            <div class="bg-purple-100 text-purple-600 p-3 rounded-lg">
              <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"
                />
              </svg>
            </div>
          </div>
          <p class="mt-4 text-sm">
            <span class="text-green-600 font-medium">+5.4%</span>
            <span class="text-gray-500"> from last month</span>
          </p>
        </div>
        <!-- Total Memories -->
        <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-100">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm text-gray-500 font-medium">Total Memories</p>
              <p class="text-3xl font-bold mt-1">{{ formatNumber(stats.memories) }}</p>
            </div>
            <div class="bg-amber-100 text-amber-600 p-3 rounded-lg">
              <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"
                />
              </svg>
            </div>
          </div>
          <p class="mt-4 text-sm">
            <span class="text-green-600 font-medium">+22%</span>
            <span class="text-gray-500"> from last month</span>
          </p>
        </div>
        <!-- Countries Count -->
        <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-100">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm text-gray-500 font-medium">Countries</p>
              <p class="text-3xl font-bold mt-1">
                {{ stats.countries.toLocaleString() }}
              </p>
            </div>
            <div class="bg-rose-100 text-rose-600 p-3 rounded-lg">
              <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"
                />
              </svg>
            </div>
          </div>
          <p class="mt-4 text-sm">
            <span class="text-rose-600 font-medium">24</span>
            <span class="text-gray-500"> items need attention</span>
          </p>
        </div>
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Recent Activity -->
        <div
          class="lg:col-span-2 bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden"
        >
          <div class="p-6 border-b border-gray-100 flex items-center justify-between">
            <h2 class="text-lg font-semibold">Recent users</h2>
            <a
              href="/admin/users"
              class="text-blue-600 text-decoration-none hover:underline text-sm font-medium"
              >View all</a
            >
          </div>
          <div class="divide-y divide-gray-100">
            <div
              v-for="user in recentActivity"
              :key="user.id"
              class="p-6 flex items-center gap-4 hover:bg-gray-50"
            >
              <div
                class="w-10 h-10 rounded-full bg-blue-100 flex items-center justify-center text-blue-600 font-semibold"
              >
                {{ user.name.charAt(0) }}
              </div>
              <div class="flex-1 min-w-0">
                <p class="font-medium text-gray-900">{{ user.name }}</p>
                <p class="text-sm text-gray-500">{{ user.email }}</p>
              </div>
              <div class="text-right">
                <p class="text-sm text-gray-500">
                  {{ new Date(user.created_at).toLocaleDateString() }}
                </p>
                <span
                  class="inline-block px-2.5 py-1 text-xs font-medium rounded-full mt-1"
                  :class="{
                    'bg-red-100 text-red-600': user.role === 'admin',
                    'bg-yellow-100 text-yellow-600': user.role === 'owner',
                    'bg-green-100 text-green-600': user.role === 'user',
                  }"
                >
                  {{ user.role }}
                </span>
              </div>
            </div>
          </div>
        </div>

        <!-- Quick Actions + Storage -->
        <div class="space-y-6">
          <!-- Quick Actions -->
          <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
            <h2 class="text-lg font-semibold mb-4">Quick Actions</h2>
            <div class="grid grid-cols-2 gap-4">
              <router-link
                to="/admin/events/create"
                class="flex text-decoration-none flex-col items-center justify-center p-4 border border-gray-200 rounded-lg hover:border-blue-500 hover:bg-blue-50 transition"
              >
                <span class="text-blue-600 text-2xl mb-1">+</span>
                <span class="text-sm font-medium">New Event</span>
              </router-link>
              <router-link
                to="/admin/users/add"
                class="flex text-decoration-none flex-col items-center justify-center p-4 border border-gray-200 rounded-lg hover:border-blue-500 hover:bg-blue-50 transition"
              >
                <span class="text-blue-600 text-2xl mb-1">👤</span>
                <span class="text-sm font-medium">Create User</span>
              </router-link>
              <button
                @click="showNotificationModal = true"
                class="flex flex-col items-center justify-center p-4 border border-gray-200 rounded-lg hover:border-blue-500 hover:bg-blue-50 transition"
              >
                <span class="text-blue-600 text-2xl mb-1">📢</span>
                <span class="text-sm font-medium">Send Notifications</span>
              </button>
            </div>
          </div>

          <!-- Storage Usage -->
          <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
            <h2 class="text-lg font-semibold mb-4">Storage Usage</h2>
            <div class="mb-2 flex justify-between text-sm">
              <span class="text-gray-600">74%</span>
              <span class="text-gray-500">Used</span>
            </div>
            <div class="w-full bg-gray-200 rounded-full h-2.5 mb-6">
              <div class="bg-blue-600 h-2.5 rounded-full" style="width: 74%"></div>
            </div>
            <div class="space-y-3 text-sm">
              <div class="flex justify-between">
                <span class="text-gray-600">Photos</span>
                <span class="font-medium">1.2 TB</span>
              </div>
              <div class="flex justify-between">
                <span class="text-gray-600">Videos</span>
                <span class="font-medium">854 GB</span>
              </div>
              <div class="flex justify-between">
                <span class="text-gray-600">Database</span>
                <span class="font-medium">42 GB</span>
              </div>
            </div>
            <button
              class="mt-6 w-full py-2 px-4 border border-blue-600 text-blue-600 rounded-lg hover:bg-blue-50 transition"
            >
              Manage Storage
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Notification Modal -->
    <div
      v-if="showNotificationModal"
      class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4"
    >
      <div class="bg-white rounded-xl shadow-2xl max-w-lg w-full overflow-hidden">
        <div class="p-6 border-b border-gray-200">
          <h3 class="text-xl font-semibold text-gray-900">Send Notification</h3>
        </div>

        <div class="p-6 space-y-5">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">
              Message
            </label>
            <textarea
              v-model="notificationMessage"
              rows="4"
              class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 resize-none"
              placeholder="Write your notification message here..."
              :class="{ 'border-red-500': errors.message }"
            ></textarea>
            <p v-if="errors.message" class="mt-1 text-sm text-red-600">
              {{ errors.message }}
            </p>
          </div>

          <!-- You can add more fields later: title, target (all/users/roles), etc. -->
        </div>

        <div class="px-6 py-4 bg-gray-50 flex justify-end gap-3 border-t border-gray-200">
          <button
            @click="showNotificationModal = false"
            class="px-5 py-2.5 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-100 transition"
          >
            Cancel
          </button>
          <button
            @click="sendNotification"
            :disabled="loading || !notificationMessage.trim()"
            class="px-5 py-2.5 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2"
          >
            <span v-if="loading" class="animate-spin inline-block w-4 h-4 border-2 border-white border-t-transparent rounded-full"></span>
            Send
          </button>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>

<script setup>
import { ref, onMounted } from "vue";
import AdminLayout from "../../layouts/AdminLayout.vue";
import axios from "axios";

const stats = ref({
  events: 0,
  users: 0,
  memories: 0,
  countries: 0,
});

const recentActivity = ref([]);
const showNotificationModal = ref(false);
const notificationMessage = ref("");
const loading = ref(false);
const errors = ref({});

const getLatestUsers = async () => {
  try {
    const response = await axios.get("/v1/users/latest/get");
    recentActivity.value = response.data.data;
  } catch (error) {
    console.error("Error fetching latest users:", error);
  }
};

async function fetchStats() {
  try {
    const [eventsRes, usersRes, memoriesRes, countriesRes] = await Promise.all([
      axios.get("/v1/events/count"),
      axios.get("/v1/users/all/count"),
      axios.get("/v1/events/memories"),
      axios.get("/v1/countries/all/count"),
    ]);

    stats.value = {
      events: eventsRes.data.status === "success" ? eventsRes.data.data : 0,
      users: usersRes.data.status === "success" ? usersRes.data.data : 0,
      memories: memoriesRes.data.status === "success" ? memoriesRes.data.data : 0,
      countries: countriesRes.data.status === "success" ? countriesRes.data.data : 0,
    };
  } catch (err) {
    console.error("Failed to load dashboard stats:", err);
  }
}

const sendNotification = async () => {
  errors.value = {};
  if (!notificationMessage.value.trim()) {
    errors.value.message = "Message is required";
    return;
  }

  loading.value = true;

  try {
    const payload = {
      message: notificationMessage.value.trim(),
      // You can add later: title, type, user_ids, roles, etc.
    };

    const response = await axios.post("/v1/notify/create", payload);

    if (response.data.status === "success") {
      alert("Notification sent successfully!");
      showNotificationModal.value = false;
      notificationMessage.value = "";
    } else {
      alert("Failed to send notification: " + (response.data.message || "Unknown error"));
    }
  } catch (error) {
    console.error("Error sending notification:", error);
    if (error.response?.data?.message) {
      alert("Error: " + error.response.data.message);
    } else if (error.response?.data?.errors) {
      // Laravel validation errors example
      errors.value = error.response.data.errors;
    } else {
      alert("Failed to send notification. Please try again.");
    }
  } finally {
    loading.value = false;
  }
};

onMounted(() => {
  getLatestUsers();
  fetchStats();
});

function formatNumber(num) {
  if (num >= 100000) return (num / 1000).toFixed(1) + "k";
  if (num >= 10000) return (num / 1000).toFixed(1) + "k";
  return num.toLocaleString();
}
</script>
