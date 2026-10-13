// CtrlField → Post Types: the post type / taxonomy form. The key follows the
// singular name until it is edited, is checked while typing (the server
// checks again on save), and the sidebar previews the URLs.
//
//   x-data="ctrlfTypeForm({ values, max, reserved, taken, home, isNew, i18n })"

/** "Équipe Membre" → "equipe_membre", at most `max` characters (pure: tested). */
export function slugify(text, max = 20) {
    return String(text ?? '')
        .normalize('NFD')
        .replace(/[̀-ͯ]/g, '')
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, '_')
        .replace(/^[^a-z]+/, '')
        .slice(0, max)
        .replace(/_+$/, '');
}

/**
 * What is wrong with a key, or '' (pure: tested):
 * 'required' | 'format' | 'reserved' | 'taken'.
 */
export function keyProblem(key, { max = 20, reserved = [], taken = [] } = {}) {
    if (!key) return 'required';
    if (!new RegExp(`^[a-z][a-z0-9_-]{0,${max - 1}}$`).test(key)) return 'format';
    if (reserved.includes(key)) return 'reserved';
    if (taken.includes(key)) return 'taken';
    return '';
}

/** "/work/" → "work" */
const path = (v) => String(v ?? '').trim().toLowerCase().replace(/^\/+|\/+$/g, '');

export function registerPostTypes(Alpine) {
    Alpine.data('ctrlfTypeForm', (cfg = {}) => ({
        v: { ...(cfg.values ?? {}) },
        keyEdited: !cfg.isNew || Boolean(cfg.values?.slug),

        init() {
            this.$watch('v.singular', (name) => {
                if (!this.keyEdited) this.v.slug = slugify(name, cfg.max ?? 20);
            });
        },
        editKey() {
            this.keyEdited = true;
        },
        get keyError() {
            const problem = keyProblem(this.v.slug, cfg);
            return problem && problem !== 'required' ? cfg.i18n?.[problem] ?? problem : '';
        },
        /** example.com/work/sample-entry/ */
        get entryUrl() {
            const base = path(this.v.rewrite_slug) || this.v.slug || '…';
            return `${cfg.home ?? ''}/${base}/${cfg.i18n?.sample ?? 'sample'}/`;
        },
        /** example.com/projects/ (archive) */
        get archiveUrl() {
            const base = path(this.v.archive_slug) || path(this.v.rewrite_slug) || this.v.slug || '…';
            return `${cfg.home ?? ''}/${base}/`;
        },
        /** /wp-json/wp/v2/projects */
        get restUrl() {
            return `/wp-json/wp/v2/${this.v.rest_base || this.v.slug || '…'}`;
        },
        has(list, item) {
            return (this.v[list] ?? []).includes(item);
        },
    }));
}
