import { registerPlugin }                          from '@wordpress/plugins';
import { PluginSidebar, PluginSidebarMoreMenuItem } from '@wordpress/edit-post';
import { useSelect }                                from '@wordpress/data';
import { store as editorStore }                     from '@wordpress/editor';
import { __  }                                      from '@wordpress/i18n';
import { CtrlFieldPanel }                          from './components/CtrlFieldPanel';

/**
 * CtrlField Gutenberg sidebar.
 *
 * - Only loads on post types that have registered field groups
 *   (PHP already guards asset enqueueing, but we guard here too for safety).
 * - Reads and writes field values via ctrlfield/v1/post/{id}.
 * - Field values are saved to _ctrlfield_data (same key as classic meta box).
 * - Both adapters share zero JS code — React and Alpine.js are independent.
 */
function CtrlFieldSidebar() {
    const postId = useSelect(
        select => select(editorStore).getCurrentPostId(),
        []
    );

    return (
        <>
            <PluginSidebarMoreMenuItem target="ctrlfield-sidebar">
                { __('CtrlField Fields', 'ctrlfield') }
            </PluginSidebarMoreMenuItem>

            <PluginSidebar
                name="ctrlfield-sidebar"
                title={ __('CtrlField Fields', 'ctrlfield') }
                icon="forms"
            >
                <CtrlFieldPanel postId={ postId } />
            </PluginSidebar>
        </>
    );
}

registerPlugin('ctrlfield', { render: CtrlFieldSidebar });
