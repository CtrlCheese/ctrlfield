// Schema Inspector: CSV import per group (InspectorServiceProvider::handleCsvImport)
// and the post chooser of the Post values tab (the picker's REST search).
//
//   x-data="ctrlfCsvImport({ group, nonce })"  ·  @change="send($event.target)"
//   x-data="ctrlfInspectorPost({ source: 'post:page,project', url })"

/** "3 updated · 1 created · 2 skipped" from the importer's stats (pure: tested). */
export function importSummary(stats, t = (s) => s) {
    if (!stats) return '';
    const parts = [
        stats.updated ? `${stats.updated} ${t('updated')}` : '',
        stats.created ? `${stats.created} ${t('created')}` : '',
        stats.skipped ? `${stats.skipped} ${t('skipped')}` : '',
    ].filter(Boolean);
    const summary = parts.length ? parts.join(' · ') : t('nothing changed');
    return stats.errors?.length ? `${summary} — ${stats.errors.slice(0, 3).join('; ')}` : summary;
}

export function registerInspector(Alpine) {
    Alpine.data('ctrlfCsvImport', (cfg = {}) => ({
        busy: false,
        result: '',
        failed: false,

        async send(input) {
            const file = input.files?.[0];
            if (!file) return;
            const d = globalThis.window?.ctrlfieldData ?? {};
            const body = new FormData();
            body.append('action', 'ctrlfield_import_csv');
            body.append('group', cfg.group ?? '');
            body.append('nonce', cfg.nonce ?? '');
            body.append('csv_file', file);
            this.busy = true;
            this.result = '';
            try {
                const res = await fetch(d.ajaxUrl ?? '/wp-admin/admin-ajax.php', { method: 'POST', body, credentials: 'same-origin' });
                const json = await res.json();
                this.failed = !json?.success;
                this.result = json?.success ? importSummary(json.data) : (json?.data?.message ?? 'Import failed.');
            } catch {
                this.failed = true;
                this.result = 'Import failed.';
            }
            this.busy = false;
            input.value = '';
        },
    }));

    Alpine.data('ctrlfInspectorPost', (cfg = {}) => ({
        q: '',
        results: [],
        loading: false,
        open: false,
        request: 0,

        async search() {
            const d = globalThis.window?.ctrlfieldData ?? {};
            const id = ++this.request;
            this.loading = true;
            this.open = true;
            try {
                const params = new URLSearchParams({ source: cfg.source ?? 'post:post', search: this.q.trim(), per_page: '8' });
                const res = await fetch(`${d.restUrl ?? '/wp-json/'}ctrlfield/v1/picker?${params}`, {
                    headers: { 'X-WP-Nonce': d.restNonce ?? '' },
                    credentials: 'same-origin',
                });
                const json = await res.json();
                if (id === this.request) this.results = json?.results ?? [];
            } catch {
                if (id === this.request) this.results = [];
            }
            if (id === this.request) this.loading = false;
        },
        link(postId) {
            return `${cfg.url ?? ''}&post=${postId}`;
        },
    }));
}
