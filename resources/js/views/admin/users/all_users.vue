<template>
  <AdminLayout>
    <div :class="themeClass">
      <!-- Users Table -->
      <div class="table-wrapper">
        <table class="users-table">
          <thead :class="theadClass">
            <tr>
              <th>ID</th>
              <th>Name</th>
              <th>Email</th>
              <th>Role</th>
              <th>Last Login</th>
              <th>Active</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="loading">
              <td colspan="7" class="center-text">Loading users...</td>
            </tr>

            <tr v-else-if="users.length === 0">
              <td colspan="7" class="center-text">No users found</td>
            </tr>

            <tr v-else v-for="user in users" :key="user.id" class="table-row">
              <td>{{ user.id }}</td>
              <td>{{ user.name || "-" }}</td>
              <td>{{ user.email }}</td>
              <td>{{ user.role || "user" }}</td>
              <td>{{ user.last_login_at || "-" }}</td>
              <td>{{ user.is_active ? "Yes" : "No" }}</td>
              <td class="actions">
                <button class="btn edit-btn" @click="openEditModal(user)">Edit</button>
                <button class="btn delete-btn" @click="deleteUser(user.id)">
                  Delete
                </button>
              </td>
            </tr>
          </tbody>
        </table>

        <!-- Pagination -->
        <div v-if="pagination.last_page > 1" class="pagination">
          <button
            class="btn page-btn"
            :disabled="pagination.current_page === 1"
            @click="fetchUsers(pagination.current_page - 1)"
          >
            Previous
          </button>

          <button
            v-for="page in pagination.last_page"
            :key="page"
            :class="[
              'btn page-btn',
              page === pagination.current_page ? 'active-page' : '',
            ]"
            @click="fetchUsers(page)"
          >
            {{ page }}
          </button>

          <button
            class="btn page-btn"
            :disabled="pagination.current_page === pagination.last_page"
            @click="fetchUsers(pagination.current_page + 1)"
          >
            Next
          </button>
        </div>
      </div>

      <!-- Edit Modal -->
      <div v-if="editModalOpen" class="modal-overlay">
        <div class="modal-wrapper">
          <h2>Edit User</h2>

          <div class="form-group">
            <label>Name</label>
            <input v-model="editForm.name" type="text" class="input-field" />
          </div>

          <div class="form-group">
            <label>Email</label>
            <input v-model="editForm.email" type="email" class="input-field" />
          </div>

          <div class="form-group checkbox-group">
            <input type="checkbox" v-model="editForm.is_active" id="is_active" />
            <label for="is_active">Active</label>
          </div>

          <div class="modal-actions">
            <button class="btn cancel-btn" @click="closeEditModal">Cancel</button>
            <button class="btn save-btn" @click="submitEdit">Save Changes</button>
          </div>

          <div v-if="editErrors.length" class="error-msg">
            <ul>
              <li v-for="(err, i) in editErrors" :key="i">{{ err }}</li>
            </ul>
          </div>

          <div v-if="editSuccess" class="success-msg">{{ editSuccess }}</div>
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
const pagination = ref({ current_page: 1, last_page: 1, per_page: 20, total: 0 });

// Edit Modal
const editModalOpen = ref(false);
const editForm = reactive({ id: null, name: "", email: "", is_active: false });
const editErrors = ref([]);
const editSuccess = ref("");

// Methods
const fetchUsers = async (page = 1) => {
  loading.value = true;
  try {
    const token = localStorage.getItem("auth_token");
    const res = await axios.get(
      `http://localhost:8000/api/v1/users/all/get?page=${page}`,
      {
        headers: { Authorization: token ? `Bearer ${token}` : "" },
      }
    );
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

const openEditModal = (user) => {
  editForm.id = user.id;
  editForm.name = user.name || "";
  editForm.email = user.email || "";
  editForm.is_active = !!user.is_active;
  editErrors.value = [];
  editSuccess.value = "";
  editModalOpen.value = true;
};

const closeEditModal = () => (editModalOpen.value = false);

const submitEdit = async () => {
  editErrors.value = [];
  editSuccess.value = "";
  try {
    const token = localStorage.getItem("auth_token");
    await axios.post(
      `http://localhost:8000/api/v1/users/${editForm.id}`,
      {
        name: editForm.name,
        email: editForm.email,
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
  if (!confirm("Delete this user permanently?")) return;
  try {
    const token = localStorage.getItem("auth_token");
    await axios.delete(`http://localhost:8000/api/v1/users/${id}`, {
      headers: { Authorization: token ? `Bearer ${token}` : "" },
    });
    users.value = users.value.filter((u) => u.id !== id);
    alert("User deleted successfully");
  } catch (err) {
    alert("Failed to delete user");
  }
};

onMounted(() => fetchUsers());
</script>

<style scoped>
/* Theme */
.light-theme {
  background: #f9fafb;
  color: #111;
}

.dark-theme {
  background: #111;
  color: #f9fafb;
}

/* Page Title */
.page-title {
  font-size: 1.75rem;
  font-weight: bold;
  margin-bottom: 1.5rem;
}

/* Table */
.table-wrapper {
  overflow-x: auto;
  border-radius: 1rem;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
}

.users-table {
  width: 100%;
  border-collapse: separate;
  border-spacing: 0;
}

.users-table th {
  padding: 0.75rem 1rem;
  text-align: left;
  font-weight: 600;
}

.users-table td {
  padding: 0.75rem 1rem;
}

.users-table thead.light-theme {
  background: #e5e7eb;
  color: #111;
}

.users-table thead.dark-theme {
  background: #1f2937;
  color: #f9fafb;
}

.table-row:hover {
  transform: scale(1.01);
  transition: all 0.2s;
}

/* Center Text */
.center-text {
  text-align: center;
  padding: 2rem;
  color: #9ca3af;
}

/* Buttons */
.btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding: 0.5rem 1rem;
  border-radius: 0.5rem;
  font-weight: 500;
  font-size: 0.875rem;
  cursor: pointer;
  transition: all 0.2s;
  transform: scale(1);
  box-shadow: 0 2px 6px rgba(0, 0, 0, 0.1);
}

.btn:hover {
  transform: scale(1.05);
}

.btn:active {
  transform: scale(0.95);
}

.edit-btn {
  background: #3b82f6;
  color: white;
  margin-right: 5px;
}

.edit-btn:hover {
  background: #2563eb;
}

.delete-btn {
  background: #ef4444;
  color: white;
}

.delete-btn:hover {
  background: #b91c1c;
}

.cancel-btn {
  background: #d1d5db;
  color: #111;
}

.cancel-btn:hover {
  background: #9ca3af;
}

.save-btn {
  background: #3b82f6;
  color: white;
}

.save-btn:hover {
  background: #2563eb;
}

.page-btn {
  background: #e5e7eb;
  color: #111;
  border: 1px solid #d1d5db;
  margin: 0 0.2rem;
}

.page-btn:hover {
  background: #d1d5db;
}

.active-page {
  background: #3b82f6;
  color: white;
  border: 1px solid #2563eb;
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
}

.modal-wrapper {
  background: #fff;
  padding: 2rem;
  border-radius: 1.25rem;
  width: 100%;
  max-width: 400px;
  box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
  color: #111;
}

.dark-theme .modal-wrapper {
  background: #1f2937;
  color: #f9fafb;
}

/* Form */
.form-group {
  margin-bottom: 1rem;
  display: flex;
  flex-direction: column;
}

.input-field {
  padding: 0.5rem 1rem;
  border-radius: 0.5rem;
  border: 1px solid #d1d5db;
  outline: none;
  transition: all 0.2s;
}

.input-field:focus {
  border-color: #3b82f6;
  box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.3);
}

.dark-theme .input-field {
  background: #374151;
  border: 1px solid #4b5563;
  color: #f9fafb;
}

.dark-theme .input-field:focus {
  border-color: #3b82f6;
  box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.3);
}

/* Modal Actions */
.modal-actions {
  display: flex;
  justify-content: flex-end;
  gap: 1rem;
  margin-top: 2rem;
}

/* Messages */
.error-msg {
  background: #fee2e2;
  border: 1px solid #fca5a5;
  padding: 1rem;
  border-radius: 0.5rem;
  color: #b91c1c;
}

.success-msg {
  background: #d1fae5;
  border: 1px solid #10b981;
  padding: 1rem;
  border-radius: 0.5rem;
  color: #065f46;
}

.dark-theme .error-msg {
  background: rgba(220, 38, 38, 0.1);
  border: 1px solid rgba(220, 38, 38, 0.5);
  color: #fee2e2;
}

.dark-theme .success-msg {
  background: rgba(5, 150, 105, 0.1);
  border: 1px solid rgba(5, 150, 105, 0.5);
  color: #d1fae5;
}

/* Checkbox */
.checkbox-group {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}
</style>
