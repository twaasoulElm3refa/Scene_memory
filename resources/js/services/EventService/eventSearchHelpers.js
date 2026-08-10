export const DEFAULT_EVENT_FALLBACK_IMAGE =
    "https://developers.elementor.com/docs/assets/img/elementor-placeholder-image.png";

const isFilled = (value) => value !== undefined && value !== null && value !== "" && value !== "all";

const firstValue = (...values) => values.find((value) => isFilled(value));

const toPositiveNumber = (value, fallback = null) => {
    const number = Number(value);

    return Number.isFinite(number) && number > 0 ? number : fallback;
};

const toArray = (value) => {
    if (Array.isArray(value)) {
        return value;
    }

    if (typeof value === "string") {
        return value.split(",");
    }

    if (isFilled(value)) {
        return [value];
    }

    return [];
};

export const compactQuery = (query = {}) => Object.fromEntries(
    Object.entries(query).filter(([, value]) => {
        if (Array.isArray(value)) {
            return value.length > 0;
        }

        return isFilled(value);
    })
);

export const normalizeEventSearchFilters = (filters = {}, options = {}) => {
    const defaultPerPage = options.defaultPerPage ?? 8;
    const rawTags = firstValue(filters.tagsIds, filters.tags_id, filters.tags);
    const tagsIds = toArray(rawTags)
        .map((tag) => toPositiveNumber(tag))
        .filter(Boolean);

    return {
        searchQuery: String(firstValue(filters.searchQuery, filters.q, filters.search) || "").trim(),
        categoryId: firstValue(filters.categoryId, filters.category_id) || null,
        subCategoryId: firstValue(filters.subCategoryId, filters.sub_category_id) || null,
        countryId: firstValue(filters.countryId, filters.country_id) || null,
        cityId: firstValue(filters.cityId, filters.city_id) || null,
        tagsIds: [...new Set(tagsIds)],
        fromDate: firstValue(filters.fromDate, filters.from_date, filters.from) || null,
        toDate: firstValue(filters.toDate, filters.to_date, filters.to) || null,
        page: toPositiveNumber(firstValue(filters.page), 1),
        perPage: toPositiveNumber(firstValue(filters.perPage, filters.per_page), defaultPerPage),
    };
};

export const eventFiltersToQuery = (filters = {}, options = {}) => {
    const normalized = normalizeEventSearchFilters(filters, {
        defaultPerPage: options.defaultPerPage ?? filters.perPage ?? 8,
    });

    return compactQuery({
        q: normalized.searchQuery || undefined,
        category_id: normalized.categoryId || undefined,
        sub_category_id: normalized.subCategoryId || undefined,
        country_id: normalized.countryId || undefined,
        city_id: normalized.cityId || undefined,
        tags: normalized.tagsIds.length ? normalized.tagsIds.join(",") : undefined,
        from_date: normalized.fromDate || undefined,
        to_date: normalized.toDate || undefined,
        page: options.includePagination ? normalized.page : undefined,
        per_page: options.includePagination ? normalized.perPage : undefined,
    });
};

export const queryToEventFilters = (query = {}, options = {}) => {
    return normalizeEventSearchFilters(
        {
            searchQuery: query.q || query.searchQuery || query.search,
            categoryId: query.category_id || query.categoryId,
            subCategoryId: query.sub_category_id || query.subCategoryId,
            countryId: query.country_id || query.countryId,
            cityId: query.city_id || query.cityId,
            tagsIds: query.tags || query.tags_id || query.tagsIds,
            fromDate: query.from_date || query.fromDate || query.from,
            toDate: query.to_date || query.toDate || query.to,
            page: query.page,
            perPage: query.per_page || query.perPage,
        },
        { defaultPerPage: options.defaultPerPage ?? 12 }
    );
};

export const toMediaUrl = (pathValue) => {
    if (!pathValue) return null;
    if (/^https?:\/\//i.test(pathValue)) return pathValue;

    return `/storage/${pathValue}`;
};

export const normalizeEvent = (ev = {}) => ({
    id: ev.id || ev._id,
    slug: ev.slug,
    translation: ev.translation,
    title: ev.title || "Untitled event",
    start_date: ev.start_date,
    city: ev.city?.translation?.name || ev.city || "Not specified",
    category_name: ev.sub_categorey?.translation?.name || "Event",
    image_url: toMediaUrl(ev.first_image?.full_url || ev.firstImage?.full_url),
    image_webp_url: toMediaUrl(
        ev.first_image?.webp_url ||
        ev.first_image?.full_url_webp ||
        ev.firstImage?.webp_url ||
        ev.firstImage?.full_url_webp
    ),
    lattitude: ev.lattitude,
    langitude: ev.langitude,
});

const buildPaginationPayload = (paginator, fallbackPerPage = 8) => {
    const events = Array.isArray(paginator?.data) ? paginator.data : [];

    return {
        events,
        currentPage: Number(paginator?.current_page ?? 1),
        lastPage: Number(paginator?.last_page ?? 1),
        perPage: Number(paginator?.per_page ?? fallbackPerPage),
        total: Number(paginator?.total ?? events.length),
        from: paginator?.from ?? (events.length ? 1 : null),
        to: paginator?.to ?? events.length,
    };
};

const buildArrayPaginationPayload = (events = []) => ({
    events,
    currentPage: 1,
    lastPage: 1,
    perPage: events.length,
    total: events.length,
    from: events.length ? 1 : null,
    to: events.length,
});

export function normalizePaginatedResponse(response, fallbackPerPage = 8) {
    const candidates = [
        response?.data?.data,
        response?.data,
        response,
    ];

    let arrayFallback = null;

    for (const candidate of candidates) {
        if (!candidate) continue;

        if (candidate?.data && Array.isArray(candidate.data)) {
            return buildPaginationPayload(candidate, fallbackPerPage);
        }

        if (candidate?.data?.data && Array.isArray(candidate.data.data)) {
            return buildPaginationPayload(candidate.data, fallbackPerPage);
        }

        if (Array.isArray(candidate) && !arrayFallback) {
            arrayFallback = candidate;
        }
    }

    if (arrayFallback) {
        return buildArrayPaginationPayload(arrayFallback);
    }

    return buildPaginationPayload(null, fallbackPerPage);
}
