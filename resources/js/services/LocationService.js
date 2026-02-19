import api from "./ApiClient";

export const LocationService = {
  async getAllCountries() {
    try {
      const res = await api.get("/countries");
      return res.data.data || [];
    } catch (err) {
      console.error("Error fetching countries:", err);
      return [];
    }
  },

  async getCitiesByCountry(countryId) {
    if (!countryId) return [];
    try {
      const res = await api.get(`/countries/${countryId}`);
      console.log(res.data);
      return res.data.data?.countries.cities || [];
    } catch (err) {
      console.error("Error fetching cities:", err);
      return [];
    }
  },
};