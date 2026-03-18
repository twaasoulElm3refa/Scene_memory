import api from "./ApiClient";

export const LocationService = {
  async getAllCountries() {
    try {
      const res = await api.get("/countries");
      console.log(res);
      return res.data.data || [];
    } catch (err) {
      console.error("Error fetching countries:", err);
      return [];
    }
  },

  async getCitiesByCountry(countryId) {
    if (!countryId) return [];
    try {
      const res = await api.get(`/countries/${countryId}/cities`);
      console.log(res);
      return res.data.data || [];
    } catch (err) {
      console.error("Error fetching cities:", err);
      return [];
    }
  },
};
