import { describe, it, expect } from 'vitest';
import { chosenIds, isFull, withItem, withoutItem, belowMin } from '../../assets/admin/src/picker.js';

describe('picker values', () => {
    it('reads one or several ids', () => {
        expect(chosenIds([3, '5', 0], true)).toEqual([3, 5]);
        expect(chosenIds(7, false)).toEqual([7]);
        expect(chosenIds(null, false)).toEqual([]);
        expect(chosenIds(0, false)).toEqual([]);
    });

    it('hides the box at the maximum (one item for a single picker)', () => {
        expect(isFull([1, 2, 3], true, 3)).toBe(true);
        expect(isFull([1, 2], true, 3)).toBe(false);
        expect(isFull([1, 2, 3], true, null)).toBe(false);
        expect(isFull(4, false, null)).toBe(true);
        expect(isFull(null, false, null)).toBe(false);
    });

    it('never adds past the maximum or twice', () => {
        expect(withItem([1, 2], 3, true, 3)).toEqual([1, 2, 3]);
        expect(withItem([1, 2, 3], 4, true, 3)).toEqual([1, 2, 3]);
        expect(withItem([1, 2], 2, true, null)).toEqual([1, 2]);
        expect(withItem(1, 9, false, 1)).toBe(9);
    });

    it('removes', () => {
        expect(withoutItem([1, 2, 3], 2, true)).toEqual([1, 3]);
        expect(withoutItem(5, 5, false)).toBeNull();
    });

    it('asks for more under the minimum, once something was chosen', () => {
        expect(belowMin([1], true, 2)).toBe(true);
        expect(belowMin([1, 2], true, 2)).toBe(false);
        expect(belowMin([], true, 2)).toBe(false);
        expect(belowMin([1], true, null)).toBe(false);
    });
});
