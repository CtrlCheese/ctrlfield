// Color field helpers (ColorRenderer.php): hex typed by hand, the browser's
// picker for the colour, a slider for transparency (#RRGGBBAA).

/**
 * "#abc", "aabbcc", "#AABBCC80" → "#aabbcc" / "#AABBCC80": 6 or 8 digits with
 * "#", case kept as typed; "" when not a colour (pure: tested).
 */
export function normalizeHex(input) {
    let v = String(input ?? '').trim().replace(/^#/, '');
    if (!/^[0-9a-f]+$/i.test(v)) return '';
    if (v.length === 3 || v.length === 4) v = [...v].map((c) => c + c).join('');
    if (v.length !== 6 && v.length !== 8) return '';
    if (v.length === 8 && v.slice(6).toLowerCase() === 'ff') v = v.slice(0, 6);
    return `#${v}`;
}

/** Same colour, whatever the case (pure: tested). */
export function sameColor(a, b) {
    const x = normalizeHex(a);
    return x !== '' && x.toLowerCase() === normalizeHex(b).toLowerCase();
}

/** The colour without transparency, for <input type="color"> (pure: tested). */
export function solidHex(value) {
    const v = normalizeHex(value);
    return v ? v.slice(0, 7).toLowerCase() : '#000000';
}

/** Opacity in percent, 100 when opaque (pure: tested). */
export function opacityOf(value) {
    const v = normalizeHex(value);
    return v.length === 9 ? Math.round((parseInt(v.slice(7), 16) / 255) * 100) : 100;
}

/** The colour with an opacity in percent; opaque colours stay 6 digits (pure: tested). */
export function withOpacity(value, percent) {
    const solid = solidHex(value);
    const p = Math.max(0, Math.min(100, Number(percent)));
    if (p >= 100) return solid;
    return solid + Math.round((p / 100) * 255).toString(16).padStart(2, '0');
}

export function registerColor(Alpine) {
    Alpine.magic('ctrlfColor', () => ({ normalizeHex, sameColor, solidHex, opacityOf, withOpacity }));
}
