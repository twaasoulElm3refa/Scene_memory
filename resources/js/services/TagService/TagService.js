import api from "../ApiClient";
import { isMobileRuntime } from "../runtimeUrls";

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

    async generateImageTags(formData) {
        return api.post("/tools/image-tags", formData, isMobileRuntime() ? {} : {
            headers: {
                "Content-Type": "multipart/form-data",
            },
        });
    },
};
