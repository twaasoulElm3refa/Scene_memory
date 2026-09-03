export function getLocationName(location) {
    return location?.translation?.name || location?.name || `#${location?.id}`;
}

export function normalizeCitySearch(value) {
    return String(value || "").trim().replace(/\s+/g, " ");
}

export function filterCityOptions(cities, value, locale = "en") {
    return filterLocationOptions(cities, value, locale);
}

export function filterLocationOptions(locations, value, locale = "en") {
    const search = normalizeCitySearch(value).toLocaleLowerCase(locale);

    if (!search) return locations;

    return locations.filter((location) => {
        const translatedName = getLocationName(location).toLocaleLowerCase(locale);
        const storedName = String(location.name || "").toLocaleLowerCase(locale);

        return translatedName.includes(search) || storedName.includes(search);
    });
}

export function cityNameExists(cities, value, locale = "en") {
    const search = normalizeCitySearch(value);

    if (!search) return false;

    return cities.some((city) =>
        getLocationName(city).localeCompare(search, locale, { sensitivity: "base" }) === 0
        || String(city.name || "").localeCompare(search, locale, { sensitivity: "base" }) === 0
    );
}
