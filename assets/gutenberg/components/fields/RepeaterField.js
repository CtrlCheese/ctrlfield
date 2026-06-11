import { Button } from '@wordpress/components';
import { FieldInput } from '../FieldInput';

export function RepeaterField({ field, value, onChange }) {
    const rows = Array.isArray(value) ? value : [];

    const emptyRow = () => {
        const row = {};
        (field.fields ?? []).forEach(sub => { row[sub.key] = null; });
        return row;
    };

    const addRow = () => onChange([...rows, emptyRow()]);

    const removeRow = (idx) => {
        if (!window.confirm('Remove this row?')) return;
        onChange(rows.filter((_, i) => i !== idx));
    };

    const updateRow = (idx, subKey, subValue) => {
        const updated = [...rows];
        updated[idx] = { ...updated[idx], [subKey]: subValue };
        onChange(updated);
    };

    const moveUp = (idx) => {
        if (idx <= 0) return;
        const updated = [...rows];
        [updated[idx - 1], updated[idx]] = [updated[idx], updated[idx - 1]];
        onChange(updated);
    };

    const moveDown = (idx) => {
        if (idx >= rows.length - 1) return;
        const updated = [...rows];
        [updated[idx], updated[idx + 1]] = [updated[idx + 1], updated[idx]];
        onChange(updated);
    };

    return (
        <div className="ff-gb-repeater">
            <p style={ { fontSize: '11px', fontWeight: 500, marginBottom: 8 } }>
                { field.label || field.key }
            </p>

            { rows.map((row, idx) => (
                <div
                    key={ idx }
                    className="ff-gb-repeater-row"
                    style={ {
                        border: '1px solid #dcdcde',
                        borderRadius: 3,
                        padding: 12,
                        marginBottom: 8,
                        background: '#fff',
                    } }
                >
                    { (field.fields ?? []).map((sub) => (
                        <FieldInput
                            key={ sub.key }
                            field={ sub }
                            value={ row[sub.key] }
                            onChange={ (v) => updateRow(idx, sub.key, v) }
                            allValues={ row }
                        />
                    )) }

                    <div style={ { display: 'flex', gap: 4, marginTop: 8 } }>
                        <Button size="small" onClick={ () => moveUp(idx) } disabled={ idx === 0 }>↑</Button>
                        <Button size="small" onClick={ () => moveDown(idx) } disabled={ idx === rows.length - 1 }>↓</Button>
                        <Button size="small" isDestructive onClick={ () => removeRow(idx) }>Remove</Button>
                    </div>
                </div>
            )) }

            <Button variant="secondary" size="small" onClick={ addRow }>
                + Add Row
            </Button>
        </div>
    );
}
