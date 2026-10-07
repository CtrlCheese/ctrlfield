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
