import api from "./ApiClient";

export const submitDataDeletionRequest = async ({ reason, confirmation_phrase = "DELETE" }) => {
    const res = await api.post("/users/data-deletion-request", {
        reason,
        confirmation_phrase,
    });
    return res.data;
};

export const confirmDataDeletionRequest = async ({ password, code, confirmation_phrase = "DELETE" }) => {
    const res = await api.post("/users/data-deletion-confirm", {
        password,
        code,
        confirmation_phrase,
    });
    return res.data;
};

export const getDataDeletionStatus = async () => {
    const res = await api.get("/users/data-deletion-status");
    return res.data;
};

export const resendDataDeletionCode = async () => {
    const res = await api.post("/users/data-deletion-resend-code");
    return res.data;
};

export const cancelDataDeletionRequest = async () => {
    const res = await api.post("/users/data-deletion-cancel");
    return res.data;
};

export const getPublicDataDeletionStatus = async (reference) => {
    const res = await api.get(`/data-deletion-status/${encodeURIComponent(reference)}`);
    return res.data;
};
