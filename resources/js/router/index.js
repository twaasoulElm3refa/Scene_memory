import { createRouter, createWebHistory } from "vue-router";

const Home = () => import("../views/home/home.vue");
const Register = () => import("../views/auth/register.vue");
const profile = () => import("../views/home/profile.vue");
const contact = () => import("../views/home/contact.vue");
const GoggleCallback = () => import("../views/auth/GoggleCallback.vue");
const privacy = () => import("../views/home/privacy.vue");
const who = () => import("../views/home/who.vue");
const terms = () => import("../views/home/terms.vue");
const dataProtction = () => import("../views/home/data-protction.vue");
const single_event = () => import("../views/home/single_event.vue");
const all_events = () => import("../views/home/all_events.vue");

const admin = () => import("../views/admin/admin.vue");
const all_users = () => import("../views/admin/users/all_users.vue");
const add_user = () => import("../views/admin/users/add_user.vue");

const all_withdrawls = () => import("../views/admin/withdraw/all_withdrawls.vue");
const single_withdraw = () => import("../views/admin/withdraw/single_withdraw.vue");
const update_withdrwal = () => import("../views/admin/withdraw/update_withdrwal.vue");
const withdrawl_status = () => import("../views/admin/withdraw/withdrawl_status.vue");

const all_purchases = () => import("../views/admin/purchase/all_purchases.vue");
const purchases_status = () => import("../views/admin/purchase/purchases_status.vue");
const purchases_type = () => import("../views/admin/purchase/purchases_type.vue");
const show_purchase = () => import("../views/admin/purchase/show_purchase.vue");
const edit_purchase = () => import("../views/admin/purchase/edit_purchase.vue");

const all_categories = () => import("../views/admin/categories/all_categories.vue");
const add_categorey = () => import("../views/admin/categories/add_categorey.vue");
const show_categorey = () => import("../views/admin/categories/show_categorey.vue");
const add_sub_categorey = () => import("../views/admin/categories/add_sub_categorey.vue");

const add_country = () => import("../views/admin/countries/add_country.vue");
const all_countries = () => import("../views/admin/countries/all_countries.vue");
const show_country = () => import("../views/admin/countries/show_country.vue");

const add_city = () => import("../views/admin/cities/add_city.vue");
const all_cities = () => import("../views/admin/cities/all_cities.vue");
const show_city = () => import("../views/admin/cities/show_city.vue");

const all_events_admin = () => import("../views/admin/events/all_events_admin.vue");
const add_event = () => import("../views/admin/events/add_event.vue");
const show_event = () => import("../views/admin/events/show_event.vue");
const edit_event = () => import("../views/admin/events/edit_event.vue");

const contacts_admin = () => import("../views/admin/settings/contacts_admin.vue");
const footer = () => import("../views/admin/settings/footer.vue");
const newsletter = () => import("../views/admin/settings/newsletter.vue");
const show_contact = () => import("../views/admin/settings/show_contact.vue");

const create_event = () => import("../views/home/create_event.vue");
const requests = () => import("../views/admin/requests/requests.vue");
const show_request = () => import("../views/admin/requests/show_request.vue");
const wishlist = () => import("../views/home/wishlist.vue");
const MediaUploadRequest = () => import("../views/admin/requests/MediaUploadRequest.vue");
const all_comments = () => import("../views/home/all_comments.vue");
const all_reports = () => import("../views/admin/reports/all_reports.vue");

const historical = () => import("../views/home/historical.vue");
const create_historical = () => import("../views/home/create_historical.vue");

const all_plans = () => import("../views/admin/Licenses/all_plans.vue");
const create_plan = () => import("../views/admin/Licenses/create_plan.vue");
const single_plan = () => import("../views/admin/Licenses/single_plan.vue");
const show_plan = () => import("../views/home/show_plan.vue");
const plans = () => import("../views/home/plans.vue");

const cart = () => import("../views/home/cart.vue");
const downloads = () => import("../views/home/download.vue");
const gate = () => import("../views/home/gate.vue");
const country_data = () => import("../views/home/country_data.vue");

const success = () => import("../views/home/success.vue");
const successDeposit = () => import("../views/home/successDeposit.vue");
const failed = () => import("../views/home/failed.vue");
const failedDeposit = () => import("../views/home/failedDeposit.vue");
const waiting = () => import("../views/home/Waiting.vue");
const waitingDeposit = () => import("../views/home/WaitingDeposit.vue");
const WalletDeposit = () => import("../views/home/WalletDeposit.vue");

const CreatorLayout = () => import("../layouts/creator/CreatorLayout.vue");
const CreatorEvents = () => import("../views/creator/CreatorEvents.vue");
const CreatorEventShow = () => import("../views/creator/CreatorEventShow.vue");
const CreatorWithdrawals = () => import("../views/creator/CreatorWithdrawals.vue");
const CreatorWithdrawalShow = () => import("../views/creator/CreatorWithdrawalShow.vue");
const CreatorWithdrawalRequest = () => import("../views/creator/CreatorWithdrawalRequest.vue");
const CreatorWithdrawalUpdate = () => import("../views/creator/CreatorWithdrawalUpdate.vue");
const AdminLogin = () => import("../views/admin/auth/login.vue");

const getCurrentLang = () => localStorage.getItem("lang") || "en";

const all_tags = () => import("../views/admin/tags/all_tags.vue");

const routes = [
    {
        path: "/",
        redirect: () => {
            return `/${getCurrentLang()}/home`;
        },
    },
    {
        path: "/admin/login",
        name: "admin-login",
        component: AdminLogin,
        meta: { hideNavbar: true, hideFooter: true },
    },
    {
        path: "/:lang/home",
        component: Home,
        meta: { hideNavbar: false, hideFooter: false },
    },
    {
        path: "/:lang/creator",
        component: CreatorLayout,
        meta: { hideNavbar: true, hideFooter: true },
        children: [
            {
                path: "",
                redirect: { name: "creator-events" },
            },
            {
                path: "events",
                name: "creator-events",
                component: CreatorEvents,
            },
            {
                path: "events/:slug",
                name: "creator-event-show",
                component: CreatorEventShow,
                props: true,
            },
            {
                path: "wallet",
                name: "creator-wallet",
                component: WalletDeposit,
            },
            {
                path: "withdrawals",
                name: "creator-withdrawals",
                component: CreatorWithdrawals,
            },
            {
                path: "withdrawals/request",
                name: "creator-withdrawals-request",
                component: CreatorWithdrawalRequest,
            },
            {
                path: "withdrawals/:id",
                name: "creator-withdrawals-show",
                component: CreatorWithdrawalShow,
                props: true,
            },
            {
                path: "withdrawals/:id/edit",
                name: "creator-withdrawals-edit",
                component: CreatorWithdrawalUpdate,
                props: true,
            },
        ],
    },
    {
        path: "/:lang/waiting",
        component: waiting,
        meta: { hideNavbar: false, hideFooter: false },
    },
    {
        path: "/:lang/Deposit/waiting",
        component: waitingDeposit,
        meta: { hideNavbar: false, hideFooter: false },
    },
    {
        path: "/:lang/Deposit",
        component: WalletDeposit,
        meta: { hideNavbar: false, hideFooter: false },
    },
    {
        path: "/:lang/success",
        component: success,
        meta: { hideNavbar: false, hideFooter: false },
    },
    {
        path: "/:lang/failed",
        component: failed,
        meta: { hideNavbar: false, hideFooter: false },
    },
    {
        path: "/:lang/deposit/success",
        component: successDeposit,
        meta: { hideNavbar: false, hideFooter: false },
    },
    {
        path: "/:lang/deposit/failed",
        component: failedDeposit,
        meta: { hideNavbar: false, hideFooter: false },
    },
    {
        path: "/:lang/downloads",
        component: downloads,
        meta: { hideNavbar: false, hideFooter: false },
    },
    {
        path: "/:lang/scemory-gate",
        component: gate,
        meta: { hideNavbar: false, hideFooter: false },
    },
    {
        path: "/:lang/:code/scemory-gate",
        component: country_data,
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
        meta: { hideNavbar: false, hideFooter: false },
    },
    {
        path: "/:lang/contact",
        component: contact,
        meta: { hideNavbar: false, hideFooter: false },
    },

    // Auth Routes
    {
        path: "/:lang/auth",
        name: "auth",
        component: Register,
        meta: { hideNavbar: true, hideFooter: true },
    },
    {
        path: "/:lang/auth/google-callback",
        name: "google-callback",
        component: GoggleCallback,
        meta: { hideNavbar: true, hideFooter: true },
    },

    // Admin dashboard
    {
        path: "/admin",
        component: admin,
        meta: { hideNavbar: true, hideFooter: true },
    },

    // Withdrawls
    {
        path: "/admin/purchases/withdrawls",
        component: all_withdrawls,
        meta: { hideNavbar: true, hideFooter: true },
    },
    {
        path: "/admin/purchases/withdrawls/:id",
        component: single_withdraw,
        meta: { hideNavbar: true, hideFooter: true },
    },
    {
        path: "/admin/purchases/withdrawls/:id/edit",
        component: update_withdrwal,
        meta: { hideNavbar: true, hideFooter: true },
    },
    {
        path: "/admin/purchases/withdrawls/:status/status",
        component: withdrawl_status,
        meta: { hideNavbar: true, hideFooter: true },
    },

    // Purchases
    {
        path: "/admin/purchases",
        name: "admin-purchases",
        component: all_purchases,
        meta: { hideNavbar: true, hideFooter: true },
    },
    {
        path: "/admin/purchases/edit/:id",
        component: edit_purchase,
        meta: { hideNavbar: true, hideFooter: true },
    },
    {
        path: "/admin/purchases/:type/type",
        component: purchases_type,
        meta: { hideNavbar: true, hideFooter: true },
    },
    {
        path: "/admin/purchases/:status/status",
        component: purchases_status,
        meta: { hideNavbar: true, hideFooter: true },
    },
    {
        path: "/admin/purchases/:id",
        component: show_purchase,
        meta: { hideNavbar: true, hideFooter: true },
    },

    // Reports
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

    // Requests
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

    // Settings
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

    // Users
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

    // Categories
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
    {
        path: "/admin/categories/:id/add",
        component: add_sub_categorey,
        meta: { hideNavbar: true, hideFooter: true },
    },
    {
        path: "/admin/categories/:id",
        component: show_categorey,
        meta: { hideNavbar: true, hideFooter: true },
    },

    // Plans
    {
        path: "/admin/plans",
        component: all_plans,
        meta: { hideNavbar: true, hideFooter: true },
    },
    {
        path: "/admin/plans/create",
        component: create_plan,
        meta: { hideNavbar: true, hideFooter: true },
    },
    {
        path: "/admin/plans/:id",
        component: single_plan,
        meta: { hideNavbar: true, hideFooter: true },
    },

    // Countries
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
        path: "/admin/countries/:id/create",
        component: add_country,
        meta: { hideNavbar: true, hideFooter: true },
    },
    {
        path: "/admin/countries/:id",
        component: show_country,
        meta: { hideNavbar: true, hideFooter: true },
    },

    // Cities
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

    // Events
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
        path: "/admin/events/:id/edit",
        component: edit_event,
        meta: { hideNavbar: true, hideFooter: true },
    },
    {
        path: "/admin/events/:id",
        component: show_event,
        meta: { hideNavbar: true, hideFooter: true },
    },

    // Tags

    {
        path: "/admin/tags",
        component: all_tags,
        meta: { hideNavbar: true, hideFooter: true },
    },
];

const router = createRouter({
    history: createWebHistory(),
    routes,
});

router.beforeEach((to, from, next) => {
    const authToken = localStorage.getItem("auth_token");
    const adminToken = localStorage.getItem("admin_token");
    const token = authToken || adminToken;
    const role = localStorage.getItem("user_role");
    const adminUser = localStorage.getItem("admin_user");
    const adminRole = (() => {
        try {
            return adminUser ? JSON.parse(adminUser)?.role : null;
        } catch (error) {
            return null;
        }
    })();
    const lang = localStorage.getItem("lang") || "en";
    const isAdminRoute = to.path.startsWith("/admin");
    const isAdminLoginRoute = to.path === "/admin/login" || to.name === "admin-login";
    const isAdmin = role === "admin" || adminRole === "admin";

    if (isAdminLoginRoute) {
        if (adminToken && isAdmin) {
            return next("/admin");
        }

        return next();
    }

    if (isAdminRoute) {
        if (!token) {
            return next("/admin/login");
        }

        if (isAdmin) {
            return next();
        }

        return next(`/${lang}/home`);
    }

    if (to.name === "auth") {
        if (!token) {
            return next();
        }

        if (role === "admin") {
            return next("/admin");
        }

        return next(`/${lang}/home`);
    }

    return next();
});

export default router;
