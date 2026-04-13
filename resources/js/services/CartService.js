import api from "@/services/ApiClient";

export const CartService = {
    async addToCart(imageId) {
        const res = await api.post(`/cart/addToCart/${imageId}`);
        return res.data;
    },

    async getCart() {
        const res = await api.get(`/cart/get`);
        return res.data;
    },

    async deleteFromCart(id) {
        const res = await api.delete(`/cart/delete/${id}`);
        return res.data;
    },
    async clearCart() {
        const res = await api.delete(`/cart/clearCart`);
        return res.data;
    },

    async wallet()
    {
        const res = await api.get(`users/wallet`);
        console.log(res.data);
        return res.data;
    },

    async payWithWallet(payload) {
        const res = await api.post(`/pay/wallet`, payload);
        return res.data;
    },
};
