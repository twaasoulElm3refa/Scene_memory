import api from "../ApiClient";

export const EventService = {
  async getSingleEvent(slug) {
    if (!slug) throw new Error("Slug is required");

    try {
      const res = await api.get(`/events/${slug}/single/get`);
      return res.data.data || null;
    } catch (err) {
      console.error("Error fetching single event:", err);
      throw err;
    }
  },
};
