import api from "../ApiClient";

export const TagService = {
    async getTags() {
        return api.get("/tags");
    },

    async searchTags({ q = "", limit = 8 } = {}) {
        return api.get("/tags/search", {
            params: {
                q,
                limit,
            },
        });
    },
};
