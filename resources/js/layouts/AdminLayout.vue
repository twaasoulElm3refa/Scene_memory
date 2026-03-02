<template>
    <div class="admin-layout">
        <AdminSidebar />

        <div class="admin-content">
            <AdminHeader />

            <main class="admin-main">
                <slot />
            </main>
        </div>
    </div>
</template>

<script setup>
import AdminHeader from '@/layouts/AdminHeader.vue'
import AdminSidebar from '@/layouts/AdminSidebar.vue'
</script>

<style scoped>
.admin-layout {
    display: flex;
    min-height: 100vh;
    transition: background 0.4s ease;
}

.admin-content {
    flex: 1;
    display: flex;
    flex-direction: column;
}

/* Main content base */
.admin-main {
    padding: 24px;
    min-height: calc(100vh - 60px);
    transition: background 0.4s ease, color 0.4s ease;
}

/* ================= DARK MODE ================= */
[data-theme='dark'] .admin-layout {
    background: #020617;
}

[data-theme='dark'] .admin-main {
    background: radial-gradient(circle at top,
            rgba(30, 41, 59, 0.65),
            rgba(2, 6, 23, 0.95));
    color: #e5e7eb;
}

/* ================= LIGHT MODE ================= */
[data-theme='light'] .admin-layout {
    background: #f1f5f9;
}

[data-theme='light'] .admin-main {
    background: #f8fafc;
    color: #111827;
}
</style>
