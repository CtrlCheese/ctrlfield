// Date, date-time and time fields: the plugin's own calendar, the same in
// every browser (DateRenderer.php). The field shows the value in the site's
// (or the field's) format; storage stays ISO: "2026-10-13", "14:30",
// "2026-10-13T14:30:00".
//
//   x-data="ctrlfDate({ mode: 'date'|'datetime'|'time', format, min, max, step })"

function data() {
    return globalThis.window?.ctrlfieldData ?? {};
}

function t(key, fallback) {
    return data().i18n?.dates?.[key] ?? fallback;
}

const pad = (n, size = 2) => String(n).padStart(size, '0');

/** "2026-10-13", "14:30", "2026-10-13T14:30:00" → parts, or null (pure: tested). */
export function parseValue(mode, value) {
    const v = String(value ?? '').trim();
    if (mode === 'time') {
        const m = v.match(/^(\d{2}):(\d{2})/);
        return m ? { h: +m[1], i: +m[2] } : null;
    }
    const m = v.match(/^(\d{4})-(\d{2})-(\d{2})(?:[T ](\d{2}):(\d{2}))?/);
    if (!m) return null;
    return { y: +m[1], m: +m[2], d: +m[3], h: m[4] !== undefined ? +m[4] : 0, i: m[5] !== undefined ? +m[5] : 0 };
}

/** Parts → the stored string (pure: tested). */
export function toValue(mode, p) {
    if (!p) return '';
    if (mode === 'time') return `${pad(p.h)}:${pad(p.i)}`;
    const date = `${p.y}-${pad(p.m)}-${pad(p.d)}`;
    return mode === 'datetime' ? `${date}T${pad(p.h ?? 0)}:${pad(p.i ?? 0)}:00` : date;
}

/** "today" / "tomorrow" / "2026-10-13" → "2026-10-13" (pure, given today: tested). */
export function resolveDay(value, today = new Date()) {
    if (!value) return null;
    const base = new Date(today.getFullYear(), today.getMonth(), today.getDate());
    if (value === 'today' || value === 'tomorrow') {
        if (value === 'tomorrow') base.setDate(base.getDate() + 1);
        return `${base.getFullYear()}-${pad(base.getMonth() + 1)}-${pad(base.getDate())}`;
    }
    return String(value).slice(0, 10);
}

/**
 * A month as 6 weeks of 7 days, starting on firstDay (0 = Sunday, 1 = Monday);
 * each cell { y, m, d, iso, inMonth } (pure: tested).
 */
export function monthGrid(year, month, firstDay = 1) {
    const first = new Date(year, month - 1, 1);
    const offset = (first.getDay() - firstDay + 7) % 7;
    const cells = [];
    for (let i = 0; i < 42; i++) {
        const day = new Date(year, month - 1, 1 - offset + i);
        const y = day.getFullYear();
        const m = day.getMonth() + 1;
        const d = day.getDate();
        cells.push({ y, m, d, iso: `${y}-${pad(m)}-${pad(d)}`, inMonth: m === month });
    }
    return cells;
}

/**
 * A PHP date() format with the parts the admin needs: d j D l N S w m n M F
 * Y y a A g G h H i s; "\x" escapes (pure: tested).
 */
export function formatPhp(format, p, locale = 'en') {
    if (!p) return '';
    const date = new Date(p.y ?? 2000, (p.m ?? 1) - 1, p.d ?? 1, p.h ?? 0, p.i ?? 0);
    const name = (opts) => new Intl.DateTimeFormat(locale, opts).format(date);
    const h = p.h ?? 0;
    const suffix = (d) => (d % 10 === 1 && d !== 11 ? 'st' : d % 10 === 2 && d !== 12 ? 'nd' : d % 10 === 3 && d !== 13 ? 'rd' : 'th');
    let out = '';
    for (let k = 0; k < format.length; k++) {
        const c = format[k];
        if (c === '\\') { out += format[++k] ?? ''; continue; }
        out += {
            d: () => pad(p.d), j: () => String(p.d), D: () => name({ weekday: 'short' }), l: () => name({ weekday: 'long' }),
            N: () => String(date.getDay() || 7), w: () => String(date.getDay()), S: () => suffix(p.d),
            m: () => pad(p.m), n: () => String(p.m), M: () => name({ month: 'short' }), F: () => name({ month: 'long' }),
            Y: () => String(p.y), y: () => pad((p.y ?? 0) % 100),
            a: () => (h < 12 ? 'am' : 'pm'), A: () => (h < 12 ? 'AM' : 'PM'),
            g: () => String(h % 12 || 12), G: () => String(h), h: () => pad(h % 12 || 12), H: () => pad(h), i: () => pad(p.i ?? 0), s: () => '00',
        }[c]?.() ?? c;
    }
    return out;
}

/** Weekday names in order from firstDay, short (pure: tested). */
export function weekdayNames(locale = 'en', firstDay = 1) {
    const names = [];
    for (let i = 0; i < 7; i++) {
        const day = new Date(2024, 0, 7 + ((firstDay + i) % 7)); // 7 Jan 2024 is a Sunday
        names.push(new Intl.DateTimeFormat(locale, { weekday: 'short' }).format(day));
    }
    return names;
}

export function registerDates(Alpine) {
    Alpine.data('ctrlfDate', (cfg = {}) => {
        const d = data().dates ?? {};
        const locale = d.locale || globalThis.document?.documentElement?.lang || 'en';
        const firstDay = Number.isInteger(d.firstDay) ? d.firstDay : 1;
        const mode = cfg.mode ?? 'date';
        const format = cfg.format || (mode === 'time' ? d.timeFormat : mode === 'datetime' ? `${d.dateFormat} ${d.timeFormat}` : d.dateFormat) || 'Y-m-d';
        const step = Math.max(1, Number(cfg.step) || 1);
        const today = new Date();

        return {
            open: false,
            view: { y: today.getFullYear(), m: today.getMonth() + 1 },
            focusIso: '',
            weekdays: weekdayNames(locale, firstDay),
            hours: Array.from({ length: 24 }, (_, n) => n),
            minutes: Array.from({ length: Math.ceil(60 / step) }, (_, n) => n * step),
            mode,

            display(value) {
                return formatPhp(format, parseValue(mode, value), locale);
            },
            title() {
                return formatPhp('F Y', { y: this.view.y, m: this.view.m, d: 1 }, locale);
            },
            days() {
                return monthGrid(this.view.y, this.view.m, firstDay);
            },
            disabled(iso) {
                const min = resolveDay(cfg.min);
                const max = resolveDay(cfg.max);
                return (min && iso < min.slice(0, 10)) || (max && iso > max.slice(0, 10));
            },
            isToday(iso) {
                return iso === resolveDay('today');
            },
            selectedIso(value) {
                const p = parseValue(mode, value);
                return p && mode !== 'time' ? toValue('date', p) : '';
            },
            toggle(value) {
                this.open = !this.open;
                if (!this.open) return;
                const p = parseValue(mode, value);
                if (p && mode !== 'time') this.view = { y: p.y, m: p.m };
                this.focusIso = this.selectedIso(value) || resolveDay('today');
                this.$nextTick(() => this.$el.querySelector('.ctrlf-cal__day.is-focus, .ctrlf-cal__time select')?.focus());
            },
            shift(months) {
                const date = new Date(this.view.y, this.view.m - 1 + months, 1);
                this.view = { y: date.getFullYear(), m: date.getMonth() + 1 };
            },
            /** The value after choosing a day (keeps the time of a date-time). */
            pickDay(value, cell) {
                const p = parseValue(mode, value) ?? { h: 9, i: 0 };
                const next = toValue(mode, { y: cell.y, m: cell.m, d: cell.d, h: p.h, i: p.i });
                this.focusIso = cell.iso;
                if (mode === 'date') this.close();
                return next;
            },
            /** The value after changing the hour or minute. */
            setTime(value, part, number) {
                const now = new Date();
                const p = parseValue(mode, value) ?? { y: now.getFullYear(), m: now.getMonth() + 1, d: now.getDate(), h: 0, i: 0 };
                p[part] = Number(number);
                return toValue(mode, p);
            },
            part(value, key) {
                return parseValue(mode, value)?.[key] ?? '';
            },
            now() {
                const n = new Date();
                const mins = Math.floor(n.getMinutes() / step) * step;
                return toValue(mode, { y: n.getFullYear(), m: n.getMonth() + 1, d: n.getDate(), h: n.getHours(), i: mins });
            },
            /** Arrow keys move the focused day; Enter picks it; PageUp/Down change the month. */
            key(e, value, set) {
                const move = { ArrowLeft: -1, ArrowRight: 1, ArrowUp: -7, ArrowDown: 7 }[e.key];
                if (move || e.key === 'PageUp' || e.key === 'PageDown') {
                    e.preventDefault();
                    const base = parseValue('date', this.focusIso) ?? parseValue('date', resolveDay('today'));
                    const date = new Date(base.y, base.m - 1, base.d);
                    if (move) date.setDate(date.getDate() + move);
                    else date.setMonth(date.getMonth() + (e.key === 'PageUp' ? -1 : 1));
                    this.focusIso = toValue('date', { y: date.getFullYear(), m: date.getMonth() + 1, d: date.getDate() });
                    this.view = { y: date.getFullYear(), m: date.getMonth() + 1 };
                    this.$nextTick(() => this.$el.querySelector('.ctrlf-cal__day.is-focus')?.focus());
                }
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    const cell = this.days().find((c) => c.iso === this.focusIso);
                    if (cell && !this.disabled(cell.iso)) set(this.pickDay(value, cell));
                }
            },
            close() {
                this.open = false;
                this.$nextTick(() => this.$el.querySelector('.ctrlf-date__button')?.focus());
            },
            label(key, fallback) {
                return t(key, fallback);
            },
        };
    });
}
