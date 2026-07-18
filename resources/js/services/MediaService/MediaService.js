import api from "@/services/ApiClient";

export const MediaService = {
    validatePhoto(formData) {
        return api.post("/media/validate-photo", formData, {
            suppressGlobalErrorToast: true,
        });
    },
};
