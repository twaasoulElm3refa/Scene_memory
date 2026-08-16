import api from '@/services/ApiClient';

export default {
  getRandomEvents(config = {}) {
    return api.get('/gate/random', config);
  },

  getAllCountries(config = {}) {
    return api.get('/gate/all', config);
  }
};
