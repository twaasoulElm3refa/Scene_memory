// Palette for the base map only. Event markers and the surrounding UI have their own styles.
export const SCEMORY_MAP_COLORS = Object.freeze({
    primary: '#114E8C',
    land: '#FFFFFF',
    water: '#E7F2FC',
    border: '#98BADC',
    road: '#D9E8F6',
    label: '#173E66',
    secondary: '#BFD7EE',
    urban: '#F3F8FD',
});

// Retain zoom expressions from OpenFreeMap's Liberty style while changing their colors.
function recolor(value, color) {
    if (Array.isArray(value)) return value.map(part => recolor(part, color));
    if (typeof value === 'string' && /^(#|rgba?\(|hsla?\()/i.test(value)) return color;
    return value;
}

function setPaintColor(layer, property, color) {
    layer.paint ??= {};
    const current = layer.paint[property];
    layer.paint[property] = current === undefined ? color : recolor(current, color);
}

/** Recolor the fetched Liberty style before MapLibre loads it. Sources and geometry stay intact. */
export function applyScemoryMapStyle(style) {
    if (!style?.layers) return style;

    const colors = SCEMORY_MAP_COLORS;

    for (const layer of style.layers) {
        const sourceLayer = layer['source-layer'];

        if (layer.type === 'background') {
            setPaintColor(layer, 'background-color', colors.land);
        } else if (layer.type === 'raster' && layer.id === 'natural_earth') {
            // The shaded Natural Earth raster supplies the original green/yellow terrain.
            // Keep the source and layer definition, but let vector water and white land show.
            layer.paint['raster-opacity'] = 0;
        } else if (layer.type === 'fill') {
            const fill = sourceLayer === 'water' ? colors.water
                : layer.id === 'landcover_ice' || layer.id === 'landcover_sand' ? colors.land
                : colors.urban;
            // Liberty uses colored sprite patterns for wetlands and pedestrian areas.
            delete layer.paint['fill-pattern'];
            setPaintColor(layer, 'fill-color', fill);
            if (layer.paint['fill-outline-color'] !== undefined) {
                setPaintColor(layer, 'fill-outline-color', colors.secondary);
            }
        } else if (layer.type === 'fill-extrusion' && sourceLayer === 'building') {
            setPaintColor(layer, 'fill-extrusion-color', colors.urban);
        } else if (layer.type === 'line') {
            if (sourceLayer === 'boundary') {
                const country = layer.id === 'boundary_2' || layer.id === 'boundary_disputed';
                setPaintColor(layer, 'line-color', country ? colors.border : colors.secondary);
                layer.paint['line-width'] = country
                    ? ['interpolate', ['linear'], ['zoom'], 3, 0.6, 5, 0.8, 12, 1.4]
                    : ['interpolate', ['linear'], ['zoom'], 5, 0.4, 11, 0.8];
            } else if (sourceLayer === 'waterway' || sourceLayer === 'park') {
                setPaintColor(layer, 'line-color', colors.secondary);
            } else if (sourceLayer === 'transportation' || sourceLayer === 'aeroway') {
                setPaintColor(layer, 'line-color', /casing|rail/.test(layer.id) ? colors.secondary : colors.road);
            }
        } else if (layer.type === 'symbol' && layer.layout?.['text-field']) {
            const isPoi = sourceLayer === 'poi';
            setPaintColor(layer, 'text-color', isPoi ? colors.primary : colors.label);
            setPaintColor(layer, 'text-halo-color', sourceLayer === 'water_name' || sourceLayer === 'waterway'
                ? colors.water : colors.land);
            layer.paint['text-halo-width'] ??= 1;
        }
    }

    return style;
}
