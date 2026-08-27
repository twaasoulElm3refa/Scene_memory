<template>
    <div class="d-flex flex-column min-vh-100">
        <!-- Navbar -->
        <transition name="layout-fade">
            <navbar-component
                v-if="layoutReady && showNavbar"
            />
        </transition>

        <!-- Page Content -->
        <main class="flex-fill">
            <router-view />
        </main>

        <!-- Footer -->
        <transition name="layout-fade">
            <footer-component
                v-if="layoutReady && !$route.meta.hideFooter"
            />
        </transition>
    </div>
</template>

<script>
export default {
    name: "App",

    data() {
        return {
            currentLang: "en",
            layoutReady: false,
            layoutTimer: null,
        };
    },

    computed: {
        showNavbar() {
            return !this.$route.meta.hideNavbar;
        },
    },

    watch: {
        /*
         * Watch language in URL.
         *
         * Example:
         * /en/home
         * /ar/home
         */
        "$route.params.lang": {
            immediate: true,

            handler(newLang) {
                this.applyLanguage(newLang);
            },
        },
    },

    created() {
        this.setupAxios();
    },

    mounted() {
        this.layoutTimer = setTimeout(() => {
            this.layoutReady = true;
        }, 1000);
    },

    beforeUnmount() {
        if (this.layoutTimer) {
            clearTimeout(this.layoutTimer);
            this.layoutTimer = null;
        }
    },

    methods: {
        /*
         * =====================================================
         * APPLY LANGUAGE
         * =====================================================
         *
         * Important:
         * This ONLY defines language + direction.
         *
         * It does NOT:
         * - mirror the page
         * - reverse flex layouts
         * - use scaleX(-1)
         * - override page-specific RTL
         */
        applyLanguage(langValue) {
            const lang = String(
                langValue ||
                this.$route.params.lang ||
                localStorage.getItem("language") ||
                "en"
            ).toLowerCase();

            this.currentLang = lang;

            /*
             * Save current language
             */
            localStorage.setItem(
                "language",
                lang
            );

            /*
             * Sync Vue i18n
             */
            if (
                this.$i18n &&
                this.$i18n.locale !== lang
            ) {
                this.$i18n.locale = lang;
            }

            /*
             * Sync Axios language header
             */
            if (
                window.axios
            ) {
                window.axios.defaults.headers.common[
                    "Accept-Language"
                ] = lang;
            }

            /*
             * HTML language
             */
            document.documentElement.setAttribute(
                "lang",
                lang
            );

            /*
             * Direction
             *
             * Arabic = RTL
             * Everything else = LTR
             */
            document.documentElement.setAttribute(
                "dir",
                lang === "ar"
                    ? "rtl"
                    : "ltr"
            );
        },

        /*
         * =====================================================
         * AXIOS SETUP
         * =====================================================
         */
        setupAxios() {
            if (!window.axios) {
                console.warn(
                    "Axios is not available on window."
                );

                return;
            }

            /*
             * CSRF Token
             */
            const csrfToken =
                document.querySelector(
                    'meta[name="csrf-token"]'
                );

            if (csrfToken) {
                window.axios.defaults.headers.common[
                    "X-CSRF-TOKEN"
                ] = csrfToken.content;
            }

            /*
             * API Config
             */
            window.axios.defaults.baseURL =
                "/api";

            window.axios.defaults.withCredentials =
                true;

            /*
             * Current Language
             */
            const lang = String(
                this.$route.params.lang ||
                localStorage.getItem("language") ||
                "en"
            ).toLowerCase();

            window.axios.defaults.headers.common[
                "Accept-Language"
            ] = lang;

            /*
             * Sync Vue i18n
             */
            if (
                this.$i18n &&
                this.$i18n.locale !== lang
            ) {
                this.$i18n.locale =
                    lang;
            }

            /*
             * Setup authentication interceptor
             */
            this.setupAuthInterceptor();
        },

        /*
         * =====================================================
         * AUTH INTERCEPTOR
         * =====================================================
         */
        setupAuthInterceptor() {
            if (!window.axios) {
                return;
            }

            window.axios.interceptors.response.use(
                /*
                 * Successful Response
                 */
                (response) => {
                    return response;
                },

                /*
                 * Error Response
                 */
                (error) => {
                    if (
                        error.response &&
                        error.response.status === 401
                    ) {
                        const lang = String(
                            this.$route.params.lang ||
                            localStorage.getItem("language") ||
                            "en"
                        ).toLowerCase();

                        /*
                         * Avoid redirect loop
                         */
                        if (
                            !this.$route.path.includes(
                                "/auth"
                            )
                        ) {
                            this.$router.push(
                                `/${lang}/auth`
                            );
                        }
                    }

                    return Promise.reject(
                        error
                    );
                }
            );
        },
    },
};
</script>

<style scoped>
/* =====================================================
   LAYOUT TRANSITION
===================================================== */

.layout-fade-enter-active,
.layout-fade-leave-active {
    transition:
        opacity 0.4s ease,
        transform 0.4s ease;
}

.layout-fade-enter-from {
    opacity: 0;
    transform: translateY(-8px);
}

.layout-fade-enter-to {
    opacity: 1;
    transform: translateY(0);
}

.layout-fade-leave-from {
    opacity: 1;
    transform: translateY(0);
}

.layout-fade-leave-to {
    opacity: 0;
    transform: translateY(-6px);
}
</style>
