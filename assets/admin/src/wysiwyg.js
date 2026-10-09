// WYSIWYG fields at any depth (groups, repeater rows, flexible sections).
//
//   <textarea x-ctrlf-wysiwyg="row_ab12['contentHtml']"></textarea>
//
// Each instance gets its own id and its own TinyMCE (wp.editor.initialize),
// bound to its state path both ways. Moving an iframe in the DOM empties
// TinyMCE, so editors are taken down when a drag starts and rebuilt when
// it ends (events from sortable.js); the content lives in Alpine state.

let counter = 0;

// wp.editor and its settings (tinyMCEPreInit) are printed in the footer,
// after this script runs: build editors once the page has loaded.
const pageLoaded = typeof document === 'undefined' || document.readyState === 'complete'
    ? Promise.resolve()
    : new Promise((resolve) => window.addEventListener('load', resolve, { once: true }));

// Fallback when the template editor is missing (another screen printed no footer).
const FALLBACK_TOOLBARS = {
    toolbar1: 'formatselect,bold,italic,bullist,numlist,blockquote,alignleft,aligncenter,alignright,link,unlink,wp_adv',
    toolbar2: 'strikethrough,hr,forecolor,pastetext,removeformat,charmap,outdent,indent,undo,redo',
};

/**
 * Settings for one editor. They start from the hidden template editor that
 * WysiwygEditorSettings.php prints, so the theme's formats, plugins, CSS and
 * toolbars apply (as in the WordPress editor). The field picks a named
 * toolbar (data-toolbar) and whether "Add Media" shows (data-media).
 */
export function editorSettings(el, preInit = globalThis.window?.tinyMCEPreInit, config = globalThis.window?.ctrlfieldData?.wysiwyg) {
    const base = globalThis.window?.wp?.editor?.getDefaultSettings?.() ?? {};
    const template = config?.template;
    const mce = { ...(preInit?.mceInit?.[template] ?? {}) };
    const qt = { ...(preInit?.qtInit?.[template] ?? {}) };
    for (const key of ['selector', 'wp_skip_init', 'body_class']) delete mce[key];
    delete qt.id;

    const hasTemplate = Object.keys(mce).length > 0;
    const toolbars = config?.toolbars ?? {};
    const name = el.dataset.toolbar || 'full';
    // Named toolbars come from the mce_buttons* and acf/fields/wysiwyg/toolbars
    // filters (PHP); an unknown name falls back to "full".
    const toolbar = toolbars[name] ?? toolbars.full ?? (hasTemplate ? {} : FALLBACK_TOOLBARS);

    return {
        ...base,
        mediaButtons: el.dataset.media !== '0',
        quicktags: Object.keys(qt).length ? qt : true,
        tinymce: {
            ...(base.tinymce ?? {}),
            ...mce,
            wpautop: mce.wpautop ?? true,
            ...toolbar,
        },
    };
}

export function registerWysiwyg(Alpine) {
    Alpine.directive('ctrlf-wysiwyg', (el, { expression }, { evaluateLater, effect, cleanup }) => {
        const id = `ctrlf_mce_${++counter}`;
        el.id = id;

        const read = evaluateLater(expression);
        const assign = evaluateLater(`${expression} = __ctrlfValue`);

        let value = '';
        let editor = null;
        let quiet = false; // ignore events while we set content ourselves

        const write = (html) => {
            if (quiet || html === value) return;
            value = html;
            assign(() => {}, { scope: { __ctrlfValue: html } });
        };

        const onText = () => write(el.value); // the "Code" tab edits the textarea

        const build = () => {
            if (editor || !el.isConnected) return;
            el.value = value;
            if (!window.wp?.editor?.initialize) {
                return; // no TinyMCE on this screen: a plain textarea still works
            }
            const s = editorSettings(el);
            s.tinymce.setup = (ed) => {
                editor = ed;
                ed.on('change keyup undo redo input', () => write(ed.getContent()));
            };
            window.wp.editor.initialize(id, s);
        };

        const teardown = () => {
            if (editor) write(editor.getContent());
            quiet = true;
            window.wp?.editor?.remove?.(id);
            quiet = false;
            editor = null;
        };

        // State → editor (initial value, and changes made elsewhere).
        effect(() => read((v) => {
            const html = v ?? '';
            if (html === value) return;
            value = html;
            quiet = true;
            if (editor) editor.setContent(html);
            else el.value = html;
            quiet = false;
        }));

        el.addEventListener('input', onText);
        const onSortStart = () => teardown();
        const onSortEnd = () => pageLoaded.then(() => Alpine.nextTick(build));
        document.addEventListener('ctrlf-sort-start', onSortStart);
        document.addEventListener('ctrlf-sort-end', onSortEnd);

        pageLoaded.then(() => Alpine.nextTick(build));

        cleanup(() => {
            document.removeEventListener('ctrlf-sort-start', onSortStart);
            document.removeEventListener('ctrlf-sort-end', onSortEnd);
            el.removeEventListener('input', onText);
            teardown();
        });
    });
}
