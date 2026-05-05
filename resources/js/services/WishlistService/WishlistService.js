import api from "../ApiClient";

export const WishlistService = {
  getMyWishlist(page = 1) {
    return api.get(`/Wishlist/me?page=${page}`);
  },

  addToWishlist(id) {
    return api.post(`/Wishlist/${id}`);
  },

  deleteFromWishlist(id) {
    return api.delete(`/Wishlist/${id}/delete`);
  },
};
