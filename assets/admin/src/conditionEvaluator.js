/**
 * Pure JS condition evaluator — no Alpine dependency.
 *
 * Receives a condition definition from PHP (ConditionGroup::toArray()) and the
 * current adminState.fields snapshot. Returns a boolean visibility decision.
 *
 * conditionDef shape:
 *   { type: 'all'|'any', conditions: [{ field, operator, value }, ...] }
 */

export function evaluateCondition(conditionDef, fieldValues) {
    const { type, conditions } = conditionDef;

    if (type === 'all') {
        return conditions.every(c => evaluateSingle(c, fieldValues));
    }
    if (type === 'any') {
        return conditions.some(c => evaluateSingle(c, fieldValues));
    }
    // Unknown type — safe default: visible
    return true;
}

function evaluateSingle({ field, operator, value }, fieldValues) {
    const a = fieldValues[field] ?? '';

    switch (operator) {
        case '==':           return a == value;   // loose: "1" == 1 is intentional
        case '!=':           return a != value;
        case '<':            return parseComparable(a) < parseComparable(value);
        case '<=':           return parseComparable(a) <= parseComparable(value);
        case '>':            return parseComparable(a) > parseComparable(value);
        case '>=':           return parseComparable(a) >= parseComparable(value);
        case 'contains':     return String(a).toLowerCase().includes(String(value).toLowerCase());
        case 'not_contains': return !String(a).toLowerCase().includes(String(value).toLowerCase());
        case 'empty':        return isEmpty(a);
        case 'not_empty':    return !isEmpty(a);
        case 'in':           return Array.isArray(value) && value.includes(a);
        case 'not_in':       return Array.isArray(value) && !value.includes(a);
        default:             return true;
    }
}

/**
 * Parses a value for ordered comparison (<, <=, >, >=).
 * Handles: ISO dates/datetimes, HH:MM time strings, numbers.
 */
function parseComparable(value) {
    if (typeof value === 'number') return value;
    const str = String(value).trim();

    // HH:MM time → minutes since midnight
    if (/^\d{2}:\d{2}$/.test(str)) {
        const [h, m] = str.split(':').map(Number);
        return h * 60 + m;
    }

    // ISO date or datetime → timestamp
    if (/^\d{4}-\d{2}-\d{2}/.test(str)) {
        const ts = Date.parse(str);
        if (!isNaN(ts)) return ts;
    }

    return parseFloat(str) || 0;
}

function isEmpty(value) {
    return !value || value === '' || (Array.isArray(value) && value.length === 0);
}
