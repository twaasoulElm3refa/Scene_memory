// src/services/userService.js
import axios from "axios";

const BASE_URL = "/v1/users";

const getAuthHeader = () => {
  const token = localStorage.getItem("auth_token");
  return token ? { Authorization: `Bearer ${token}` } : {};
};

export const userService = {
  /**
   * جلب قائمة المستخدمين مع التصفح (pagination)
   */
  async fetchUsers(page = 1) {
    try {
      const res = await axios.get(`${BASE_URL}/all/get?page=${page}`, {
        headers: getAuthHeader(),
      });
      return {
        success: true,
        data: res.data.data, // { data: [], current_page, last_page, per_page, total }
      };
    } catch (error) {
      return handleApiError(error, "فشل جلب قائمة المستخدمين");
    }
  },

  /**
   * إنشاء مستخدم جديد
   */
  async createUser(userData) {
    try {
      const response = await axios.post(`${BASE_URL}/create`, userData, {
        headers: getAuthHeader(),
      });
      return {
        success: true,
        data: response.data.data || response.data,
      };
    } catch (error) {
      return handleApiError(error, "فشل إنشاء المستخدم");
    }
  },

  /**
   * تعديل بيانات مستخدم
   */
  async updateUser(userId, data) {
    try {
      const res = await axios.post(`${BASE_URL}/${userId}`, data, {
        headers: getAuthHeader(),
      });
      return {
        success: true,
        data: res.data,
      };
    } catch (error) {
      return handleApiError(error, "فشل تعديل بيانات المستخدم");
    }
  },

  /**
   * حذف مستخدم
   */
  async deleteUser(userId) {
    try {
      await axios.delete(`${BASE_URL}/${userId}`, {
        headers: getAuthHeader(),
      });
      return { success: true };
    } catch (error) {
      return handleApiError(error, "فشل حذف المستخدم");
    }
  },
};

/**
 * دالة مساعدة لمعالجة الأخطاء بشكل موحد
 */
function handleApiError(error, defaultMessage) {
  let normalized = {
    message: defaultMessage || "حدث خطأ غير متوقع",
    status: null,
    errors: {},
    isValidationError: false,
  };

  if (error.response) {
    const { status, data } = error.response;
    normalized.status = status;

    if (status === 422) {
      normalized.isValidationError = true;
      normalized.message = data.message || "البيانات المدخلة غير صالحة";
      normalized.errors = data.errors || {};
    } else if (status === 419) {
      normalized.message = "انتهت الجلسة (CSRF token mismatch). أعد تحميل الصفحة.";
    } else if (status >= 500) {
      normalized.message = "خطأ في الخادم. حاول مرة أخرى لاحقًا.";
    } else {
      normalized.message = data.message || `خطأ ${status}`;
    }
  } else if (error.request) {
    normalized.message = "لا يمكن الاتصال بالخادم. تحقق من الإنترنت.";
  } else {
    normalized.message = error.message || "خطأ غير معروف";
  }

  return {
    success: false,
    error: normalized,
  };
}

export default userService;