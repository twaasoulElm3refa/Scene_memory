<template>
    <div class="d-flex flex-column min-vh-100">
        <transition name="layout-fade">
            <navbar-component
                v-if="layoutReady && showNavbar"
            />
        </transition>

        <main class="flex-fill">
            <router-view />
        </main>

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
        }
    },

    methods: {
        applyLanguage(langValue) {
            const lang = String(
                langValue ||
                this.$route.params.lang ||
                localStorage.getItem("language") ||
                "en"
            ).toLowerCase();

            this.currentLang = lang;

            localStorage.setItem(
                "language",
                lang
            );

            if (this.$i18n.locale !== lang) {
                this.$i18n.locale = lang;
            }

            if (window.axios) {
                window.axios.defaults.headers.common[
                    "Accept-Language"
                ] = lang;
            }

            const isArabic =
                lang === "ar";

            document.documentElement.lang =
                lang;

            document.documentElement.dir =
                isArabic
                    ? "rtl"
                    : "ltr";

            document.body.setAttribute(
                "dir",
                isArabic
                    ? "rtl"
                    : "ltr"
            );

            document.body.classList.toggle(
                "is-rtl",
                isArabic
            );

            document.body.classList.toggle(
                "is-ltr",
                !isArabic
            );
        },

        setupAxios() {
            const csrfToken =
                document.querySelector(
                    'meta[name="csrf-token"]'
                );

            if (csrfToken) {
                window.axios.defaults.headers.common[
                    "X-CSRF-TOKEN"
                ] = csrfToken.content;
            }

            window.axios.defaults.baseURL =
                "/api";

            window.axios.defaults.withCredentials =
                true;

            const lang = (
                this.$route.params.lang ||
                localStorage.getItem("language") ||
                "en"
            ).toLowerCase();

            window.axios.defaults.headers.common[
                "Accept-Language"
            ] = lang;

            if (
                this.$i18n.locale !==
                lang
            ) {
                this.$i18n.locale =
                    lang;
            }

            this.setupAuthInterceptor();
        },

        setupAuthInterceptor() {
            window.axios.interceptors.response.use(
                (response) =>
                    response,

                (error) => {
                    if (
                        error.response &&
                        error.response.status ===
                            401
                    ) {
                        const lang =
                            this.$route.params.lang ||
                            "en";

                        this.$router.push(
                            `/${lang}/auth`
                        );
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
.layout-fade-enter-active {
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
</style>
