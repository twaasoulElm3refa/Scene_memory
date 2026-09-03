import api from "../ApiClient";

export const profileTimeline = {

    async getTimeline(params = {}) {

        const response = await api.get("/users/timeline", {
            params
        });

        console.log("Timeline Response:", response);

        return response;

    }

};