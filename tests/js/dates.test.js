import { describe, it, expect } from 'vitest';
import { parseValue, toValue, resolveDay, monthGrid, formatPhp, weekdayNames } from '../../assets/admin/src/dates.js';

describe('stored values', () => {
    it('round-trips the three modes', () => {
        expect(toValue('date', parseValue('date', '2026-10-13'))).toBe('2026-10-13');
        expect(toValue('time', parseValue('time', '09:05'))).toBe('09:05');
        expect(toValue('datetime', parseValue('datetime', '2026-10-13T14:30:00'))).toBe('2026-10-13T14:30:00');
        expect(parseValue('date', 'nope')).toBeNull();
    });

    it('resolves today and tomorrow', () => {
        const today = new Date(2026, 9, 31); // 31 Oct
        expect(resolveDay('today', today)).toBe('2026-10-31');
        expect(resolveDay('tomorrow', today)).toBe('2026-11-01');
        expect(resolveDay('2026-01-02T10:00:00', today)).toBe('2026-01-02');
    });
});

describe('calendar', () => {
    it('lays out 6 weeks from the first weekday', () => {
        const monday = monthGrid(2026, 10, 1); // October 2026 starts on a Thursday
        expect(monday).toHaveLength(42);
        expect(monday[0].iso).toBe('2026-09-28');
        expect(monday[3]).toMatchObject({ iso: '2026-10-01', inMonth: true });

        const sunday = monthGrid(2026, 10, 0);
        expect(sunday[0].iso).toBe('2026-09-27');
    });

    it('names weekdays from the first day', () => {
        expect(weekdayNames('en', 1)[0]).toBe('Mon');
        expect(weekdayNames('en', 0)[0]).toBe('Sun');
    });
});

describe('formatPhp', () => {
    const p = { y: 2026, m: 10, d: 3, h: 14, i: 5 };

    it('formats like PHP date()', () => {
        expect(formatPhp('d/m/Y', p)).toBe('03/10/2026');
        expect(formatPhp('j \\d\\e F \\d\\e Y', p, 'pt-BR')).toBe('3 de outubro de 2026');
        expect(formatPhp('F jS, Y g:i a', p, 'en')).toBe('October 3rd, 2026 2:05 pm');
        expect(formatPhp('H:i', p)).toBe('14:05');
    });
});
