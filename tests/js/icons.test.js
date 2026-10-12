import { describe, it, expect } from 'vitest';
import { parseIcon, iconValue, normalizeLibraries, filterIcons } from '../../assets/admin/src/icons.js';

describe('parseIcon / iconValue', () => {
    it('reads both libraries', () => {
        expect(parseIcon('lucide:arrow-right')).toEqual({ lib: 'lucide', name: 'arrow-right' });
        expect(parseIcon('dashicons-admin-site')).toEqual({ lib: 'dashicons', name: 'admin-site' });
        expect(parseIcon('')).toEqual({ lib: '', name: '' });
    });

    it('round-trips', () => {
        expect(iconValue('lucide', 'phone')).toBe('lucide:phone');
        expect(iconValue('dashicons', 'phone')).toBe('dashicons-phone');
    });
});

describe('normalizeLibraries', () => {
    it('defaults to every library, Lucide first', () => {
        expect(normalizeLibraries(undefined)).toEqual(['lucide', 'dashicons']);
        expect(normalizeLibraries(['fontawesome'])).toEqual(['lucide', 'dashicons']);
    });

    it('keeps the chosen ones in display order', () => {
        expect(normalizeLibraries('dashicons')).toEqual(['dashicons']);
        expect(normalizeLibraries(['dashicons', 'lucide'])).toEqual(['lucide', 'dashicons']);
    });
});

describe('filterIcons', () => {
    const icons = [
        { lib: 'lucide', name: 'arrow-right', tags: 'forward direction' },
        { lib: 'lucide', name: 'phone', tags: 'call contact' },
        { lib: 'dashicons', name: 'phone', tags: '' },
        { lib: 'lucide', name: 'mail', tags: 'email contact' },
    ];

    it('returns everything without a query', () => {
        expect(filterIcons(icons, '  ')).toHaveLength(4);
    });

    it('matches names before tags', () => {
        const names = filterIcons(icons, 'contact').map((i) => i.name);
        expect(names).toEqual(['phone', 'mail']);
        expect(filterIcons(icons, 'phone').map((i) => i.lib)).toEqual(['lucide', 'dashicons']);
    });

    it('ranks exact and whole-word names first', () => {
        const set = [
            { lib: 'lucide', name: 'headphones', tags: '' },
            { lib: 'lucide', name: 'phone-call', tags: '' },
            { lib: 'lucide', name: 'smartphone', tags: '' },
            { lib: 'lucide', name: 'phone', tags: '' },
            { lib: 'lucide', name: 'mic', tags: 'phone' },
        ];
        expect(filterIcons(set, 'phone').map((i) => i.name)).toEqual(['phone', 'phone-call', 'headphones', 'smartphone', 'mic']);
    });

    it('needs every word, with spaces for dashes', () => {
        expect(filterIcons(icons, 'arrow right').map((i) => i.name)).toEqual(['arrow-right']);
        expect(filterIcons(icons, 'arrow left')).toHaveLength(0);
    });
});
