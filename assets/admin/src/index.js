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
            this.$nextTick(() => this.initWysiwygs());
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
    }));
});

Alpine.start();
