import { Button } from '@wordpress/components';
import { MediaUpload, MediaUploadCheck } from '@wordpress/block-editor';

export function ImageField({ field, value, onChange }) {
    const attachmentId = Number(value) || 0;

    return (
        <div className="ctrlf-gb-image-field">
            <p style={ { fontSize: '11px', fontWeight: 500, marginBottom: 4 } }>
                { field.label || field.key }
            </p>
            <MediaUploadCheck>
                <MediaUpload
                    onSelect={ (media) => onChange(media.id) }
                    allowedTypes={ ['image'] }
                    value={ attachmentId }
                    render={ ({ open }) => (
                        <div>
                            { attachmentId > 0 && (
                                <img
                                    src={ window.wp?.media?.attachment(attachmentId)?.get('url') || '' }
                                    alt=""
                                    style={ { maxWidth: '100%', maxHeight: 120, display: 'block', marginBottom: 8 } }
                                />
                            ) }
                            <Button
                                variant={ attachmentId > 0 ? 'secondary' : 'primary' }
                                onClick={ open }
                                size="small"
                            >
                                { attachmentId > 0 ? 'Change Image' : 'Select Image' }
                            </Button>
                            { attachmentId > 0 && (
                                <Button
                                    variant="link"
                                    isDestructive
                                    onClick={ () => onChange(0) }
                                    size="small"
                                    style={ { marginLeft: 8 } }
                                >
                                    Remove
                                </Button>
                            ) }
                        </div>
                    ) }
                />
            </MediaUploadCheck>
        </div>
    );
}
