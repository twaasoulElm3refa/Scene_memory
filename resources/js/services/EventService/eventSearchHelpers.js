export const DEFAULT_EVENT_FALLBACK_IMAGE =
    "https://developers.elementor.com/docs/assets/img/elementor-placeholder-image.png";

export const DISCOVERY_RESULT_TYPES = ["all", "event", "image", "video"];

export const createDiscoverySeed = () => Math.floor(Math.random() * 2147483645) + 1;

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
        type: DISCOVERY_RESULT_TYPES.includes(String(filters.type || "").toLowerCase())
            ? String(filters.type).toLowerCase()
            : (options.defaultType || "all"),
        seed: toPositiveNumber(firstValue(filters.seed), options.defaultSeed ?? null),
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
        type: normalized.type,
        seed: normalized.seed || undefined,
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
            type: query.type,
            seed: query.seed,
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
    if (String(pathValue).startsWith("/")) return pathValue;

    return `/storage/${pathValue}`;
};

export const normalizeDiscoveryResult = (result = {}) => {
    const resultType = ["event", "image", "video"].includes(result.result_type)
        ? result.result_type
        : "event";
    const eventSlug = result.event_slug || result.slug;
    const title = result.title || result.translation?.title || "Untitled event";
    const description = result.description || result.translation?.description || "";
    const mediaUrl = toMediaUrl(
        result.media_url ||
        result.first_image?.full_url ||
        result.firstImage?.full_url
    );
    const thumbnailUrl = toMediaUrl(
        result.thumbnail_url ||
        result.first_image?.preview_url ||
        result.first_image?.full_url ||
        result.firstImage?.preview_url ||
        result.firstImage?.full_url
    );
    const cityName = result.city?.translation?.name || result.city?.name || result.city || "Not specified";
    const subCategory = result.sub_category || result.sub_categorey;
    const categoryName = result.category?.translation?.name ||
        result.category?.name ||
        subCategory?.translation?.name ||
        subCategory?.name ||
        "Event";

    return {
        ...result,
        result_type: resultType,
        id: result.id || result._id,
        media_id: result.media_id || (resultType === "event" ? null : result.id || result._id),
        media_type: result.media_type || result.type || resultType,
        price: result.price ?? result.media_price ?? null,
        event_id: result.event_id || result.id || result._id,
        event_slug: eventSlug,
        slug: eventSlug,
        title,
        description,
        translation: {
            ...(result.translation || {}),
            title,
            description: result.translation?.description || description,
        },
        media_url: mediaUrl,
        thumbnail_url: thumbnailUrl || mediaUrl,
        image_url: thumbnailUrl || mediaUrl,
        image_webp_url: null,
        city_name: cityName,
        category_name: categoryName,
    };
};

export const normalizeEvent = (event = {}) => {
    const normalized = normalizeDiscoveryResult(event);

    return {
        ...normalized,
        city: normalized.city_name,
    };
};

export const discoveryResultsToMapEvents = (results = []) => {
    const events = new Map();

    for (const rawResult of results) {
        const result = normalizeDiscoveryResult(rawResult);
        const eventId = result.event_id;

        if (!eventId || events.has(String(eventId))) {
            continue;
        }

        events.set(String(eventId), {
            id: eventId,
            slug: result.event_slug,
            title: result.title,
            description: result.translation?.description || result.description,
            translation: result.translation,
            start_date: result.start_date,
            city: rawResult.city,
            sub_categorey: rawResult.sub_category || rawResult.sub_categorey,
            first_image: rawResult.first_image || {
                full_url: rawResult.thumbnail_url || rawResult.media_url,
            },
            lattitude: result.lattitude,
            langitude: result.langitude,
        });
    }

    return [...events.values()];
};

const buildPaginationPayload = (paginator, fallbackPerPage = 8) => {
    const results = Array.isArray(paginator?.data) ? paginator.data : [];

    return {
        results,
        events: results,
        currentPage: Number(paginator?.current_page ?? 1),
        lastPage: Number(paginator?.last_page ?? 1),
        perPage: Number(paginator?.per_page ?? fallbackPerPage),
        total: Number(paginator?.total ?? results.length),
        from: paginator?.from ?? (results.length ? 1 : null),
        to: paginator?.to ?? results.length,
        type: paginator?.type || "all",
        seed: toPositiveNumber(paginator?.seed),
    };
};

const buildArrayPaginationPayload = (events = []) => ({
    results: events,
    events,
    currentPage: 1,
    lastPage: 1,
    perPage: events.length,
    total: events.length,
    from: events.length ? 1 : null,
    to: events.length,
    type: "all",
    seed: null,
});

export const normalizeDiscoverySearchFilters = normalizeEventSearchFilters;
export const discoveryFiltersToQuery = eventFiltersToQuery;
export const queryToDiscoveryFilters = queryToEventFilters;

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
