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
    f.postType = f.postType ?? [];
    f.roles = f.roles ?? [];
    return f;
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
    if (f._cond?.field) out.visibleWhen = { ...f._cond };
    else delete out.visibleWhen;
    out.fields = (f.fields ?? []).map(dehydrateField);
    for (const k of ['options', 'fields', 'postType', 'roles']) {
        if (Array.isArray(out[k]) && out[k].length === 0) delete out[k];
    }
    return out;
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
        /** Indexes into nested "fields" arrays: [] = top level. */
        path: [],
        payload: '',

        get currentFields() {
            let list = this.group.fields;
            for (const i of this.path) list = list[i].fields;
            return list;
        },
        get breadcrumb() {
            const crumbs = [];
            let list = this.group.fields;
            this.path.forEach((i, depth) => {
                crumbs.push({ label: list[i].label || list[i].key || '…', depth: depth + 1 });
                list = list[i].fields;
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
            this.path = [...this.path, index];
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
