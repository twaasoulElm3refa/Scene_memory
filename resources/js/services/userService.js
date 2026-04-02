import api from "./ApiClient";

export const getProfile = async () => {
  const res = await api.get("/users/profile");
  return res.data.data;
};

// ✏️ Update Profile
export const updateProfileAPI = async (editData) => {
  const res = await api.post("/users/update-profile", editData);
  return res.data;
};

// 🔐 Update Password
export const updatePasswordAPI = async (passwordData) => {
  const res = await api.put("/users/password", passwordData);
  return res.data;
};
