import { describe, it, expect } from 'vitest';
import { buildLink, linkKind, defaultStyle, anchorItems } from '../../assets/admin/src/link.js';

const form = (over = {}) => ({ url: '', title: '', newTab: false, style: '', ...over });

describe('buildLink', () => {
    it('keeps the id of a picked post while the URL is unchanged', () => {
        const picked = { kind: 'post', id: 12, object: 'page', url: 'https://x.test/about/' };
        expect(buildLink(form({ url: 'https://x.test/about/', title: 'About' }), picked)).toEqual({
            url: 'https://x.test/about/', title: 'About', target: '_self', type: 'post', id: 12, object: 'page',
        });
    });

    it('drops the id once the URL is edited by hand', () => {
        const picked = { kind: 'post', id: 12, object: 'page', url: 'https://x.test/about/' };
        expect(buildLink(form({ url: 'https://other.test/' }), picked)).toEqual({
            url: 'https://other.test/', title: '', target: '_self', type: 'external',
        });
    });

    it('recognises anchors, new tab and style', () => {
        expect(buildLink(form({ url: '#contact', newTab: true, style: 'primary' }), null)).toEqual({
            url: '#contact', title: '', target: '_blank', type: 'anchor', style: 'primary',
        });
    });

    it('an empty URL has no type', () => {
        expect(buildLink(form(), null)).toEqual({ url: '', title: '', target: '_self' });
    });
});

describe('linkKind', () => {
    it('names the kind shown before the URL', () => {
        expect(linkKind({ url: '#top' })).toBe('Anchor');
        expect(linkKind({ url: 'https://x.test', type: 'external' })).toBe('External');
        expect(linkKind({ url: 'https://x.test/a/', type: 'post', object: 'page' }, { typeLabel: 'Page' })).toBe('Page');
        expect(linkKind({ url: 'https://x.test/a/', type: 'post', object: 'page' })).toBe('page');
    });
});

describe('buttons: default styles, icons, typed paths', () => {
    const config = { styles: { primary: 'Primary', outline: 'Outline' }, defaultStyles: ['primary', 'outline'] };

    it('gives a new link the style of its position', () => {
        expect(defaultStyle(config, 0)).toBe('primary');
        expect(defaultStyle(config, 1)).toBe('outline');
        expect(defaultStyle(config, 2)).toBe('primary'); // past the list: the first style
        expect(defaultStyle({ styles: {} }, 0)).toBe('');
        expect(defaultStyle({ styles: { ghost: 'Ghost' }, defaultStyles: ['primary'] }, 0)).toBe('ghost');
    });

    it('stores the icon and its side', () => {
        const link = buildLink(form({ url: 'https://x.test', icon: 'lucide:arrow-right', iconPosition: 'right' }), null);
        expect(link.icon).toBe('lucide:arrow-right');
        expect(link.icon_position).toBe('right');
        expect(buildLink(form({ url: 'https://x.test' }), null)).not.toHaveProperty('icon');
    });

    it('marks a typed path as this site', () => {
        expect(buildLink(form({ url: '/services/#price' }), null).type).toBe('internal');
        expect(buildLink(form({ url: '//cdn.test/x' }), null).type).toBe('external');
        expect(linkKind({ url: '/services/', type: 'internal' })).toBe('This site');
    });
});

describe('anchorItems', () => {
    const body = {
        results: [{ anchor: 'form', label: '#form' }],
        site: [{ anchor: 'price', id: 7, object: 'page', title: 'Services', url: 'https://x.test/services/#price' }],
    };

    it('lists this page first, then other pages as post links with the fragment', () => {
        const items = anchorItems(body);
        expect(items.map((i) => i.url)).toEqual(['#form', 'https://x.test/services/#price']);
        expect(items[1]).toMatchObject({ kind: 'post', id: 7, object: 'page', title: 'Services', typeLabel: '#price' });
    });

    it('searches anchors and page titles', () => {
        expect(anchorItems(body, 'serv').map((i) => i.url)).toEqual(['https://x.test/services/#price']);
        expect(anchorItems(body, 'form').map((i) => i.url)).toEqual(['#form']);
    });

    it('a picked anchor of another page is stored as a post link', () => {
        const picked = anchorItems(body)[1];
        const link = buildLink(form({ url: picked.url }), picked);
        expect(link).toMatchObject({ type: 'post', id: 7, object: 'page', url: 'https://x.test/services/#price' });
    });
});
