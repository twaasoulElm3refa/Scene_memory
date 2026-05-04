import api from "./ApiClient";

export const PaymentService = {
  pay(payload) {
    return api.post("/pay", payload);
  },

  depositPay(payload) {
    return api.post("/deposit/pay", payload);
  },

  paypalSuccess(token) {
    return api.get("/paypal/success", { params: { token } });
  },

  orderStatus(orderId) {
    return api.get(`/order/status/${orderId}`);
  },

  walletOrderStatus(orderId) {
    return api.get(`/wallet/order-status/${orderId}`);
  },
};
