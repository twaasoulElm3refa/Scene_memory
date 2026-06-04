import api from "@/services/ApiClient";

export const EventService = {
  getCount() {
    return api.get("/events/count");
  },

  getMemories() {
    return api.get("/events/memories");
  },

  getAll(page = 1) {
    return api.get("/events", { params: { page } });
  },

  getHistorical(page = 1) {
    return api.get("/events/historical", { params: { page } });
  },

  getSingleEvent(slug) {
    return api.get(`/events/${slug}/single/get`);
  },

  create(formData) {
    return api.post("/events/create", formData);
  },

  createUser(formData) {
    return api.post("/events/create/user", formData);
  },

  createHistoricUser(formData) {
    return api.post("/events/historic/user", formData);
  },

  deleteEventById(id) {
    return api.delete(`/events/${id}/delete`);
  },

  /**
   * البحث عن الفعاليات باستخدام filters
   */
  async searchEvents(filters = {}) {
    try {
      const cityId = filters.cityId || "all";
      const subCategoryId = filters.subCategoryId || "all";
      const params = {};
      if (filters.fromDate) params.from = filters.fromDate;
      if (filters.toDate) params.to = filters.toDate;
      if (filters.searchQuery?.trim()) params.search = filters.searchQuery.trim();
      const url = `/events/${cityId}/${subCategoryId}`;

      const res = await api.get(url, { params });

      return res.data?.data || res.data || [];
    } catch (err) {
      console.error("❌ Error searching events:", err);

      throw new Error(
        err.response?.data?.message ||
        "حدث خطأ أثناء جلب الفعاليات"
      );
    }
  },

  /**
   * Legacy function
   */
  async searchEventsLegacy(cityId = null, subCategoryId = null, fromDate = null, toDate = null) {
    return this.searchEvents({ cityId, subCategoryId, fromDate, toDate });
  },
};
