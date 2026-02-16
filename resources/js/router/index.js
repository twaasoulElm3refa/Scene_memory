import { createRouter, createWebHistory } from "vue-router";
import Home from "../views/home/home.vue";
import Register from "../views/auth/register.vue";
import profile from "../views/home/profile.vue";
import contact from "../views/home/contact.vue";
import GoggleCallback from "../views/auth/GoggleCallback.vue";
import privacy from "../views/home/privacy.vue";
import who from "../views/home/who.vue";
import terms from "../views/home/terms.vue";
import dataProtction from "../views/home/data-protction.vue";
import single_event from "../views/home/single_event.vue";
import all_events from "../views/home/all_events.vue";
import admin from "../views/admin/admin.vue";
import all_users from "../views/admin/users/all_users.vue";
import add_user from "../views/admin/users/add_user.vue";
import all_categories from "../views/admin/categories/all_categories.vue";
import add_categorey from "../views/admin/categories/add_categorey.vue";
const routes = [
    {
        path: "/",
        component: Home,
        meta: { hideNavbar: false, hideFooter: false },
    },

    {
        path: "/all_events",
        component: all_events,
        meta: { hideNavbar: false, hideFooter: false },
    },

    {
        path: "/single_event/:slug",
        component: single_event,
        meta: { hideNavbar: false, hideFooter: false },
    },

    {
        path: "/who",
        component: who,
        meta: { hideNavbar: false, hideFooter: false },
    },

    {
        path: "/terms",
        component: terms,
        meta: { hideNavbar: false, hideFooter: false },
    },

    {
        path: "/privacy-policy",
        component: privacy,
        meta: { hideNavbar: false, hideFooter: false },
    },

    {
        path: "/data-protection",
        component: dataProtction,
        meta: { hideNavbar: false, hideFooter: false },
    },
    {
        path: "/profile",
        component: profile,
    },
    {
        path: "/contact",
        component: contact,
    },
    {
        path: "/auth",
        component: Register,
        meta: { hideNavbar: true, hideFooter: true },
    },
    {
        path: '/v1/users/google-callback',
        name: 'GoogleCallback',
        component: GoggleCallback,
    },

    {
        path: "/admin",
        component: admin,
        meta: { hideNavbar: true, hideFooter: true },
    },

    {
        path: "/admin/users",
        component: all_users,
        meta: { hideNavbar: true, hideFooter: true },
    },

    {
        path: "/admin/users/add",
        component: add_user,
        meta: { hideNavbar: true, hideFooter: true },
    },

    {
        path: "/admin/categories",
        component: all_categories,
        meta: { hideNavbar: true, hideFooter: true },
    },

    {
        path: "/admin/categories/create",
        component: add_categorey,
        meta: { hideNavbar: true, hideFooter: true },
    },

];

const router = createRouter({
    history: createWebHistory(),
    routes,
});

router.beforeEach((to, from, next) => {
    const token = localStorage.getItem("auth_token");
    const role = localStorage.getItem("user_role");

    if (to.path.startsWith("/admin")) {
        if (!token) return next("/auth"); 
        if (role !== "admin") return next("/");
        return next(); 
    }

    if (to.path === "/auth") {
        if (!token) return next(); 
        return role === "admin" ? next("/admin") : next("/");
    }

    next();
});

export default router;
