import api from "@/services/ApiClient";

export const getCreatorEvents = (params = {}) => {
    return api.get("/creator/all", { params });
};

export const getCreatorEvent = (slug) => {
    return api.get(`/creator/show/${slug}`);
};
