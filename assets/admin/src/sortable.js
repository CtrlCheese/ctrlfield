// Drag-and-drop reordering for every list in the admin (repeater rows,
// flexible content sections, gallery images, relationship picks, the field
// group editor). SortableJS moves the DOM; we put the DOM back and move the
// data instead, so Alpine's keyed x-for stays the single source of truth.
//
//   <div x-ctrlf-sort="list">                       ← the array to reorder
//     <template x-for="item in list" :key="$ctrlfKey(item)">
//       <div data-ctrlf-item>
//         <button type="button" x-ctrlf-handle>⋮⋮</button> …
//
// The handle also works from the keyboard: arrow keys move the item.

import Sortable from 'sortablejs';

/** Move list[from] to position `to` in place. Returns whether it moved. */
export function moveItem(list, from, to) {
    if (!Array.isArray(list) || from === to || from < 0 || to < 0 || from >= list.length || to >= list.length) {
        return false;
    }
    const [item] = list.splice(from, 1);
    list.splice(to, 0, item);
    return true;
}

const ids = new WeakMap();
let counter = 0;

/** Stable x-for key: objects keep one id for life (also after moving), values key by themselves. */
export function itemKey(item, raw = (v) => v) {
    if (item === null || typeof item !== 'object') {
        return `v:${String(item)}`;
    }
    const target = raw(item);
    if (!ids.has(target)) ids.set(target, `o:${++counter}`);
    return ids.get(target);
}

let listCounter = 0;

export function registerSortable(Alpine) {
    Alpine.magic('ctrlfKey', () => (item) => itemKey(item, Alpine.raw));

    Alpine.directive('ctrlf-sort', (el, { expression, modifiers }, { evaluateLater, cleanup }) => {
        const id = `cfs${++listCounter}`;
        el.dataset.ctrlfSort = id;
        const getList = evaluateLater(expression);
        const items = () => [...el.children].filter((c) => c.hasAttribute('data-ctrlf-item'));

        const move = (from, to) => {
            getList((list) => { moveItem(list, from, to); });
            el.dispatchEvent(new CustomEvent('ctrlf-sorted', { bubbles: true, detail: { from, to } }));
        };
        el._ctrlfMove = move;
        el._ctrlfItems = items;

        const sortable = Sortable.create(el, {
            handle: `.ctrlf-drag--${id}`,
            draggable: '[data-ctrlf-item]',
            direction: modifiers.includes('grid') ? 'horizontal' : 'vertical',
            animation: 180,
            easing: 'cubic-bezier(0.2, 0, 0, 1)',
            forceFallback: true,          // same smooth ghost in every browser, inside meta boxes too
            fallbackOnBody: true,
            fallbackTolerance: 4,         // a click on the handle is not a drag
            delayOnTouchOnly: true,
            delay: 120,                   // long-press on touch screens, page still scrolls
            scroll: true,
            scrollSensitivity: 90,
            scrollSpeed: 14,
            bubbleScroll: true,
            ghostClass: 'ctrlf-sort-ghost',
            chosenClass: 'ctrlf-sort-chosen',
            dragClass: 'ctrlf-sort-drag',
            fallbackClass: 'ctrlf-sort-fallback',
            // The drag ghost is a copy of the item appended to <body>; Alpine would
            // try to initialise it outside its x-for scope. x-ignore (copied into
            // the ghost) prevents that; mutateDom keeps Alpine from seeing the
            // attribute on the live item.
            onChoose: (evt) => Alpine.mutateDom(() => evt.item.setAttribute('x-ignore', '')),
            onUnchoose: (evt) => Alpine.mutateDom(() => evt.item.removeAttribute('x-ignore')),
            onStart: () => document.body.classList.add('ctrlf-is-sorting'),
            onEnd: (evt) => {
                document.body.classList.remove('ctrlf-is-sorting');
                const from = evt.oldDraggableIndex;
                const to = evt.newDraggableIndex;
                if (from === undefined || to === undefined || from === to) return;

                // Undo Sortable's DOM move; Alpine re-orders the keyed nodes from the data.
                const current = items().filter((n) => n !== evt.item);
                const anchor = current[from] ?? null;
                el.insertBefore(evt.item, anchor ?? (current.length ? current[current.length - 1].nextSibling : null));
                move(from, to);
            },
        });

        cleanup(() => sortable.destroy());
    });

    Alpine.directive('ctrlf-handle', (el, _, { cleanup }) => {
        const list = el.closest('[data-ctrlf-sort]');
        if (!list) return;
        el.classList.add('ctrlf-drag', `ctrlf-drag--${list.dataset.ctrlfSort}`);
        if (!el.hasAttribute('aria-label')) el.setAttribute('aria-label', 'Drag to reorder (or use the arrow keys)');

        const onKey = (e) => {
            const delta = { ArrowUp: -1, ArrowLeft: -1, ArrowDown: 1, ArrowRight: 1 }[e.key];
            if (!delta) return;
            e.preventDefault();
            const item = el.closest('[data-ctrlf-item]');
            const nodes = list._ctrlfItems();
            const from = nodes.indexOf(item);
            const to = from + delta;
            if (from < 0 || to < 0 || to >= nodes.length) return;
            list._ctrlfMove(from, to);
            Alpine.nextTick(() => el.focus());
        };
        el.addEventListener('keydown', onKey);
        cleanup(() => el.removeEventListener('keydown', onKey));
    });
}
