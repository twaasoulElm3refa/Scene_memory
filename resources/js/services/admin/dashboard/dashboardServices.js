import AdminApiClient from "../../AdminApiClient";

export const getAdminDashboardStats = async () => {
    const response = await AdminApiClient.get("/admin/dashboard/stats");
    return response.data;
};

export default {
    getAdminDashboardStats,
};
