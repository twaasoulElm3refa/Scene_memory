import api from "./ApiClient";

export function getWeeklyLeaderboardPreview() {
    return api.get("/leaderboard/weekly-preview", {
        baseURL: "/api",
        suppressGlobalErrorToast: true,
    });
}

export function getWeeklyLeaderboard(page = 1) {
    return api.get("/leaderboard/weekly", {
        baseURL: "/api",
        params: { page, per_page: 25 },
        suppressGlobalErrorToast: true,
    });
}
