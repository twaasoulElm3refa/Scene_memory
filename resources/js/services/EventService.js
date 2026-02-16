import api from "./ApiClient";

export const EventService = {
  async searchEvents(cityId = null, categoryId = null, fromDate = null, toDate = null) {
    try {
      const params = {};

      if (cityId) params.city_id = cityId;
      if (categoryId) params.category_id = categoryId;
      if (fromDate) params.from = fromDate;
      if (toDate) params.to = toDate;

      const res = await api.get("/events", { params });

      return res.data.data || [];
    } catch (err) {
      console.error("Error searching events:", err);
      throw err;
    }
  },
};
