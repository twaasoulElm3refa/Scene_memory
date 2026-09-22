import api from "./ApiClient";

export function getMonthlyLeaderboardPreview() {
    return api.get("/leaderboard/monthly-preview", {
        baseURL: "/api",
        suppressGlobalErrorToast: true,
    });
}
