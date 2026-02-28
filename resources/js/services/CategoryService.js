import api from "./ApiClient";

export const CategoryService = {
  async getAllCategories() {
    try {
      const res = await api.get("/categories");
      console.log(res.data.data);
      return res.data.data || [];
    } catch (err) {
      console.error("Error fetching categories:", err);
      return [];
    }
  },
};