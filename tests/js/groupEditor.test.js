import { describe, it, expect, vi } from 'vitest';
import {
    slugify, optionsToText, textToOptions, hydrateField, dehydrateField, newField, fieldGroupEditor,
} from '../../assets/admin/src/groupEditor.js';

const types = {
    text: { label: 'Text', settings: ['required'] },
    group: { label: 'Group', settings: ['fields'] },
    select: { label: 'Select', settings: ['options'] },
};

function editor(group = {}) {
    return fieldGroupEditor({
        group: { key: '', title: '', location: [], fields: [], ...group },
        types,
        locationChoices: { post_type: { post: 'Post' } },
        i18n: { confirmRemove: 'Remove?' },
    });
}

describe('slugify', () => {
    it('makes valid keys', () => {
        expect(slugify('Título do Herói!')).toBe('titulo_do_heroi');
        expect(slugify('  Hello   World ')).toBe('hello_world');
        expect(slugify('123 abc')).toBe('f_123_abc');
        expect(slugify('***')).toBe('');
        expect(slugify('a'.repeat(100))).toHaveLength(64);
    });
});

describe('options text', () => {
    it('round-trips "value : label" lines', () => {
        const pairs = [['a', 'Alpha'], ['b', 'b'], ['c', 'C : with colon']];
        expect(textToOptions(optionsToText(pairs))).toEqual(pairs);
    });

    it('ignores blank lines and fills the label', () => {
        expect(textToOptions('x\n\n  y : Why  \n')).toEqual([['x', 'x'], ['y', 'Why']]);
    });
});

describe('hydrate / dehydrate', () => {
    it('drops UI-only properties and empty lists', () => {
        const stored = {
            key: 'box', type: 'group', label: 'Box',
            fields: [{ key: 'inner', type: 'select', options: [['a', 'A']] }],
        };
        const out = dehydrateField(hydrateField(stored));
        expect(out).toEqual(stored);
    });

    it('keeps the condition only when a field is chosen', () => {
        const f = hydrateField({ key: 'a', type: 'text' });
        expect(dehydrateField(f).visibleWhen).toBeUndefined();
        f._cond = { field: 'b', operator: '==', value: '1' };
        expect(dehydrateField(f).visibleWhen).toEqual({ field: 'b', operator: '==', value: '1' });
    });

    it('new fields derive their key from the label, loaded ones do not', () => {
        expect(newField()._keyTouched).toBe(false);
        expect(hydrateField({ key: 'x', type: 'text' })._keyTouched).toBe(true);
    });
});

describe('fieldGroupEditor', () => {
    it('derives keys until the user edits them', () => {
        const ed = editor();
        ed.group.title = 'Home Hero';
        ed.titleChanged();
        expect(ed.group.key).toBe('home_hero');
        ed.keyTouched = true;
        ed.group.title = 'Other';
        ed.titleChanged();
        expect(ed.group.key).toBe('home_hero');

        ed.addField();
        const f = ed.currentFields[0];
        f.label = 'Sub Title';
        ed.labelChanged(f);
        expect(f.key).toBe('sub_title');
    });

    it('navigates into sub-fields', () => {
        const ed = editor({ fields: [{ key: 'box', type: 'group', label: 'Box', fields: [] }] });
        ed.enter(0);
        ed.addField();
        expect(ed.group.fields[0].fields).toHaveLength(1);
        expect(ed.breadcrumb).toEqual([{ label: 'Box', depth: 1 }]);
        ed.goTo(0);
        expect(ed.currentFields).toBe(ed.group.fields);
    });

    it('moves and removes fields', () => {
        const ed = editor({ fields: [{ key: 'a', type: 'text' }, { key: 'b', type: 'text' }] });
        ed.move(0, 1);
        expect(ed.currentFields.map((f) => f.key)).toEqual(['b', 'a']);
        ed.move(1, 1); // out of range: no-op
        expect(ed.currentFields.map((f) => f.key)).toEqual(['b', 'a']);

        vi.stubGlobal('window', { confirm: () => true });
        ed.removeField(0);
        expect(ed.currentFields.map((f) => f.key)).toEqual(['a']);
        vi.unstubAllGlobals();
    });

    it('serializes clean JSON', () => {
        const ed = editor({
            key: 'g', title: 'G', location: [{ key: 'post_type', operator: '==', value: 'post' }],
            fields: [{ key: 's', type: 'select', options: [['a', 'A']] }],
        });
        ed.currentFields[0]._optionsText = 'a : A\nb';
        ed.serialize();
        const out = JSON.parse(ed.payload);
        expect(out.fields[0]).toEqual({ key: 's', type: 'select', options: [['a', 'A'], ['b', 'b']] });
        expect(JSON.stringify(out)).not.toContain('_uid');
    });

    it('knows which settings a type has', () => {
        const ed = editor();
        expect(ed.has({ type: 'select' }, 'options')).toBe(true);
        expect(ed.has({ type: 'text' }, 'options')).toBe(false);
        expect(ed.choicesFor('post_type')).toEqual({ post: 'Post' });
        expect(ed.choicesFor('post')).toBeNull();
    });
});
