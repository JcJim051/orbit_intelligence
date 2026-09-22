const normalizedProperty = (properties, key) => Object.entries(properties || {})
    .find(([property]) => property.toLocaleLowerCase('es') === key)?.[1];

export function territoryLabel(properties = {}, fallback = '') {
    const candidates = [
        'mp_nombre',
        'nombre_municipio',
        'nom_mpio',
        'municipio',
        'nombre',
        'name',
    ];

    for (const candidate of candidates) {
        const value = normalizedProperty(properties, candidate);
        if (value !== undefined && value !== null && String(value).trim() !== '') return String(value).trim();
    }

    return String(fallback ?? '').trim();
}
