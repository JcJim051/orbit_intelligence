const defaultPalette = ['#dcfce7', '#86efac', '#4ade80', '#16a34a', '#166534'];

export function buildChoroplethScale(features, configuration = {}) {
    const property = configuration.property ?? 'valor';
    const palette = Array.isArray(configuration.palette) && configuration.palette.length
        ? configuration.palette
        : defaultPalette;
    const noDataColor = configuration.noDataColor ?? '#e2e8f0';
    const values = features
        .flatMap(feature => {
            const value = feature?.properties?.[property];
            const numericValue = Number(value);

            return value === null || value === undefined || value === '' || ! Number.isFinite(numericValue)
                ? []
                : [numericValue];
        })
        .sort((left, right) => left - right);
    const thresholds = [];

    for (let index = 1; index < palette.length && values.length; index += 1) {
        const quantileIndex = Math.min(values.length - 1, Math.ceil(index * values.length / palette.length) - 1);
        thresholds.push(values[quantileIndex]);
    }

    return {
        property,
        thresholds,
        palette,
        noDataColor,
        color(value) {
            const numericValue = Number(value);
            if (value === null || value === '' || ! Number.isFinite(numericValue)) {
                return noDataColor;
            }

            const thresholdIndex = thresholds.findIndex(threshold => numericValue <= threshold);

            return palette[thresholdIndex === -1 ? palette.length - 1 : thresholdIndex];
        },
    };
}

export function choroplethLegendEntries(scale) {
    if (! scale.thresholds.length) {
        return [];
    }

    return scale.palette.map((color, index) => ({
        color,
        minimum: index === 0 ? null : scale.thresholds[index - 1],
        maximum: index < scale.thresholds.length ? scale.thresholds[index] : null,
    }));
}
