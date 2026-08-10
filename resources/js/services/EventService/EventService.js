import api from "@/services/ApiClient";
import { normalizeEventSearchFilters } from "@/services/EventService/eventSearchHelpers";

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

  getAll(page = 1, isReal = null) {
    const params = { page };

    if (isReal === true || isReal === 1 || isReal === "1" || isReal === "true" || isReal === "real") {
      params.is_real = 1;
    } else if (isReal === false || isReal === 0 || isReal === "0" || isReal === "false" || isReal === "general") {
      params.is_real = 0;
    }

    return api.get("/events", { params });
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
      const normalizedFilters = normalizeEventSearchFilters(filters, {
        defaultPerPage: filters.perPage || filters.per_page || 8,
      });

      const params = {
        country_id: normalizedFilters.countryId || "all",
        city_id: normalizedFilters.cityId || "all",
        category_id: normalizedFilters.categoryId || "all",
        sub_category_id: normalizedFilters.subCategoryId || "all",
        from: normalizedFilters.fromDate || null,
        to: normalizedFilters.toDate || null,
        q: normalizedFilters.searchQuery || null,
        page: normalizedFilters.page,
        per_page: normalizedFilters.perPage,
      };

      if (normalizedFilters.tagsIds.length) {
        params.tags_id = normalizedFilters.tagsIds;
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
