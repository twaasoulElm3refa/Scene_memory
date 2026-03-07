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

const routes = [
    {
        path: "/",
        component: Home,
        meta: { hideNavbar: false, hideFooter: false },
    },

    {
        path: "/WishList",
        component: wishlist,
        meta: { hideNavbar: false, hideFooter: false },
    },

    {
        path: "/add_event",
        component: create_event,
        meta: { hideNavbar: false, hideFooter: false },
    },

    {
        path: "/all_events",
        component: all_events,
        meta: { hideNavbar: false, hideFooter: false },
    },

    {
        path: "/single_event/:slug",
        name: "single_event",
        component: single_event,
        meta: { hideNavbar: false, hideFooter: false },
    },

    {
        path: "/single_event/:slug/comments",
        name: "all_comments",
        component: all_comments,
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

    // Auth Routes
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


    // Admin dashboard
    {
        path: "/admin",
        component: admin,
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


    // // Owner dashboard
    // {
    //     path: "/owner",
    //     component: user_home,
    //     meta: { hideNavbar: true, hideFooter: true },
    // },

    // {
    //     path: "/owner/requests",
    //     component: media_requests,
    //     meta: { hideNavbar: true, hideFooter: true },
    // },
    // {
    //     path: "/owner/create",
    //     component: create_event_user,
    //     meta: { hideNavbar: true, hideFooter: true },
    // },
    // {
    //     path: "/owner/:slug",
    //     component: single_event_dashbaord,
    //     meta: { hideNavbar: true, hideFooter: true },
    // },
    // {
    //     path: "/owner/:slug/update",
    //     component: update_event_user,
    //     meta: { hideNavbar: true, hideFooter: true },
    // },
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
