// Content pickers (Post Object, Relationship, Page Link, Taxonomy, User).
//
// In the field: the chosen items as lines (thumbnail, title, type · date, ×,
// drag handle when sortable), and a "Search or choose…" box whose dropdown
// lists the 7 newest items (or the 7 best matches) and ends with "Browse all…",
// which opens a dialog with search, tabs and the whole list, loaded while
// scrolling. At ->max() the box hides; under ->min() a message asks for more.
//
//   x-data="ctrlfPicker({ source, multiple, min, max, tabs, title })"
//   path = add(path, item) · path = remove(path, id) · browse(path, (v) => path = v, $el)
//
// Data: GET ctrlfield/v1/picker (PickerController.php).

const DROPDOWN = 7;
const PAGE = 20;

function data() {
    return globalThis.window?.ctrlfieldData ?? {};
}

function t(key, fallback) {
    return data().i18n?.picker?.[key] ?? fallback;
}

/** The post being edited: never offered as a choice for itself. */
function currentPostId() {
    const q = new URLSearchParams(globalThis.window?.location?.search ?? '').get('post');
    return Number(q || globalThis.document?.getElementById('post_ID')?.value || 0);
}

async function rest(path, params) {
    const d = data();
    try {
        const res = await fetch(`${d.restUrl ?? ''}ctrlfield/v1/${path}?${new URLSearchParams(params)}`, {
            headers: { 'X-WP-Nonce': d.restNonce ?? '' },
        });
        return res.ok ? await res.json() : null;
    } catch {
        return null;
    }
}

/** The chosen ids as a list, for one or several (pure: tested). */
export function chosenIds(value, multiple) {
    if (multiple) return Array.isArray(value) ? value.map(Number).filter((id) => id > 0) : [];
    const id = Number(value);
    return id > 0 ? [id] : [];
}

/** Whether nothing more can be added (pure: tested). */
export function isFull(value, multiple, max) {
    const n = chosenIds(value, multiple).length;
    return multiple ? (max != null && max > 0 && n >= max) : n > 0;
}

/** The value after adding an item (pure: tested). */
export function withItem(value, id, multiple, max) {
    if (!multiple) return id;
    const list = chosenIds(value, true);
    if (list.includes(id) || isFull(list, true, max)) return list;
    return [...list, id];
}

/** The value after removing an item (pure: tested). */
export function withoutItem(value, id, multiple) {
    return multiple ? chosenIds(value, true).filter((x) => x !== id) : null;
}

/** Under the minimum, once something was chosen (pure: tested). */
export function belowMin(value, multiple, min) {
    const n = chosenIds(value, multiple).length;
    return min != null && min > 0 && n > 0 && n < min;
}

// ── What we know about items: source:id → { title, meta, date, thumbnail } ──

let store = null;
const missing = new Map(); // source → Set(ids) to fetch
let timer = null;

function key(source, id) {
    return `${source}|${id}`;
}

function remember(source, items) {
    for (const item of items ?? []) store[key(source, item.id)] = item;
}

function flush() {
    timer = null;
    for (const [source, ids] of missing) {
        const list = [...ids];
        missing.delete(source);
        rest('picker/items', { source, ids: list.join(',') }).then((body) => {
            remember(source, body?.results);
            for (const id of list) {
                store[key(source, id)] ??= { id, title: `#${id}`, meta: '', missing: true };
            }
        });
    }
}

function info(source, id) {
    const k = key(source, id);
    if (!(k in store)) {
        store[k] = { id, title: '…', meta: '', loading: true };
        if (!missing.has(source)) missing.set(source, new Set());
        missing.get(source).add(id);
        timer ??= setTimeout(flush, 30);
    }
    return store[k];
}

// ── The dialog ──────────────────────────────────────────────────────────────

function el(tag, attrs = {}, children = []) {
    const node = document.createElement(tag);
    for (const [k, v] of Object.entries(attrs)) {
        if (k === 'text') node.textContent = v;
        else if (k.startsWith('on')) node.addEventListener(k.slice(2), v);
        else if (v !== false && v !== null && v !== undefined) node.setAttribute(k, v === true ? '' : v);
    }
    for (const c of [].concat(children)) if (c) node.append(c);
    return node;
}

function thumb(item, cls) {
    return item.thumbnail
        ? el('img', { class: cls, src: item.thumbnail, alt: '', loading: 'lazy' })
        : el('span', { class: `${cls} ${cls}--empty`, 'aria-hidden': 'true' });
}

/**
 * Search, tabs and the whole list. Single: a click picks and closes.
 * Multiple: clicks toggle, up to max; "Select" applies.
 */
export function openPickerDialog(cfg, chosen, done, opener) {
    const tabs = cfg.tabs?.length > 1
        ? [{ source: cfg.source, label: t('all', 'All') }, ...cfg.tabs]
        : [{ source: cfg.source, label: '' }];
    let active = tabs[0].source;
    let selected = [...chosen];
    let page = 1;
    let more = false;
    let loading = false;
    let request = 0;
    let searchTimer = null;

    const titleId = 'ctrlf-pickdlg-heading';
    const search = el('input', { type: 'search', class: 'ctrlf-icondlg__search', placeholder: t('search', 'Search'), 'aria-label': t('search', 'Search'), autocomplete: 'off' });
    const nav = el('div', { class: 'ctrlf-linkdlg__tabs ctrlf-icondlg__tabs', role: 'tablist' });
    const list = el('ul', { class: 'ctrlf-icondlg__list ctrlf-pickdlg__list', role: 'listbox', 'aria-multiselectable': cfg.multiple ? 'true' : 'false' });
    const status = el('p', { class: 'ctrlf-icondlg__status' });
    const count = el('span', { class: 'ctrlf-pickdlg__count' });
    const apply = el('button', { type: 'button', class: 'button button-primary', text: t('select', 'Select'), onclick: () => { done(selected); close(); } });

    function full() {
        return cfg.max != null && cfg.max > 0 && selected.length >= cfg.max;
    }

    function paintCount() {
        count.textContent = cfg.max
            ? t('countMax', '%1$d of %2$d selected').replace('%1$d', String(selected.length)).replace('%2$d', String(cfg.max))
            : t('count', '%d selected').replace('%d', String(selected.length));
        for (const li of list.children) {
            const id = Number(li.dataset.id);
            const on = selected.includes(id);
            li.classList.toggle('is-selected', on);
            li.setAttribute('aria-selected', on ? 'true' : 'false');
            li.classList.toggle('is-disabled', !on && cfg.multiple && full());
        }
    }

    function row(item) {
        const li = el('li', { role: 'option', tabindex: '-1', class: 'ctrlf-icondlg__item ctrlf-pickdlg__item', 'data-id': String(item.id) }, [
            thumb(item, 'ctrlf-linkdlg__thumb'),
            el('span', { class: 'ctrlf-linkdlg__item-text' }, [
                el('span', { class: 'ctrlf-linkdlg__item-title', text: item.title }),
                el('span', { class: 'ctrlf-linkdlg__item-meta', text: [item.meta, item.date].filter(Boolean).join(' · ') }),
            ]),
            cfg.multiple ? el('span', { class: 'ctrlf-pickdlg__check', 'aria-hidden': 'true' }) : null,
        ]);
        const pick = () => {
            remember(cfg.source, [item]);
            if (!cfg.multiple) { done([item.id]); close(); return; }
            if (selected.includes(item.id)) selected = selected.filter((x) => x !== item.id);
            else if (!full()) selected = [...selected, item.id];
            paintCount();
        };
        li.addEventListener('click', pick);
        li.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); pick(); }
            if (e.key === 'ArrowDown') { e.preventDefault(); li.nextElementSibling?.focus(); }
            if (e.key === 'ArrowUp') { e.preventDefault(); (li.previousElementSibling ?? search).focus(); }
        });
        return li;
    }

    async function load(reset) {
        const mine = ++request;
        if (reset) { page = 1; list.replaceChildren(); list.scrollTop = 0; }
        loading = true;
        status.textContent = t('loading', 'Loading…');
        const body = await rest('picker', { source: active, search: search.value.trim(), page, per_page: PAGE, exclude: currentPostId() });
        if (mine !== request) return;
        loading = false;
        more = !!body?.more;
        list.append(...(body?.results ?? []).map(row));
        paintCount();
        status.textContent = list.children.length ? '' : t('noResults', 'Nothing found.');
    }

    list.addEventListener('scroll', () => {
        if (more && !loading && list.scrollTop + list.clientHeight >= list.scrollHeight - 60) {
            page += 1;
            load(false);
        }
    });
    search.addEventListener('input', () => { clearTimeout(searchTimer); searchTimer = setTimeout(() => load(true), 250); });
    search.addEventListener('keydown', (e) => { if (e.key === 'ArrowDown') { e.preventDefault(); list.firstElementChild?.focus(); } });

    function renderTabs() {
        nav.replaceChildren(...tabs.map((tab) => el('button', {
            type: 'button', role: 'tab',
            class: `ctrlf-linkdlg__tab${tab.source === active ? ' is-active' : ''}`,
            'aria-selected': tab.source === active ? 'true' : 'false',
            text: tab.label,
            onclick: () => { active = tab.source; renderTabs(); load(true); search.focus(); },
        })));
    }

    const dialog = el('div', { class: 'ctrlf-icondlg ctrlf-pickdlg', role: 'dialog', 'aria-modal': 'true', 'aria-labelledby': titleId }, [
        el('div', { class: 'ctrlf-linkdlg__head' }, [
            el('h2', { id: titleId, text: cfg.title || t('title', 'Choose') }),
            el('button', { type: 'button', class: 'ctrlf-linkdlg__close', 'aria-label': t('close', 'Close'), text: '×', onclick: () => close() }),
        ]),
        el('div', { class: 'ctrlf-icondlg__body' }, [search, tabs.length > 1 ? nav : null, list, status]),
        el('div', { class: 'ctrlf-linkdlg__foot ctrlf-icondlg__foot' }, [
            cfg.multiple ? count : el('span'),
            el('span', { class: 'ctrlf-pickdlg__actions' }, [
                el('button', { type: 'button', class: 'button', text: t('cancel', 'Cancel'), onclick: () => close() }),
                cfg.multiple ? apply : null,
            ]),
        ]),
    ]);
    const backdrop = el('div', { class: 'ctrlf-linkdlg-backdrop' }, [dialog]);

    function close() {
        document.removeEventListener('keydown', onKey, true);
        backdrop.remove();
        opener?.focus?.();
    }

    function onKey(e) {
        if (e.key === 'Escape') { e.preventDefault(); e.stopPropagation(); close(); }
        if (e.key === 'Tab') {
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
    load(true);
    search.focus();
}

// ── The field ───────────────────────────────────────────────────────────────

export function registerPicker(Alpine) {
    store = Alpine.reactive({});

    Alpine.data('ctrlfPicker', (cfg = {}) => ({
        cfg,
        open: false,
        q: '',
        items: [],
        loading: false,
        active: -1,
        request: 0,

        list(value) { return chosenIds(value, cfg.multiple); },
        full(value) { return isFull(value, cfg.multiple, cfg.max); },
        belowMin(value) { return belowMin(value, cfg.multiple, cfg.min); },
        info(id) { return info(cfg.source, id); },

        async load() {
            const mine = ++this.request;
            this.loading = true;
            const body = await rest('picker', { source: cfg.source, search: this.q.trim(), page: 1, per_page: DROPDOWN, exclude: currentPostId() });
            if (mine !== this.request) return;
            this.items = body?.results ?? [];
            remember(cfg.source, this.items);
            this.loading = false;
            this.active = -1;
        },
        focus() {
            this.open = true;
            if (!this.items.length && !this.loading) this.load();
        },
        move(step) {
            if (!this.open) { this.focus(); return; }
            const last = this.items.length; // the "Browse all…" row
            this.active = Math.max(0, Math.min(last, this.active + step));
        },
        add(value, item) {
            remember(cfg.source, [item]);
            const next = withItem(value, item.id, cfg.multiple, cfg.max);
            this.q = '';
            if (!cfg.multiple || isFull(next, true, cfg.max)) this.open = false;
            return next;
        },
        remove(value, id) {
            return withoutItem(value, id, cfg.multiple);
        },
        browse(value, set, opener) {
            this.open = false;
            openPickerDialog(cfg, this.list(value), (ids) => set(cfg.multiple ? ids : (ids[0] ?? null)), opener);
        },
    }));
}
