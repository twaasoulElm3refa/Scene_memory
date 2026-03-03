import api from "@/services/ApiClient";

export const EventService = {
  /**
   * البحث عن الفعاليات باستخدام city و subCategory في الـ URL
   * @param {Object} filters
   * @returns {Promise<Array>}
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
      console.error("Error searching events:", err);
      throw new Error(
        err.response?.data?.message ||
        "حدث خطأ أثناء جلب الفعاليات"
      );
    }
  },

  async searchEventsLegacy(cityId = null, subCategoryId = null, fromDate = null, toDate = null) {
    return this.searchEvents({ cityId, subCategoryId, fromDate, toDate });
  },
};
