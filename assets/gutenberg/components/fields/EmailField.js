import { TextControl } from '@wordpress/components';

export function EmailField({ field, value, onChange }) {
    return (
        <TextControl
            label={ field.label || field.key }
            type="email"
            value={ value ?? '' }
            onChange={ onChange }
            required={ field.required }
            __nextHasNoMarginBottom
        />
    );
}
