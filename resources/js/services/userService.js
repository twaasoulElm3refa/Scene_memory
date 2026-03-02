export const getProfile = async () => {
  const token = localStorage.getItem("auth_token");
  const res = await fetch("/api/v1/users/profile", {
    headers: {
      Authorization: `Bearer ${token}`,
      Accept: "application/json",
    },
  });

  if (!res.ok) throw new Error("Unauthorized");

  const data = await res.json();
  return data.data;
};

export const updateProfileAPI = async (editData) => {
  const token = localStorage.getItem("auth_token");
  const res = await fetch("/api/v1/users/profile", {
    method: "PUT",
    headers: {
      Authorization: `Bearer ${token}`,
      "Content-Type": "application/json",
      Accept: "application/json",
    },
    credentials: "include",
    body: JSON.stringify(editData),
  });

  if (!res.ok) {
    const text = await res.text();
    throw new Error(text || "Error updating profile");
  }

  return await res.json();
};

export const updatePasswordAPI = async (passwordData) => {
  const token = localStorage.getItem("auth_token");

  const res = await fetch("/api/v1/users/password", {
    method: "PUT",
    headers: {
      Authorization: `Bearer ${token}`,
      "Content-Type": "application/json",
      Accept: "application/json",
    },
    credentials: "include",
    body: JSON.stringify(passwordData),
  });
  const data = await res.json(); 
  if (!res.ok) {
    throw new Error(data.message || "حدث خطأ أثناء تحديث كلمة المرور");
  }
  return data;
};

