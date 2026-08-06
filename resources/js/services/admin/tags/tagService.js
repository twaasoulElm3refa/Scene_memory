import api from "../../ApiClient";

export const tagService = {
  async getTags(page = 1, perPage = 30) {
    try {
      const response = await api.get("/tags/all/paginated", {
        params: {
          page,
          per_page: perPage,
        },
      });

      const paginated = response.data?.data || {};

      return {
        success: true,
        data: {
          tags: Array.isArray(paginated.data)
            ? paginated.data.map((item) => ({
                id: item.id,
                name: item.name || "",
                display_name:
                  item.translation?.name ||
                  item.name ||
                  "",
                slug: item.slug || "",
                mode: item.mode || "",
                translation: item.translation || null,
                created_at: item.created_at || null,
              }))
            : [],
          pagination: {
            current_page: Number(
              paginated.current_page || 1
            ),
            per_page: Number(
              paginated.per_page || perPage
            ),
            total: Number(paginated.total || 0),
            last_page: Number(
              paginated.last_page || 1
            ),
            from: Number(paginated.from || 0),
            to: Number(paginated.to || 0),
          },
        },
      };
    } catch (error) {
      return {
        success: false,
        error:
          error.response?.data?.message ||
          error.message ||
          "Failed to load tags",
      };
    }
  },

  async getTag(slug) {
    try {
      const response = await api.get(
        `/tags/${encodeURIComponent(slug)}`
      );

      return {
        success: true,
        data: response.data?.data || null,
      };
    } catch (error) {
      return {
        success: false,
        error:
          error.response?.data?.message ||
          "Failed to load tag",
      };
    }
  },

  async createTag(payload) {
    try {
      const response = await api.post(
        "/tags/create",
        payload
      );

      return {
        success: true,
        data: response.data,
      };
    } catch (error) {
      if (error.response?.status === 422) {
        return {
          success: false,
          error: {
            type: "validation",
            messages:
              error.response.data.errors || {},
            message:
              error.response.data.message ||
              "Validation failed",
          },
        };
      }

      return {
        success: false,
        error:
          error.response?.data?.message ||
          "Failed to create tag",
      };
    }
  },

  async updateTag(slug, payload) {
    try {
      const response = await api.post(
        `/tags/update/${encodeURIComponent(slug)}`,
        payload
      );

      return {
        success: true,
        data: response.data,
      };
    } catch (error) {
      if (error.response?.status === 422) {
        return {
          success: false,
          error: {
            type: "validation",
            messages:
              error.response.data.errors || {},
            message:
              error.response.data.message ||
              "Validation failed",
          },
        };
      }

      return {
        success: false,
        error:
          error.response?.data?.message ||
          "Failed to update tag",
      };
    }
  },

  async deleteTag(slug) {
    try {
      await api.delete(
        `/tags/delete/${encodeURIComponent(slug)}`
      );

      return {
        success: true,
      };
    } catch (error) {
      return {
        success: false,
        error:
          error.response?.data?.message ||
          "Failed to delete tag",
      };
    }
  },
};
