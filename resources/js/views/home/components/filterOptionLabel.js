export function getFilterOptionLabel(option) {
    const translatedName = option?.translation?.name;
    const sourceName = option?.name;
    const label = translatedName || sourceName;

    if (label !== undefined && label !== null && String(label).trim()) {
        return String(label);
    }

    return option?.id !== undefined && option?.id !== null
        ? `#${option.id}`
        : "";
}
