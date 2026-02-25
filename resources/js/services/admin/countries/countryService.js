import api from '@/services/admin/cities/api';   

const COUNTRIES_ENDPOINT = '/v1/countries';

export const countryService = {
  /**
   * جلب الدول مع الـ pagination + بحث
   * @param {number} page
   * @param {string} search
   * @returns {Promise}
   */
  getPaginatedCountries(page = 1, search = '') {
    const params = { page };
    if (search.trim()) {
      params.search = search.trim();
    }
    return api.get(`${COUNTRIES_ENDPOINT}/paginated/get`, { params });
  },

  /**
   * جلب تفاصيل دولة واحدة (للـ edit modal)
   * @param {number} id
   * @returns {Promise}
   */
  getCountryById(id) {
    return api.get(`${COUNTRIES_ENDPOINT}/${id}`);
  },

  /**
   * جلب تفاصيل دولة واحدة مع الإحصائيات + المدن + الـ events (paginated)
   * @param {number|string} id          - معرف الدولة
   * @param {number} [page=1]           - رقم صفحة الـ events
   * @param {Object} [params={}]        - فلاتر إضافية إذا احتجتها مستقبلاً
   * @returns {Promise}
   */
  getCountryDetails(id, page = 1, params = {}) {
    const queryParams = { ...params, page };
    return api.get(`${COUNTRIES_ENDPOINT}/${id}`, { params: queryParams });
  },

  /**
   * تحديث دولة (يدعم رفع صورة multipart)
   * @param {number} id
   * @param {Object} data
   * @param {File} [imageFile] - اختياري
   * @returns {Promise}
   */
  updateCountry(id, data, imageFile = null) {
    const formData = new FormData();
    formData.append('name', data.name || '');
    formData.append('code', data.code || '');

    if (imageFile) {
      formData.append('image', imageFile);
    }

    return api.post(`${COUNTRIES_ENDPOINT}/${id}/update`, formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    });
  },

  /**
   * حذف دولة
   * @param {number} id
   * @returns {Promise}
   */
  deleteCountry(id) {
    console.log(id);
    return api.delete(`${COUNTRIES_ENDPOINT}/${id}/delete`);
  },

  // إضافة دولة جديدة (لو هتحتاجها في صفحة الإنشاء لاحقًا)
  createCountry(data, imageFile = null) {
    const formData = new FormData();
    formData.append('name', data.name);
    formData.append('code', data.code || '');

    if (imageFile) {
      formData.append('image', imageFile);
    }

    return api.post(`${COUNTRIES_ENDPOINT}/store`, formData, {   // أو /create حسب الـ backend
      headers: { 'Content-Type': 'multipart/form-data' },
    });
  },
};