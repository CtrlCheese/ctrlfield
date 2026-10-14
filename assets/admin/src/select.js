// The Select field with many options (SelectRenderer.php): a button showing
// the choice, and a dropdown with a search box over the options.
//
//   x-data="ctrlfSelect({ options: [[value, label], …], none: '— None —' | null })"

/** Options whose label (or value) contains every word of the query (pure: tested). */
export function filterOptions(options, query) {
    const words = String(query ?? '').toLowerCase().trim().split(/\s+/).filter(Boolean);
    if (!words.length) return options;
    return options.filter(([value, label]) => {
        const text = `${label} ${value}`.toLowerCase();
        return words.every((w) => text.includes(w));
    });
}

export function registerSelect(Alpine) {
    Alpine.data('ctrlfSelect', (cfg = {}) => ({
        open: false,
        q: '',
        active: 0,

        all() {
            return cfg.none ? [['', cfg.none], ...(cfg.options ?? [])] : (cfg.options ?? []);
        },
        matches() {
            return filterOptions(this.all(), this.q);
        },
        labelOf(value) {
            const v = String(value ?? '');
            if (v === '') return '';
            return (cfg.options ?? []).find(([key]) => key === v)?.[1] ?? v;
        },
        toggle(force) {
            this.open = force ?? !this.open;
            if (this.open) {
                this.q = '';
                this.active = 0;
                this.$nextTick(() => this.$refs.search?.focus());
            }
        },
        move(step) {
            const n = this.matches().length;
            if (n) this.active = (this.active + step + n) % n;
        },
        pick(option) {
            this.open = false;
            this.$nextTick(() => this.$el.querySelector('.ctrlf-select__button')?.focus());
            return option[0];
        },
    }));
}
