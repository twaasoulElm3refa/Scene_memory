<template>
  <AdminLayout>
    <div class="contacts-page">
      <!-- Header -->
      <div class="page-header">
        <div class="header-info">
          <h1 class="page-title">Inquiry Management</h1>
          <p class="page-subtitle">Review and respond to messages from users</p>
        </div>
      </div>

      <!-- Stats Cards -->
      <div class="stats-grid">
        <div class="stat-card">
          <div class="stat-header">
            <span class="stat-label">Total Inquiries</span>
            <div class="stat-icon stat-icon--blue">
              <svg
                width="20"
                height="20"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
                viewBox="0 0 24 24"
              >
                <path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z" />
              </svg>
            </div>
          </div>
          <div class="stat-value">{{ stats.total }}</div>
          <div class="stat-trend stat-trend--up">
            <svg
              width="14"
              height="14"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="2.5"
            >
              <polyline points="23 6 13.5 15.5 8.5 10.5 1 18" />
              <polyline points="17 6 23 6 23 12" />
            </svg>
            +12.5% this month
          </div>
        </div>

        <div class="stat-card">
          <div class="stat-header">
            <span class="stat-label">Unread Messages</span>
            <div class="stat-icon stat-icon--orange">
              <svg
                width="20"
                height="20"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
                viewBox="0 0 24 24"
              >
                <path
                  d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"
                />
                <polyline points="22,6 12,13 2,6" />
              </svg>
            </div>
          </div>
          <div class="stat-value">{{ stats.unread }}</div>
          <div class="stat-trend stat-trend--warn">
            <svg
              width="14"
              height="14"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="2.5"
            >
              <circle cx="12" cy="12" r="10" />
              <line x1="12" y1="8" x2="12" y2="12" />
              <line x1="12" y1="16" x2="12.01" y2="16" />
            </svg>
            Requires attention
          </div>
        </div>

        <div class="stat-card">
          <div class="stat-header">
            <span class="stat-label">Avg. Response Time</span>
            <div class="stat-icon stat-icon--teal">
              <svg
                width="20"
                height="20"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
                viewBox="0 0 24 24"
              >
                <circle cx="12" cy="12" r="10" />
                <polyline points="12 6 12 12 16 14" />
              </svg>
            </div>
          </div>
          <div class="stat-value">{{ stats.avgResponseTime }} hr</div>
          <div class="stat-trend stat-trend--down">
            <svg
              width="14"
              height="14"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="2.5"
            >
              <polyline points="23 18 13.5 8.5 8.5 13.5 1 6" />
              <polyline points="17 18 23 18 23 12" />
            </svg>
            15% faster than avg
          </div>
        </div>
      </div>

      <!-- Table Card -->
      <div class="table-card">
        <!-- Filters -->
        <div class="table-filters">
          <div class="filter-tabs">
            <button
              class="filter-tab"
              :class="{ active: activeFilter === 'all' }"
              @click="setFilter('all')"
            >
              All Inquiries
            </button>
            <button
              class="filter-tab"
              :class="{ active: activeFilter === 'new' }"
              @click="setFilter('new')"
            >
              New
              <span class="badge" v-if="stats.new > 0">{{ stats.new }}</span>
            </button>
            <button
              class="filter-tab"
              :class="{ active: activeFilter === 'replied' }"
              @click="setFilter('replied')"
            >
              Replied
            </button>
          </div>
          <div class="filter-right">
            <div class="sort-by">
              <span class="sort-label">Sort by:</span>
              <select class="sort-select" v-model="sortOrder">
                <option value="newest">Newest First</option>
                <option value="oldest">Oldest First</option>
              </select>
            </div>
          </div>
        </div>

        <!-- Loading State -->
        <div class="loading-overlay" v-if="loading">
          <div class="spinner"></div>
          <span>Loading inquiries...</span>
        </div>

        <!-- Table -->
        <div class="table-wrapper" v-else>
          <table class="contacts-table">
            <thead>
              <tr>
                <th class="th-check">
                  <input type="checkbox" @change="toggleAll" :checked="allSelected" />
                </th>
                <th>CONTACT</th>
                <th>SUBJECT</th>
                <th>STATUS</th>
                <th>DATE</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="contact in filteredContacts"
                :key="contact.id"
                class="table-row"
                :class="{ selected: selectedIds.includes(contact.id) }"
              >
                <td class="td-check">
                  <input type="checkbox" :value="contact.id" v-model="selectedIds" />
                </td>
                <td class="td-contact">
                  <div
                    class="avatar"
                    :style="{ background: getAvatarColor(contact.name) }"
                  >
                    {{ getInitials(contact.name) }}
                  </div>
                  <div class="contact-info">
                    <span class="contact-name">{{ contact.name }}</span>
                    <span class="contact-email">{{ contact.email }}</span>
                  </div>
                </td>
                <td class="td-subject">
                  <span class="subject-text">{{ contact.subject }}</span>
                </td>
                <td class="td-status">
                  <span
                    class="status-badge"
                    :class="
                      getStatus(contact) === 'Replied' ? 'status-replied' : 'status-new'
                    "
                  >
                    <span class="status-dot"></span>
                    {{ getStatus(contact) }}
                  </span>
                </td>
                <td class="td-date">{{ formatDate(contact.created_at) }}</td>
                <td class="td-actions">
                  <div class="action-menu-wrapper">
                    <button class="action-btn" @click.stop="toggleMenu(contact.id)">
                      <svg width="18" height="18" fill="currentColor" viewBox="0 0 24 24">
                        <circle cx="12" cy="5" r="1.5" />
                        <circle cx="12" cy="12" r="1.5" />
                        <circle cx="12" cy="19" r="1.5" />
                      </svg>
                    </button>
                    <div class="action-dropdown" v-if="openMenuId === contact.id">
                      <router-link
                        :to="`/admin/contacts/${contact.id}`"
                        class="dropdown-item"
                      >
                        <svg
                          width="14"
                          height="14"
                          fill="none"
                          stroke="currentColor"
                          stroke-width="2"
                          viewBox="0 0 24 24"
                        >
                          <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" />
                          <circle cx="12" cy="12" r="3" />
                        </svg>
                        View
                      </router-link>

                      <button
                        class="dropdown-item dropdown-item--danger"
                        @click="deleteContact(contact.id)"
                      >
                        <svg
                          width="14"
                          height="14"
                          fill="none"
                          stroke="currentColor"
                          stroke-width="2"
                          viewBox="0 0 24 24"
                        >
                          <polyline points="3 6 5 6 21 6" />
                          <path d="M19 6l-1 14H6L5 6" />
                          <path d="M10 11v6M14 11v6" />
                          <path d="M9 6V4h6v2" />
                        </svg>
                        Delete
                      </button>
                    </div>
                  </div>
                </td>
              </tr>

              <!-- Empty State -->
              <tr v-if="filteredContacts.length === 0">
                <td colspan="6" class="empty-state">
                  <svg
                    width="40"
                    height="40"
                    fill="none"
                    stroke="#ccc"
                    stroke-width="1.5"
                    viewBox="0 0 24 24"
                  >
                    <path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z" />
                  </svg>
                  <p>No inquiries found</p>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Pagination -->
        <div class="table-footer">
          <span class="pagination-info">
            Showing <strong>{{ paginationInfo.from }}</strong> to
            <strong>{{ paginationInfo.to }}</strong> of
            <strong>{{ paginationInfo.total }}</strong> entries
          </span>
          <div class="pagination">
            <button
              class="page-btn"
              :disabled="currentPage === 1"
              @click="changePage(currentPage - 1)"
            >
              <svg
                width="16"
                height="16"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
                viewBox="0 0 24 24"
              >
                <polyline points="15 18 9 12 15 6" />
              </svg>
            </button>
            <button
              v-for="page in totalPages"
              :key="page"
              class="page-btn page-num"
              :class="{ active: page === currentPage }"
              @click="changePage(page)"
            >
              {{ page }}
            </button>
            <button
              class="page-btn"
              :disabled="currentPage === totalPages"
              @click="changePage(currentPage + 1)"
            >
              <svg
                width="16"
                height="16"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
                viewBox="0 0 24 24"
              >
                <polyline points="9 18 15 12 9 6" />
              </svg>
            </button>
          </div>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>

<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from "vue";
import AdminLayout from "../../../layouts/AdminLayout.vue";
import { ContactService } from "../../../services/admin/contacts/contactService";

/* ─── State ─────────────────────────────────────────────────────────── */
const contacts = ref([]);
const apiStats = ref({});
const loading = ref(false);
const currentPage = ref(1);
const totalPages = ref(1);
const totalItems = ref(0);
const perPage = ref(5);
const activeFilter = ref("all");
const sortOrder = ref("newest");
const selectedIds = ref([]);
const openMenuId = ref(null);

/* ─── Computed ───────────────────────────────────────────────────────── */
const stats = computed(() => ({
  total: apiStats.value.total ?? 0,
  unread: apiStats.value.unread ?? 0,
  new: apiStats.value.new ?? 0,
  avgResponseTime: apiStats.value.avg_response_time_hours ?? "—",
}));

const filteredContacts = computed(() => {
  let list = [...contacts.value];

  if (activeFilter.value === "new") {
    list = list.filter((c) => (c.contact_responds ?? []).length === 0);
  } else if (activeFilter.value === "replied") {
    list = list.filter((c) => (c.contact_responds ?? []).length > 0);
  }

  // ترتيب
  if (sortOrder.value === "oldest") {
    list.sort((a, b) => new Date(a.created_at) - new Date(b.created_at));
  } else {
    list.sort((a, b) => new Date(b.created_at) - new Date(a.created_at));
  }

  return list;
});

const allSelected = computed(
  () =>
    filteredContacts.value.length > 0 &&
    filteredContacts.value.every((c) => selectedIds.value.includes(c.id))
);

const paginationInfo = computed(() => {
  const from = contacts.value.length ? (currentPage.value - 1) * perPage.value + 1 : 0;
  const to = from + contacts.value.length - 1;
  return { from, to, total: totalItems.value };
});

/* ─── Methods ────────────────────────────────────────────────────────── */
async function fetchContacts(page = 1) {
  loading.value = true;
  try {
    const res = await ContactService.getAll(page);

    // ─── تصحيح المسارات حسب الـ JSON الفعلي ───────────────────────
    const payload = res.data ?? {};

    contacts.value = payload.data ?? [];
    currentPage.value = payload.current_page ?? 1;
    totalPages.value = payload.last_page ?? 1;
    perPage.value = payload.per_page ?? 5;
    totalItems.value = payload.total ?? 0;

    apiStats.value = payload.stats ?? {};
  } catch (err) {
    console.error("Failed to load contacts:", err);
  } finally {
    loading.value = false;
  }
}

function changePage(page) {
  if (page < 1 || page > totalPages.value) return;
  currentPage.value = page;
  fetchContacts(page);
}

function setFilter(filter) {
  activeFilter.value = filter;
  selectedIds.value = [];
}

function toggleAll(e) {
  if (e.target.checked) {
    selectedIds.value = filteredContacts.value.map((c) => c.id);
  } else {
    selectedIds.value = [];
  }
}

function toggleMenu(id) {
  openMenuId.value = openMenuId.value === id ? null : id;
}

function closeMenu() {
  openMenuId.value = null;
}

async function deleteContact(id) {
  closeMenu();

  if (!confirm("Are you sure you want to delete this contact?")) return;

  try {
    await ContactService.delete(id);

    contacts.value = contacts.value.filter((c) => c.id !== id);
    totalItems.value = Math.max(0, totalItems.value - 1);
    // refresh page
    fetchContacts(currentPage.value);
  } catch (err) {
    console.error("Delete failed:", err);
    alert("Failed to delete contact");
  }
}

/* ─── Helpers ────────────────────────────────────────────────────────── */
function getStatus(contact) {
  return (contact.contact_responds ?? []).length > 0 ? "Replied" : "New";
}

function getInitials(name = "") {
  return name
    .trim()
    .split(/\s+/)
    .slice(0, 2)
    .map((w) => w[0]?.toUpperCase() ?? "")
    .join("");
}

const AVATAR_COLORS = [
  "#4f8ef7",
  "#38b2ac",
  "#ed8936",
  "#9f7aea",
  "#f56565",
  "#48bb78",
  "#ed64a6",
  "#667eea",
];

function getAvatarColor(name = "") {
  if (!name.trim()) return "#9ca3af";
  let hash = 0;
  for (let i = 0; i < name.length; i++) {
    hash = name.charCodeAt(i) + ((hash << 5) - hash);
  }
  return AVATAR_COLORS[Math.abs(hash) % AVATAR_COLORS.length];
}

function formatDate(dateStr) {
  if (!dateStr) return "—";
  try {
    return new Date(dateStr).toLocaleDateString("en-US", {
      month: "short",
      day: "numeric",
      year: "numeric",
    });
  } catch {
    return "—";
  }
}

/* ─── Lifecycle ──────────────────────────────────────────────────────── */
onMounted(() => {
  fetchContacts(1);
  document.addEventListener("click", closeMenu);
});

onBeforeUnmount(() => {
  document.removeEventListener("click", closeMenu);
});
</script>

<style scoped>
/* ── Layout ──────────────────────────────────────────────────────────── */
.contacts-page {
  padding: 32px;
  max-width: 1200px;
  margin: 0 auto;
  font-family: "DM Sans", "Segoe UI", sans-serif;
  color: #1a202c;
}

/* ── Header ──────────────────────────────────────────────────────────── */
.page-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  margin-bottom: 32px;
}

.page-title {
  font-size: 28px;
  font-weight: 800;
  color: #1a202c;
  margin: 0 0 6px;
  letter-spacing: -0.5px;
}

.page-subtitle {
  font-size: 14px;
  color: #718096;
  margin: 0;
}

.header-actions {
  display: flex;
  gap: 12px;
}

.btn-export,
.btn-log {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 10px 20px;
  border-radius: 10px;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.18s ease;
  border: none;
}

.btn-export {
  background: #fff;
  border: 1.5px solid #e2e8f0;
  color: #4a5568;
}

.btn-export:hover {
  background: #f7fafc;
  border-color: #cbd5e0;
}

.btn-log {
  background: #3b82f6;
  color: #fff;
  box-shadow: 0 4px 12px rgba(59, 130, 246, 0.35);
}

.btn-log:hover {
  background: #2563eb;
  box-shadow: 0 6px 16px rgba(59, 130, 246, 0.45);
  transform: translateY(-1px);
}

/* ── Stats Grid ──────────────────────────────────────────────────────── */
.stats-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 20px;
  margin-bottom: 28px;
}

.stat-card {
  background: #fff;
  border-radius: 16px;
  padding: 24px;
  border: 1px solid #e8edf2;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
}

.stat-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 14px;
}

.stat-label {
  font-size: 13px;
  font-weight: 600;
  color: #718096;
  text-transform: uppercase;
  letter-spacing: 0.4px;
}

.stat-icon {
  width: 38px;
  height: 38px;
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
}

.stat-icon--blue {
  background: #ebf4ff;
  color: #3b82f6;
}
.stat-icon--orange {
  background: #fff7ed;
  color: #f97316;
}
.stat-icon--teal {
  background: #e6fffa;
  color: #0d9488;
}

.stat-value {
  font-size: 36px;
  font-weight: 800;
  color: #1a202c;
  line-height: 1;
  margin-bottom: 10px;
  letter-spacing: -1px;
}

.stat-trend {
  display: flex;
  align-items: center;
  gap: 5px;
  font-size: 13px;
  font-weight: 600;
}

.stat-trend--up {
  color: #16a34a;
}
.stat-trend--warn {
  color: #dc2626;
}
.stat-trend--down {
  color: #16a34a;
}

/* ── Table Card ──────────────────────────────────────────────────────── */
.table-card {
  background: #fff;
  border-radius: 16px;
  border: 1px solid #e8edf2;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
  overflow: hidden;
}

/* ── Filters ─────────────────────────────────────────────────────────── */
.table-filters {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 16px 24px;
  border-bottom: 1px solid #f0f4f8;
}

.filter-tabs {
  display: flex;
  align-items: center;
  gap: 4px;
  background: #f7fafc;
  border-radius: 10px;
  padding: 4px;
}

.filter-tab {
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 8px 16px;
  border-radius: 8px;
  border: none;
  background: transparent;
  font-size: 13px;
  font-weight: 600;
  color: #718096;
  cursor: pointer;
  transition: all 0.15s ease;
}

.filter-tab:hover {
  color: #4a5568;
}
.filter-tab.active {
  background: #fff;
  color: #3b82f6;
  box-shadow: 0 1px 4px rgba(0, 0, 0, 0.08);
}

.badge {
  background: #3b82f6;
  color: #fff;
  font-size: 11px;
  font-weight: 700;
  border-radius: 20px;
  padding: 1px 7px;
  min-width: 20px;
  text-align: center;
}

.filter-right {
  display: flex;
  align-items: center;
  gap: 12px;
}

.sort-by {
  display: flex;
  align-items: center;
  gap: 8px;
}
.sort-label {
  font-size: 13px;
  color: #718096;
  font-weight: 500;
}

.sort-select {
  border: 1.5px solid #e2e8f0;
  border-radius: 8px;
  padding: 7px 12px;
  font-size: 13px;
  font-weight: 600;
  color: #4a5568;
  background: #fff;
  cursor: pointer;
  outline: none;
  appearance: none;
  -webkit-appearance: none;
  background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%23718096' stroke-width='2'%3E%3Cpolyline points='6 9 12 15 18 9'/%3E%3C/svg%3E");
  background-repeat: no-repeat;
  background-position: right 10px center;
  padding-right: 30px;
}

/* ── Table ───────────────────────────────────────────────────────────── */
.table-wrapper {
  overflow-x: auto;
}

.contacts-table {
  width: 100%;
  border-collapse: collapse;
}

.contacts-table thead tr {
  border-bottom: 1.5px solid #f0f4f8;
}

.contacts-table th {
  padding: 12px 20px;
  text-align: left;
  font-size: 11px;
  font-weight: 700;
  color: #a0aec0;
  letter-spacing: 0.8px;
  text-transform: uppercase;
}

.th-check {
  width: 48px;
}

.table-row {
  border-bottom: 1px solid #f7fafc;
  transition: background 0.12s ease;
  cursor: pointer;
}

.table-row:hover {
  background: #f9fbfd;
}
.table-row.selected {
  background: #ebf4ff;
}
.table-row:last-child {
  border-bottom: none;
}

td {
  padding: 14px 20px;
  vertical-align: middle;
}

.td-check input[type="checkbox"],
thead input[type="checkbox"] {
  width: 16px;
  height: 16px;
  accent-color: #3b82f6;
  cursor: pointer;
}

/* Contact cell */
.td-contact {
  display: flex;
  align-items: center;
  gap: 12px;
  min-width: 220px;
}

.avatar {
  width: 38px;
  height: 38px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 13px;
  font-weight: 700;
  color: #fff;
  flex-shrink: 0;
}

.contact-info {
  display: flex;
  flex-direction: column;
  gap: 2px;
}
.contact-name {
  font-size: 14px;
  font-weight: 600;
  color: #2d3748;
}
.contact-email {
  font-size: 12px;
  color: #a0aec0;
}

/* Subject */
.td-subject {
  max-width: 260px;
}
.subject-text {
  font-size: 14px;
  color: #4a5568;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  display: block;
}

/* Status */
.status-badge {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 5px 12px;
  border-radius: 20px;
  font-size: 12px;
  font-weight: 700;
}

.status-dot {
  width: 7px;
  height: 7px;
  border-radius: 50%;
}

.status-new {
  background: #ebf8ff;
  color: #2b6cb0;
}
.status-new .status-dot {
  background: #3b82f6;
}

.status-replied {
  background: #f0fff4;
  color: #276749;
}
.status-replied .status-dot {
  background: #38a169;
}

/* Date */
.td-date {
  font-size: 13px;
  color: #a0aec0;
  white-space: nowrap;
}

/* Actions */
.td-actions {
  position: relative;
  width: 48px;
  text-align: center;
}

.action-btn {
  background: none;
  border: none;
  padding: 6px;
  border-radius: 6px;
  cursor: pointer;
  color: #a0aec0;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: background 0.12s;
}

.action-btn:hover {
  background: #f0f4f8;
  color: #4a5568;
}

.action-menu-wrapper {
  position: relative;
  display: flex;
  justify-content: center;
}

.action-dropdown {
  position: absolute;
  right: 0;
  top: calc(100% + 4px);
  background: #fff;
  border: 1px solid #e2e8f0;
  border-radius: 10px;
  box-shadow: 0 8px 24px rgba(0, 0, 0, 0.12);
  min-width: 140px;
  z-index: 100;
  overflow: hidden;
  animation: dropIn 0.12s ease;
}

@keyframes dropIn {
  from {
    opacity: 0;
    transform: translateY(-6px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

.dropdown-item {
  display: flex;
  align-items: center;
  gap: 8px;
  width: 100%;
  padding: 10px 14px;
  border: none;
  background: none;
  font-size: 13px;
  font-weight: 500;
  color: #4a5568;
  cursor: pointer;
  text-align: left;
  transition: background 0.12s;
}

.dropdown-item:hover {
  background: #f7fafc;
}
.dropdown-item--danger {
  color: #e53e3e;
}
.dropdown-item--danger:hover {
  background: #fff5f5;
}

/* Empty State */
.empty-state {
  text-align: center;
  padding: 48px 20px;
  color: #a0aec0;
}

.empty-state svg {
  margin: 0 auto 12px;
  display: block;
}
.empty-state p {
  font-size: 14px;
  font-weight: 500;
}

/* Loading */
.loading-overlay {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 12px;
  padding: 64px 20px;
  color: #a0aec0;
  font-size: 14px;
}

.spinner {
  width: 32px;
  height: 32px;
  border: 3px solid #e2e8f0;
  border-top-color: #3b82f6;
  border-radius: 50%;
  animation: spin 0.7s linear infinite;
}

@keyframes spin {
  to {
    transform: rotate(360deg);
  }
}

/* ── Footer / Pagination ─────────────────────────────────────────────── */
.table-footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 16px 24px;
  border-top: 1px solid #f0f4f8;
}

.pagination-info {
  font-size: 13px;
  color: #718096;
}

.pagination {
  display: flex;
  align-items: center;
  gap: 6px;
}

.page-btn {
  width: 36px;
  height: 36px;
  border-radius: 8px;
  border: 1.5px solid #e2e8f0;
  background: #fff;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  font-size: 13px;
  font-weight: 600;
  color: #4a5568;
  transition: all 0.15s;
}

.page-btn:hover:not(:disabled):not(.active) {
  background: #f7fafc;
  border-color: #cbd5e0;
}

.page-btn.active {
  background: #3b82f6;
  border-color: #3b82f6;
  color: #fff;
  box-shadow: 0 4px 10px rgba(59, 130, 246, 0.35);
}

.page-btn:disabled {
  opacity: 0.35;
  cursor: not-allowed;
}

/* ── Responsive ──────────────────────────────────────────────────────── */
@media (max-width: 768px) {
  .contacts-page {
    padding: 16px;
  }
  .stats-grid {
    grid-template-columns: 1fr;
  }
  .page-header {
    flex-direction: column;
    gap: 16px;
  }
  .header-actions {
    width: 100%;
  }
  .btn-export,
  .btn-log {
    flex: 1;
    justify-content: center;
  }
}
</style>
