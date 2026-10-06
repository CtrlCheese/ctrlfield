import { Button } from '@wordpress/components';
import { MediaUpload, MediaUploadCheck } from '@wordpress/block-editor';

export function FileField({ field, value, onChange }) {
    const attachmentId = Number(value) || 0;

    return (
        <div className="ctrlf-gb-file-field">
            <p style={ { fontSize: '11px', fontWeight: 500, marginBottom: 4 } }>
                { field.label || field.key }
            </p>
            <MediaUploadCheck>
                <MediaUpload
                    onSelect={ (media) => onChange(media.id) }
                    value={ attachmentId }
                    render={ ({ open }) => (
                        <div>
                            { attachmentId > 0 && (
                                <p style={ { fontSize: '11px', color: '#50575e', marginBottom: 4 } }>
                                    File ID: { attachmentId }
                                </p>
                            ) }
                            <Button
                                variant={ attachmentId > 0 ? 'secondary' : 'primary' }
                                onClick={ open }
                                size="small"
                            >
                                { attachmentId > 0 ? 'Change File' : 'Select File' }
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
