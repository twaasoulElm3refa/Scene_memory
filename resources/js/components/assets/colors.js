const COLORS = {
    /* ---------- LIGHT MODE ---------- */
    BG: "#F9FAFB", // خلفية الصفحة
    PRIMARY: "#FFFFFF", // الكروت
    SECONDARY: "#111827", // العناوين
    TEXT: "#111827", // النص الأساسي
    TEXT_MUTED: "#6B7280", // نص خفيف
    CARD_BORDER: "#E5E7EB",

    INPUT: "#F3F4F6",
    ACCENT: "#D4AF37", // اللون الذهبي
    BTN_TEXT: "#111827",

    SUCCESS: "#16A34A",
    LINK_HOVER: "#D4AF37",

    /* ---------- DARK MODE ---------- */
    BG_DARK: "#0F172A", // خلفية الصفحة
    CARD_DARK: "#1E293B", // الكروت
    INPUT_DARK: "#334155", // input
    TEXT_DARK: "#E5E7EB", // النص
};

/* ---------- rgba helper ---------- */
export const rgba = (hex, alpha = 1) => {
    const r = parseInt(hex.slice(1, 3), 16);
    const g = parseInt(hex.slice(3, 5), 16);
    const b = parseInt(hex.slice(5, 7), 16);
    return `rgba(${r}, ${g}, ${b}, ${alpha})`;
};

export default COLORS;
