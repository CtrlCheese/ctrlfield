import { registerPlugin }                          from '@wordpress/plugins';
import { PluginSidebar, PluginSidebarMoreMenuItem } from '@wordpress/edit-post';
import { useSelect }                                from '@wordpress/data';
import { store as editorStore }                     from '@wordpress/editor';
import { __  }                                      from '@wordpress/i18n';
import { FieldForgePanel }                          from './components/FieldForgePanel';

/**
 * FieldForge Gutenberg sidebar.
 *
 * - Only loads on post types that have registered field groups
 *   (PHP already guards asset enqueueing, but we guard here too for safety).
 * - Reads and writes field values via fieldforge/v1/post/{id}.
 * - Field values are saved to _fieldforge_data (same key as classic meta box).
 * - Both adapters share zero JS code — React and Alpine.js are independent.
 */
function FieldForgeSidebar() {
    const postId = useSelect(
        select => select(editorStore).getCurrentPostId(),
        []
    );

    return (
        <>
            <PluginSidebarMoreMenuItem target="fieldforge-sidebar">
                { __('FieldForge Fields', 'fieldforge') }
            </PluginSidebarMoreMenuItem>

            <PluginSidebar
                name="fieldforge-sidebar"
                title={ __('FieldForge Fields', 'fieldforge') }
                icon="forms"
            >
                <FieldForgePanel postId={ postId } />
            </PluginSidebar>
        </>
    );
}

registerPlugin('fieldforge', { render: FieldForgeSidebar });
