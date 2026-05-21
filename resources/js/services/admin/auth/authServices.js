import AdminApiClient from "../../AdminApiClient";

export const login = async (payload) => {
    const response = await AdminApiClient.post("/admin/login", payload);
    return response.data;
};

export const adminLogin = login;

export default {
    login,
    adminLogin,
};
