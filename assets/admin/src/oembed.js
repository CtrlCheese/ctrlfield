// oEmbed field: the preview WordPress makes for a YouTube, Vimeo, … address
// (admin-ajax "ctrlfield_oembed_preview"), loaded for the saved value and
// while typing, with a loading state and a note when there is no preview.
//
//   x-data="ctrlfOembed({ width })"  ·  x-init="load(path)" · @input.debounce="load(path)"

const cache = new Map(); // url → html ('' = no preview)

/** Whether a value looks like an address worth asking about (pure: tested). */
export function isEmbeddable(url) {
    return /^https?:\/\/[^\s/]+\.[^\s/]+\/?\S*$/i.test(String(url ?? '').trim());
}

export function registerOembed(Alpine) {
    Alpine.data('ctrlfOembed', (cfg = {}) => ({
        html: '',
        state: 'idle', // idle | loading | ready | none
        request: 0,

        async load(url) {
            const value = String(url ?? '').trim();
            const mine = ++this.request;
            if (!isEmbeddable(value)) {
                this.html = '';
                this.state = 'idle';
                return;
            }
            if (cache.has(value)) {
                this.html = cache.get(value);
                this.state = this.html ? 'ready' : 'none';
                return;
            }
            this.state = 'loading';
            const d = globalThis.window?.ctrlfieldData ?? {};
            const params = new URLSearchParams({ action: 'ctrlfield_oembed_preview', nonce: d.oembedNonce ?? '', url: value, width: String(cfg.width ?? 640) });
            let html = '';
            try {
                const res = await fetch(`${d.ajaxUrl ?? '/wp-admin/admin-ajax.php'}?${params}`, { credentials: 'same-origin' });
                const body = await res.json();
                html = body?.success ? String(body.data?.html ?? '') : '';
            } catch {
                html = '';
            }
            cache.set(value, html);
            if (mine !== this.request) return;
            this.html = html;
            this.state = html ? 'ready' : 'none';
        },
    }));
}
