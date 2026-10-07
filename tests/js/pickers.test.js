import { describe, it, expect } from 'vitest';
import { pickValue, mergeGallery, mapWith, hasCoords, osmEmbedUrl } from '../../assets/admin/src/pickers.js';

describe('pickValue', () => {
    it('single field takes the picked id', () => {
        expect(pickValue(3, 9, false)).toBe(9);
        expect(pickValue(null, 9, false)).toBe(9);
    });
    it('multiple field appends once and respects max', () => {
        expect(pickValue([1], 2, true)).toEqual([1, 2]);
        expect(pickValue([1, 2], 2, true)).toEqual([1, 2]);
        expect(pickValue([1, 2], 3, true, 2)).toEqual([1, 2]);
        expect(pickValue(null, 5, true)).toEqual([5]);
    });
    it('does not mutate the current value', () => {
        const current = [1];
        pickValue(current, 2, true);
        expect(current).toEqual([1]);
    });
});

describe('mergeGallery', () => {
    it('appends new ids without duplicates up to max', () => {
        expect(mergeGallery([1, 2], [2, 3, 4], 3)).toEqual([1, 2, 3]);
        expect(mergeGallery(undefined, [7])).toEqual([7]);
    });
});

describe('map helpers', () => {
    it('mapWith builds a value and clears empty coordinates instead of storing 0', () => {
        const v = mapWith(null, 'lat', '52.5', 12);
        expect(v).toEqual({ lat: 52.5, lng: null, zoom: 12, address: '' });
        expect(mapWith(v, 'lat', '').lat).toBeNull();
        expect(mapWith(v, 'lng', 'abc').lng).toBeNull();
    });
    it('hasCoords needs both numbers', () => {
        expect(hasCoords({ lat: 0, lng: 0 })).toBe(true);
        expect(hasCoords({ lat: 52, lng: null })).toBe(false);
        expect(hasCoords(null)).toBe(false);
    });
    it('osmEmbedUrl puts a marker on the coordinates', () => {
        const url = osmEmbedUrl({ lat: 52.52, lng: 13.405 });
        expect(url).toContain('openstreetmap.org/export/embed.html');
        expect(url).toContain('marker=52.52,13.405');
        expect(osmEmbedUrl({ lat: null, lng: 1 })).toBe('');
    });
});
