import { TextControl } from '@wordpress/components';

export function TextField({ field, value, onChange }) {
    return (
        <TextControl
            label={ field.label || field.key }
            value={ value ?? '' }
            onChange={ onChange }
            required={ field.required }
            __nextHasNoMarginBottom
        />
    );
}
