import { CheckboxControl } from '@wordpress/components';

export function CheckboxField({ field, value, onChange }) {
    const checked = Array.isArray(value) ? value : [];

    const toggle = (optValue) => {
        if (checked.includes(optValue)) {
            onChange(checked.filter(v => v !== optValue));
        } else {
            onChange([...checked, optValue]);
        }
    };

    return (
        <fieldset style={ { border: 'none', padding: 0, margin: 0 } }>
            <legend style={ { fontSize: '11px', fontWeight: 500, marginBottom: 4 } }>
                { field.label || field.key }
            </legend>
            { Object.entries(field.options ?? {}).map(([optValue, optLabel]) => (
                <CheckboxControl
                    key={ optValue }
                    label={ optLabel }
                    checked={ checked.includes(optValue) }
                    onChange={ () => toggle(optValue) }
                    __nextHasNoMarginBottom
                />
            )) }
        </fieldset>
    );
}
