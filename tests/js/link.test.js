import { describe, it, expect } from 'vitest';
import { buildLink, linkKind } from '../../assets/admin/src/link.js';

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
