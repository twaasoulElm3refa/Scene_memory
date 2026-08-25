import api from "./ApiClient";

export const SpecialCoverageRequestService = {
    create(payload) {
        return api.post("/special-coverage-requests", payload);
    },
};
