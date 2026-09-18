export function geoViewerPopupFields(properties, preferredFields = [], showAllAttributes = false) {
    const availableFields = Object.keys(properties);

    if (! showAllAttributes) {
        return preferredFields.filter(field => Object.hasOwn(properties, field));
    }

    return [...new Set([
        ...preferredFields.filter(field => Object.hasOwn(properties, field)),
        ...availableFields,
    ])];
}
