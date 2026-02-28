import api from "./ApiClient";

export const CategoryService = {
  async getAllCategories() {
    try {
      const res = await api.get("/categories");
      return res.data.data || [];
    } catch (err) {
      console.error("Error fetching categories:", err);
      return [];
    }
  },
};