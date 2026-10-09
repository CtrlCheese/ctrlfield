// Link field dialog, modelled on the WordPress "Insert/edit link" dialog: URL
// and link text on top, then tabs (All, one per post type / taxonomy, Anchors)
// with search. A picked post or term is stored with its id, so the link
// follows permalink changes (LinkResolver.php).
//
//   $ctrlfLink(value, (v) => path = v, config, $el)   opens the dialog
//   $ctrlfLinkState(value)                            what the compact line shows

const ANCHOR = /^#[A-Za-z][A-Za-z0-9_:.-]*$/;

function data() {
    return globalThis.window?.ctrlfieldData ?? {};
}

function t(key, fallback) {
    return data().i18n?.link?.[key] ?? fallback;
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

function currentPostId() {
    const q = new URLSearchParams(window.location.search).get('post');
    return Number(q || document.getElementById('post_ID')?.value || 0);
}

/** The value to store once the dialog is confirmed (pure: tested). */
export function buildLink(form, picked) {
    const url = form.url.trim();
    const link = { url, title: form.title.trim(), target: form.newTab ? '_blank' : '_self' };
    if (picked && picked.url === url) {
        link.type = picked.kind;
        link.id = picked.id;
        link.object = picked.object;
    } else if (ANCHOR.test(url)) {
        link.type = 'anchor';
    } else if (url !== '') {
        link.type = 'external';
    }
    if (form.style) link.style = form.style;
    return link;
}

/** "Page", "Anchor", "External" — the kind shown before the URL (pure: tested). */
export function linkKind(link, state = {}) {
    if (!link) return '';
    if (link.type === 'anchor' || ANCHOR.test(link.url ?? '')) return t('anchor', 'Anchor');
    if (link.type === 'post' || link.type === 'term') return state.typeLabel || link.object || '';
    return t('external', 'External');
}

// ── Saved links: current title / URL, and whether the target still exists ──

let store = null;
const queue = new Set();
let timer = null;

function flush() {
    timer = null;
    const refs = [...queue];
    queue.clear();
    rest('link/status', { links: refs.join(',') }).then((body) => {
        for (const ref of refs) {
            const r = body?.results?.[ref];
            store[ref] = r ? { ...r, missing: r.exists === false } : { missing: false };
        }
    });
}

function linkState(Alpine, link) {
    store ??= Alpine.reactive({});
    if (!link || !link.url) return {};
    const ref = link.id && (link.type === 'post' || link.type === 'term') ? `${link.type}:${link.id}` : '';
    if (!ref) return { kind: linkKind(link) };
    if (!(ref in store)) {
        store[ref] = { loading: true };
        queue.add(ref);
        timer ??= setTimeout(flush, 50);
    }
    const s = store[ref];
    return { ...s, kind: linkKind(link, s) };
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

function openDialog(value, set, config, opener) {
    const link = value && typeof value === 'object' ? value : {};
    const styles = Object.entries(config.styles ?? {});
    const form = {
        url: link.url ?? '',
        title: link.title ?? '',
        newTab: link.target === '_blank',
        style: link.style ?? (styles[0]?.[0] ?? ''),
    };
    let picked = link.id ? { kind: link.type, id: link.id, object: link.object, url: link.url } : null;
    let autoTitle = ''; // the title filled from a result, replaced by the next pick

    const postTabs = (config.tabs ?? []).filter((tab) => tab.source.startsWith('post:'));
    const tabs = [
        ...(postTabs.length > 1 || (config.tabs ?? []).length > postTabs.length
            ? [{ source: config.all, label: t('all', 'All') }] : []),
        ...(config.tabs ?? []),
        ...(config.anchors ? [{ source: 'anchors', label: t('anchors', 'Anchors') }] : []),
    ].filter((tab) => tab.source);
    let active = tabs[0]?.source ?? '';
    let page = 1;
    let more = false;
    let loading = false;
    let request = 0;
    let searchTimer = null;

    // Fields
    const urlInput = el('input', { type: 'text', class: 'ctrlf-linkdlg__input', id: 'ctrlf-linkdlg-url', value: form.url, placeholder: 'https:// · #anchor', autocomplete: 'off' });
    const titleInput = el('input', { type: 'text', class: 'ctrlf-linkdlg__input', id: 'ctrlf-linkdlg-title', value: form.title });
    const newTab = config.showTarget
        ? el('input', { type: 'checkbox', id: 'ctrlf-linkdlg-newtab', checked: form.newTab })
        : null;
    const styleSelect = styles.length
        ? el('select', { class: 'ctrlf-linkdlg__input', id: 'ctrlf-linkdlg-style' },
            styles.map(([v, label]) => el('option', { value: v, text: label, selected: v === form.style })))
        : null;

    const search = el('input', { type: 'search', class: 'ctrlf-linkdlg__search', placeholder: t('search', 'Search'), 'aria-label': t('search', 'Search') });
    const list = el('ul', { class: 'ctrlf-linkdlg__results', role: 'listbox', 'aria-label': t('results', 'Results') });
    const status = el('p', { class: 'ctrlf-linkdlg__status', 'aria-live': 'polite' });
    const tabBar = el('div', { class: 'ctrlf-linkdlg__tabs', role: 'tablist' });

    urlInput.addEventListener('input', () => {
        form.url = urlInput.value;
        markSelected();
    });
    titleInput.addEventListener('input', () => { form.title = titleInput.value; autoTitle = ''; });

    function markSelected() {
        for (const li of list.children) {
            const on = li.dataset.url !== undefined && li.dataset.url === form.url.trim();
            li.classList.toggle('is-selected', on);
            li.setAttribute('aria-selected', on ? 'true' : 'false');
        }
    }

    function choose(item) {
        picked = item.kind === 'anchor' ? null : item;
        form.url = item.url;
        urlInput.value = item.url;
        if (form.title === '' || form.title === autoTitle) {
            form.title = item.title;
            titleInput.value = item.title;
            autoTitle = item.title;
        }
        markSelected();
    }

    function row(item) {
        const meta = [item.typeLabel, item.status, item.date].filter(Boolean).join(' · ');
        const li = el('li', { role: 'option', tabindex: '-1', 'data-url': item.url, class: 'ctrlf-linkdlg__item' }, [
            item.thumbnail
                ? el('img', { class: 'ctrlf-linkdlg__thumb', src: item.thumbnail, alt: '', loading: 'lazy' })
                : el('span', { class: 'ctrlf-linkdlg__thumb ctrlf-linkdlg__thumb--empty', 'aria-hidden': 'true' }),
            el('span', { class: 'ctrlf-linkdlg__item-text' }, [
                el('span', { class: 'ctrlf-linkdlg__item-title', text: item.title }),
                el('span', { class: 'ctrlf-linkdlg__item-meta', text: meta }),
            ]),
            el('span', { class: 'ctrlf-linkdlg__item-url', text: item.url.replace(/^https?:\/\/[^/]+/, '') || '/' }),
        ]);
        li.addEventListener('click', () => choose(item));
        li.addEventListener('dblclick', () => { choose(item); submit(); });
        li.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); choose(item); }
            if (e.key === 'ArrowDown') { e.preventDefault(); li.nextElementSibling?.focus(); }
            if (e.key === 'ArrowUp') { e.preventDefault(); (li.previousElementSibling ?? search).focus(); }
        });
        return li;
    }

    async function load(reset) {
        const mine = ++request;
        if (reset) { page = 1; list.replaceChildren(); }
        loading = true;
        status.textContent = t('loading', 'Loading…');
        let items = [];
        if (active === 'anchors') {
            const id = currentPostId();
            const body = id ? await rest('link/anchors', { post_id: id }) : { results: [] };
            const q = search.value.trim().toLowerCase();
            items = (body?.results ?? [])
                .filter((a) => !q || a.anchor.toLowerCase().includes(q))
                .map((a) => ({ kind: 'anchor', url: `#${a.anchor}`, title: a.label ?? `#${a.anchor}`, typeLabel: t('anchor', 'Anchor') }));
            more = false;
        } else {
            const body = await rest('link/search', { source: active, search: search.value.trim(), page });
            items = body?.results ?? [];
            more = !!body?.more;
        }
        if (mine !== request) return; // a newer search started meanwhile
        loading = false;
        list.append(...items.map(row));
        markSelected();
        status.textContent = list.children.length
            ? ''
            : (active === 'anchors' ? t('noAnchors', 'No anchors on this page yet. Type #name in the URL.') : t('noResults', 'Nothing found.'));
    }

    list.addEventListener('scroll', () => {
        if (more && !loading && list.scrollTop + list.clientHeight >= list.scrollHeight - 40) {
            page += 1;
            load(false);
        }
    });
    search.addEventListener('input', () => {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(() => load(true), 250);
    });
    search.addEventListener('keydown', (e) => {
        if (e.key === 'ArrowDown') { e.preventDefault(); list.firstElementChild?.focus(); }
    });

    function renderTabs() {
        tabBar.replaceChildren(...tabs.map((tab) => el('button', {
            type: 'button',
            role: 'tab',
            class: `ctrlf-linkdlg__tab${tab.source === active ? ' is-active' : ''}`,
            'aria-selected': tab.source === active ? 'true' : 'false',
            text: tab.label,
            onclick: () => { active = tab.source; renderTabs(); load(true); },
        })));
    }

    // Layout
    const titleId = 'ctrlf-linkdlg-heading';
    const field = (label, id, input) => el('div', { class: 'ctrlf-linkdlg__field' }, [
        el('label', { for: id, text: label }), input,
    ]);
    const dialog = el('div', { class: 'ctrlf-linkdlg', role: 'dialog', 'aria-modal': 'true', 'aria-labelledby': titleId }, [
        el('div', { class: 'ctrlf-linkdlg__head' }, [
            el('h2', { id: titleId, text: t('title', 'Insert/edit link') }),
            el('button', { type: 'button', class: 'ctrlf-linkdlg__close', 'aria-label': t('close', 'Close'), text: '×', onclick: () => close() }),
        ]),
        el('div', { class: 'ctrlf-linkdlg__fields' }, [
            field(t('url', 'URL'), 'ctrlf-linkdlg-url', urlInput),
            field(t('text', 'Link text'), 'ctrlf-linkdlg-title', titleInput),
            styleSelect ? field(t('style', 'Style'), 'ctrlf-linkdlg-style', styleSelect) : null,
            newTab ? el('label', { class: 'ctrlf-linkdlg__check', for: 'ctrlf-linkdlg-newtab' }, [newTab, ` ${t('newTab', 'Open link in a new tab')}`]) : null,
        ]),
        tabs.length ? el('div', { class: 'ctrlf-linkdlg__browse' }, [
            el('p', { class: 'ctrlf-linkdlg__hint', text: t('hint', 'Or link to existing content') }),
            tabBar, search, list, status,
        ]) : null,
        el('div', { class: 'ctrlf-linkdlg__foot' }, [
            el('button', { type: 'button', class: 'button', text: t('cancel', 'Cancel'), onclick: () => close() }),
            el('button', { type: 'button', class: 'button button-primary', text: link.url ? t('update', 'Update') : t('add', 'Add link'), onclick: () => submit() }),
        ]),
    ]);
    const backdrop = el('div', { class: 'ctrlf-linkdlg-backdrop' }, [dialog]);

    function submit() {
        form.newTab = newTab?.checked ?? false;
        form.style = styleSelect?.value ?? '';
        set(buildLink(form, picked));
        close();
    }

    function close() {
        document.removeEventListener('keydown', onKey, true);
        backdrop.remove();
        opener?.focus?.();
    }

    function onKey(e) {
        if (e.key === 'Escape') { e.preventDefault(); e.stopPropagation(); close(); }
        if (e.key === 'Enter' && (e.target === urlInput || e.target === titleInput)) { e.preventDefault(); submit(); }
        if (e.key === 'Tab') { // keep focus inside the dialog
            const focusable = [...dialog.querySelectorAll('button, input, select, [tabindex="0"]')].filter((n) => !n.disabled);
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
    if (tabs.length) load(true);
    urlInput.focus();
}

export function registerLink(Alpine) {
    Alpine.magic('ctrlfLink', () => (value, set, config, opener) => openDialog(value, set, config ?? {}, opener));
    Alpine.magic('ctrlfLinkState', () => (value) => linkState(Alpine, value));
}
