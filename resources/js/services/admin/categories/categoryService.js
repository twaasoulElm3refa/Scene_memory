// src/services/categoryService.js
import axios from 'axios'

const BASE_URL = '/v1/categories'

/**
 * @typedef {Object} Category
 * @property {number} id
 * @property {string} name
 * @property {string|null} image
 * @property {number} events_count
 * @property {string} created_at
 */

/**
 * @typedef {Object} PaginationMeta
 * @property {number} current_page
 * @property {number} per_page
 * @property {number} total
 * @property {number} last_page
 * @property {number} from
 * @property {number} to
 */

/**
 * @typedef {Object} PaginatedResponse
 * @property {Category[]} data
 * @property {PaginationMeta} pagination
 */

export const categoryService = {
    /**
     * جلب التصنيفات مع التصفح
     * @param {number} page
     * @returns {Promise<{ success: boolean, data?: PaginatedResponse, error?: any }>}
     */
    async getCategories(page = 1) {
        try {
            const response = await axios.get(`${BASE_URL}/all/paginated`, {
                params: { page }
            })

            const pag = response.data.data || {}

            const formatted = {
                data: (pag.data || []).map(item => ({
                    id: item.id,
                    name: item.name,
                    image: item.image || null,
                    events_count: item.sub_categories_count ?? item.events_count ?? 0,
                    created_at: item.created_at
                })),
                pagination: {
                    current_page: pag.current_page || 1,
                    per_page: pag.per_page || 10,
                    total: pag.total || 0,
                    last_page: pag.last_page || 1,
                    from: pag.from || 1,
                    to: pag.to || 0
                }
            }

            return { success: true, data: formatted }
        } catch (error) {
            return {
                success: false,
                error: error.response?.data || error.message || 'فشل جلب التصنيفات'
            }
        }
    },

    /**
     * إنشاء تصنيف جديد
     * @param {string} name
     * @returns {Promise<{ success: boolean, data?: any, error?: any }>}
     */
    async createCategory(name) {
        try {
            const trimmed = (name || '').trim()
            if (!trimmed) throw new Error('اسم التصنيف مطلوب')

            const response = await axios.post(`${BASE_URL}/create`, {
                name: trimmed
            })

            return { success: true, data: response.data }
        } catch (error) {
            if (error.response?.status === 422) {
                return {
                    success: false,
                    error: {
                        type: 'validation',
                        messages: error.response.data.errors || {}
                    }
                }
            }

            return {
                success: false,
                error: error.response?.data?.message || 'فشل إنشاء التصنيف'
            }
        }
    },

    /**
     * تعديل تصنيف موجود
     * @param {number} id
     * @param {string} name
     * @returns {Promise<{ success: boolean, data?: any, error?: any }>}
     */
    async updateCategory(id, name) {
        try {
            const trimmed = (name || '').trim()
            if (!trimmed) throw new Error('اسم التصنيف مطلوب')

            // ملاحظة: المسار طويل جدًا – يفضل تبسيطه في الـ backend إن أمكن
            const response = await axios.post(
                `${BASE_URL}/edit/${id}/update/edit`,
                { name: trimmed }
            )

            return { success: true, data: response.data }
        } catch (error) {
            if (error.response?.status === 422) {
                return {
                    success: false,
                    error: {
                        type: 'validation',
                        messages: error.response.data.errors || {}
                    }
                }
            }

            return {
                success: false,
                error: error.response?.data?.message || 'فشل تعديل التصنيف'
            }
        }
    },

    /**
     * حذف تصنيف
     * @param {number} id
     * @returns {Promise<{ success: boolean, error?: any }>}
     */
    async deleteCategory(id) {
        try {
            // نفس الملاحظة عن طول المسار
            await axios.delete(`${BASE_URL}/delete/${id}/delete/delete`)
            return { success: true }
        } catch (error) {
            return {
                success: false,
                error: error.response?.data?.message || 'فشل حذف التصنيف'
            }
        }
    },

    async createCategoryWithImage({ name, active = true, featured = false, coverImage = null }) {
        try {
            const formData = new FormData()

            if (!name?.trim()) {
                throw new Error('اسم التصنيف مطلوب')
            }

            formData.append('name', name.trim())
            formData.append('active', active ? '1' : '0')
            formData.append('featured', featured ? '1' : '0')

            if (coverImage instanceof File) {
                formData.append('cover_image', coverImage)
            }

            const response = await axios.post(`${BASE_URL}/create`, formData, {
                headers: {
                    'Content-Type': 'multipart/form-data'
                }
            })

            return {
                success: true,
                data: response.data
            }
        } catch (error) {
            if (error.response?.status === 422) {
                return {
                    success: false,
                    error: {
                        type: 'validation',
                        messages: error.response.data.errors || {},
                        message: error.response.data.message || 'البيانات غير صالحة'
                    }
                }
            }

            return {
                success: false,
                error: {
                    message: error.response?.data?.message || error.message || 'فشل إنشاء التصنيف',
                    details: error
                }
            }
        }
    },

    // إذا أردت دالة منفصلة للـ preview فقط (اختياري)
    generatePreviewUrl(file) {
        return new Promise((resolve, reject) => {
            if (!file || !file.type.startsWith('image/')) {
                reject(new Error('الملف يجب أن يكون صورة'))
                return
            }

            const reader = new FileReader()
            reader.onload = (e) => resolve(e.target.result)
            reader.onerror = () => reject(new Error('فشل قراءة الملف'))
            reader.readAsDataURL(file)
        })
    },

    async getCategoryWithSubs(id) {
        try {
            const res = await axios.get(`${BASE}/${id}`)
            if (res.data.status === 'success') {
                return {
                    success: true,
                    data: res.data.data
                }
            }
            return { success: false, error: 'لم يتم العثور على الفئة' }
        } catch (err) {
            return {
                success: false,
                error: err.response?.data?.message || 'فشل جلب تفاصيل الفئة'
            }
        }
    },

    /**
     * تعديل اسم فئة فرعية
     * @param {number} subId
     * @param {string} name
     */
    async updateSubCategory(subId, name) {
        try {
            const trimmed = (name || '').trim()
            if (!trimmed) throw new Error('اسم التصنيف الفرعي مطلوب')

            const res = await axios.post(`${SUB_BASE}/update/${subId}`, {
                name: trimmed
            })

            return { success: true, data: res.data }
        } catch (err) {
            if (err.response?.status === 422) {
                return {
                    success: false,
                    error: {
                        type: 'validation',
                        messages: err.response.data.errors || {},
                        message: err.response.data.message || 'البيانات غير صالحة'
                    }
                }
            }
            return {
                success: false,
                error: err.response?.data?.message || 'فشل تعديل التصنيف الفرعي'
            }
        }
    },

    /**
     * حذف فئة فرعية
     * @param {number} subId
     */
    async deleteSubCategory(subId) {
        try {
            await axios.delete(`${SUB_BASE}/delete/${subId}`)
            return { success: true }
        } catch (err) {
            return {
                success: false,
                error: err.response?.data?.message || 'فشل حذف التصنيف الفرعي'
            }
        }
    },

    // إذا أردت إضافة دالة لإنشاء فئة فرعية جديدة (لصفحة الإضافة)
    async createSubCategory(categoryId, name) {
        try {
            const trimmed = (name || '').trim()
            if (!trimmed) throw new Error('اسم التصنيف الفرعي مطلوب')

            const res = await axios.post(`${SUB_BASE}/create`, {
                category_id: categoryId,
                name: trimmed
            })

            return { success: true, data: res.data }
        } catch (err) {
            // نفس معالجة الأخطاء السابقة
            if (err.response?.status === 422) {
                return {
                    success: false,
                    error: {
                        type: 'validation',
                        messages: err.response.data.errors || {}
                    }
                }
            }
            return {
                success: false,
                error: err.response?.data?.message || 'فشل إنشاء التصنيف الفرعي'
            }
        }
    },

    /**
   * إنشاء تصنيف فرعي مع دعم رفع صورة
   * @param {Object} data
   * @param {number} data.category_id
   * @param {string} data.name
   * @param {File|null} data.image
   */
    async createSubCategoryWithImage({ category_id, name, image = null }) {
        try {
            const trimmed = (name || '').trim()
            if (!trimmed) throw new Error('اسم التصنيف الفرعي مطلوب')
            if (!category_id) throw new Error('معرف الفئة الأم مطلوب')

            const formData = new FormData()
            formData.append('category_id', category_id)
            formData.append('name', trimmed)

            if (image instanceof File) {
                formData.append('image', image)
            }

            const res = await axios.post('/v1/sub_categories/create', formData, {
                headers: { 'Content-Type': 'multipart/form-data' }
            })

            return { success: true, data: res.data }
        } catch (err) {
            if (err.response?.status === 422) {
                return {
                    success: false,
                    error: {
                        type: 'validation',
                        messages: err.response.data.errors || {},
                        message: err.response.data.message || 'البيانات غير صالحة'
                    }
                }
            }
            return {
                success: false,
                error: {
                    message: err.response?.data?.message || err.message || 'فشل إنشاء التصنيف الفرعي'
                }
            }
        }
    },
}
