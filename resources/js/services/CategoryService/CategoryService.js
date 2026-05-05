import api from "../ApiClient";

export const CategoryService = {
  async getCategories() {
    return api.get("/categories");
  },

  async getCategoryById(id) {
    return api.get(`/categories/${id}`);
  },

  async getSubCategoriesByCategory(id) {
    return api.get(`/categories/${id}/sub_categories/get`);
  },

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
