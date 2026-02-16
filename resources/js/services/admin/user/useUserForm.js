// src/composables/useUserForm.js
import { reactive, ref } from "vue";
import userService from "@/services/admin/user/userService";

export function useUserForm() {
  const form = reactive({
    name: "",
    email: "",
    password: "",
    password_confirmation: "",
    phone: "",
    country: "",
    position: "",
    date_of_birth: "",
    role: "viewer",
    is_active: true,
  });

  const fieldErrors = reactive({});
  const generalErrors = ref([]);
  const successMessage = ref("");
  const isSubmitting = ref(false);

  const resetForm = () => {
    form.name = "";
    form.email = "";
    form.password = "";
    form.password_confirmation = "";
    form.phone = "";
    form.country = "";
    form.position = "";
    form.date_of_birth = "";
    form.role = "viewer";
    form.is_active = true;
  };

  const clearErrors = () => {
    Object.keys(fieldErrors).forEach((key) => (fieldErrors[key] = ""));
    generalErrors.value = [];
    successMessage.value = "";
  };

const submit = async () => {
  clearErrors();
  isSubmitting.value = true;
  
  if (form.password !== form.password_confirmation) {
    fieldErrors.password_confirmation = "كلمة المرور وتأكيدها غير متطابقين";
    isSubmitting.value = false;
    return false;
  }

  try {
    const result = await userService.createUser({ ...form });

    if (result.success) {
      successMessage.value = "تم إنشاء المستخدم بنجاح!";
      resetForm();
      return true;
    }

    // ── التعامل مع الإيرور النظيف ──
    const err = result.error;

    if (err.isValidationError) {
      // حط الإيرورز في الحقول
      Object.keys(err.errors).forEach((key) => {
        fieldErrors[key] = err.errors[key][0] || err.errors[key]; // أول رسالة
      });
    }

    // رسالة عامة إضافية (لو مش validation)
    generalErrors.value.push(err.message);

    return false;
  } catch (unexpected) {
    // ده نادر جدًا دلوقتي لأننا بنمسك كل حاجة
    generalErrors.value.push("خطأ غير متوقع تمامًا");
    console.error(unexpected);
    return false;
  } finally {
    isSubmitting.value = false;
  }
};

  return {
    form,
    fieldErrors,
    generalErrors,
    successMessage,
    isSubmitting,
    submit,
    resetForm,
    clearErrors,
  };
}