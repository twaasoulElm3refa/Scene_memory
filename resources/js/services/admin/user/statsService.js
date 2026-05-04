// src/services/statsService.js
import api from "../../ApiClient";

export const statsService = {
  async fetchActiveUsersCount() {
    try {
      const res = await api.get("/users/all/last-login");
      return res.data.status === "success" ? res.data.data : 0;
    } catch (err) {
      console.error("Error fetching active users count:", err);
      return 0;
    }
  },

  async fetchNewSignups() {
    try {
      const res = await api.get("/users/all/new-users");
      if (res.data.status === "success") {
        return {
          newSignups: res.data.data || 0,
          pendingApproval: res.data.pendingApproval || 0,
        };
      }
      return { newSignups: 0, pendingApproval: 0 };
    } catch (err) {
      console.error("Error fetching new users:", err);
      return { newSignups: 0, pendingApproval: 0 };
    }
  },
};
