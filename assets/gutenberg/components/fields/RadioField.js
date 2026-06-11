import { RadioControl } from '@wordpress/components';

export function RadioField({ field, value, onChange }) {
    const options = Object.entries(field.options ?? {}).map(
        ([val, label]) => ({ label, value: val })
    );

    return (
        <RadioControl
            label={ field.label || field.key }
            selected={ value ?? '' }
            options={ options }
            onChange={ onChange }
        />
    );
}
