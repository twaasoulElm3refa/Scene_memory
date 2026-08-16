import api from '@/services/ApiClient';

const CountryService = {
  getCountryStats(code, config = {}) {
    return api.get(`/gate/${code}/stats`, config);
  },
};

export default CountryService;
