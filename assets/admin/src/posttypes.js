// CtrlField → Post Types: the post type / taxonomy form. The key follows the
// singular name until it is edited, is checked while typing (the server
// checks again on save), and the sidebar previews the URLs.
//
//   x-data="ctrlfTypeForm({ values, max, reserved, taken, home, isNew, i18n })"
//   x-data="ctrlfModelWizard({ type, link, newTax, fields, step, reserved, taken, home, i18n })"

/** "Équipe Membre" → "equipe_membre", at most `max` characters (pure: tested). */
export function slugify(text, max = 20) {
    return String(text ?? '')
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
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

/** "team-member" → "team_member_fields" (pure: tested). */
export function groupKeyFor(postType) {
    return `${String(postType ?? '').replace(/-/g, '_')}_fields`;
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

    // The content model wizard (ContentModelWizard.php): four steps, one submit.
    Alpine.data('ctrlfModelWizard', (cfg = {}) => ({
        step: cfg.step ?? 1,
        type: { ...(cfg.type ?? {}) },
        typeKeyEdited: Boolean(cfg.type?.slug),
        link: [...(cfg.link ?? [])],
        newTax: (cfg.newTax ?? []).map((t) => ({ ...t, keyEdited: Boolean(t.slug) })),
        fields: { ...(cfg.fields ?? {}) },

        init() {
            this.$watch('type.singular', (name) => {
                if (!this.typeKeyEdited) this.type.slug = slugify(name, 20);
            });
        },
        message(problem) {
            return problem && problem !== 'required' ? cfg.i18n?.[problem] ?? problem : '';
        },
        get typeKeyError() {
            return this.message(keyProblem(this.type.slug, { max: 20, reserved: cfg.reserved?.types ?? [], taken: cfg.taken?.types ?? [] }));
        },
        taxSingular(tax) {
            if (!tax.keyEdited) tax.slug = slugify(tax.singular, 32);
        },
        taxKeyError(tax, i) {
            if (!tax.plural && !tax.singular) return '';
            const others = this.newTax.filter((t, j) => j !== i).map((t) => t.slug);
            return this.message(keyProblem(tax.slug, { max: 32, reserved: cfg.reserved?.taxes ?? [], taken: [...(cfg.taken?.taxes ?? []), ...others] }));
        },
        groupKey() {
            return groupKeyFor(this.type.slug || '…');
        },
        groupTitle() {
            return this.type.singular || '';
        },
        groupError() {
            return this.fields.create && (cfg.taken?.groups ?? []).includes(this.groupKey()) ? cfg.i18n?.taken ?? 'taken' : '';
        },
        entryUrl() {
            return `${cfg.home ?? ''}/${this.type.slug || '…'}/`;
        },
        stepOk(step) {
            if (step === 1) return Boolean(this.type.plural?.trim() && this.type.singular?.trim()) && keyProblem(this.type.slug, { max: 20, reserved: cfg.reserved?.types ?? [], taken: cfg.taken?.types ?? [] }) === '';
            if (step === 2) return this.newTax.every((t, i) => (!t.plural && !t.singular) || (t.plural && t.singular && !this.taxKeyError(t, i) && t.slug));
            if (step === 3) return !this.groupError();
            return true;
        },
        canReach(step) {
            for (let s = 1; s < step; s++) if (!this.stepOk(s)) return false;
            return true;
        },
        goTo(step) {
            if (this.canReach(step)) this.step = step;
        },
        canCreate() {
            return this.canReach(4);
        },
    }));
}
