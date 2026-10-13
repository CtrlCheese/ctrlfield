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
