export function validateCommunity( community, translate = ( text ) => text ) {
	const errors = {};
	if ( ! community?.title?.trim() ) {
		errors.title = translate( 'Name is required.', 'ohmylms' );
	}
	if ( ! community?.slug?.trim() ) {
		errors.slug = translate( 'Slug is required.', 'ohmylms' );
	} else if ( ! /^[a-z0-9-]+$/.test( community.slug ) ) {
		errors.slug = translate(
			'Slug can only contain lowercase letters, numbers, and hyphens.',
			'ohmylms'
		);
	} else if ( community.slug.length < 3 ) {
		errors.slug = translate(
			'Slug must be at least 3 characters long.',
			'ohmylms'
		);
	}
	return errors;
}
