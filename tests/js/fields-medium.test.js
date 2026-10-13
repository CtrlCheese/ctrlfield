import { describe, it, expect } from 'vitest';
import { normalizeHex, sameColor, solidHex, opacityOf, withOpacity } from '../../assets/admin/src/color.js';
import { filterOptions } from '../../assets/admin/src/select.js';

describe('color', () => {
    it('normalizes what is typed, keeping its case', () => {
        expect(normalizeHex('abc')).toBe('#aabbcc');
        expect(normalizeHex('#FF5733')).toBe('#FF5733');
        expect(normalizeHex('#ff5733ff')).toBe('#ff5733'); // opaque: 6 digits
        expect(normalizeHex('#ff573380')).toBe('#ff573380');
        expect(normalizeHex('red')).toBe('');
        expect(normalizeHex('#12345')).toBe('');
    });

    it('compares without case', () => {
        expect(sameColor('#FF5733', '#ff5733')).toBe(true);
        expect(sameColor('', '')).toBe(false);
    });

    it('splits and joins the opacity', () => {
        expect(solidHex('#FF573380')).toBe('#ff5733');
        expect(opacityOf('#ff573380')).toBe(50);
        expect(opacityOf('#ff5733')).toBe(100);
        expect(withOpacity('#ff5733', 50)).toBe('#ff573380');
        expect(withOpacity('#ff573380', 100)).toBe('#ff5733');
    });
});

describe('select search', () => {
    const options = [['br', 'Brasil'], ['de', 'Alemanha'], ['pt', 'Portugal']];

    it('matches labels and values, every word', () => {
        expect(filterOptions(options, 'al').map((o) => o[0])).toEqual(['de', 'pt']);
        expect(filterOptions(options, 'de')).toEqual([['de', 'Alemanha']]);
        expect(filterOptions(options, '')).toHaveLength(3);
    });
});

import { isEmbeddable } from '../../assets/admin/src/oembed.js';

describe('oembed', () => {
    it('asks only about web addresses', () => {
        expect(isEmbeddable('https://www.youtube.com/watch?v=abc')).toBe(true);
        expect(isEmbeddable('https://vimeo.com/123')).toBe(true);
        expect(isEmbeddable('youtube')).toBe(false);
        expect(isEmbeddable('')).toBe(false);
    });
});

import { importSummary } from '../../assets/admin/src/inspector.js';

describe('inspector CSV import', () => {
    it('summarises the importer stats', () => {
        expect(importSummary({ updated: 3, created: 1, skipped: 0, errors: [] })).toBe('3 updated · 1 created');
        expect(importSummary({ updated: 0, created: 0, skipped: 0, errors: [] })).toBe('nothing changed');
        expect(importSummary({ updated: 1, created: 0, skipped: 2, errors: ['Row 4: no ID'] })).toBe('1 updated · 2 skipped — Row 4: no ID');
    });
});
