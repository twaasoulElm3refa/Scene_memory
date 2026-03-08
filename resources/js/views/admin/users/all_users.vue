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
                    <!-- <button class="btn export-btn">
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
          </button> -->
                    <RouterLink to="/admin/users/add" class="btn create-btn">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none"
                            stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="12" y1="5" x2="12" y2="19"></line>
                            <line x1="5" y1="12" x2="19" y2="12"></line>
                        </svg>
                        Create New User
                    </RouterLink>
                </div>
            </div>

            <!-- Filters Section -->
            <div class="filters-section">
                <div class="search-box">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none"
                        stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="11" cy="11" r="8"></circle>
                        <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                    </svg>
                    <input type="text" placeholder="Filter by name, email, or position..." v-model="searchQuery"
                        class="search-input" @input="currentPage = 1" />
                </div>

                <div class="filter-dropdowns">
                    <select v-model="roleFilter" class="filter-select" @change="currentPage = 1">
                        <option value="">Role: All</option>
                        <option value="admin">Admin</option>
                        <option value="user">User</option>
                    </select>

                    <select v-model="statusFilter" class="filter-select" @change="currentPage = 1">
                        <option value="">Status: All</option>
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>

                    <select v-model="joinedFilter" class="filter-select" @change="currentPage = 1">
                        <option value="">Joined: All Time</option>
                        <option value="7">Last 7 Days</option>
                        <option value="30">Last 30 Days</option>
                        <option value="90">Last 90 Days</option>
                    </select>

                    <button class="btn refresh-btn" @click="fetchUsers(currentPage)">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none"
                            stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="23 4 23 10 17 10"></polyline>
                            <polyline points="1 20 1 14 7 14"></polyline>
                            <path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"></path>
                        </svg>
                    </button>
                </div>
            </div>

            <!-- Users List -->
            <div class="users-list">
                <div v-if="loading" class="loading-state">
                    <div class="spinner"></div>
                    <p>Loading users...</p>
                </div>

                <div v-else-if="filteredUsers.length === 0" class="empty-state">
                    <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none"
                        stroke="currentColor" stroke-width="1.5">
                        <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path>
                        <circle cx="9" cy="7" r="4"></circle>
                        <path d="M22 21v-2a4 4 0 0 0-3-3.87"></path>
                        <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                    </svg>
                    <p>No users found</p>
                </div>

                <div v-else class="user-cards-grid">
                    <div class="table-header">
                        <div class="col-user">USER</div>
                        <div class="col-contact">CONTACT</div>
                        <div class="col-demographics">COUNTRY / BIRTH</div>
                        <div class="col-role">ROLE & POSITION</div>
                        <div class="col-activity">ACTIVITY</div>
                        <div class="col-actions">ACTIONS</div>
                    </div>

                    <div v-for="user in paginatedUsers" :key="user.id" class="user-card">
                        <div class="col-user">
                            <div class="user-info">
                                <div class="user-avatar">
                                    <img v-if="user.avatar" :src="user.avatar" :alt="user.name" />
                                    <div v-else class="avatar-placeholder">
                                        {{ format.getInitials(user.name || user.email) }}
                                    </div>
                                </div>
                                <div class="user-details">
                                    <div class="user-name">{{ user.name || "Unknown User" }}</div>
                                    <div class="user-meta">
                                        Member since {{ format.formatYear(user.created_at) }}
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-contact">
                            <div class="contact-email">{{ user.email }}</div>
                            <div class="contact-phone">{{ user.phone || "— — —" }}</div>
                        </div>

                        <div class="col-demographics">
                            <div class="demo-country">{{ user.country || "N/A" }}</div>
                            <div class="demo-date">
                                {{ user.birth_date || format.formatDate(user.created_at) }}
                            </div>
                        </div>

                        <div class="col-role">
                            <div class="role-title">{{ format.getRoleTitle(user.role) }}</div>
                            <span :class="['role-badge', format.getRoleBadgeClass(user.role)]">
                                {{ (user.role || "user").toUpperCase() }}
                            </span>
                        </div>

                        <div class="col-activity">
                            <span :class="[
                                'status-badge',
                                user.is_active ? 'status-active' : 'status-inactive',
                            ]">
                                {{ user.is_active ? "Active" : "Inactive" }}
                            </span>
                            <div class="last-login">
                                Last login: {{ format.formatLastLogin(user.last_login_at) }}
                            </div>
                        </div>

                        <div class="col-actions">
                            <button class="btn-icon edit-icon" @click="openEditModal(user)" title="Edit">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"
                                    fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                                    <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                                </svg>
                            </button>
                            <button class="btn-icon delete-icon" @click="handleDeleteUser(user.id)" title="Delete">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"
                                    fill="none" stroke="currentColor" stroke-width="2">
                                    <polyline points="3 6 5 6 21 6"></polyline>
                                    <path
                                        d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2">
                                    </path>
                                    <line x1="10" y1="11" x2="10" y2="17"></line>
                                    <line x1="14" y1="11" x2="14" y2="17"></line>
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Pagination -->
                <div v-if="pagination.total > 0" class="pagination-footer">
                    <div class="pagination-info">
                        Showing <strong>{{ paginationStart }}</strong>– <strong>{{ paginationEnd }}</strong> of
                        <strong>{{ pagination.total }}</strong> users
                    </div>

                    <div class="pagination-controls">
                        <button class="btn page-btn" :disabled="currentPage === 1" @click="changePage(currentPage - 1)">
                            ← Previous
                        </button>

                        <button v-for="page in visiblePages" :key="page"
                            :class="{ 'active-page': page === currentPage }" class="btn page-number"
                            @click="changePage(page)">
                            {{ page }}
                        </button>

                        <button class="btn page-btn" :disabled="currentPage === pagination.last_page"
                            @click="changePage(currentPage + 1)">
                            Next →
                        </button>
                    </div>
                </div>
            </div>

            <!-- Statistics Cards -->
            <div class="stats-section">
                <div class="stat-card">
                    <div class="stat-icon">👥</div>
                    <div class="stat-content">
                        <div class="stat-label">Active Users Today</div>
                        <div class="stat-value">{{ stats.activeUsers }}</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">📈</div>
                    <div class="stat-content">
                        <div class="stat-label">New Signups</div>
                        <div class="stat-value">{{ stats.newSignups }}</div>
                        <div class="stat-subtitle">Pending: {{ stats.pendingApproval }}</div>
                    </div>
                </div>
            </div>

            <!-- Edit Modal -->
            <div v-if="editModalOpen" class="modal-overlay" @click.self="closeEditModal">
                <div class="modal-content">
                    <h2>Edit User</h2>
                    <div v-if="editErrors.length" class="error-list">
                        <ul>
                            <li v-for="(err, i) in editErrors" :key="i">{{ err }}</li>
                        </ul>
                    </div>
                    <div v-if="editSuccess" class="success-msg">{{ editSuccess }}</div>

                    <div class="form-group">
                        <label>Name</label>
                        <input v-model="editForm.name" type="text" />
                    </div>
                    <div class="form-group">
                        <label>Email</label>
                        <input v-model="editForm.email" type="email" />
                    </div>
                    <div class="form-group">
                        <label>Role</label>
                        <select v-model="editForm.role">
                            <option value="user">User</option>
                            <option value="editor">Editor</option>
                            <option value="admin">Admin</option>
                            <option value="viewer">Viewer</option>
                        </select>
                    </div>
                    <div class="form-group checkbox">
                        <input type="checkbox" v-model="editForm.is_active" id="active" />
                        <label for="active">Account is active</label>
                    </div>

                    <div class="modal-actions">
                        <button class="btn cancel" @click="closeEditModal">Cancel</button>
                        <button class="btn save" @click="submitEdit" :disabled="loading">Save</button>
                    </div>
                </div>
            </div>

            <!-- Create Modal -->
            <div v-if="createModalOpen" class="modal-overlay" @click.self="closeCreateModal">
                <div class="modal-content">
                    <h2>Create New User</h2>
                    <div v-if="createErrors.length" class="error-list">
                        <ul>
                            <li v-for="(err, i) in createErrors" :key="i">{{ err }}</li>
                        </ul>
                    </div>

                    <div class="form-group">
                        <label>Full Name</label>
                        <input v-model="createForm.name" type="text" required />
                    </div>
                    <div class="form-group">
                        <label>Email</label>
                        <input v-model="createForm.email" type="email" required />
                    </div>
                    <div class="form-group">
                        <label>Password</label>
                        <input v-model="createForm.password" type="password" required />
                    </div>
                    <div class="form-group">
                        <label>Role</label>
                        <select v-model="createForm.role">
                            <option value="user">User</option>
                            <option value="editor">Editor</option>
                            <option value="admin">Admin</option>
                            <option value="viewer">Viewer</option>
                        </select>
                    </div>

                    <div class="modal-actions">
                        <button class="btn cancel" @click="closeCreateModal">Cancel</button>
                        <button class="btn save" @click="submitCreate" :disabled="loading">
                            Create User
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from "vue";
import AdminLayout from "@/layouts/AdminLayout.vue";
import { userService } from "@/services/admin/user/userService";
import { statsService } from "@/services/admin/user/statsService";
import { userFormatService as format } from "@/services/admin/user/userFormatService";

const themeClass = computed(() => {
    return localStorage.getItem("theme") === "dark" ? "dark-theme" : "light-theme";
});

const users = ref([]);
const loading = ref(false);

const pagination = ref({
    current_page: 1,
    last_page: 1,
    per_page: 10,
    total: 0,
});

const currentPage = computed({
    get: () => pagination.value.current_page,
    set: (val) => {
        pagination.value.current_page = val;
        fetchUsers(val);
    },
});

const stats = ref({
    activeUsers: 0,
    newSignups: 0,
    pendingApproval: 0,
});

const searchQuery = ref("");
const roleFilter = ref("");
const statusFilter = ref("");
const joinedFilter = ref("");

// Edit modal
const editModalOpen = ref(false);
const editForm = reactive({
    id: null,
    name: "",
    email: "",
    role: "user",
    is_active: true,
});
const editErrors = ref([]);
const editSuccess = ref("");

// Create modal
const createModalOpen = ref(false);
const createForm = reactive({
    name: "",
    email: "",
    password: "",
    role: "user",
});
const createErrors = ref([]);

const filteredUsers = computed(() => {
    let list = [...users.value];

    if (searchQuery.value.trim()) {
        const q = searchQuery.value.toLowerCase().trim();
        list = list.filter(
            (u) =>
                (u.name && u.name.toLowerCase().includes(q)) ||
                (u.email && u.email.toLowerCase().includes(q))
        );
    }

    if (roleFilter.value) {
        list = list.filter((u) => (u.role || "user") === roleFilter.value);
    }

    if (statusFilter.value) {
        const active = statusFilter.value === "active";
        list = list.filter((u) => !!u.is_active === active);
    }

    // joinedFilter logic can be added here if backend supports it

    return list;
});

const paginatedUsers = computed(() => {
    const start = (pagination.value.current_page - 1) * pagination.value.per_page;
    return filteredUsers.value.slice(start, start + pagination.value.per_page);
});

const paginationStart = computed(() => {
    return (pagination.value.current_page - 1) * pagination.value.per_page + 1;
});

const paginationEnd = computed(() => {
    const end = pagination.value.current_page * pagination.value.per_page;
    return Math.min(end, filteredUsers.value.length);
});

const visiblePages = computed(() => {
    const current = pagination.value.current_page;
    const total = pagination.value.last_page;
    const pages = [];

    if (total <= 7) {
        for (let i = 1; i <= total; i++) pages.push(i);
    } else {
        if (current <= 4) {
            for (let i = 1; i <= 5; i++) pages.push(i);
            pages.push("...");
            pages.push(total);
        } else if (current >= total - 3) {
            pages.push(1);
            pages.push("...");
            for (let i = total - 4; i <= total; i++) pages.push(i);
        } else {
            pages.push(1);
            pages.push("...");
            for (let i = current - 1; i <= current + 1; i++) pages.push(i);
            pages.push("...");
            pages.push(total);
        }
    }
    return pages.filter((p) => p !== "...");
});

const changePage = (page) => {
    if (page < 1 || page > pagination.value.last_page) return;
    currentPage.value = page;
};

const fetchUsers = async (page = 1) => {
    loading.value = true;
    try {
        const result = await userService.fetchUsers(page);
        if (result.success) {
            users.value = result.data.data || [];
            pagination.value = {
                current_page: result.data.current_page,
                last_page: result.data.last_page,
                per_page: result.data.per_page,
                total: result.data.total,
            };
        } else {
            alert(result.error?.message || "Failed to load users");
        }
    } catch (e) {
        console.error(e);
        alert("Network or server error");
    } finally {
        loading.value = false;
    }
};

const fetchStats = async () => {
    stats.value.activeUsers = await statsService.fetchActiveUsersCount();
    const newData = await statsService.fetchNewSignups();
    stats.value.newSignups = newData.newSignups || 0;
    stats.value.pendingApproval = newData.pendingApproval || 0;
};

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
    loading.value = true;

    const result = await userService.updateUser(editForm.id, { ...editForm });

    if (result.success) {
        const idx = users.value.findIndex((u) => u.id === editForm.id);
        if (idx !== -1) {
            Object.assign(users.value[idx], editForm);
        }
        editSuccess.value = "User updated successfully";
        setTimeout(closeEditModal, 1200);
    } else {
        if (result.error.isValidationError) {
            editErrors.value = Object.values(result.error.errors).flat();
        } else {
            editErrors.value = [result.error.message];
        }
    }
    loading.value = false;
};

const openCreateModal = () => {
    createForm.name = "";
    createForm.email = "";
    createForm.password = "";
    createForm.role = "user";
    createErrors.value = [];
    createModalOpen.value = true;
};

const closeCreateModal = () => {
    createModalOpen.value = false;
};

const submitCreate = async () => {
    createErrors.value = [];
    loading.value = true;

    const result = await userService.createUser({ ...createForm });

    if (result.success) {
        users.value.unshift(result.data);
        alert("User created successfully");
        closeCreateModal();
    } else {
        if (result.error.isValidationError) {
            createErrors.value = Object.values(result.error.errors).flat();
        } else {
            createErrors.value = [result.error.message];
        }
    }
    loading.value = false;
};

const handleDeleteUser = async (id) => {
    if (!confirm("Delete this user permanently?")) return;

    loading.value = true;
    const result = await userService.deleteUser(id);

    if (result.success) {
        users.value = users.value.filter((u) => u.id !== id);
        alert("User deleted");
    } else {
        alert(result.error?.message || "Delete failed");
    }
    loading.value = false;
};

onMounted(() => {
    fetchUsers();
    fetchStats();
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
