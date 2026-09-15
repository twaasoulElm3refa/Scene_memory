import api from "../ApiClient";

export const profileTimeline = {

    async getTimeline(params = {}, options = {}) {

        const response = await api.get("/users/timeline", {
            params,
            signal: options.signal,
        });

        return response;

    }

};
