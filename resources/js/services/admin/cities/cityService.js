// src/services/cityService.js
import api from './api';

const CITY_ENDPOINT = '/v1/cities';

export const cityService = {
  /**
   * جلب المدن مع الـ pagination + فلاتر
   * @param {Object} params 
   * @returns {Promise}
   */
  getPaginatedCities(params = {}) {
    return api.get(`${CITY_ENDPOINT}/paginated/get`, { params });
  },

  /**
   * جلب كل الدول (للفلتر)
   * @returns {Promise}
   */
  getAllCountries() {
    return api.get('/api/v1/countries');
  },

  /**
   * جلب إحصائيات المدن
   * @returns {Promise}
   */
  getCitiesStatistics() {
    return api.get('/api/v1/cities/statistics');
  },

  /**
   * تحديث مدينة
   * @param {number} id 
   * @param {Object} data {name, country_id}
   * @returns {Promise}
   */
  updateCity(id, data) {
    return api.post(`${CITY_ENDPOINT}/${id}/update`, data);
  },

  /**
   * حذف مدينة
   * @param {number} id 
   * @returns {Promise}
   */
  deleteCity(id) {
    return api.delete(`${CITY_ENDPOINT}/${id}/delete`);
  },

  createCity(data) {
    return api.post(`${CITY_ENDPOINT}/create`, data);
  },

  getCityById(id) {
    return api.get(`${CITY_ENDPOINT}/${id}`);
  },

  
};