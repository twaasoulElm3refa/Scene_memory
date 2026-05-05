import api from '@/services/ApiClient';

const CountryService = {
  getCountryStats(code) {
    return api.get(`/gate/${code}/stats`);
  },
};

export default CountryService;
