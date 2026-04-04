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
import show_categorey from "../views/admin/categories/show_categorey.vue";
import add_sub_categorey from "../views/admin/categories/add_sub_categorey.vue";
import add_country from "../views/admin/countries/add_country.vue";
import all_countries from "../views/admin/countries/all_countries.vue";
import show_country from "../views/admin/countries/show_country.vue";
import add_city from "../views/admin/cities/add_city.vue";
import all_cities from "../views/admin/cities/all_cities.vue";
import show_city from "../views/admin/cities/show_city.vue";
import all_events_admin from "../views/admin/events/all_events_admin.vue";
import add_event from "../views/admin/events/add_event.vue";
import show_event from "../views/admin/events/show_event.vue";
import contacts_admin from "../views/admin/settings/contacts_admin.vue";
import footer from "../views/admin/settings/footer.vue";
import newsletter from "../views/admin/settings/newsletter.vue";
import edit_event from "../views/admin/events/edit_event.vue";
import show_contact from "../views/admin/settings/show_contact.vue";
import create_event from "../views/home/create_event.vue";
import requests from "../views/admin/requests/requests.vue";
import show_request from "../views/admin/requests/show_request.vue";
import wishlist from "../views/home/wishlist.vue";
import MediaUploadRequest from "../views/admin/requests/MediaUploadRequest.vue";
import all_comments from "../views/home/all_comments.vue";
import all_reports from "../views/admin/reports/all_reports.vue";
import historical from "../views/home/historical.vue";
import create_historical from "../views/home/create_historical.vue";
import all_plans from "../views/admin/Licenses/all_plans.vue";
import create_plan from "../views/admin/Licenses/create_plan.vue";
import single_plan from "../views/admin/Licenses/single_plan.vue";
import show_plan from "../views/home/show_plan.vue";
import plans from "../views/home/plans.vue";
import cart from "../views/home/cart.vue";
import downloads from "../views/home/download.vue"

const routes = [
    {
        path: "/",
        redirect: () => {
            const lang = localStorage.getItem("lang") || "en";
            return `/${lang}/home`;
        },
    },
    {
        path: "/:lang/home",
        component: Home,
        meta: { hideNavbar: false, hideFooter: false },
    },

    {
        path: "/:lang/downloads",
        component: downloads,
        meta: { hideNavbar: false, hideFooter: false },
    },

    {
        path: "/:lang/plans",
        component: plans,
        meta: { hideNavbar: false, hideFooter: false },
    },
    {
        path: "/:lang/cart",
        component: cart,
        meta: { hideNavbar: false, hideFooter: false },
    },
    {
        path: "/:lang/plan/:slug",
        component: show_plan,
        meta: { hideNavbar: false, hideFooter: false },
    },

    {
        path: "/:lang/historical",
        component: historical,
        meta: { hideNavbar: false, hideFooter: false },
    },

    {
        path: "/:lang/WishList",
        component: wishlist,
        meta: { hideNavbar: false, hideFooter: false },
    },

    {
        path: "/:lang/add_event",
        component: create_event,
        meta: { hideNavbar: false, hideFooter: false },
    },

    {
        path: "/:lang/add_event/historical",
        component: create_historical,
        meta: { hideNavbar: false, hideFooter: false },
    },

    {
        path: "/:lang/all_events",
        component: all_events,
        meta: { hideNavbar: false, hideFooter: false },
    },

    {
        path: "/:lang/single_event/:slug",
        name: "single_event",
        component: single_event,
        meta: { hideNavbar: false, hideFooter: false },
    },

    {
        path: "/:lang/single_event/:slug/comments",
        name: "all_comments",
        component: all_comments,
        meta: { hideNavbar: false, hideFooter: false },
    },

    {
        path: "/:lang/who",
        component: who,
        meta: { hideNavbar: false, hideFooter: false },
    },

    {
        path: "/:lang/terms",
        component: terms,
        meta: { hideNavbar: false, hideFooter: false },
    },

    {
        path: "/:lang/privacy-policy",
        component: privacy,
        meta: { hideNavbar: false, hideFooter: false },
    },

    {
        path: "/:lang/data-protection",
        component: dataProtction,
        meta: { hideNavbar: false, hideFooter: false },
    },
    {
        path: "/:lang/profile",
        component: profile,
    },
    {
        path: "/:lang/contact",
        component: contact,
    },

    // Auth Routes
    {
        path: "/:lang/auth",
        component: Register,
        meta: { hideNavbar: true, hideFooter: true },
    },
    {
        path: '/v1/users/google-callback',
        name: 'GoogleCallback',
        component: GoggleCallback,
    },


    // Admin dashboard
    {
        path: "/admin",
        component: admin,
        meta: { hideNavbar: true, hideFooter: true },
    },
    {
        path: "/admin/reports",
        component: all_reports,
        meta: { hideNavbar: true, hideFooter: true },
    },
    {
        path: "/admin/media",
        component: MediaUploadRequest,
        meta: { hideNavbar: true, hideFooter: true },
    },
    {
        path: "/admin/requests",
        component: requests,
        meta: { hideNavbar: true, hideFooter: true },
    },
    {
        path: "/admin/requests/:id",
        component: show_request,
        meta: { hideNavbar: true, hideFooter: true },
    },
    {
        path: "/admin/contacts",
        component: contacts_admin,
        meta: { hideNavbar: true, hideFooter: true },
    },

    {
        path: "/admin/contacts/:id",
        component: show_contact,
        meta: { hideNavbar: true, hideFooter: true },
    },
    {
        path: "/admin/footer",
        component: footer,
        meta: { hideNavbar: true, hideFooter: true },
    },
    {
        path: "/admin/newsletters",
        component: newsletter,
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
        path: "/admin/categories/:id",
        component: show_categorey,
        meta: { hideNavbar: true, hideFooter: true },
    },

    {
        path: "/admin/categories/:id/add",
        component: add_sub_categorey,
        meta: { hideNavbar: true, hideFooter: true },
    },

    {
        path: "/admin/categories/create",
        component: add_categorey,
        meta: { hideNavbar: true, hideFooter: true },
    },

    {
        path: "/admin/plans",
        component: all_plans,
        meta: { hideNavbar: true, hideFooter: true },
    },

    {
        path: "/admin/plans/:id",
        component: single_plan,
        meta: { hideNavbar: true, hideFooter: true },
    },
    {
        path: "/admin/plans/create",
        component: create_plan,
        meta: { hideNavbar: true, hideFooter: true },
    },

    {
        path: "/admin/countries",
        component: all_countries,
        meta: { hideNavbar: true, hideFooter: true },
    },
    {
        path: "/admin/countries/create",
        component: add_country,
        meta: { hideNavbar: true, hideFooter: true },
    },
    {
        path: "/admin/countries/:id",
        component: show_country,
        meta: { hideNavbar: true, hideFooter: true },
    },
    {
        path: "/admin/countries/:id/create",
        component: add_country,
        meta: { hideNavbar: true, hideFooter: true },
    },

    {
        path: "/admin/cities",
        component: all_cities,
        meta: { hideNavbar: true, hideFooter: true },
    },

    {
        path: "/admin/cities/create",
        component: add_city,
        meta: { hideNavbar: true, hideFooter: true },
    },

    {
        path: "/admin/cities/:id",
        component: show_city,
        meta: { hideNavbar: true, hideFooter: true },
    },

    {
        path: "/admin/events",
        component: all_events_admin,
        meta: { hideNavbar: true, hideFooter: true },
    },
    {
        path: "/admin/events/create",
        component: add_event,
        meta: { hideNavbar: true, hideFooter: true },
    },

    {
        path: "/admin/events/:id",
        component: show_event,
        meta: { hideNavbar: true, hideFooter: true },
    },

    {
        path: "/admin/events/:id/edit",
        component: edit_event,
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
        if (role === "admin") return next();
        return next("/");
    }

    if (to.path === "/auth") {
        if (!token) return next();
        if (role === "admin") return next("/admin");
        return next("/");
    }

    next();
});


export default router;
