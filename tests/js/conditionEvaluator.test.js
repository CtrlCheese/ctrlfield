import { describe, it, expect } from 'vitest';
import { evaluateCondition } from '../../assets/admin/src/conditionEvaluator.js';

// Helper to build a condition definition
const all = (...conditions) => ({ type: 'all', conditions });
const any = (...conditions) => ({ type: 'any', conditions });
const cond = (field, operator, value) => ({ field, operator, value });

// ─── AND / OR group logic ────────────────────────────────────────────────────

describe('AND group', () => {
    it('returns true when all conditions match', () => {
        expect(evaluateCondition(
            all(cond('type', '==', 'external'), cond('tier', '!=', 'free')),
            { type: 'external', tier: 'pro' }
        )).toBe(true);
    });

    it('returns false when any condition fails', () => {
        expect(evaluateCondition(
            all(cond('type', '==', 'external'), cond('tier', '!=', 'free')),
            { type: 'external', tier: 'free' }
        )).toBe(false);
    });

    it('returns false when all conditions fail', () => {
        expect(evaluateCondition(
            all(cond('type', '==', 'external'), cond('tier', '==', 'pro')),
            { type: 'internal', tier: 'free' }
        )).toBe(false);
    });
});

describe('OR group', () => {
    it('returns true when at least one condition matches', () => {
        expect(evaluateCondition(
            any(cond('type', '==', 'external'), cond('type', '==', 'agency')),
            { type: 'agency' }
        )).toBe(true);
    });

    it('returns false when no condition matches', () => {
        expect(evaluateCondition(
            any(cond('type', '==', 'external'), cond('type', '==', 'agency')),
            { type: 'internal' }
        )).toBe(false);
    });

    it('returns true when all conditions match (OR is inclusive)', () => {
        expect(evaluateCondition(
            any(cond('type', '==', 'external'), cond('tier', '==', 'pro')),
            { type: 'external', tier: 'pro' }
        )).toBe(true);
    });
});

// ─── Equality operators ───────────────────────────────────────────────────────

describe('== operator', () => {
    it('matches equal strings', () => {
        expect(evaluateCondition(all(cond('x', '==', 'foo')), { x: 'foo' })).toBe(true);
    });

    it('uses loose equality: "1" == 1', () => {
        expect(evaluateCondition(all(cond('x', '==', 1)), { x: '1' })).toBe(true);
    });

    it('returns false when values differ', () => {
        expect(evaluateCondition(all(cond('x', '==', 'foo')), { x: 'bar' })).toBe(false);
    });

    it('treats missing field as empty string', () => {
        expect(evaluateCondition(all(cond('missing', '==', '')), {})).toBe(true);
    });
});

describe('!= operator', () => {
    it('returns true when values differ', () => {
        expect(evaluateCondition(all(cond('x', '!=', 'foo')), { x: 'bar' })).toBe(true);
    });

    it('returns false when values are equal', () => {
        expect(evaluateCondition(all(cond('x', '!=', 'foo')), { x: 'foo' })).toBe(false);
    });
});

// ─── Numeric comparison operators ────────────────────────────────────────────

describe('< operator', () => {
    it('returns true when a < b', () => {
        expect(evaluateCondition(all(cond('score', '<', 10)), { score: 5 })).toBe(true);
    });

    it('returns false when a == b', () => {
        expect(evaluateCondition(all(cond('score', '<', 10)), { score: 10 })).toBe(false);
    });

    it('returns false when a > b', () => {
        expect(evaluateCondition(all(cond('score', '<', 10)), { score: 15 })).toBe(false);
    });
});

describe('<= operator', () => {
    it('returns true when a < b', () => {
        expect(evaluateCondition(all(cond('n', '<=', 10)), { n: 9 })).toBe(true);
    });

    it('returns true when a == b', () => {
        expect(evaluateCondition(all(cond('n', '<=', 10)), { n: 10 })).toBe(true);
    });
});

describe('> operator', () => {
    it('returns true when a > b', () => {
        expect(evaluateCondition(all(cond('n', '>', 5)), { n: 10 })).toBe(true);
    });

    it('returns false when a == b', () => {
        expect(evaluateCondition(all(cond('n', '>', 5)), { n: 5 })).toBe(false);
    });
});

describe('>= operator', () => {
    it('returns true when a == b', () => {
        expect(evaluateCondition(all(cond('n', '>=', 5)), { n: 5 })).toBe(true);
    });

    it('returns true when a > b', () => {
        expect(evaluateCondition(all(cond('n', '>=', 5)), { n: 10 })).toBe(true);
    });
});

// ─── Text operators ───────────────────────────────────────────────────────────

describe('contains operator', () => {
    it('returns true when substring found (case-insensitive)', () => {
        expect(evaluateCondition(all(cond('body', 'contains', 'hello')), { body: 'Hello World' })).toBe(true);
    });

    it('returns false when substring not found', () => {
        expect(evaluateCondition(all(cond('body', 'contains', 'xyz')), { body: 'Hello World' })).toBe(false);
    });
});

describe('not_contains operator', () => {
    it('returns true when substring not found', () => {
        expect(evaluateCondition(all(cond('body', 'not_contains', 'xyz')), { body: 'Hello World' })).toBe(true);
    });

    it('returns false when substring found', () => {
        expect(evaluateCondition(all(cond('body', 'not_contains', 'Hello')), { body: 'Hello World' })).toBe(false);
    });
});

// ─── Empty / not_empty operators ─────────────────────────────────────────────

describe('empty operator', () => {
    it('returns true for empty string', () => {
        expect(evaluateCondition(all(cond('x', 'empty', null)), { x: '' })).toBe(true);
    });

    it('returns true for missing field', () => {
        expect(evaluateCondition(all(cond('x', 'empty', null)), {})).toBe(true);
    });

    it('returns true for empty array', () => {
        expect(evaluateCondition(all(cond('x', 'empty', null)), { x: [] })).toBe(true);
    });

    it('returns false for non-empty string', () => {
        expect(evaluateCondition(all(cond('x', 'empty', null)), { x: 'hello' })).toBe(false);
    });

    it('returns false for string "0" (non-empty string)', () => {
        expect(evaluateCondition(all(cond('x', 'empty', null)), { x: '0' })).toBe(false);
    });
});

describe('not_empty operator', () => {
    it('returns true for non-empty string', () => {
        expect(evaluateCondition(all(cond('x', 'not_empty', null)), { x: 'hello' })).toBe(true);
    });

    it('returns false for empty string', () => {
        expect(evaluateCondition(all(cond('x', 'not_empty', null)), { x: '' })).toBe(false);
    });
});

// ─── Array operators ──────────────────────────────────────────────────────────

describe('in operator', () => {
    it('returns true when value is in the array', () => {
        expect(evaluateCondition(all(cond('color', 'in', ['red', 'blue'])), { color: 'red' })).toBe(true);
    });

    it('returns false when value is not in the array', () => {
        expect(evaluateCondition(all(cond('color', 'in', ['red', 'blue'])), { color: 'green' })).toBe(false);
    });
});

describe('not_in operator', () => {
    it('returns true when value is not in the array', () => {
        expect(evaluateCondition(all(cond('color', 'not_in', ['red', 'blue'])), { color: 'green' })).toBe(true);
    });

    it('returns false when value is in the array', () => {
        expect(evaluateCondition(all(cond('color', 'not_in', ['red', 'blue'])), { color: 'blue' })).toBe(false);
    });
});

// ─── Date / time comparison ───────────────────────────────────────────────────

describe('date comparison', () => {
    it('compares ISO dates correctly', () => {
        expect(evaluateCondition(all(cond('deadline', '>', '2026-01-01')), { deadline: '2026-06-15' })).toBe(true);
        expect(evaluateCondition(all(cond('deadline', '<', '2026-01-01')), { deadline: '2026-06-15' })).toBe(false);
    });

    it('compares ISO datetimes correctly', () => {
        expect(evaluateCondition(
            all(cond('ts', '>', '2026-01-01T00:00:00')),
            { ts: '2026-06-15T12:00:00' }
        )).toBe(true);
    });
});

describe('time comparison', () => {
    it('converts HH:MM to minutes for comparison', () => {
        expect(evaluateCondition(all(cond('start', '<', '14:00')), { start: '09:00' })).toBe(true);
        expect(evaluateCondition(all(cond('start', '>', '14:00')), { start: '09:00' })).toBe(false);
    });

    it('treats equal times as not less than', () => {
        expect(evaluateCondition(all(cond('t', '<', '12:00')), { t: '12:00' })).toBe(false);
        expect(evaluateCondition(all(cond('t', '<=', '12:00')), { t: '12:00' })).toBe(true);
    });
});

// ─── Edge cases ───────────────────────────────────────────────────────────────

describe('edge cases', () => {
    it('unknown type returns true (safe default)', () => {
        expect(evaluateCondition({ type: 'unknown', conditions: [] }, {})).toBe(true);
    });

    it('unknown operator returns true (safe default)', () => {
        expect(evaluateCondition(all(cond('x', 'unsupported_op', 'y')), { x: 'z' })).toBe(true);
    });

    it('empty conditions AND group returns true', () => {
        expect(evaluateCondition({ type: 'all', conditions: [] }, {})).toBe(true);
    });

    it('empty conditions OR group returns false (Array.some of empty is false)', () => {
        expect(evaluateCondition({ type: 'any', conditions: [] }, {})).toBe(false);
    });
});
