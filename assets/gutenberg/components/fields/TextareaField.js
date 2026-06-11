import { TextareaControl } from '@wordpress/components';

export function TextareaField({ field, value, onChange }) {
    return (
        <TextareaControl
            label={ field.label || field.key }
            value={ value ?? '' }
            onChange={ onChange }
            rows={ 5 }
            __nextHasNoMarginBottom
        />
    );
}
