import { __, sprintf } from '@wordpress/i18n';

/**
 * Validate a selection before upload; the server independently validates bytes.
 * @param {Array}  existing Existing composer attachments.
 * @param {Array}  incoming Selected or dropped File objects.
 * @param {Object} limits   Server upload limits.
 * @return {Object} Accepted attachments and a useful rejection message.
 */
export function prepareAttachments( existing, incoming, limits = {} ) {
	const attachments = [ ...existing ];
	const maxFiles = limits?.max_files || 3;
	const maxBytes = limits?.max_bytes || 2097152;
	const extensions = limits?.extensions || [
		'pdf',
		'png',
		'jpg',
		'jpeg',
		'webp',
		'txt',
		'md',
		'csv',
		'json',
		'docx',
		'xlsx',
		'pptx',
	];
	const errors = [];
	for ( const file of incoming ) {
		if (
			attachments.some(
				( item ) =>
					item.file.name === file.name &&
					item.file.size === file.size &&
					item.file.lastModified === file.lastModified
			)
		) {
			continue;
		}
		if ( attachments.length >= maxFiles ) {
			errors.push(
				__( 'Attach up to three files per message.', 'ohmylms' )
			);
			break;
		}
		if (
			! extensions.includes(
				file.name.split( '.' ).pop().toLowerCase()
			) ||
			! file.size ||
			file.size > maxBytes
		) {
			errors.push(
				sprintf(
					/* translators: %s: filename. */ __(
						'%s is empty, too large, or an unsupported file type.',
						'ohmylms'
					),
					file.name
				)
			);
			continue;
		}
		attachments.push( { id: crypto.randomUUID(), file, uploaded: null } );
	}
	return { attachments, error: errors.join( ' ' ) };
}
