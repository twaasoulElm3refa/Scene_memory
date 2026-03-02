// src/services/statsService.js
import axios from "axios";

const getAuthHeader = () => {
  const token = localStorage.getItem("auth_token");
  return token ? { Authorization: `Bearer ${token}` } : {};
};

export const statsService = {
  async fetchActiveUsersCount() {
    try {
      const res = await axios.get("/v1/users/all/last-login", {
        headers: getAuthHeader(),
      });
      return res.data.status === "success" ? res.data.data : 0;
    } catch (err) {
      console.error("Error fetching active users count:", err);
      return 0;
    }
  },

  async fetchNewSignups() {
    try {
      const res = await axios.get("/v1/users/all/new-users", {
        headers: getAuthHeader(),
      });
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