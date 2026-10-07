// Pure helpers for the post / term / gallery / map pickers.
// Kept free of Alpine and the DOM so Vitest can cover them.

/**
 * Returns the new field value after picking `id`.
 * Single fields take the id; multiple fields append it once, up to `max`.
 */
export function pickValue(current, id, multiple, max = null) {
    if (!multiple) {
        return id;
    }
    const list = Array.isArray(current) ? [...current] : [];
    if (list.includes(id)) {
        return list;
    }
    if (max && list.length >= max) {
        return list;
    }
    list.push(id);
    return list;
}

/** Appends attachment ids to a gallery value: no duplicates, at most `max`. */
export function mergeGallery(current, ids, max = null) {
    const list = Array.isArray(current) ? [...current] : [];
    for (const id of ids) {
        if (max && list.length >= max) break;
        if (!list.includes(id)) list.push(id);
    }
    return list;
}

/**
 * Returns a map value with one property changed. Lat/lng/zoom are numbers;
 * an empty input clears the coordinate instead of storing 0 (a real place).
 */
export function mapWith(current, prop, raw, defaultZoom = 14) {
    const base = current && typeof current === 'object' && !Array.isArray(current)
        ? { ...current }
        : { lat: null, lng: null, zoom: defaultZoom, address: '' };

    if (prop === 'address') {
        base.address = String(raw ?? '');
        return base;
    }
    const n = raw === '' || raw === null || raw === undefined ? null : Number(raw);
    base[prop] = Number.isFinite(n) ? n : null;
    return base;
}

/** True when the map value has a usable coordinate pair. */
export function hasCoords(value) {
    return !!value && Number.isFinite(value.lat) && Number.isFinite(value.lng);
}

/** OpenStreetMap embed URL with a marker, or '' without coordinates. */
export function osmEmbedUrl(value, delta = 0.01) {
    if (!hasCoords(value)) return '';
    const { lat, lng } = value;
    const bbox = [lng - delta, lat - delta, lng + delta, lat + delta].map((n) => n.toFixed(5)).join(',');
    return `https://www.openstreetmap.org/export/embed.html?bbox=${bbox}&layer=mapnik&marker=${lat},${lng}`;
}
