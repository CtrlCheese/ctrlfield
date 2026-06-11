import { useState, useEffect, useCallback, useRef } from '@wordpress/element';
import { useSelect }                                 from '@wordpress/data';
import { store as editorStore }                      from '@wordpress/editor';
import { PanelBody, Spinner, Notice }                from '@wordpress/components';
import apiFetch                                      from '@wordpress/api-fetch';
import { FieldInput }                                from './FieldInput';

const { restBase, nonce, postType } = window.fieldforgeGutenberg ?? {};

export function FieldForgePanel({ postId }) {
    const [schema, setSchema]   = useState([]);
    const [values, setValues]   = useState({});
    const [loading, setLoading] = useState(true);
    const [notice, setNotice]   = useState(null); // { type: 'success'|'error', message: string }

    // Track whether we already saved in this save cycle to avoid double-save.
    const savedRef = useRef(false);

    // -----------------------------------------------------------------------
    // Load
    // -----------------------------------------------------------------------
    useEffect(() => {
        if (!postId || !restBase) return;

        apiFetch({
            url:     `${restBase}${postId}`,
            method:  'GET',
            headers: { 'X-WP-Nonce': nonce },
        })
            .then(({ schema: s, values: v }) => {
                setSchema(s ?? []);
                setValues(v ?? {});
            })
            .catch(() => setNotice({ type: 'error', message: 'Could not load FieldForge data.' }))
            .finally(() => setLoading(false));
    }, [postId]);

    // -----------------------------------------------------------------------
    // Save — fires when Gutenberg triggers a save (not autosave)
    // -----------------------------------------------------------------------
    const isSaving = useSelect(
        select =>
            select(editorStore).isSavingPost() &&
            !select(editorStore).isAutosavingPost(),
        []
    );

    const save = useCallback(() => {
        if (!postId || !restBase || loading) return;

        setNotice(null);

        apiFetch({
            url:    `${restBase}${postId}`,
            method: 'POST',
            headers: { 'X-WP-Nonce': nonce },
            data:   { payload: JSON.stringify(values) },
        })
            .then(() => setNotice({ type: 'success', message: 'FieldForge data saved.' }))
            .catch(err => {
                const msg = err?.message ?? 'Save failed.';
                setNotice({ type: 'error', message: msg });
            });
    }, [postId, values, loading]);

    useEffect(() => {
        if (isSaving && !savedRef.current) {
            savedRef.current = true;
            save();
        } else if (!isSaving) {
            savedRef.current = false;
        }
    }, [isSaving, save]);

    // -----------------------------------------------------------------------
    // Render
    // -----------------------------------------------------------------------
    if (loading) {
        return (
            <PanelBody>
                <Spinner />
            </PanelBody>
        );
    }

    if (schema.length === 0) {
        return (
            <PanelBody>
                <p style={ { fontSize: '12px', color: '#50575e' } }>
                    No fields registered for this post type.
                </p>
            </PanelBody>
        );
    }

    const updateField = (key, val) => setValues(prev => ({ ...prev, [key]: val }));

    return (
        <PanelBody title="Fields" initialOpen={ true }>
            { notice && (
                <Notice
                    status={ notice.type }
                    isDismissible
                    onRemove={ () => setNotice(null) }
                >
                    { notice.message }
                </Notice>
            ) }

            { schema.map(field => (
                <FieldInput
                    key={ field.key }
                    field={ field }
                    value={ values[field.key] }
                    onChange={ v => updateField(field.key, v) }
                    allValues={ values }
                />
            )) }
        </PanelBody>
    );
}
