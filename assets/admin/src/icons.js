// Icon field: a dialog with tabs (All, Lucide, sets a theme adds, WordPress),
// a search box and one icon per row, rendered in batches while scrolling. The
// sets are static JSON (assets/icons/, bin/build-icons.mjs; a theme's own via
// the ctrlfield/icon_sets filter), fetched the first time they are needed —
// nothing is requested from a third-party server.
//
// Stored values: "dashicons-admin-site" (WordPress), "lucide:arrow-right"
// (Iconify-style, so themes that used Iconify names keep working), "{set}:{name}".
//
//   $ctrlfIconPicker(value, set, config, $el)   opens the dialog
//   $ctrlfIcon(value)                           the icon's HTML (preview)

export const LIBRARIES = ['lucide', 'dashicons'];
const BATCH = 60;
const LUCIDE_ATTRS = { fill: 'none', stroke: 'currentColor', 'stroke-width': 2, 'stroke-linecap': 'round', 'stroke-linejoin': 'round' };

function data() {
    return globalThis.window?.ctrlfieldData ?? {};
}

function t(key, fallback) {
    return data().i18n?.icon?.[key] ?? fallback;
}

/** SVG sets: id => { label, url, attrs } (Lucide, plus those a theme added). */
function svgSets() {
    const out = {};
    for (const set of data().icons?.sets ?? []) out[set.id] = set;
    out.lucide ??= { id: 'lucide', label: 'Lucide', url: `${data().icons?.url ?? ''}lucide.json`, attrs: LUCIDE_ATTRS };
    return out;
}

/** Every library id in tab order: Lucide, added sets, Dashicons. */
export function knownLibraries() {
    return [...Object.keys(svgSets()), 'dashicons'];
}

/** "lucide:arrow-right" → { lib: 'lucide', name: 'arrow-right' } (pure: tested). */
export function parseIcon(value, known = knownLibraries()) {
    const v = String(value ?? '').trim();
    if (v.startsWith('dashicons-')) return { lib: 'dashicons', name: v.slice(10) };
    const colon = v.indexOf(':');
    if (colon > 0 && known.includes(v.slice(0, colon))) return { lib: v.slice(0, colon), name: v.slice(colon + 1) };
    return { lib: '', name: v };
}

/** The value stored for an icon (pure: tested). */
export function iconValue(lib, name) {
    return lib === 'dashicons' ? `dashicons-${name}` : `${lib}:${name}`;
}

/** The libraries a field offers, in display order (pure: tested). */
export function normalizeLibraries(libraries, known = knownLibraries()) {
    const list = [].concat(libraries ?? []).filter((l) => known.includes(l));
    return list.length ? known.filter((l) => list.includes(l)) : [...known];
}

/**
 * Icons matching every word of the query, best first: exact name, name
 * starting with the query, a whole word of the name, part of the name, then
 * tag-only matches (pure: tested).
 */
export function filterIcons(entries, query) {
    const q = String(query ?? '').toLowerCase().trim();
    const words = q.split(/\s+/).filter(Boolean);
    if (!words.length) return entries;
    const dashed = words.join('-');
    const ranked = [];
    for (const icon of entries) {
        const parts = icon.name.split('-');
        const inName = (w) => icon.name.includes(w);
        let rank;
        if (icon.name === dashed) rank = 0;
        else if (icon.name.startsWith(dashed)) rank = 1;
        else if (words.every((w) => parts.includes(w))) rank = 2;
        else if (words.every(inName)) rank = 3;
        else if (icon.tags && words.every((w) => inName(w) || icon.tags.includes(w))) rank = 4;
        else continue;
        ranked.push([rank, icon]);
    }
    return ranked.sort((a, b) => a[0] - b[0]).map(([, icon]) => icon); // stable: keeps A–Z within a rank
}

// ── Icon sets ───────────────────────────────────────────────────────────────

const sets = {};    // lib => Promise<entries>
const bodies = {};  // svg set => { name: body | { body, viewBox } }, once loaded
const aliases = {}; // svg set => { old name: current name }
let ready = null;   // Alpine-reactive { [set]: true } so previews re-render

function loadSet(lib) {
    sets[lib] ??= (async () => {
        const d = data();
        const svg = svgSets()[lib];
        const base = svg ? svg.url : `${d.icons?.url ?? ''}${lib}.json`;
        const url = `${base}${base.includes('?') ? '&' : '?'}ver=${encodeURIComponent(d.icons?.version ?? '')}`;
        try {
            const res = await fetch(url, { credentials: 'same-origin' });
            const body = res.ok ? await res.json() : null;
            if (svg) {
                bodies[lib] = body?.icons ?? {};
                aliases[lib] = body?.aliases ?? {};
                if (ready) ready[lib] = true;
                return Object.keys(bodies[lib]).map((name) => ({ lib, name, tags: body?.tags?.[name] ?? '' }));
            }
            return (body?.icons ?? []).map((name) => ({ lib, name, tags: '' }));
        } catch {
            delete sets[lib]; // try again next time
            return [];
        }
    })();
    return sets[lib];
}

function svgHtml(lib, name, size = 20) {
    const icon = bodies[lib]?.[name] ?? bodies[lib]?.[aliases[lib]?.[name]];
    const body = typeof icon === 'string' ? icon : icon?.body;
    if (!body) return '';
    const viewBox = (typeof icon === 'object' && icon.viewBox) || '0 0 24 24';
    const attrs = Object.entries(svgSets()[lib]?.attrs ?? {})
        .map(([k, v]) => ` ${k.replace(/[^a-zA-Z0-9:_-]/g, '')}="${String(v).replace(/"/g, '&quot;')}"`).join('');
    return `<svg xmlns="http://www.w3.org/2000/svg" width="${size}" height="${size}" viewBox="${viewBox}"${attrs} aria-hidden="true" focusable="false">${body}</svg>`;
}

/** The icon as HTML; SVG icons appear once their set has loaded. */
export function iconHtml(value, size = 20) {
    const { lib, name } = parseIcon(value);
    if (!name || !/^[a-z0-9-]+$/.test(name)) return '';
    if (lib === 'dashicons') {
        return `<span class="dashicons dashicons-${name}" style="font-size:${size}px;width:${size}px;height:${size}px" aria-hidden="true"></span>`;
    }
    if (lib && svgSets()[lib]) {
        if (!bodies[lib]) loadSet(lib);
        return svgHtml(lib, name, size);
    }
    return '';
}

// ── The dialog ──────────────────────────────────────────────────────────────

function el(tag, attrs = {}, children = []) {
    const node = document.createElement(tag);
    for (const [k, v] of Object.entries(attrs)) {
        if (k === 'text') node.textContent = v;
        else if (k === 'html') node.innerHTML = v;
        else if (k.startsWith('on')) node.addEventListener(k.slice(2), v);
        else if (v !== false && v !== null && v !== undefined) node.setAttribute(k, v === true ? '' : v);
    }
    for (const c of [].concat(children)) if (c) node.append(c);
    return node;
}

export function openIconPicker(value, set, config, opener) {
    const libraries = normalizeLibraries(config.libraries);
    const labels = { dashicons: t('wordpress', 'WordPress') };
    for (const [id, set] of Object.entries(svgSets())) labels[id] = set.label;
    const tabs = [
        ...(libraries.length > 1 ? [{ id: 'all', label: t('all', 'All') }] : []),
        ...libraries.map((lib) => ({ id: lib, label: labels[lib] })),
    ];
    const current = parseIcon(value);
    let active = tabs[0].id;
    let results = [];
    let shown = 0;
    let request = 0;
    let searchTimer = null;

    const titleId = 'ctrlf-icondlg-heading';
    const search = el('input', { type: 'search', class: 'ctrlf-icondlg__search', placeholder: t('search', 'Search icons'), 'aria-label': t('search', 'Search icons'), autocomplete: 'off' });
    const list = el('ul', { class: 'ctrlf-icondlg__list', role: 'listbox', 'aria-label': t('results', 'Icons') });
    const status = el('p', { class: 'ctrlf-icondlg__status', 'aria-live': 'polite' });
    const nav = el('div', { class: 'ctrlf-linkdlg__tabs ctrlf-icondlg__tabs', role: 'tablist' });
    const sentinel = el('li', { class: 'ctrlf-icondlg__more', 'aria-hidden': 'true' });

    function row(icon) {
        const selected = icon.lib === current.lib && icon.name === current.name;
        const li = el('li', {
            role: 'option',
            tabindex: '-1',
            class: `ctrlf-icondlg__item${selected ? ' is-selected' : ''}`,
            'aria-selected': selected ? 'true' : 'false',
        }, [
            el('span', { class: 'ctrlf-icondlg__glyph', html: iconHtml(iconValue(icon.lib, icon.name), 22) }),
            el('span', { class: 'ctrlf-icondlg__name', text: icon.name }),
            active === 'all' ? el('span', { class: 'ctrlf-icondlg__lib', text: labels[icon.lib] }) : null,
        ]);
        const pick = () => { set(iconValue(icon.lib, icon.name)); close(); };
        li.addEventListener('click', pick);
        li.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); pick(); }
            if (e.key === 'ArrowDown') { e.preventDefault(); (li.nextElementSibling !== sentinel ? li.nextElementSibling : null)?.focus(); }
            if (e.key === 'ArrowUp') { e.preventDefault(); (li.previousElementSibling ?? search).focus(); }
        });
        return li;
    }

    function more() {
        if (shown >= results.length) return;
        const next = results.slice(shown, shown + BATCH);
        shown += next.length;
        sentinel.before(...next.map(row));
        sentinel.hidden = shown >= results.length;
    }

    async function load() {
        const mine = ++request;
        status.textContent = t('loading', 'Loading…');
        const libs = active === 'all' ? libraries : [active];
        const entries = (await Promise.all(libs.map(loadSet))).flat();
        if (mine !== request) return; // a newer search / tab meanwhile
        results = filterIcons(entries, search.value);
        shown = 0;
        list.replaceChildren(sentinel);
        list.scrollTop = 0;
        more();
        status.textContent = results.length
            ? t('count', '%d icons').replace('%d', String(results.length))
            : t('noResults', 'No icons found.');
    }

    function renderTabs() {
        nav.replaceChildren(...tabs.map((tab) => el('button', {
            type: 'button',
            role: 'tab',
            class: `ctrlf-linkdlg__tab${tab.id === active ? ' is-active' : ''}`,
            'aria-selected': tab.id === active ? 'true' : 'false',
            text: tab.label,
            onclick: () => { active = tab.id; renderTabs(); load(); search.focus(); },
        })));
    }

    const observer = new IntersectionObserver((entries) => {
        if (entries.some((e) => e.isIntersecting)) more();
    }, { root: list, rootMargin: '200px' });
    observer.observe(sentinel);

    search.addEventListener('input', () => {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(load, 150);
    });
    search.addEventListener('keydown', (e) => {
        if (e.key === 'ArrowDown') { e.preventDefault(); list.querySelector('.ctrlf-icondlg__item')?.focus(); }
    });

    const dialog = el('div', { class: 'ctrlf-icondlg', role: 'dialog', 'aria-modal': 'true', 'aria-labelledby': titleId }, [
        el('div', { class: 'ctrlf-linkdlg__head' }, [
            el('h2', { id: titleId, text: t('title', 'Select icon') }),
            el('button', { type: 'button', class: 'ctrlf-linkdlg__close', 'aria-label': t('close', 'Close'), text: '×', onclick: () => close() }),
        ]),
        el('div', { class: 'ctrlf-icondlg__body' }, [
            search,
            tabs.length > 1 ? nav : null,
            list, status,
        ]),
        el('div', { class: 'ctrlf-linkdlg__foot ctrlf-icondlg__foot' }, [
            current.name
                ? el('button', { type: 'button', class: 'button ctrlf-icondlg__remove', text: t('remove', 'Remove icon'), onclick: () => { set(''); close(); } })
                : el('span'),
            el('button', { type: 'button', class: 'button', text: t('cancel', 'Cancel'), onclick: () => close() }),
        ]),
    ]);
    const backdrop = el('div', { class: 'ctrlf-linkdlg-backdrop' }, [dialog]);

    function close() {
        observer.disconnect();
        document.removeEventListener('keydown', onKey, true);
        backdrop.remove();
        opener?.focus?.();
    }

    function onKey(e) {
        if (e.key === 'Escape') { e.preventDefault(); e.stopPropagation(); close(); }
        if (e.key === 'Tab') { // keep focus inside the dialog
            const focusable = [...dialog.querySelectorAll('button, input')].filter((n) => !n.disabled);
            const first = focusable[0];
            const last = focusable[focusable.length - 1];
            if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
            else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
        }
    }

    backdrop.addEventListener('mousedown', (e) => { if (e.target === backdrop) close(); });
    document.addEventListener('keydown', onKey, true);
    document.body.append(backdrop);
    renderTabs();
    load();
    search.focus();
}

export function registerIcons(Alpine) {
    ready = Alpine.reactive({});
    Alpine.magic('ctrlfIconPicker', () => (value, set, config, opener) => openIconPicker(value, set, config ?? {}, opener));
    // Reads ready.lucide so a Lucide preview re-renders once the set arrives.
    Alpine.magic('ctrlfIcon', () => (value, size) => (ready[parseIcon(value).lib], iconHtml(value, size)));
}
