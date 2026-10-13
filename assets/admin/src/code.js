// Code field: CodeMirror (shipped with WordPress) on the field's textarea.
//
//   <div x-ctrlf-code data-language="css" data-wrap-lines="false"><textarea x-model="path"></textarea></div>
//
// A directive, so it starts wherever Alpine renders the field — repeater and
// flexible rows included — and writes through the textarea's own x-model.
// CodeMirror started inside a hidden tab or accordion draws nothing until it
// is refreshed: it is refreshed whenever it becomes visible.

export function registerCode(Alpine) {
    Alpine.directive('ctrlf-code', (el, _directive, { cleanup }) => {
        const textarea = el.querySelector('textarea');
        const CodeMirror = globalThis.wp?.CodeMirror;
        if (!textarea || !CodeMirror || el._ctrlfCode) return;

        // After Alpine filled the textarea from its x-model.
        queueMicrotask(() => {
            const cm = CodeMirror.fromTextArea(textarea, {
                mode: el.dataset.language || 'text',
                lineNumbers: true,
                lineWrapping: el.dataset.wrapLines === 'true',
                indentUnit: 4,
                tabSize: 4,
                indentWithTabs: false,
                readOnly: textarea.readOnly ? 'nocursor' : false,
            });
            el._ctrlfCode = cm;

            cm.on('change', () => {
                textarea.value = cm.getValue();
                textarea.dispatchEvent(new Event('input', { bubbles: true }));
            });

            // The value changed from outside (row duplicated, restored…).
            const sync = () => {
                if (textarea.value !== cm.getValue()) cm.setValue(textarea.value);
            };
            textarea.addEventListener('ctrlf:sync', sync);

            const visible = new IntersectionObserver((entries) => {
                if (entries.some((e) => e.isIntersecting)) cm.refresh();
            });
            visible.observe(el);

            cleanup(() => {
                visible.disconnect();
                textarea.removeEventListener('ctrlf:sync', sync);
                cm.toTextArea();
                el._ctrlfCode = null;
            });
        });
    });
}
