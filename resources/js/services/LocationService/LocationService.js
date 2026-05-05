import api from "../ApiClient";

export const LocationService = {
  getCountriesAll() {
    return api.get("/countries/all/get");
  },

  getCountryById(countryId) {
    return api.get(`/countries/${countryId}`);
  },

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
      const res = await api.get(`/countries/${countryId}/cities`);
      return res.data.data || [];
    } catch (err) {
      console.error("Error fetching cities:", err);
      return [];
    }
  },
};
