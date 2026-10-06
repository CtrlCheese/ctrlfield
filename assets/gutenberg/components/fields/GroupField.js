import { PanelBody } from '@wordpress/components';
import { FieldInput } from '../FieldInput';

export function GroupField({ field, value, onChange, allValues }) {
    const groupValues = (typeof value === 'object' && value !== null) ? value : {};

    const updateSub = (subKey, subValue) => {
        onChange({ ...groupValues, [subKey]: subValue });
    };

    return (
        <PanelBody
            title={ field.label || field.key }
            initialOpen={ true }
            className="ctrlf-gb-group"
        >
            { (field.fields ?? []).map((sub) => (
                <FieldInput
                    key={ sub.key }
                    field={ sub }
                    value={ groupValues[sub.key] }
                    onChange={ (v) => updateSub(sub.key, v) }
                    allValues={ groupValues }
                />
            )) }
        </PanelBody>
    );
}
