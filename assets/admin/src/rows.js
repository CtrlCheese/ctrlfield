// One-line summary of a repeater / flexible content row, shown when the row
// is collapsed: the first text value in the row ("Ana Souza", "Welcome!").

const MAX = 70;

function text(value) {
    if (typeof value === 'string') {
        return value.replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim();
    }
    if (value && typeof value === 'object' && !Array.isArray(value)) {
        // Link: { title, url } — the visible text first.
        return text(value.title) || text(value.url) || '';
    }
    return '';
}

export function rowSummary(row) {
    if (!row || typeof row !== 'object') return '';
    for (const [key, value] of Object.entries(row)) {
        if (key.startsWith('_')) continue; // _layout and other internals
        const t = text(value);
        if (t !== '') return t.length > MAX ? `${t.slice(0, MAX - 1)}…` : t;
    }
    return '';
}

/** Stable id of a flexible section (global block overrides refer to it). */
export function newInstanceId() {
    return Math.random().toString(36).slice(2, 12);
}

/**
 * Deep copy of a row for "Duplicate": every flexible section inside it (the
 * row itself included) gets a new _instance_id, so copies stay independent.
 */
export function cloneRow(row) {
    const copy = JSON.parse(JSON.stringify(row ?? {}));
    const restamp = (value) => {
        if (Array.isArray(value)) { value.forEach(restamp); return; }
        if (!value || typeof value !== 'object') return;
        if ('_instance_id' in value) value._instance_id = newInstanceId();
        Object.values(value).forEach(restamp);
    };
    restamp(copy);
    return copy;
}

/** Whether one more row fits under `max` (null / undefined = no limit). */
export function canAdd(list, max) {
    return max === null || max === undefined || (Array.isArray(list) ? list.length : 0) < max;
}

/** Whether one more `layout` section fits: the field's limit and the layout's own. */
export function canAddLayout(list, layout, layoutMax = {}, fieldMax = null) {
    const rows = Array.isArray(list) ? list : [];
    if (!canAdd(rows, fieldMax)) return false;
    const max = layoutMax?.[layout];
    return max === undefined || max === null || rows.filter((r) => r?._layout === layout).length < max;
}
