// Remembers what the editor left open or closed — collapsed rows, accordion
// sections, the active tab — per screen, in this browser (localStorage). The
// classic editor reloads the page on save; without this everything reopened.
// Nothing here is saved with the post.

const STORAGE = 'ctrlfield-ui';

function screenKey() {
    const q = new URLSearchParams(window.location.search);
    return q.get('post') ? `post:${q.get('post')}`
        : q.get('page') ? `page:${q.get('page')}`
        : q.get('tag_ID') ? `term:${q.get('tag_ID')}`
        : window.location.pathname;
}

function read() {
    try {
        const all = JSON.parse(window.localStorage.getItem(STORAGE) ?? '{}');
        return all && typeof all === 'object' ? all : {};
    } catch {
        return {};
    }
}

function write(all) {
    try {
        window.localStorage.setItem(STORAGE, JSON.stringify(all));
    } catch {
        // private mode / storage full: the editor still works, it just forgets
    }
}

/**
 * Where an element sits: its meta box, the positions of the rows around it,
 * and a name. Stable across reloads as long as the rows keep their order.
 */
export function uiPath(el, name) {
    const parts = [];
    let box = '';
    for (let n = el; n && n !== document.body; n = n.parentElement) {
        if (n.hasAttribute?.('data-ctrlf-item') && n.parentElement) {
            const items = [...n.parentElement.children].filter((c) => c.hasAttribute('data-ctrlf-item'));
            parts.unshift(items.indexOf(n));
        }
        if (!box && n.id && (n.id.startsWith('ctrlfield-') || n.classList?.contains('ctrlfield-options-group'))) {
            box = n.id || 'options';
        }
    }
    return `${box}/${parts.join('.')}/${name}`;
}

export function uiGet(path, fallback) {
    const value = read()[screenKey()]?.[path];
    return value === undefined ? fallback : value;
}

export function uiSet(path, value) {
    const all = read();
    const screen = screenKey();
    all[screen] = { ...(all[screen] ?? {}), [path]: value };
    write(all);
}

/** After a drag, positions changed: save again what lives in that list. */
function resaveList(list) {
    list.querySelectorAll('.ctrlf-row__head').forEach((head) => {
        uiSet(uiPath(head, 'row'), !head.classList.contains('is-open'));
    });
    list.querySelectorAll('.ctrlf-accordion, .ctrlf-tabs').forEach((el) => {
        const data = el._x_dataStack?.[0];
        const name = (el.getAttribute('x-effect') ?? '').match(/\$ctrlfUiSet\('([^']+)'/)?.[1];
        if (!data || !name) return;
        uiSet(uiPath(el, name), 'open' in data ? data.open : data.ctrlfTab);
    });
}

export function registerUiState(Alpine) {
    document.addEventListener('ctrlf-sorted', (e) => {
        const list = e.target;
        Alpine.nextTick(() => setTimeout(() => resaveList(list), 0));
    });

    // x-data="{ open: $ctrlfUi('acc:intro', true) }" x-effect="$ctrlfUiSet('acc:intro', open)"
    Alpine.magic('ctrlfUi', (el) => (name, fallback) => uiGet(uiPath(el, name), fallback));
    Alpine.magic('ctrlfUiSet', (el) => (name, value) => uiSet(uiPath(el, name), value));
}
