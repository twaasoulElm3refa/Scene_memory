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
    media: {
        price: "Price",
        addToCart: "Add to Cart",
        adding: "Adding...",
        added: "Added",
        playVideo: "Play video",
        pauseVideo: "Pause video",
    },
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

const localizedMedia = {
    ar: { price: "السعر", addToCart: "أضف إلى السلة", adding: "جارٍ الإضافة...", added: "تمت الإضافة", playVideo: "تشغيل الفيديو", pauseVideo: "إيقاف الفيديو مؤقتًا" },
    de: { price: "Preis", addToCart: "In den Warenkorb", adding: "Wird hinzugefügt...", added: "Hinzugefügt", playVideo: "Video abspielen", pauseVideo: "Video pausieren" },
    es: { price: "Precio", addToCart: "Añadir al carrito", adding: "Añadiendo...", added: "Añadido", playVideo: "Reproducir vídeo", pauseVideo: "Pausar vídeo" },
    fa: { price: "قیمت", addToCart: "افزودن به سبد خرید", adding: "در حال افزودن...", added: "افزوده شد", playVideo: "پخش ویدیو", pauseVideo: "توقف موقت ویدیو" },
    fr: { price: "Prix", addToCart: "Ajouter au panier", adding: "Ajout...", added: "Ajouté", playVideo: "Lire la vidéo", pauseVideo: "Mettre la vidéo en pause" },
    hi: { price: "मूल्य", addToCart: "कार्ट में जोड़ें", adding: "जोड़ा जा रहा है...", added: "जोड़ दिया गया", playVideo: "वीडियो चलाएँ", pauseVideo: "वीडियो रोकें" },
    it: { price: "Prezzo", addToCart: "Aggiungi al carrello", adding: "Aggiunta...", added: "Aggiunto", playVideo: "Riproduci video", pauseVideo: "Metti in pausa il video" },
    ja: { price: "価格", addToCart: "カートに追加", adding: "追加中...", added: "追加済み", playVideo: "動画を再生", pauseVideo: "動画を一時停止" },
    ru: { price: "Цена", addToCart: "Добавить в корзину", adding: "Добавление...", added: "Добавлено", playVideo: "Воспроизвести видео", pauseVideo: "Приостановить видео" },
    tr: { price: "Fiyat", addToCart: "Sepete Ekle", adding: "Ekleniyor...", added: "Eklendi", playVideo: "Videoyu oynat", pauseVideo: "Videoyu duraklat" },
    ur: { price: "قیمت", addToCart: "کارٹ میں شامل کریں", adding: "شامل کیا جا رہا ہے...", added: "شامل ہو گیا", playVideo: "ویڈیو چلائیں", pauseVideo: "ویڈیو موقوف کریں" },
    zh: { price: "价格", addToCart: "加入购物车", adding: "正在添加...", added: "已添加", playVideo: "播放视频", pauseVideo: "暂停视频" },
};

const messages = { en: english };

Object.entries(localizedTabs).forEach(([locale, tabs]) => {
    messages[locale] = {
        ...english,
        tabs,
        media: localizedMedia[locale],
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
