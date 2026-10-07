import { describe, it, expect } from 'vitest';
import { moveItem, itemKey } from '../../assets/admin/src/sortable.js';
import { rowSummary } from '../../assets/admin/src/rows.js';

describe('moveItem', () => {
    it('moves down and up in place', () => {
        const list = ['a', 'b', 'c', 'd'];
        expect(moveItem(list, 0, 2)).toBe(true);
        expect(list).toEqual(['b', 'c', 'a', 'd']);
        moveItem(list, 3, 0);
        expect(list).toEqual(['d', 'b', 'c', 'a']);
    });

    it('ignores no-ops and out-of-range moves', () => {
        const list = ['a', 'b'];
        expect(moveItem(list, 1, 1)).toBe(false);
        expect(moveItem(list, -1, 0)).toBe(false);
        expect(moveItem(list, 0, 2)).toBe(false);
        expect(moveItem(undefined, 0, 1)).toBe(false);
        expect(list).toEqual(['a', 'b']);
    });

    it('keeps nested data with the moved row', () => {
        const rows = [{ name: 'A', dishes: [{ n: 1 }] }, { name: 'B', dishes: [] }];
        moveItem(rows, 0, 1);
        expect(rows[1]).toEqual({ name: 'A', dishes: [{ n: 1 }] });
    });
});

describe('itemKey', () => {
    it('gives an object the same key for life, even after moving', () => {
        const a = { x: 1 };
        const b = { x: 1 };
        const list = [a, b];
        const ka = itemKey(a);
        moveItem(list, 0, 1);
        expect(itemKey(list[1])).toBe(ka);
        expect(itemKey(b)).not.toBe(ka);
    });

    it('keys values by themselves', () => {
        expect(itemKey(26)).toBe('v:26');
        expect(itemKey('26')).toBe('v:26');
    });

    it('uses the raw object behind a proxy', () => {
        const raw = { x: 1 };
        const proxy = new Proxy(raw, {});
        expect(itemKey(proxy, () => raw)).toBe(itemKey(raw));
    });
});

describe('rowSummary', () => {
    it('uses the first text value', () => {
        expect(rowSummary({ photo: 12, name: 'Ana Souza', role: 'Dev' })).toBe('Ana Souza');
    });

    it('skips internals, HTML and empty values', () => {
        expect(rowSummary({ _layout: 'hero', title: '', body: '<p>Hello <b>world</b></p>' })).toBe('Hello world');
    });

    it('reads link titles and truncates long text', () => {
        expect(rowSummary({ cta: { title: 'Buy now', url: 'https://x.test' } })).toBe('Buy now');
        expect(rowSummary({ t: 'x'.repeat(100) })).toHaveLength(70);
    });

    it('returns empty for empty rows', () => {
        expect(rowSummary({})).toBe('');
        expect(rowSummary(null)).toBe('');
    });
});
