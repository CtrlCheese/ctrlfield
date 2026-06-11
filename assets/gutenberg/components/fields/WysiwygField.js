import { TextareaControl } from '@wordpress/components';

/**
 * WYSIWYG in the Gutenberg sidebar is rendered as a plain textarea.
 * TinyMCE cannot run inside a React sidebar panel.
 * Full WYSIWYG editing is available in the classic meta box.
 */
export function WysiwygField({ field, value, onChange }) {
    return (
        <div>
            <TextareaControl
                label={ field.label || field.key }
                value={ value ?? '' }
                onChange={ onChange }
                rows={ 8 }
                help="HTML is supported. For rich editing, use the classic meta box below the editor."
                __nextHasNoMarginBottom
            />
        </div>
    );
}
