const english = {
    title: "Discovery search",
    description: "Explore events, images and videos with the same location, category and date filters.",
    tabs: { all: "All", event: "Events", image: "Images", video: "Videos" },
    showing: "Showing",
    of: "of",
    results: "results",
    noResultsTitle: "No results found",
    noResultsDescription: "No events or media match the current filters.",
    details: "View event",
    resultLabels: { event: "Event", image: "Image", video: "Video" },
    filters: { allCountries: "All Countries", allCities: "All Cities" },
};

const localizedTabs = {
    ar: { all: "الكل", event: "الفعاليات", image: "الصور", video: "الفيديوهات" },
    de: { all: "Alle", event: "Veranstaltungen", image: "Bilder", video: "Videos" },
    es: { all: "Todo", event: "Eventos", image: "Imágenes", video: "Vídeos" },
    fa: { all: "همه", event: "رویدادها", image: "تصاویر", video: "ویدیوها" },
    fr: { all: "Tout", event: "Événements", image: "Images", video: "Vidéos" },
    hi: { all: "सभी", event: "इवेंट", image: "छवियाँ", video: "वीडियो" },
    it: { all: "Tutti", event: "Eventi", image: "Immagini", video: "Video" },
    ja: { all: "すべて", event: "イベント", image: "画像", video: "動画" },
    ru: { all: "Все", event: "События", image: "Изображения", video: "Видео" },
    tr: { all: "Tümü", event: "Etkinlikler", image: "Görseller", video: "Videolar" },
    ur: { all: "سب", event: "ایونٹس", image: "تصاویر", video: "ویڈیوز" },
    zh: { all: "全部", event: "活动", image: "图片", video: "视频" },
};

const messages = { en: english };

Object.entries(localizedTabs).forEach(([locale, tabs]) => {
    messages[locale] = {
        ...english,
        tabs,
    };
});

messages.ar = {
    ...messages.ar,
    title: "بحث Scemory الموحد",
    description: "استكشف الفعاليات والصور والفيديوهات بنفس فلاتر الموقع والتصنيف والتاريخ.",
    showing: "عرض",
    of: "من",
    results: "نتيجة",
    noResultsTitle: "لا توجد نتائج",
    noResultsDescription: "لا توجد فعاليات أو وسائط تطابق الفلاتر الحالية.",
    details: "عرض الفعالية",
    resultLabels: { event: "فعالية", image: "صورة", video: "فيديو" },
    filters: { allCountries: "كل الدول", allCities: "كل المدن" },
};

export default messages;
