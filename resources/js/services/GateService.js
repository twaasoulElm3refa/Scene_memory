import api from '@/services/ApiClient';

export default {
  getRandomEvents() {
    return api.get('/gate/random');
  },

  getAllCountries() {
    return api.get('/gate/all');
  }
};
