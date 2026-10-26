import { describe, expect, it } from 'vitest';
import { applyScemoryMapStyle, SCEMORY_MAP_COLORS as colors } from '../../resources/js/services/MapService/scemoryMapStyle';

const layer = (id, type, sourceLayer, paint, extra = {}) => ({
    id,
    type,
    source: 'openmaptiles',
    'source-layer': sourceLayer,
    paint,
    ...extra,
});

describe('Scemory base map style', () => {
    it('brands the Liberty layers without changing tile sources, filters, labels, or layer order', () => {
        const style = {
            version: 8,
            sources: { openmaptiles: { type: 'vector', url: 'https://tiles.openfreemap.org/planet' } },
            sprite: 'https://tiles.openfreemap.org/sprites/ofm_f384/ofm',
            glyphs: 'https://tiles.openfreemap.org/fonts/{fontstack}/{range}.pbf',
            layers: [
                { id: 'background', type: 'background', paint: { 'background-color': '#f8f4f0' } },
                { id: 'natural_earth', type: 'raster', source: 'ne2_shaded', paint: { 'raster-opacity': ['interpolate', ['linear'], ['zoom'], 0, 0.6, 6, 0.1] } },
                layer('water', 'fill', 'water', { 'fill-color': 'rgb(158,189,255)' }),
                layer('landcover_wetland', 'fill', 'landcover', { 'fill-pattern': 'wetland_bg_11', 'fill-opacity': 0.8 }, { filter: ['==', ['get', 'class'], 'wetland'] }),
                layer('road_motorway', 'line', 'transportation', { 'line-color': ['interpolate', ['linear'], ['zoom'], 5, '#fc8', 6, '#fea'], 'line-width': ['interpolate', ['linear'], ['zoom'], 5, 1, 20, 18] }),
                layer('road_motorway_casing', 'line', 'transportation', { 'line-color': '#e9ac77' }),
                layer('building', 'fill', 'building', { 'fill-color': 'hsl(35,8%,85%)' }),
                layer('boundary_2', 'line', 'boundary', { 'line-color': 'hsl(248,1%,41%)' }),
                layer('label_country_1', 'symbol', 'place', { 'text-color': '#000', 'text-halo-color': '#fff' }, { layout: { 'text-field': ['get', 'name:ar'] } }),
                layer('poi_r1', 'symbol', 'poi', { 'text-color': '#666' }, { layout: { 'text-field': ['get', 'name:en'] } }),
            ],
        };
        const original = structuredClone(style);

        applyScemoryMapStyle(style);
        const paint = id => style.layers.find(item => item.id === id).paint;

        expect(paint('background')['background-color']).toBe(colors.land);
        expect(paint('natural_earth')['raster-opacity']).toBe(0);
        expect(paint('water')['fill-color']).toBe(colors.water);
        expect(paint('landcover_wetland')).toMatchObject({ 'fill-color': colors.urban, 'fill-opacity': 0.8 });
        expect(paint('landcover_wetland')).not.toHaveProperty('fill-pattern');
        expect(paint('road_motorway')['line-color']).toEqual(['interpolate', ['linear'], ['zoom'], 5, colors.road, 6, colors.road]);
        expect(paint('road_motorway')['line-width']).toEqual(original.layers[4].paint['line-width']);
        expect(paint('road_motorway_casing')['line-color']).toBe(colors.secondary);
        expect(paint('building')['fill-color']).toBe(colors.urban);
        expect(paint('boundary_2')['line-color']).toBe(colors.border);
        expect(paint('label_country_1')['text-color']).toBe(colors.label);
        expect(paint('poi_r1')['text-color']).toBe(colors.primary);
        expect(style.sources).toEqual(original.sources);
        expect(style.sprite).toBe(original.sprite);
        expect(style.glyphs).toBe(original.glyphs);
        expect(style.layers.map(({ id }) => id)).toEqual(original.layers.map(({ id }) => id));
        expect(style.layers.map(({ filter, layout }) => ({ filter, layout })))
            .toEqual(original.layers.map(({ filter, layout }) => ({ filter, layout })));
    });
});
