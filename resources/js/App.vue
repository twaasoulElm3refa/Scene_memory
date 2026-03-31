<template>
    <div class="d-flex flex-column min-vh-100">
        <navbar-component v-if="showNavbar" />

        <main class="flex-fill">
            <router-view />
        </main>

        <footer-component v-if="!$route.meta.hideFooter" />
    </div>
</template>

<script>
export default {
    name: "App",

    data() {
        return {
            currentLang: "en",
        };
    },

    computed: {
        showNavbar() {
            return !this.$route.meta.hideNavbar;
        },
    },

    created() {
        this.setupAxios();
    },

    methods: {
        setupAxios() {
            const csrfToken = document.querySelector('meta[name="csrf-token"]');

            if (csrfToken) {
                window.axios.defaults.headers.common["X-CSRF-TOKEN"] = csrfToken.content;
            }

            window.axios.defaults.baseURL = "/api";
            window.axios.defaults.withCredentials = true;

            const lang = (this.$route.params.lang || "en").toLowerCase();
            window.axios.defaults.headers.common["Accept-Language"] = lang;

            if (this.$i18n.locale !== lang) {
                this.$i18n.locale = lang;
            }

            this.setupAuthInterceptor();
        },

        setupAuthInterceptor() {
            window.axios.interceptors.response.use(
                (response) => response,
                (error) => {
                    if (error.response && error.response.status === 401) {
                        const lang = this.$route.params.lang || "en";
                        this.$router.push(`/${lang}/auth`);
                    }
                    return Promise.reject(error);
                }
            );
        },
    },
};
</script>
