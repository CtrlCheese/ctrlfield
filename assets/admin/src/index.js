// CSS is compiled separately by @tailwindcss/cli → assets/admin/fieldforge.css
import Alpine from 'alpinejs';
import { evaluateCondition } from './conditionEvaluator.js';

// -----------------------------------------------------------------------
// fieldForgeAdmin — single root Alpine component for the meta box
// -----------------------------------------------------------------------

document.addEventListener('alpine:init', () => {
    Alpine.data('fieldForgeAdmin', ({ values, conditions }) => ({

        /** Reactive field state — serialised into fieldforge_payload on save. */
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

            // Pre-populate attachment URLs from fieldforgeData (PHP-localised).
            const phData = window.fieldforgeData ?? {};
            this.attachmentUrls = phData.attachments ?? {};

            // WYSIWYG bridge is initialised after the DOM + TinyMCE are ready.
            this.$nextTick(() => {
                this.initWysiwygs();
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
        // Repeater
        // -------------------------------------------------------------------

        /**
         * Adds a new empty row to the repeater.
         * @param {string}  fieldKey  - Key of the repeater field in adminState.
         * @param {object}  emptyRow  - Object with all sub-keys set to null.
         */
        addRow(fieldKey, emptyRow) {
            if (!Array.isArray(this.adminState[fieldKey])) {
                this.adminState[fieldKey] = [];
            }
            this.adminState[fieldKey].push(JSON.parse(JSON.stringify(emptyRow)));
        },

        /**
         * Removes a row after user confirmation.
         */
        removeRow(fieldKey, idx) {
            if (!confirm('Remove this row?')) return;
            this.adminState[fieldKey].splice(idx, 1);
        },

        moveRowUp(fieldKey, idx) {
            if (idx <= 0) return;
            const arr = this.adminState[fieldKey];
            [arr[idx - 1], arr[idx]] = [arr[idx], arr[idx - 1]];
        },

        moveRowDown(fieldKey, idx) {
            const arr = this.adminState[fieldKey];
            if (idx >= arr.length - 1) return;
            [arr[idx], arr[idx + 1]] = [arr[idx + 1], arr[idx]];
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
                console.warn('FieldForge: wp.media is not available.');
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
        // WYSIWYG (TinyMCE bridge)
        // -------------------------------------------------------------------

        /**
         * Iterates all [data-fieldforge-wysiwyg] wrappers and attaches a
         * TinyMCE change listener that keeps adminState in sync.
         * Retries every 200ms until TinyMCE initialises (WP loads it async).
         */
        initWysiwygs() {
            this.$el.querySelectorAll('[data-fieldforge-wysiwyg]').forEach(wrapper => {
                const fieldKey  = wrapper.dataset.fieldforgeWysiwyg;
                const statePath = wrapper.dataset.statepath;
                const editorId  = 'ff_wysiwyg_' + fieldKey;

                const tryBind = setInterval(() => {
                    const editor = window.tinymce?.get(editorId);
                    if (!editor) return;

                    clearInterval(tryBind);

                    // Sync initial stored value into TinyMCE
                    const stored = this.adminState[fieldKey] ?? '';
                    if (stored && editor.getContent() !== stored) {
                        editor.setContent(stored);
                    }

                    // Sync TinyMCE → adminState on every change
                    editor.on('Change KeyUp SetContent Undo Redo', () => {
                        this.adminState[fieldKey] = editor.getContent();
                    });
                }, 200);

                // Stop polling after 10 s to avoid infinite loops on pages
                // where TinyMCE never initialises (e.g. quick-edit context).
                setTimeout(() => clearInterval(tryBind), 10_000);
            });
        },

        // -------------------------------------------------------------------
        // Code Editor (CodeMirror)
        // -------------------------------------------------------------------

        /**
         * Initialises CodeMirror on all [data-fieldforge-code] wrappers.
         * Called in init() after the DOM is ready.
         */
        initCodeEditors() {
            this.$el.querySelectorAll('[data-fieldforge-code]').forEach(wrap => {
                const fieldKey = wrap.dataset.fieldforgeCode;
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
         * Search WordPress users via the FieldForge REST endpoint.
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

            const phData = window.fieldforgeData ?? {};
            const params = new URLSearchParams({ search: query, per_page: 20 });

            if (Array.isArray(roles) && roles.length > 0) {
                params.set('roles', roles.join(','));
            }

            try {
                const res = await fetch(
                    `${phData.restUrl ?? ''}fieldforge/v1/search/users?${params}`,
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
    }));
});

Alpine.start();
