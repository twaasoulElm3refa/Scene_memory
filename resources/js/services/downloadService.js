import api from "./ApiClient";

export default {
  async getDownloads() {
    try {
      const response = await api.get("/users/downloads");
      return response.data;
    } catch (error) {
      console.error("Error fetching downloads:", error);
      throw error;
    }
  },
};
