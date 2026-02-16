<template>
  <AdminLayout>
    <div :class="themeClass" class="user-directory">
      <!-- Header Section -->
      <div class="header-section">
        <div class="header-content">
          <h1 class="page-title">User Directory</h1>
          <p class="page-subtitle">
            Manage platform members, assign roles, and monitor account activity.
          </p>
        </div>
        <div class="header-actions">
          <button class="btn export-btn">
            <svg
              xmlns="http://www.w3.org/2000/svg"
              width="18"
              height="18"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="2"
              stroke-linecap="round"
              stroke-linejoin="round"
            >
              <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
              <polyline points="7 10 12 15 17 10"></polyline>
              <line x1="12" y1="15" x2="12" y2="3"></line>
            </svg>
            Export CSV
          </button>
          <a href="/admin/users/add" class="btn create-btn" @click="openCreateModal">
            <svg
              xmlns="http://www.w3.org/2000/svg"
              width="18"
              height="18"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="2"
              stroke-linecap="round"
              stroke-linejoin="round"
            >
              <line x1="12" y1="5" x2="12" y2="19"></line>
              <line x1="5" y1="12" x2="19" y2="12"></line>
            </svg>
            Create New User
          </a>
        </div>
      </div>

      <!-- Filters Section -->
      <div class="filters-section">
        <div class="search-box">
          <svg
            xmlns="http://www.w3.org/2000/svg"
            width="18"
            height="18"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="2"
            stroke-linecap="round"
            stroke-linejoin="round"
          >
            <line x1="4" y1="21" x2="4" y2="14"></line>
            <line x1="4" y1="10" x2="4" y2="3"></line>
            <line x1="12" y1="21" x2="12" y2="12"></line>
            <line x1="12" y1="8" x2="12" y2="3"></line>
            <line x1="20" y1="21" x2="20" y2="16"></line>
            <line x1="20" y1="12" x2="20" y2="3"></line>
            <line x1="1" y1="14" x2="7" y2="14"></line>
            <line x1="9" y1="8" x2="15" y2="8"></line>
            <line x1="17" y1="16" x2="23" y2="16"></line>
          </svg>
          <input
            type="text"
            placeholder="Filter by name, email, or position..."
            v-model="searchQuery"
            class="search-input"
          />
        </div>

        <div class="filter-dropdowns">
          <select v-model="roleFilter" class="filter-select">
            <option value="">Role: All</option>
            <option value="admin">Admin</option>
            <option value="editor">Editor</option>
            <option value="viewer">Viewer</option>
            <option value="user">User</option>
          </select>

          <select v-model="statusFilter" class="filter-select">
            <option value="">Status: All</option>
            <option value="active">Active</option>
            <option value="inactive">Inactive</option>
          </select>

          <select v-model="joinedFilter" class="filter-select">
            <option value="">Joined: All Time</option>
            <option value="7">Last 7 Days</option>
            <option value="30">Last 30 Days</option>
            <option value="90">Last 90 Days</option>
          </select>

          <button class="btn refresh-btn" @click="fetchUsers()">
            <svg
              xmlns="http://www.w3.org/2000/svg"
              width="18"
              height="18"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="2"
              stroke-linecap="round"
              stroke-linejoin="round"
            >
              <polyline points="23 4 23 10 17 10"></polyline>
              <polyline points="1 20 1 14 7 14"></polyline>
              <path
                d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"
              ></path>
            </svg>
          </button>
        </div>
      </div>

      <!-- Users Card List -->
      <div class="users-list">
        <div v-if="loading" class="loading-state">
          <div class="spinner"></div>
          <p>Loading users...</p>
        </div>

        <div v-else-if="filteredUsers.length === 0" class="empty-state">
          <svg
            xmlns="http://www.w3.org/2000/svg"
            width="48"
            height="48"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="1.5"
            stroke-linecap="round"
            stroke-linejoin="round"
          >
            <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path>
            <circle cx="9" cy="7" r="4"></circle>
            <path d="M22 21v-2a4 4 0 0 0-3-3.87"></path>
            <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
          </svg>
          <p>No users found</p>
        </div>

        <div v-else class="user-cards-grid">
          <!-- Table Header -->
          <div class="table-header">
            <div class="col-user">USER</div>
            <div class="col-contact">CONTACT</div>
            <div class="col-demographics">Country</div>
            <div class="col-role">ROLE & POSITION</div>
            <div class="col-activity">ACTIVITY</div>
            <div class="col-actions">ACTIONS</div>
          </div>

          <!-- User Cards -->
          <div v-for="user in paginatedUsers" :key="user.id" class="user-card">
            <div class="col-user">
              <div class="user-info">
                <div class="user-avatar">
                  <img v-if="user.avatar" :src="user.avatar" :alt="user.name" />
                  <div v-else class="avatar-placeholder">
                    {{ getInitials(user.name || user.email) }}
                  </div>
                </div>
                <div class="user-details">
                  <div class="user-name">{{ user.name || "Unknown User" }}</div>
                  <div class="user-meta">
                    Member since {{ formatYear(user.created_at) }}
                  </div>
                </div>
              </div>
            </div>

            <div class="col-contact">
              <div class="contact-info">
                <div class="contact-email">{{ user.email }}</div>
                <div class="contact-phone">{{ user.phone || "+1 (555) 123-4567" }}</div>
              </div>
            </div>

            <div class="col-demographics">
              <div class="demographics-info">
                <div class="demo-country">{{ user.country || "United States" }}</div>
                <div class="demo-date">
                  {{ user.birth_date || formatDate(user.created_at) }}
                </div>
              </div>
            </div>

            <div class="col-role">
              <div class="role-info">
                <div class="role-title">{{ getRoleTitle(user.role) }}</div>
                <span :class="['role-badge', getRoleBadgeClass(user.role)]">
                  {{ (user.role || "user").toUpperCase() }}
                </span>
              </div>
            </div>

            <div class="col-activity">
              <div class="activity-info">
                <span
                  :class="[
                    'status-badge',
                    user.is_active ? 'status-active' : 'status-inactive',
                  ]"
                >
                  {{ user.is_active ? "Active" : "Inactive" }}
                </span>
                <div class="last-login">
                  Last login: {{ formatLastLogin(user.last_login_at) }}
                </div>
              </div>
            </div>

            <div class="col-actions">
              <div class="action-buttons">
                <button
                  class="btn-icon edit-icon"
                  @click="openEditModal(user)"
                  title="Edit user"
                >
                  <svg
                    xmlns="http://www.w3.org/2000/svg"
                    width="16"
                    height="16"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                  >
                    <path
                      d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"
                    ></path>
                    <path
                      d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"
                    ></path>
                  </svg>
                </button>
                <button
                  class="btn-icon delete-icon"
                  @click="deleteUser(user.id)"
                  title="Delete user"
                >
                  <svg
                    xmlns="http://www.w3.org/2000/svg"
                    width="16"
                    height="16"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                  >
                    <polyline points="3 6 5 6 21 6"></polyline>
                    <path
                      d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"
                    ></path>
                    <line x1="10" y1="11" x2="10" y2="17"></line>
                    <line x1="14" y1="11" x2="14" y2="17"></line>
                  </svg>
                </button>
              </div>
            </div>
          </div>
        </div>

        <!-- Pagination Footer -->
        <div v-if="pagination.last_page > 1" class="pagination-footer">
          <div class="pagination-info">
            Showing <strong>{{ paginationStart }}</strong> to
            <strong>{{ paginationEnd }}</strong> of
            <strong>{{ pagination.total }}</strong> users
          </div>

          <div class="pagination-controls">
            <button
              class="btn page-btn"
              :disabled="pagination.current_page === 1"
              @click="fetchUsers(pagination.current_page - 1)"
            >
              <svg
                xmlns="http://www.w3.org/2000/svg"
                width="16"
                height="16"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
                stroke-linecap="round"
                stroke-linejoin="round"
              >
                <polyline points="15 18 9 12 15 6"></polyline>
              </svg>
            </button>

            <button
              v-for="page in visiblePages"
              :key="page"
              :class="[
                'btn page-number',
                page === pagination.current_page ? 'active-page' : '',
              ]"
              @click="fetchUsers(page)"
            >
              {{ page }}
            </button>

            <span v-if="showEllipsis" class="pagination-ellipsis">...</span>

            <button
              v-if="pagination.last_page > 5"
              :class="[
                'btn page-number',
                pagination.last_page === pagination.current_page ? 'active-page' : '',
              ]"
              @click="fetchUsers(pagination.last_page)"
            >
              {{ pagination.last_page }}
            </button>

            <button
              class="btn page-btn"
              :disabled="pagination.current_page === pagination.last_page"
              @click="fetchUsers(pagination.current_page + 1)"
            >
              <svg
                xmlns="http://www.w3.org/2000/svg"
                width="16"
                height="16"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
                stroke-linecap="round"
                stroke-linejoin="round"
              >
                <polyline points="9 18 15 12 9 6"></polyline>
              </svg>
            </button>
          </div>
        </div>
      </div>

      <!-- Statistics Cards -->
      <div class="stats-section">
        <div class="stat-card retention-card">
          <div class="stat-icon">
            <svg
              xmlns="http://www.w3.org/2000/svg"
              width="24"
              height="24"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="2"
              stroke-linecap="round"
              stroke-linejoin="round"
            >
              <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
              <circle cx="9" cy="7" r="4"></circle>
              <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
              <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
            </svg>
          </div>
          <div class="stat-content">
            <div class="stat-label">ACCOUNT RETENTION</div>
            <div class="stat-value">94.2%</div>
            <div class="stat-change positive">+2.4% from last month</div>
          </div>
        </div>

        <div class="stat-card active-card">
          <div class="stat-icon">
            <svg
              xmlns="http://www.w3.org/2000/svg"
              width="24"
              height="24"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="2"
              stroke-linecap="round"
              stroke-linejoin="round"
            >
              <polyline points="23 6 13.5 15.5 8.5 10.5 1 18"></polyline>
              <polyline points="17 6 23 6 23 12"></polyline>
            </svg>
          </div>
          <div class="stat-content">
            <div class="stat-label">DAILY ACTIVE USERS</div>
            <div class="stat-value">{{ stats.activeUsers || "1,208" }}</div>
            <div class="stat-subtitle">Real-time platform activity</div>
          </div>
        </div>

        <div class="stat-card signups-card">
          <div class="stat-icon">
            <svg
              xmlns="http://www.w3.org/2000/svg"
              width="24"
              height="24"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="2"
              stroke-linecap="round"
              stroke-linejoin="round"
            >
              <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
              <circle cx="8.5" cy="7" r="4"></circle>
              <line x1="20" y1="8" x2="20" y2="14"></line>
              <line x1="23" y1="11" x2="17" y2="11"></line>
            </svg>
          </div>
          <div class="stat-content">
            <div class="stat-label">NEW SIGNUPS</div>
            <div class="stat-value">{{ stats.newSignups || "42" }}</div>
            <div class="stat-subtitle">
              Pending approval: {{ stats.pendingApproval || "3" }}
            </div>
          </div>
        </div>
      </div>

      <!-- Edit Modal -->
      <div v-if="editModalOpen" class="modal-overlay" @click.self="closeEditModal">
        <div class="modal-wrapper">
          <div class="modal-header">
            <h2>Edit User</h2>
            <button class="close-btn" @click="closeEditModal">
              <svg
                xmlns="http://www.w3.org/2000/svg"
                width="20"
                height="20"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
                stroke-linecap="round"
                stroke-linejoin="round"
              >
                <line x1="18" y1="6" x2="6" y2="18"></line>
                <line x1="6" y1="6" x2="18" y2="18"></line>
              </svg>
            </button>
          </div>

          <div class="modal-body">
            <div class="form-group">
              <label>Name</label>
              <input
                v-model="editForm.name"
                type="text"
                class="input-field"
                placeholder="Enter full name"
              />
            </div>

            <div class="form-group">
              <label>Email</label>
              <input
                v-model="editForm.email"
                type="email"
                class="input-field"
                placeholder="Enter email address"
              />
            </div>

            <div class="form-group">
              <label>Role</label>
              <select v-model="editForm.role" class="input-field">
                <option value="user">User</option>
                <option value="admin">Admin</option>
                <option value="editor">Editor</option>
                <option value="viewer">Viewer</option>
              </select>
            </div>

            <div class="form-group checkbox-group">
              <input type="checkbox" v-model="editForm.is_active" id="is_active" />
              <label for="is_active">Account is active</label>
            </div>

            <div v-if="editErrors.length" class="error-msg">
              <ul>
                <li v-for="(err, i) in editErrors" :key="i">{{ err }}</li>
              </ul>
            </div>

            <div v-if="editSuccess" class="success-msg">{{ editSuccess }}</div>
          </div>

          <div class="modal-footer">
            <button class="btn cancel-btn" @click="closeEditModal">Cancel</button>
            <button class="btn save-btn" @click="submitEdit">
              <svg
                xmlns="http://www.w3.org/2000/svg"
                width="16"
                height="16"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
                stroke-linecap="round"
                stroke-linejoin="round"
              >
                <polyline points="20 6 9 17 4 12"></polyline>
              </svg>
              Save Changes
            </button>
          </div>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>

<script setup>
import AdminLayout from "@/layouts/AdminLayout.vue";
import { ref, reactive, onMounted, computed } from "vue";
import axios from "axios";

const theme = localStorage.getItem("theme") || "light";
const themeClass = computed(() => (theme === "dark" ? "dark-theme" : "light-theme"));

// State
const users = ref([]);
const loading = ref(false);
const pagination = ref({ current_page: 1, last_page: 1, per_page: 10, total: 0 });

const stats = ref({
  activeUsers: 0,
  newSignups: 0,
  pendingApproval: 0,
});

// Filters
const searchQuery = ref("");
const roleFilter = ref("");
const statusFilter = ref("");
const joinedFilter = ref("");

// Edit Modal
const editModalOpen = ref(false);
const editForm = reactive({
  id: null,
  name: "",
  email: "",
  role: "user",
  is_active: false,
});
const editErrors = ref([]);
const editSuccess = ref("");

// Create Modal
const createModalOpen = ref(false);
const createForm = reactive({ name: "", email: "", password: "", role: "user" });

// Computed
const filteredUsers = computed(() => {
  let filtered = users.value;

  if (searchQuery.value) {
    const query = searchQuery.value.toLowerCase();
    filtered = filtered.filter(
      (u) =>
        (u.name && u.name.toLowerCase().includes(query)) ||
        (u.email && u.email.toLowerCase().includes(query))
    );
  }

  if (roleFilter.value) {
    filtered = filtered.filter((u) => (u.role || "user") === roleFilter.value);
  }

  if (statusFilter.value) {
    const isActive = statusFilter.value === "active";
    filtered = filtered.filter((u) => !!u.is_active === isActive);
  }

  return filtered;
});

const paginatedUsers = computed(() => {
  const start = (pagination.value.current_page - 1) * pagination.value.per_page;
  const end = start + pagination.value.per_page;
  return filteredUsers.value.slice(start, end);
});

const paginationStart = computed(() => {
  return (pagination.value.current_page - 1) * pagination.value.per_page + 1;
});

const paginationEnd = computed(() => {
  const end = pagination.value.current_page * pagination.value.per_page;
  return Math.min(end, pagination.value.total);
});

const visiblePages = computed(() => {
  const current = pagination.value.current_page;
  const total = pagination.value.last_page;
  const pages = [];

  if (total <= 5) {
    for (let i = 1; i <= total; i++) pages.push(i);
  } else {
    if (current <= 3) {
      for (let i = 1; i <= 3; i++) pages.push(i);
    } else if (current >= total - 2) {
      for (let i = total - 2; i < total; i++) pages.push(i);
    } else {
      pages.push(current - 1, current, current + 1);
    }
  }

  return pages;
});

const showEllipsis = computed(() => {
  return (
    pagination.value.last_page > 5 &&
    pagination.value.current_page < pagination.value.last_page - 2
  );
});

// Methods
const fetchUsers = async (page = 1) => {
  loading.value = true;
  try {
    const token = localStorage.getItem("auth_token");
    const res = await axios.get(`/v1/users/all/get?page=${page}`, {
      headers: { Authorization: token ? `Bearer ${token}` : "" },
    });
    const data = res.data.data;
    users.value = data.data || [];
    pagination.value = {
      current_page: data.current_page,
      last_page: data.last_page,
      per_page: data.per_page,
      total: data.total,
    };
  } catch (err) {
    alert("Failed to load users");
  } finally {
    loading.value = false;
  }
};

// دالة لجلب عدد المستخدمين النشطين اليوم
async function fetchActiveUsers() {
  try {
    const response = await axios.get("/v1/users/all/last-login");
    if (response.data.status === "success") {
      stats.value.activeUsers = response.data.data;
    }
  } catch (error) {
    console.error("Error fetching active users:", error);
  }
}

// دالة لجلب عدد المستخدمين الجدد
async function fetchNewUsers() {
  try {
    const response = await axios.get("/v1/users/all/new-users");
    if (response.data.status === "success") {
      stats.value.newSignups = response.data.data;
      // لو في pendingApproval داخل الـ API ممكن تحطه هنا برضه
      stats.value.pendingApproval = response.data.pendingApproval || 0;
    }
  } catch (error) {
    console.error("Error fetching new users:", error);
  }
}
const openEditModal = (user) => {
  editForm.id = user.id;
  editForm.name = user.name || "";
  editForm.email = user.email || "";
  editForm.role = user.role || "user";
  editForm.is_active = !!user.is_active;
  editErrors.value = [];
  editSuccess.value = "";
  editModalOpen.value = true;
};

const closeEditModal = () => {
  editModalOpen.value = false;
};

const submitEdit = async () => {
  editErrors.value = [];
  editSuccess.value = "";
  try {
    const token = localStorage.getItem("auth_token");
    await axios.post(
      `/v1/users/${editForm.id}`,
      {
        name: editForm.name,
        email: editForm.email,
        role: editForm.role,
        is_active: editForm.is_active,
      },
      { headers: { Authorization: token ? `Bearer ${token}` : "" } }
    );
    const idx = users.value.findIndex((u) => u.id === editForm.id);
    if (idx !== -1) users.value[idx] = { ...users.value[idx], ...editForm };
    editSuccess.value = "User updated successfully!";
    setTimeout(() => closeEditModal(), 1400);
  } catch (err) {
    if (err.response?.status === 422 && err.response.data?.errors)
      editErrors.value = Object.values(err.response.data.errors).flat();
    else editErrors.value = [err.response?.data?.message || "Failed to update user"];
  }
};

const deleteUser = async (id) => {
  if (
    !confirm("Are you sure you want to delete this user? This action cannot be undone.")
  )
    return;
  try {
    const token = localStorage.getItem("auth_token");
    await axios.delete(`/v1/users/${id}`, {
      headers: { Authorization: token ? `Bearer ${token}` : "" },
    });
    users.value = users.value.filter((u) => u.id !== id);
    alert("User deleted successfully");
  } catch (err) {
    alert("Failed to delete user");
  }
};

const openCreateModal = () => {
  createForm.name = "";
  createForm.email = "";
  createForm.password = "";
  createForm.role = "user";
  createModalOpen.value = true;
};

const closeCreateModal = () => {
  createModalOpen.value = false;
};

const submitCreate = async () => {
  try {
    const token = localStorage.getItem("auth_token");
    const res = await axios.post(`/v1/users/create`, createForm, {
      headers: { Authorization: token ? `Bearer ${token}` : "" },
    });
    users.value.unshift(res.data.data);
    alert("User created successfully");
    closeCreateModal();
  } catch (err) {
    alert(err.response?.data?.message || "Failed to create user");
  }
};

// Helpers
const getInitials = (name) => {
  if (!name) return "?";
  const parts = name.split(" ");
  if (parts.length >= 2) return `${parts[0][0]}${parts[1][0]}`.toUpperCase();
  return name.substring(0, 2).toUpperCase();
};

const formatYear = (date) => {
  if (!date) return new Date().getFullYear();
  return new Date(date).getFullYear();
};

const formatDate = (date) => {
  if (!date) return "Mar 12, 1990";
  const d = new Date(date);
  return d.toLocaleDateString("en-US", {
    month: "short",
    day: "numeric",
    year: "numeric",
  });
};

const formatLastLogin = (date) => {
  if (!date) return "2h ago";
  const now = new Date();
  const login = new Date(date);
  const diff = Math.floor((now - login) / 1000);

  if (diff < 60) return "just now";
  if (diff < 3600) return `${Math.floor(diff / 60)}m ago`;
  if (diff < 86400) return `${Math.floor(diff / 3600)}h ago`;
  if (diff < 604800) return `${Math.floor(diff / 86400)}d ago`;
  return `${Math.floor(diff / 604800)}w ago`;
};

const getRoleTitle = (role) => {
  const titles = {
    admin: "Creative Director",
    editor: "Senior UX Designer",
    viewer: "Lead Photographer",
    user: "Project Manager",
  };
  return titles[role] || "Team Member";
};

const getRoleBadgeClass = (role) => {
  const classes = {
    admin: "role-admin",
    editor: "role-editor",
    viewer: "role-viewer",
    user: "role-user",
  };
  return classes[role] || "role-user";
};

onMounted(() => {
  fetchUsers();
  fetchActiveUsers();
  fetchNewUsers();
});
</script>

<style scoped>
/* Theme Variables */
.light-theme {
  --bg-primary: #f8f9fb;
  --bg-secondary: #ffffff;
  --bg-card: #ffffff;
  --text-primary: #1f2937;
  --text-secondary: #6b7280;
  --text-muted: #9ca3af;
  --border-color: #e5e7eb;
  --border-hover: #d1d5db;
  --shadow-sm: 0 1px 3px rgba(0, 0, 0, 0.05);
  --shadow-md: 0 4px 12px rgba(0, 0, 0, 0.08);
  --shadow-lg: 0 10px 25px rgba(0, 0, 0, 0.12);
}

.dark-theme {
  --bg-primary: #0f1419;
  --bg-secondary: #1a1f2e;
  --bg-card: #1e2532;
  --text-primary: #f9fafb;
  --text-secondary: #9ca3af;
  --text-muted: #6b7280;
  --border-color: #2d3748;
  --border-hover: #374151;
  --shadow-sm: 0 1px 3px rgba(0, 0, 0, 0.3);
  --shadow-md: 0 4px 12px rgba(0, 0, 0, 0.4);
  --shadow-lg: 0 10px 25px rgba(0, 0, 0, 0.5);
}

/* Base Styles */
.user-directory {
  background: var(--bg-primary);
  color: var(--text-primary);
  min-height: 100vh;
  padding: 2rem;
}

/* Header Section */
.header-section {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 2rem;
  flex-wrap: wrap;
  gap: 1.5rem;
}

.header-content {
  flex: 1;
}

.page-title {
  font-size: 2rem;
  font-weight: 700;
  margin: 0 0 0.5rem 0;
  color: var(--text-primary);
}

.page-subtitle {
  font-size: 1rem;
  color: var(--text-secondary);
  margin: 0;
}

.header-actions {
  display: flex;
  gap: 0.75rem;
  flex-wrap: wrap;
}

/* Buttons */
.btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 0.5rem;
  padding: 0.625rem 1.25rem;
  border-radius: 0.5rem;
  font-weight: 500;
  font-size: 0.875rem;
  cursor: pointer;
  transition: all 0.2s;
  border: 1px solid transparent;
  white-space: nowrap;
}

.export-btn {
  background: var(--bg-secondary);
  color: var(--text-primary);
  border: 1px solid var(--border-color);
  box-shadow: var(--shadow-sm);
}

.export-btn:hover {
  background: var(--bg-primary);
  border-color: var(--border-hover);
}

.create-btn {
  background: #3b82f6;
  color: white;
  box-shadow: 0 2px 8px rgba(59, 130, 246, 0.3);
}

.create-btn:hover {
  background: #2563eb;
  box-shadow: 0 4px 12px rgba(59, 130, 246, 0.4);
}

/* Filters Section */
.filters-section {
  background: var(--bg-card);
  padding: 1.5rem;
  border-radius: 1rem;
  margin-bottom: 1.5rem;
  box-shadow: var(--shadow-sm);
  border: 1px solid var(--border-color);
}

.search-box {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  padding: 0.75rem 1rem;
  background: var(--bg-primary);
  border: 1px solid var(--border-color);
  border-radius: 0.5rem;
  margin-bottom: 1rem;
}

.search-box svg {
  color: var(--text-muted);
  flex-shrink: 0;
}

.search-input {
  flex: 1;
  border: none;
  background: transparent;
  outline: none;
  color: var(--text-primary);
  font-size: 0.875rem;
}

.search-input::placeholder {
  color: var(--text-muted);
}

.filter-dropdowns {
  display: flex;
  gap: 0.75rem;
  flex-wrap: wrap;
}

.filter-select {
  padding: 0.625rem 1rem;
  border: 1px solid var(--border-color);
  border-radius: 0.5rem;
  background: var(--bg-primary);
  color: var(--text-primary);
  font-size: 0.875rem;
  cursor: pointer;
  outline: none;
  transition: all 0.2s;
}

.filter-select:hover,
.filter-select:focus {
  border-color: var(--border-hover);
}

.refresh-btn {
  background: var(--bg-primary);
  color: var(--text-primary);
  border: 1px solid var(--border-color);
  padding: 0.625rem;
}

.refresh-btn:hover {
  background: var(--bg-secondary);
}

/* Users List */
.users-list {
  background: var(--bg-card);
  border-radius: 1rem;
  box-shadow: var(--shadow-sm);
  border: 1px solid var(--border-color);
  overflow: hidden;
  margin-bottom: 1.5rem;
}

/* Loading State */
.loading-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 4rem 2rem;
  gap: 1rem;
}

.spinner {
  width: 40px;
  height: 40px;
  border: 3px solid var(--border-color);
  border-top-color: #3b82f6;
  border-radius: 50%;
  animation: spin 1s linear infinite;
}

@keyframes spin {
  to {
    transform: rotate(360deg);
  }
}

/* Empty State */
.empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 4rem 2rem;
  gap: 1rem;
  color: var(--text-muted);
}

.empty-state svg {
  opacity: 0.5;
}

/* User Cards Grid */
.user-cards-grid {
  display: flex;
  flex-direction: column;
}

.table-header {
  display: grid;
  grid-template-columns: 2fr 1.5fr 1.5fr 1.5fr 1.2fr 0.8fr;
  gap: 1rem;
  padding: 1rem 1.5rem;
  background: var(--bg-primary);
  border-bottom: 1px solid var(--border-color);
  font-size: 0.75rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: var(--text-muted);
}

.user-card {
  display: grid;
  grid-template-columns: 2fr 1.5fr 1.5fr 1.5fr 1.2fr 0.8fr;
  gap: 1rem;
  padding: 1.25rem 1.5rem;
  border-bottom: 1px solid var(--border-color);
  align-items: center;
  transition: all 0.2s;
}

.user-card:hover {
  background: var(--bg-primary);
}

.user-card:last-child {
  border-bottom: none;
}

/* User Info */
.user-info {
  display: flex;
  align-items: center;
  gap: 0.875rem;
}

.user-avatar {
  width: 48px;
  height: 48px;
  border-radius: 50%;
  overflow: hidden;
  flex-shrink: 0;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  display: flex;
  align-items: center;
  justify-content: center;
}

.user-avatar img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.avatar-placeholder {
  color: white;
  font-weight: 600;
  font-size: 0.875rem;
}

.user-details {
  flex: 1;
  min-width: 0;
}

.user-name {
  font-weight: 600;
  font-size: 0.9375rem;
  color: var(--text-primary);
  margin-bottom: 0.25rem;
}

.user-meta {
  font-size: 0.8125rem;
  color: var(--text-muted);
}

/* Contact Info */
.contact-info {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
}

.contact-email {
  font-size: 0.875rem;
  color: var(--text-primary);
}

.contact-phone {
  font-size: 0.8125rem;
  color: var(--text-muted);
}

/* Demographics */
.demographics-info {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
}

.demo-country {
  font-size: 0.875rem;
  color: var(--text-primary);
}

.demo-date {
  font-size: 0.8125rem;
  color: var(--text-muted);
}

/* Role Info */
.role-info {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.role-title {
  font-size: 0.875rem;
  color: var(--text-primary);
  font-weight: 500;
}

.role-badge {
  display: inline-flex;
  align-items: center;
  padding: 0.25rem 0.625rem;
  border-radius: 0.375rem;
  font-size: 0.6875rem;
  font-weight: 600;
  letter-spacing: 0.025em;
  width: fit-content;
}

.role-admin {
  background: #fef3c7;
  color: #92400e;
}

.dark-theme .role-admin {
  background: rgba(251, 191, 36, 0.15);
  color: #fbbf24;
}

.role-editor {
  background: #dbeafe;
  color: #1e40af;
}

.dark-theme .role-editor {
  background: rgba(59, 130, 246, 0.15);
  color: #60a5fa;
}

.role-viewer {
  background: #f3e8ff;
  color: #6b21a8;
}

.dark-theme .role-viewer {
  background: rgba(168, 85, 247, 0.15);
  color: #a78bfa;
}

.role-user {
  background: #e5e7eb;
  color: #374151;
}

.dark-theme .role-user {
  background: rgba(107, 114, 128, 0.15);
  color: #9ca3af;
}

/* Activity Info */
.activity-info {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.status-badge {
  display: inline-flex;
  align-items: center;
  gap: 0.375rem;
  padding: 0.25rem 0.625rem;
  border-radius: 0.375rem;
  font-size: 0.8125rem;
  font-weight: 500;
  width: fit-content;
}

.status-badge::before {
  content: "";
  width: 6px;
  height: 6px;
  border-radius: 50%;
}

.status-active {
  background: #d1fae5;
  color: #065f46;
}

.status-active::before {
  background: #10b981;
}

.dark-theme .status-active {
  background: rgba(16, 185, 129, 0.15);
  color: #6ee7b7;
}

.status-inactive {
  background: #fee2e2;
  color: #991b1b;
}

.status-inactive::before {
  background: #ef4444;
}

.dark-theme .status-inactive {
  background: rgba(239, 68, 68, 0.15);
  color: #fca5a5;
}

.last-login {
  font-size: 0.8125rem;
  color: var(--text-muted);
}

/* Action Buttons */
.action-buttons {
  display: flex;
  gap: 0.5rem;
}

.btn-icon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 32px;
  height: 32px;
  border-radius: 0.375rem;
  cursor: pointer;
  transition: all 0.2s;
  border: 1px solid var(--border-color);
  background: var(--bg-secondary);
}

.edit-icon {
  color: #3b82f6;
}

.edit-icon:hover {
  background: #eff6ff;
  border-color: #3b82f6;
}

.dark-theme .edit-icon:hover {
  background: rgba(59, 130, 246, 0.1);
}

.delete-icon {
  color: #ef4444;
}

.delete-icon:hover {
  background: #fef2f2;
  border-color: #ef4444;
}

.dark-theme .delete-icon:hover {
  background: rgba(239, 68, 68, 0.1);
}

/* Pagination Footer */
.pagination-footer {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 1.25rem 1.5rem;
  border-top: 1px solid var(--border-color);
  background: var(--bg-primary);
  flex-wrap: wrap;
  gap: 1rem;
}

.pagination-info {
  font-size: 0.875rem;
  color: var(--text-secondary);
}

.pagination-info strong {
  font-weight: 600;
  color: var(--text-primary);
}

.pagination-controls {
  display: flex;
  align-items: center;
  gap: 0.375rem;
}

.page-btn {
  background: var(--bg-secondary);
  color: var(--text-primary);
  border: 1px solid var(--border-color);
  padding: 0.5rem;
  min-width: 36px;
}

.page-btn:hover:not(:disabled) {
  background: var(--bg-primary);
  border-color: var(--border-hover);
}

.page-btn:disabled {
  opacity: 0.4;
  cursor: not-allowed;
}

.page-number {
  background: var(--bg-secondary);
  color: var(--text-primary);
  border: 1px solid var(--border-color);
  padding: 0.5rem 0.75rem;
  min-width: 36px;
  font-size: 0.875rem;
}

.page-number:hover {
  background: var(--bg-primary);
}

.active-page {
  background: #3b82f6;
  color: white;
  border-color: #3b82f6;
}

.active-page:hover {
  background: #2563eb;
  border-color: #2563eb;
}

.pagination-ellipsis {
  color: var(--text-muted);
  padding: 0 0.5rem;
}

/* Statistics Cards */
.stats-section {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
  gap: 1.5rem;
  margin-top: 1.5rem;
}

.stat-card {
  background: var(--bg-card);
  border: 1px solid var(--border-color);
  border-radius: 1rem;
  padding: 1.5rem;
  display: flex;
  gap: 1.25rem;
  box-shadow: var(--shadow-sm);
  transition: all 0.2s;
}

.stat-card:hover {
  box-shadow: var(--shadow-md);
  transform: translateY(-2px);
}

.stat-icon {
  width: 48px;
  height: 48px;
  border-radius: 0.75rem;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.retention-card .stat-icon {
  background: #dbeafe;
  color: #1e40af;
}

.dark-theme .retention-card .stat-icon {
  background: rgba(59, 130, 246, 0.15);
  color: #60a5fa;
}

.active-card .stat-icon {
  background: #d1fae5;
  color: #065f46;
}

.dark-theme .active-card .stat-icon {
  background: rgba(16, 185, 129, 0.15);
  color: #6ee7b7;
}

.signups-card .stat-icon {
  background: #f3e8ff;
  color: #6b21a8;
}

.dark-theme .signups-card .stat-icon {
  background: rgba(168, 85, 247, 0.15);
  color: #a78bfa;
}

.stat-content {
  flex: 1;
}

.stat-label {
  font-size: 0.75rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: var(--text-muted);
  margin-bottom: 0.5rem;
}

.stat-value {
  font-size: 2rem;
  font-weight: 700;
  color: var(--text-primary);
  line-height: 1.2;
  margin-bottom: 0.375rem;
}

.stat-change {
  font-size: 0.8125rem;
  font-weight: 500;
}

.stat-change.positive {
  color: #10b981;
}

.stat-subtitle {
  font-size: 0.8125rem;
  color: var(--text-muted);
}

/* Modal */
.modal-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.6);
  backdrop-filter: blur(4px);
  display: flex;
  justify-content: center;
  align-items: center;
  z-index: 50;
  padding: 1rem;
}

.modal-wrapper {
  background: var(--bg-card);
  border-radius: 1rem;
  width: 100%;
  max-width: 480px;
  box-shadow: var(--shadow-lg);
  border: 1px solid var(--border-color);
  max-height: 90vh;
  overflow-y: auto;
}

.modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 1.5rem;
  border-bottom: 1px solid var(--border-color);
}

.modal-header h2 {
  font-size: 1.25rem;
  font-weight: 600;
  margin: 0;
  color: var(--text-primary);
}

.close-btn {
  width: 32px;
  height: 32px;
  border-radius: 0.375rem;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: all 0.2s;
  background: transparent;
  border: none;
  color: var(--text-muted);
}

.close-btn:hover {
  background: var(--bg-primary);
  color: var(--text-primary);
}

.modal-body {
  padding: 1.5rem;
}

.modal-footer {
  display: flex;
  justify-content: flex-end;
  gap: 0.75rem;
  padding: 1.5rem;
  border-top: 1px solid var(--border-color);
}

/* Form */
.form-group {
  margin-bottom: 1.25rem;
}

.form-group label {
  display: block;
  font-size: 0.875rem;
  font-weight: 500;
  color: var(--text-primary);
  margin-bottom: 0.5rem;
}

.input-field {
  width: 100%;
  padding: 0.625rem 0.875rem;
  border-radius: 0.5rem;
  border: 1px solid var(--border-color);
  background: var(--bg-primary);
  color: var(--text-primary);
  font-size: 0.875rem;
  outline: none;
  transition: all 0.2s;
}

.input-field:focus {
  border-color: #3b82f6;
  box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

.checkbox-group {
  display: flex;
  align-items: center;
  gap: 0.625rem;
}

.checkbox-group input[type="checkbox"] {
  width: 18px;
  height: 18px;
  cursor: pointer;
}

.checkbox-group label {
  margin: 0;
  cursor: pointer;
}

.cancel-btn {
  background: var(--bg-primary);
  color: var(--text-primary);
  border: 1px solid var(--border-color);
}

.cancel-btn:hover {
  background: var(--bg-secondary);
}

.save-btn {
  background: #3b82f6;
  color: white;
}

.save-btn:hover {
  background: #2563eb;
}

/* Messages */
.error-msg {
  background: #fee2e2;
  border: 1px solid #fca5a5;
  padding: 0.875rem;
  border-radius: 0.5rem;
  color: #991b1b;
  margin-top: 1rem;
}

.error-msg ul {
  margin: 0;
  padding-left: 1.25rem;
}

.success-msg {
  background: #d1fae5;
  border: 1px solid #10b981;
  padding: 0.875rem;
  border-radius: 0.5rem;
  color: #065f46;
  margin-top: 1rem;
}

.dark-theme .error-msg {
  background: rgba(239, 68, 68, 0.1);
  border-color: rgba(239, 68, 68, 0.3);
  color: #fca5a5;
}

.dark-theme .success-msg {
  background: rgba(16, 185, 129, 0.1);
  border-color: rgba(16, 185, 129, 0.3);
  color: #6ee7b7;
}

/* Responsive */
@media (max-width: 1400px) {
  .table-header,
  .user-card {
    grid-template-columns: 2fr 1.5fr 1.5fr 1.5fr 1fr 0.8fr;
  }
}

@media (max-width: 1200px) {
  .col-demographics {
    display: none;
  }

  .table-header,
  .user-card {
    grid-template-columns: 2fr 1.5fr 1.5fr 1fr 0.8fr;
  }
}

@media (max-width: 968px) {
  .col-contact {
    display: none;
  }

  .table-header,
  .user-card {
    grid-template-columns: 2fr 1.5fr 1fr 0.8fr;
  }
}

@media (max-width: 768px) {
  .user-directory {
    padding: 1rem;
  }

  .header-section {
    flex-direction: column;
  }

  .header-actions {
    width: 100%;
  }

  .header-actions .btn {
    flex: 1;
  }

  .table-header {
    display: none;
  }

  .user-card {
    grid-template-columns: 1fr;
    gap: 1rem;
    padding: 1.5rem;
  }

  .col-user,
  .col-contact,
  .col-demographics,
  .col-role,
  .col-activity,
  .col-actions {
    display: block !important;
  }

  .action-buttons {
    justify-content: flex-start;
  }

  .stats-section {
    grid-template-columns: 1fr;
  }
}
</style>
