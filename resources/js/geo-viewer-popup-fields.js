export function geoViewerPopupFields(properties, preferredFields = [], showAllAttributes = false) {
    const technicalFields = new Set(['id', 'form_version', 'updated_at']);
    const availableFields = Object.keys(properties)
        .filter(field => ! showAllAttributes || ! technicalFields.has(field));
    const selectedFields = preferredFields.filter(field => availableFields.includes(field));

    if (! showAllAttributes) {
        return selectedFields;
    }

    return [...new Set([
        ...selectedFields,
        ...availableFields,
    ])];
}

export function geoViewerPopupTitle(layerName, properties, fields) {
    const nameFields = ['nombre', 'name', 'denombre', 'nombre_geo', 'institucion', 'entidad', 'titulo'];
    const nameField = nameFields
        .map(name => fields.find(field => field.toLowerCase() === name))
        .find(field => field && typeof properties[field] === 'string' && properties[field].trim() !== '');

    return nameField ? properties[nameField] : layerName;
}
