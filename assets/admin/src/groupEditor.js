// Field group editor (CtrlField → Field Groups). The server validates
// everything again (JsonGroup::normalize); these helpers only shape the state.

let uidCounter = 0;
const uid = () => `f${++uidCounter}`;

/** "Hero Title!" → "hero_title" (a valid field / group key). */
export function slugify(text) {
    const s = String(text ?? '')
        .normalize('NFD').replace(/[̀-ͯ]/g, '')
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, '_')
        .replace(/^_+|_+$/g, '')
        .slice(0, 64);
    return /^[a-z]/.test(s) ? s : (s ? `f_${s}`.slice(0, 64) : '');
}

/** [[value, label], …] → "value : label" lines. */
export function optionsToText(options) {
    return (options ?? []).map(([v, l]) => (l && l !== v ? `${v} : ${l}` : v)).join('\n');
}

/** "value : label" lines (or just "value") → [[value, label], …]. */
export function textToOptions(text) {
    return String(text ?? '').split('\n').map((line) => {
        const i = line.indexOf(' : ');
        const value = (i === -1 ? line : line.slice(0, i)).trim();
        const label = (i === -1 ? value : line.slice(i + 3)).trim();
        return [value, label || value];
    }).filter(([v]) => v !== '');
}

/** Stored field → editor field (adds UI-only properties, recursively). */
export function hydrateField(field, open = false) {
    const f = { ...field };
    f._uid = uid();
    f._open = open;
    f._keyTouched = true; // existing keys are never re-derived from the label
    f._optionsText = optionsToText(f.options);
    f._cond = f.visibleWhen ? { ...f.visibleWhen } : { field: '', operator: '==', value: '' };
    f.fields = (f.fields ?? []).map((sub) => hydrateField(sub));
    f.layouts = (f.layouts ?? []).map((l) => hydrateLayout(l));
    f.postType = f.postType ?? [];
    f.roles = f.roles ?? [];
    f.taxonomies = f.taxonomies ?? [];
    f._stylesText = optionsToText(f.styles);
    return f;
}

/** Stored flexible content layout → editor layout. */
export function hydrateLayout(layout = {}) {
    return {
        key: layout.key ?? '',
        label: layout.label ?? '',
        max: layout.max ?? '',
        _uid: uid(),
        _keyTouched: Boolean(layout.key),
        fields: (layout.fields ?? []).map((f) => hydrateField(f)),
    };
}

export function newField(type = 'text') {
    return { ...hydrateField({ key: '', type, label: '' }, true), _keyTouched: false };
}

/** Editor field → stored field (drops UI-only properties, recursively). */
export function dehydrateField(f) {
    const out = {};
    for (const [k, v] of Object.entries(f)) {
        if (!k.startsWith('_')) out[k] = v;
    }
    out.options = textToOptions(f._optionsText);
    out.styles = textToOptions(f._stylesText);
    if (f._cond?.field) out.visibleWhen = { ...f._cond };
    else delete out.visibleWhen;
    out.fields = (f.fields ?? []).map(dehydrateField);
    out.layouts = (f.layouts ?? []).map((l) => {
        const layout = { key: l.key, label: l.label, fields: (l.fields ?? []).map(dehydrateField) };
        if (Number(l.max) > 0) layout.max = Number(l.max);
        return layout;
    });
    for (const k of ['options', 'fields', 'layouts', 'postType', 'roles', 'taxonomies', 'styles']) {
        if (Array.isArray(out[k]) && out[k].length === 0) delete out[k];
    }
    return out;
}

function stepInto(list, step) {
    const field = list[step.i];
    return step.l !== undefined ? field.layouts[step.l].fields : field.fields;
}

export function fieldGroupEditor(config) {
    return {
        config,
        group: {
            ...config.group,
            location: (config.group.location ?? []).map((r) => ({ ...r })),
            locationAny: (config.group.locationAny ?? []).map((r) => ({ ...r })),
            fields: (config.group.fields ?? []).map((f) => hydrateField(f)),
        },
        keyTouched: Boolean(config.group.key),
        /**
         * Steps into nested field lists: { i } = the sub-fields of field i,
         * { i, l } = the fields of layout l of flexible content field i. [] = top level.
         */
        path: [],
        payload: '',

        get currentFields() {
            let list = this.group.fields;
            for (const step of this.path) list = stepInto(list, step);
            return list;
        },
        get breadcrumb() {
            const crumbs = [];
            let list = this.group.fields;
            this.path.forEach((step, depth) => {
                const field = list[step.i];
                const name = field.label || field.key || '…';
                const layout = step.l !== undefined ? field.layouts[step.l] : null;
                crumbs.push({ label: layout ? `${name}: ${layout.label || layout.key || '…'}` : name, depth: depth + 1 });
                list = stepInto(list, step);
            });
            return crumbs;
        },

        has(field, setting) {
            return (this.config.types[field.type]?.settings ?? []).includes(setting);
        },
        typeLabel(type) {
            return this.config.types[type]?.label ?? type;
        },
        siblings(field) {
            return this.currentFields.filter((f) => f !== field && f.key);
        },
        returnFormats(field) {
            return this.config.returnFormats?.[field.type] ?? null;
        },
        choicesFor(param) {
            return this.config.locationChoices[param] ?? null;
        },

        titleChanged() {
            if (!this.keyTouched) this.group.key = slugify(this.group.title);
        },
        labelChanged(field) {
            if (!field._keyTouched) field.key = slugify(field.label);
        },

        addField() {
            this.currentFields.forEach((f) => { f._open = false; });
            this.currentFields.push(newField());
        },
        removeField(index) {
            if (window.confirm(this.config.i18n.confirmRemove)) this.currentFields.splice(index, 1);
        },
        move(index, delta) {
            const list = this.currentFields;
            const to = index + delta;
            if (to < 0 || to >= list.length) return;
            [list[index], list[to]] = [list[to], list[index]];
        },
        enter(index) {
            this.path = [...this.path, { i: index }];
        },
        enterLayout(index, layoutIndex) {
            this.path = [...this.path, { i: index, l: layoutIndex }];
        },

        // Flexible content layouts (each with its own fields).
        addLayout(field) {
            field.layouts = [...(field.layouts ?? []), hydrateLayout()];
        },
        removeLayout(field, l) {
            if (window.confirm(this.config.i18n.confirmRemoveLayout ?? this.config.i18n.confirmRemove)) field.layouts.splice(l, 1);
        },
        moveLayout(field, l, delta) {
            const to = l + delta;
            if (to < 0 || to >= field.layouts.length) return;
            [field.layouts[l], field.layouts[to]] = [field.layouts[to], field.layouts[l]];
        },
        layoutLabelChanged(layout) {
            if (!layout._keyTouched) layout.key = slugify(layout.label);
        },
        goTo(depth) {
            this.path = this.path.slice(0, depth);
        },

        addRule() {
            this.group.location.push({ key: 'post_type', operator: '==', value: '' });
        },
        removeRule(index) {
            this.group.location.splice(index, 1);
        },

        serialize() {
            this.payload = JSON.stringify({
                ...this.group,
                fields: this.group.fields.map(dehydrateField),
            });
        },
    };
}
