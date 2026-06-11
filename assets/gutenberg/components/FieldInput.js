import { TextField }    from './fields/TextField';
import { TextareaField } from './fields/TextareaField';
import { NumberField }   from './fields/NumberField';
import { EmailField }    from './fields/EmailField';
import { UrlField }      from './fields/UrlField';
import { SelectField }   from './fields/SelectField';
import { CheckboxField } from './fields/CheckboxField';
import { RadioField }    from './fields/RadioField';
import { ImageField }    from './fields/ImageField';
import { FileField }     from './fields/FileField';
import { WysiwygField }  from './fields/WysiwygField';
import { GroupField }    from './fields/GroupField';
import { RepeaterField } from './fields/RepeaterField';

const FIELD_MAP = {
    text:     TextField,
    textarea: TextareaField,
    number:   NumberField,
    email:    EmailField,
    url:      UrlField,
    select:   SelectField,
    checkbox: CheckboxField,
    radio:    RadioField,
    image:    ImageField,
    file:     FileField,
    wysiwyg:  WysiwygField,
    group:    GroupField,
    repeater: RepeaterField,
};

/**
 * Returns true when the field's visible_when condition is satisfied.
 * Mirrors the Alpine.js isVisible() logic exactly.
 */
function isVisible(field, allValues) {
    const cond = field.visible_when;
    if (!cond) return true;

    const actual = allValues[cond.field] ?? '';
    if (cond.operator === '==') return actual == cond.value;   // loose, same as PHP ==
    if (cond.operator === '!=') return actual != cond.value;
    return true;
}

/**
 * Routes a field definition to its concrete component.
 * Skips rendering when visible_when condition is false.
 */
export function FieldInput({ field, value, onChange, allValues = {} }) {
    if (!isVisible(field, allValues)) return null;

    const Component = FIELD_MAP[field.type] ?? TextField;

    return (
        <div style={ { marginBottom: 12 } }>
            <Component
                field={ field }
                value={ value }
                onChange={ onChange }
                allValues={ allValues }
            />
        </div>
    );
}
