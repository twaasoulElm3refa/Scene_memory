import api from "../ApiClient";

export const TagService = {
    async getTags() {
        return api.get("/tags");
    }
}
