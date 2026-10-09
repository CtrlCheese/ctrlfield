// CSS is compiled separately by @tailwindcss/cli → assets/admin/ctrlfield.css
import Alpine from 'alpinejs';
import collapse from '@alpinejs/collapse';
import { evaluateCondition } from './conditionEvaluator.js';
import { pickValue, mergeGallery, mapWith, hasCoords, osmEmbedUrl } from './pickers.js';
import { fieldGroupEditor } from './groupEditor.js';
import { registerSortable, itemKey } from './sortable.js';
import { registerWysiwyg } from './wysiwyg.js';
import { registerUiState, uiPath, uiGet, uiSet } from './uiState.js';
import { rowSummary } from './rows.js';
import { registerLink } from './link.js';

function markBlockEditorDirty() {
    const editor = window.wp?.data?.select?.('core/editor');
    if (!editor?.getCurrentPostType?.()) return; // classic editor: the form posts anyway
    window.wp.data.dispatch('core/editor').editPost({ ctrlfield_changed: Date.now() });
}

// Block editor: after meta boxes finish saving, fetch the validation error (if any).
let watchingMetaBoxSaves = false;
function watchMetaBoxSaves() {
    const data = window.wp?.data;
    const editPost = data?.select?.('core/edit-post');
    if (watchingMetaBoxSaves || !editPost?.isSavingMetaBoxes) return;
    watchingMetaBoxSaves = true;
    let saving = false;
    data.subscribe(async () => {
        const now = editPost.isSavingMetaBoxes();
        const finished = saving && !now;
        saving = now; // before any await: the store fires many times meanwhile
        if (finished) {
            const postId = data.select('core/editor').getCurrentPostId();
            const phData = window.ctrlfieldData ?? {};
            try {
                const res = await fetch(`${phData.restUrl ?? ''}ctrlfield/v1/save-error/${postId}`, {
                    headers: { 'X-WP-Nonce': phData.restNonce ?? '' },
                });
                const body = await res.json();
                if (body.error) {
                    saveErrorShown = false;
                    showSaveError(body.error);
                } else {
                    data.dispatch('core/notices').removeNotice('ctrlfield-save-error');
                }
            } catch (e) { /* network error: nothing to show */ }
        }
    });
}

let saveErrorShown = false;
function showSaveError(message) {
    if (saveErrorShown) return; // one notice even with several meta boxes
    saveErrorShown = true;
    const text = `CtrlField: ${message}`;
    const notices = window.wp?.data?.dispatch?.('core/notices');
    if (notices && window.wp.data.select('core/editor')?.getCurrentPostType?.()) {
        notices.createErrorNotice(text, { id: 'ctrlfield-save-error' });
        return;
    }
    const wrap = document.querySelector('.wrap h1, #wpbody-content');
    if (wrap) {
        const div = document.createElement('div');
        div.className = 'notice notice-error';
        div.innerHTML = '<p></p>';
        div.firstChild.textContent = text;
        wrap.after(div);
    }
}

// -----------------------------------------------------------------------
// ctrlFieldAdmin — single root Alpine component for the meta box
// -----------------------------------------------------------------------

Alpine.plugin(collapse);
registerSortable(Alpine);
registerWysiwyg(Alpine);
registerUiState(Alpine);
registerLink(Alpine);

document.addEventListener('alpine:init', () => {
    // CtrlField → Field Groups editor; config comes from the root's data-config.
    Alpine.data('ctrlFieldGroupEditor', () => fieldGroupEditor(
        JSON.parse(document.getElementById('ctrlfield-group-editor')?.dataset.config ?? '{}'),
    ));

    Alpine.data('ctrlFieldAdmin', ({ values, conditions, labels = {}, attachments = {} }) => ({

        /** Reactive field state — serialised into ctrlfield_payload on save. */
        adminState: {},

        /** Cache of attachment URLs fetched from WP (id → url). */
        attachmentUrls: {},

        /** Active tab per group — keyed by group key, value is active tab key. */
        activeTabs: {},

        /** User search results per field key. */
        userResults: {},

        /** Cache of selected user display names. */
        selectedUserLabels: {},

        /** Icon picker search query. */
        iconSearch: '',

        // -------------------------------------------------------------------
        // Lifecycle
        // -------------------------------------------------------------------

        init() {
            // Deep-clone so we don't mutate the JSON passed from PHP.
            this.adminState = JSON.parse(JSON.stringify(values));

            // Pre-populate attachment URLs from ctrlfieldData (PHP-localised).
            const phData = window.ctrlfieldData ?? {};
            this.attachmentUrls = { ...(phData.attachments ?? {}), ...attachments };

            // Names of posts / terms already selected, so pickers show titles, not ids.
            this.pickerLabels = { ...labels };

            // Block editor: changes made only in meta boxes did not mark the post
            // as edited, so Save stayed disabled and the values were lost. Flag
            // an edit the REST API ignores (like ACF does) whenever a field changes.
            this.$watch('adminState', () => markBlockEditorDirty());
            watchMetaBoxSaves();

            // Validation error from the previous save (shown once).
            if (phData.saveError) {
                showSaveError(phData.saveError);
            }

            // Code editors are initialised after the DOM is ready (WYSIWYG: x-ctrlf-wysiwyg).
            this.$nextTick(() => {
                this.initCodeEditors();
            });
        },

        // -------------------------------------------------------------------
        // Visibility conditions
        // -------------------------------------------------------------------

        /**
         * Returns true when the field should be visible.
         * A field with no condition is always visible.
         * Delegates to the pure evaluateCondition() function for testability.
         */
        isVisible(fieldKey) {
            const cond = conditions[fieldKey];
            if (!cond) return true;
            return evaluateCondition(cond, this.adminState);
        },

        // -------------------------------------------------------------------
        // Rows (repeater, flexible content) — methods act on the list itself,
        // so they work at any nesting depth. Open / closed state follows the
        // row object, not its index, so it survives drag-and-drop.
        // -------------------------------------------------------------------

        /** { rowKey: false } — rows are open unless collapsed. */
        closedRows: {},

        /** Append a copy of `row` to `list` (created when missing); returns the list to assign back. */
        withRow(list, row) {
            const rows = Array.isArray(list) ? list : [];
            rows.push(JSON.parse(JSON.stringify(row)));
            return rows;
        },
        removeRowFrom(list, idx, message = 'Remove this row?') {
            if (!Array.isArray(list) || !window.confirm(message)) return;
            list.splice(idx, 1);
        },
        rowKey(row) {
            return itemKey(row, Alpine.raw);
        },
        isRowOpen(row) {
            return !this.closedRows[this.rowKey(row)];
        },
        /** Restores a row collapsed before the last reload (uiState.js). */
        initRow(row, el) {
            if (uiGet(uiPath(el, 'row'), false)) this.closedRows[this.rowKey(row)] = true;
        },
        toggleRow(row, el = null) {
            const k = this.rowKey(row);
            this.closedRows[k] = !this.closedRows[k];
            if (el) uiSet(uiPath(el, 'row'), this.closedRows[k]);
        },
        setRowsOpen(list, open, el = null) {
            (Array.isArray(list) ? list : []).forEach((row) => { this.closedRows[this.rowKey(row)] = !open; });
            // Remember for every row of this list (heads are the toggles' parents).
            const rows = el?.closest('.ctrlf-repeater, .ctrlf-flex-content')?.querySelector(':scope > .ctrlf-rows');
            rows?.querySelectorAll(':scope > [data-ctrlf-item] > .ctrlf-row__head').forEach((head) => uiSet(uiPath(head, 'row'), !open));
        },
        rowSummary(row) {
            return rowSummary(row);
        },

        // -------------------------------------------------------------------
        // Image / File — WP Media Library
        // -------------------------------------------------------------------

        /**
         * Opens the WP media library and calls `callback(id, url)` on selection.
         * PHP renderers pass an arrow function that writes to the correct state path:
         *   @click="openMediaLibrary((id, url) => { adminState['key'] = id; }, 'image')"
         *
         * @param {Function} callback - Receives (attachmentId: number, url: string).
         * @param {string}   type     - 'image' or 'file'.
         */
        openMediaLibrary(callback, type = 'image') {
            if (typeof wp === 'undefined' || !wp.media) {
                console.warn('CtrlField: wp.media is not available.');
                return;
            }

            const frame = wp.media({
                title:    type === 'image' ? 'Select Image' : 'Select File',
                button:   { text: type === 'image' ? 'Use this image' : 'Use this file' },
                multiple: false,
                library:  { type: type === 'image' ? 'image' : '' },
            });

            frame.on('select', () => {
                const attachment = frame.state().get('selection').first().toJSON();
                const url = attachment.sizes?.thumbnail?.url ?? attachment.url ?? '';

                // Cache for getAttachmentUrl()
                this.attachmentUrls[attachment.id] = url;

                callback.call(this, attachment.id, url);
            });

            frame.open();
        },

        /**
         * Returns a cached thumbnail URL for a given attachment ID.
         * Falls back to empty string if not yet fetched.
         */
        getAttachmentUrl(id) {
            if (!id) return '';
            return this.attachmentUrls[id] ?? '';
        },

        // -------------------------------------------------------------------
        // Code Editor (CodeMirror)
        // -------------------------------------------------------------------

        /**
         * Initialises CodeMirror on all [data-ctrlfield-code] wrappers.
         * Called in init() after the DOM is ready.
         */
        initCodeEditors() {
            this.$el.querySelectorAll('[data-ctrlfield-code]').forEach(wrap => {
                const fieldKey = wrap.dataset.ctrlfieldCode;
                const textarea = wrap.querySelector('textarea');
                if (!textarea || typeof wp === 'undefined' || !wp.CodeMirror) return;

                const cm = wp.CodeMirror.fromTextArea(textarea, {
                    mode:          wrap.dataset.language || 'text',
                    lineNumbers:   true,
                    lineWrapping:  wrap.dataset.wrapLines === 'true',
                    indentUnit:    4,
                    tabSize:       4,
                    indentWithTabs: false,
                    theme:         'default',
                });

                // Sync CodeMirror → adminState
                cm.on('change', () => {
                    this.adminState[fieldKey] = cm.getValue();
                });

                // Sync stored value → CodeMirror
                const stored = this.adminState[fieldKey];
                if (stored && cm.getValue() !== stored) {
                    cm.setValue(stored);
                }
            });
        },

        // -------------------------------------------------------------------
        // User Field — AJAX search
        // -------------------------------------------------------------------

        /**
         * Search WordPress users via the CtrlField REST endpoint.
         * Debounced via Alpine's @input.debounce modifier in the rendered HTML.
         *
         * @param {string}   query    Search string (min 2 chars).
         * @param {string}   fieldKey Key of the UserField.
         * @param {string[]} roles    Optional role filter.
         */
        async searchUsers(query, fieldKey, roles) {
            if (query.length < 2) {
                this.userResults[fieldKey] = [];
                return;
            }

            const phData = window.ctrlfieldData ?? {};
            const params = new URLSearchParams({ search: query, per_page: 20 });

            if (Array.isArray(roles) && roles.length > 0) {
                params.set('roles', roles.join(','));
            }

            try {
                const res = await fetch(
                    `${phData.restUrl ?? ''}ctrlfield/v1/search/users?${params}`,
                    { headers: { 'X-WP-Nonce': phData.restNonce ?? '' } },
                );
                const data = await res.json();
                this.userResults[fieldKey] = data.results ?? [];
            } catch (e) {
                this.userResults[fieldKey] = [];
            }
        },

        /**
         * Selects a user result and updates adminState.
         *
         * @param {string}  fieldKey  Key of the UserField.
         * @param {object}  user      User object from the REST API.
         * @param {boolean} multiple  Whether the field accepts multiple users.
         */
        selectUser(fieldKey, user, multiple) {
            if (multiple) {
                if (!Array.isArray(this.adminState[fieldKey])) {
                    this.adminState[fieldKey] = [];
                }
                if (!this.adminState[fieldKey].includes(user.id)) {
                    this.adminState[fieldKey].push(user.id);
                    this.selectedUserLabels[fieldKey + '_' + user.id] = user.display_name;
                }
            } else {
                this.adminState[fieldKey]        = user.id;
                this.selectedUserLabels[fieldKey] = user.display_name;
            }
            this.userResults[fieldKey] = [];
        },

        // -------------------------------------------------------------------
        // Pickers — Post Object, Relationship, Taxonomy, Gallery, Map.
        // Renderers give each picker its own x-data ({ q, results }) and write
        // straight to the field's state path, so they also work inside
        // repeater and flexible-content rows.
        // -------------------------------------------------------------------

        /** 'post:12' / 'term:5' → display name. */
        pickerLabels: {},

        pickValue,
        mapWith,
        hasCoords,
        osmEmbedUrl,

        itemLabel(kind, id) {
            return this.pickerLabels[`${kind}:${id}`] ?? `#${id}`;
        },

        rememberLabel(kind, id, text) {
            this.pickerLabels[`${kind}:${id}`] = text;
        },

        async restGet(path, params) {
            const phData = window.ctrlfieldData ?? {};
            try {
                const res = await fetch(
                    `${phData.restUrl ?? ''}ctrlfield/v1/${path}?${new URLSearchParams(params)}`,
                    { headers: { 'X-WP-Nonce': phData.restNonce ?? '' } },
                );
                if (!res.ok) return [];
                const data = await res.json();
                return data.results ?? [];
            } catch (e) {
                return [];
            }
        },

        /** Published posts of the given types; latest first when the query is empty. */
        async searchPosts(query, postTypes) {
            const types = (Array.isArray(postTypes) ? postTypes : [postTypes]).filter(Boolean);
            const results = await this.restGet('search/posts', {
                post_type: types.length ? types.join(',') : 'post',
                search: query ?? '',
                per_page: 20,
            });
            return results.map((r) => ({ id: r.id, title: r.title || `#${r.id}`, meta: r.type ?? '' }));
        },

        async searchTerms(query, taxonomy) {
            const results = await this.restGet('search/terms', {
                taxonomy, search: query ?? '', per_page: 30,
            });
            return results.map((r) => ({ id: r.id, title: r.name, meta: `(${r.count})` }));
        },

        /**
         * Gallery: opens the media library (multiple selection) and passes the
         * merged id list to `done(ids)`.
         */
        openGallery(current, max, done) {
            if (typeof wp === 'undefined' || !wp.media) {
                console.warn('CtrlField: wp.media is not available.');
                return;
            }
            const frame = wp.media({
                title: 'Add images', button: { text: 'Add to gallery' },
                multiple: 'add', library: { type: 'image' },
            });
            frame.on('select', () => {
                const picked = frame.state().get('selection').toJSON();
                picked.forEach((a) => {
                    this.attachmentUrls[a.id] = a.sizes?.thumbnail?.url ?? a.url ?? '';
                });
                done(mergeGallery(current, picked.map((a) => a.id), max));
            });
            frame.open();
        },

        /** Address search via OpenStreetMap Nominatim (free, max 1 request/second). */
        async geocode(query) {
            if (!query || query.trim().length < 3) return [];
            try {
                const res = await fetch(
                    `https://nominatim.openstreetmap.org/search?${new URLSearchParams({ format: 'json', limit: 5, q: query })}`,
                    { headers: { 'Accept-Language': document.documentElement.lang || 'en' } },
                );
                const data = await res.json();
                return data.map((r) => ({ lat: Number(r.lat), lng: Number(r.lon), address: r.display_name }));
            } catch (e) {
                return [];
            }
        },

        // -------------------------------------------------------------------
        // FlexibleContent (Pro) — state managed here in the parent component
        // so all directives in the renderer HTML share the same scope.
        // -------------------------------------------------------------------

        /** { 'fieldKey': bool } — picker modal visibility */
        flexPickerOpen:   {},
        /** { 'fieldKey': string } — live search query */
        flexPickerSearch:   {},
        /** { 'fieldKey': string } — active category tab */
        flexPickerCategory: {},

        openFlexPicker(key) {
            this.flexPickerOpen[key]    = true;
            this.flexPickerSearch[key]  = '';
            this.$nextTick(() => {
                document.querySelector(`[data-ctrlf-picker="${key}"] .ctrlf-picker-search`)?.focus();
            });
        },
        closeFlexPicker(key) {
            this.flexPickerOpen[key]   = false;
            this.flexPickerSearch[key] = '';
        },
        isFlexPickerOpen(key) {
            return !!this.flexPickerOpen[key];
        },

        setFlexPickerSearch(key, val) {
            this.flexPickerSearch[key] = val;
        },
        getFlexPickerSearch(key) {
            return this.flexPickerSearch[key] ?? '';
        },
        setFlexPickerCategory(key, cat) {
            this.flexPickerCategory[key] = cat;
        },
        getFlexPickerCategory(key) {
            return this.flexPickerCategory[key] ?? 'all';
        },

        getFlexPickerCategories(layouts) {
            const cats = [...new Set(
                layouts.map(l => l.category).filter(c => c && c !== 'general')
            )];
            return cats.sort();
        },
        getFlexPickerFiltered(key, layouts) {
            const search = (this.flexPickerSearch[key] ?? '').toLowerCase();
            const cat    = this.flexPickerCategory[key] ?? 'all';
            return layouts.filter(l => {
                const matchSearch = !search
                    || l.label.toLowerCase().includes(search)
                    || (l.description || '').toLowerCase().includes(search);
                const matchCat = cat === 'all' || l.category === cat;
                return matchSearch && matchCat;
            });
        },

        /** Add a section of `layoutKey` to `list`; returns the list to assign back. */
        withLayout(list, layoutKey, pickerKey, defaults = {}) {
            const rows = Array.isArray(list) ? list : [];
            rows.push({ ...JSON.parse(JSON.stringify(defaults ?? {})), _layout: layoutKey });
            this.closeFlexPicker(pickerKey);
            return rows;
        },
    }));
});

Alpine.start();
