import { TextControl } from '@wordpress/components';

export function UrlField({ field, value, onChange }) {
    return (
        <TextControl
            label={ field.label || field.key }
            type="url"
            value={ value ?? '' }
            onChange={ onChange }
            required={ field.required }
            __nextHasNoMarginBottom
        />
    );
}
