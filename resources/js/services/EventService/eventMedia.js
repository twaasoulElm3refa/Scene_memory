const SCEMORY_EVENT_FALLBACK =
    "data:image/svg+xml;charset=UTF-8," +
    encodeURIComponent(`
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 800 450">
            <defs>
                <linearGradient id="surface" x1="0" y1="0" x2="1" y2="1">
                    <stop offset="0" stop-color="#eef6ff"/>
                    <stop offset="1" stop-color="#dce8f5"/>
                </linearGradient>
            </defs>
            <rect width="800" height="450" fill="url(#surface)"/>
            <circle cx="400" cy="196" r="58" fill="#ffffff" opacity=".9"/>
            <path d="M372 210l30-38 46 58h-96z" fill="#1677ff" opacity=".72"/>
            <circle cx="379" cy="187" r="10" fill="#0d4d97" opacity=".78"/>
            <rect x="322" y="275" width="156" height="12" rx="6" fill="#0d4d97" opacity=".2"/>
            <rect x="355" y="300" width="90" height="9" rx="4.5" fill="#0d4d97" opacity=".12"/>
        </svg>
    `);

export const EVENT_FALLBACK_IMAGE = SCEMORY_EVENT_FALLBACK;

export function getBackendOrigin() {
    const apiUrl =
        import.meta.env.VITE_API_URL ||
        import.meta.env.VITE_API_BASE_URL ||
        import.meta.env.VITE_BACKEND_URL ||
        "";

    if (apiUrl) {
        try {
            return new URL(apiUrl, window.location.origin).origin;
        } catch {
            return String(apiUrl).replace(/\/+$/, "");
        }
    }

    if (
        ["localhost", "127.0.0.1"].includes(window.location.hostname) &&
        window.location.port &&
        window.location.port !== "8000"
    ) {
        return `${window.location.protocol}//${window.location.hostname}:8000`;
    }

    return window.location.origin;
}

export function getMediaRawPath(mediaOrPath) {
    if (!mediaOrPath) return "";
    if (typeof mediaOrPath === "string") return mediaOrPath;

    return (
        mediaOrPath.preview_url ||
        mediaOrPath.previewUrl ||
        mediaOrPath.image_url ||
        mediaOrPath.imageUrl ||
        mediaOrPath.webp_url ||
        mediaOrPath.webpUrl ||
        mediaOrPath.full_url ||
        mediaOrPath.fullUrl ||
        mediaOrPath.full_url_webp ||
        mediaOrPath.fullUrlWebp ||
        mediaOrPath.url ||
        mediaOrPath.path ||
        mediaOrPath.image ||
        mediaOrPath.file_path ||
        mediaOrPath.filePath ||
        mediaOrPath.file ||
        mediaOrPath.src ||
        ""
    );
}

export function getStorageUrl(mediaOrPath) {
    const rawPath = getMediaRawPath(mediaOrPath);
    if (!rawPath || typeof rawPath !== "string") return EVENT_FALLBACK_IMAGE;

    const path = rawPath.replace(/\\/g, "/").trim();
    if (!path) return EVENT_FALLBACK_IMAGE;
    if (/^https?:\/\//i.test(path)) return path;
    if (path.startsWith("//")) return `${window.location.protocol}${path}`;

    const backendOrigin = getBackendOrigin();
    if (path.startsWith("/storage/") || path.startsWith("/uploads/")) {
        return `${backendOrigin}${path}`;
    }
    if (path.startsWith("storage/") || path.startsWith("uploads/")) {
        return `${backendOrigin}/${path}`;
    }
    if (path.startsWith("public/")) {
        return `${backendOrigin}/storage/${path.replace(/^public\//, "")}`;
    }

    return `${backendOrigin}/storage/${path.replace(/^\/+/, "")}`;
}

export function getEventImageCandidate(event) {
    return (
        getMediaRawPath(event?.first_image) ||
        getMediaRawPath(event?.firstImage) ||
        event?.image_webp_url ||
        event?.imageWebpUrl ||
        event?.image_url ||
        event?.imageUrl ||
        getMediaRawPath(event?.images?.[0]) ||
        event?.image ||
        ""
    );
}

export function getEventImageUrl(event) {
    return getStorageUrl(getEventImageCandidate(event));
}

export function formatEventDate(dateValue, locale = "en") {
    if (!dateValue) return "—";

    const date = new Date(dateValue);
    if (Number.isNaN(date.getTime())) return "—";

    try {
        return new Intl.DateTimeFormat(locale, {
            day: "numeric",
            month: "short",
            year: "numeric",
        }).format(date);
    } catch {
        return new Intl.DateTimeFormat("en", {
            day: "numeric",
            month: "short",
            year: "numeric",
        }).format(date);
    }
}

export function applyEventImageFallback(domEvent) {
    const target = domEvent?.target;
    if (!target || target.dataset.fallbackApplied === "1") return;

    target.dataset.fallbackApplied = "1";
    target.src = EVENT_FALLBACK_IMAGE;
}
