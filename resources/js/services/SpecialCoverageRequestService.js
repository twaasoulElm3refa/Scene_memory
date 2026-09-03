import api from "./ApiClient";

export const SpecialCoverageRequestService = {
    create(payload) {
        return api.post("/special-coverage-requests", payload);
    },

    createCity(payload) {
        return api.post("/special-coverage-requests/cities", payload, {
            suppressGlobalErrorToast: true,
        });
    },
};
