import { describe, it, expect } from 'vitest';
import { editorSettings } from '../../assets/admin/src/wysiwyg.js';

const el = (dataset = {}) => ({ dataset });
const preInit = {
    mceInit: { tpl: { selector: '#tpl', wp_skip_init: true, body_class: 'tpl', style_formats: '[{"title":"Button"}]', toolbar1: 'bold', plugins: 'wplink' } },
    qtInit: { tpl: { id: 'tpl', buttons: 'strong,em' } },
};
const config = {
    template: 'tpl',
    toolbars: {
        full: { toolbar1: 'formatselect,styleselect,link', toolbar2: '', toolbar3: '', toolbar4: '' },
        basic: { toolbar1: 'bold,italic', toolbar2: '', toolbar3: '', toolbar4: '' },
    },
};

describe('editorSettings', () => {
    it('starts from the theme template: formats and plugins, not its selector', () => {
        const s = editorSettings(el(), preInit, config);
        expect(s.tinymce.style_formats).toBe('[{"title":"Button"}]');
        expect(s.tinymce.plugins).toBe('wplink');
        expect(s.tinymce.selector).toBeUndefined();
        expect(s.tinymce.wp_skip_init).toBeUndefined();
        expect(s.quicktags).toEqual({ buttons: 'strong,em' });
    });

    it('uses the named toolbar of the field', () => {
        expect(editorSettings(el(), preInit, config).tinymce.toolbar1).toBe('formatselect,styleselect,link');
        expect(editorSettings(el({ toolbar: 'basic' }), preInit, config).tinymce.toolbar1).toBe('bold,italic');
        expect(editorSettings(el({ toolbar: 'nope' }), preInit, config).tinymce.toolbar1).toBe('formatselect,styleselect,link');
    });

    it('hides Add Media when asked', () => {
        expect(editorSettings(el({ media: '0' }), preInit, config).mediaButtons).toBe(false);
        expect(editorSettings(el(), preInit, config).mediaButtons).toBe(true);
    });

    it('still works without a template', () => {
        const s = editorSettings(el(), {}, {});
        expect(s.tinymce.toolbar1).toContain('formatselect');
        expect(s.tinymce.wpautop).toBe(true);
    });
});
