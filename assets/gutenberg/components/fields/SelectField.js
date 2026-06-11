import { SelectControl } from '@wordpress/components';

export function SelectField({ field, value, onChange }) {
    const options = [
        { label: '— Select —', value: '' },
        ...Object.entries(field.options ?? {}).map(([val, label]) => ({ label, value: val })),
    ];

    return (
        <SelectControl
            label={ field.label || field.key }
            value={ value ?? '' }
            options={ options }
            onChange={ onChange }
            __nextHasNoMarginBottom
        />
    );
}
