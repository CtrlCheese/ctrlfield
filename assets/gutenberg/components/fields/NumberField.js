import { TextControl } from '@wordpress/components';

export function NumberField({ field, value, onChange }) {
    return (
        <TextControl
            label={ field.label || field.key }
            type="number"
            value={ value ?? '' }
            onChange={ v => onChange(v === '' ? null : Number(v)) }
            required={ field.required }
            __nextHasNoMarginBottom
        />
    );
}
