import api from "@/services/ApiClient";

export const EventService = {
  getCount() {
    return api.get("/events/count");
  },

  getMemories() {
    return api.get("/events/memories");
  },

  getTrending() {
    return api.get("/events/trending");
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
      const countryId = filters.countryId || filters.country_id || "all";
      const cityId = filters.cityId || filters.city_id || "all";
      const subCategoryId = filters.subCategoryId || filters.sub_category_id || "all";
      const searchQuery = String(filters.searchQuery || filters.search || filters.q || "").trim();
      const page = Number(filters.page) || 1;
      const perPage = Number(filters.perPage || filters.per_page) || 8;

      const tagsIds = Array.isArray(filters.tagsIds)
        ? filters.tagsIds.filter(Boolean)
        : Array.isArray(filters.tags_id)
          ? filters.tags_id.filter(Boolean)
          : [];

      const params = {
        country_id: countryId || "all",
        city_id: cityId || "all",
        sub_category_id: subCategoryId || "all",
        from: filters.fromDate || filters.from || null,
        to: filters.toDate || filters.to || null,
        q: searchQuery || null,
        page,
        per_page: perPage,
      };

      if (tagsIds.length) {
        params.tags_id = tagsIds;
      }

      const url = `/events/${params.city_id}/${params.sub_category_id}`;

      const res = await api.get(url, {
        params,
        paramsSerializer: {
          indexes: false,
        },
      });

      return res;
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
