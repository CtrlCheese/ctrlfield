import { describe, it, expect } from 'vitest';
import { cloneRow, canAdd, canAddLayout } from '../../assets/admin/src/rows.js';

describe('cloneRow', () => {
    it('copies deeply and gives every flexible section a new id', () => {
        const row = { _layout: 'hero', _instance_id: 'a1', title: 'Hi', items: [{ _layout: 'card', _instance_id: 'b2', text: 'x' }] };
        const copy = cloneRow(row);
        expect(copy.title).toBe('Hi');
        expect(copy._instance_id).not.toBe('a1');
        expect(copy.items[0]._instance_id).not.toBe('b2');
        copy.items[0].text = 'changed';
        expect(row.items[0].text).toBe('x');
    });

    it('leaves repeater rows without ids as they are', () => {
        expect(cloneRow({ name: 'Ana' })).toEqual({ name: 'Ana' });
    });
});

describe('limits', () => {
    it('canAdd respects the maximum', () => {
        expect(canAdd([1, 2], 2)).toBe(false);
        expect(canAdd([1], 2)).toBe(true);
        expect(canAdd([1, 2, 3], null)).toBe(true);
        expect(canAdd(undefined, 1)).toBe(true);
    });

    it('canAddLayout checks the layout limit and the field limit', () => {
        const list = [{ _layout: 'hero' }, { _layout: 'text' }];
        expect(canAddLayout(list, 'hero', { hero: 1 })).toBe(false);
        expect(canAddLayout(list, 'text', { hero: 1 })).toBe(true);
        expect(canAddLayout(list, 'text', {}, 2)).toBe(false);
    });
});
