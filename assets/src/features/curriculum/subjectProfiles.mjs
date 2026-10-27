/**
 * Pure helpers for the subject profile editor: form values, domain ids and JSON import/export.
 */

/**
 * A domain id from its name: lower-case letters, digits and hyphens, starting with a letter.
 * @param {string} label Domain name.
 * @return {string} Id, or empty when the name has no Latin letters to build one from.
 */
export function domainId( label ) {
	const id = String( label || '' )
		.normalize( 'NFKD' )
		.replace( /[̀-ͯ]/g, '' )
		.toLowerCase()
		.replace( /[^a-z0-9]+/g, '-' )
		.replace( /^[^a-z]+/, '' )
		.replace( /-+$/, '' )
		.slice( 0, 40 )
		.replace( /-+$/, '' );
	return id;
}

/**
 * Editor values for a profile; an empty profile starts a new one.
 * @param {?Object} [profile] Profile from the server; empty or null starts a new one.
 * @return {Object} key, label, language, domains (id, label), levels (one per line), role, context, level_hints.
 */
export function formFromProfile( profile ) {
	profile = profile || {};
	return {
		key: profile.key || '',
		label: profile.label || '',
		language: profile.language || '',
		domains: ( profile.domains || [] ).map( ( domain ) => ( {
			id: domain.id,
			label: domain.label,
		} ) ),
		levels: ( profile.levels || [] ).join( '\n' ),
		role: profile.ai?.role || '',
		context: profile.ai?.context || '',
		level_hints: profile.ai?.level_hints || '',
	};
}

/**
 * The save payload for editor values. Blank domain ids are built from their names.
 * @param {Object} form Editor values.
 * @return {Object} key, label, language, domains, levels, ai.
 */
export function profileFromForm( form ) {
	return {
		...( form.key ? { key: form.key } : {} ),
		label: form.label.trim(),
		language: form.language.trim(),
		domains: form.domains
			.filter( ( domain ) => domain.label.trim() || domain.id.trim() )
			.map( ( domain ) => ( {
				id: domain.id.trim() || domainId( domain.label ),
				label: domain.label.trim(),
			} ) ),
		levels: form.levels
			.split( /\r?\n/ )
			.map( ( level ) => level.trim() )
			.filter( Boolean ),
		ai: {
			role: form.role.trim(),
			context: form.context.trim(),
			level_hints: form.level_hints.trim(),
		},
	};
}

/**
 * A profile as portable JSON, without site-specific flags.
 * @param {Object} form Editor values.
 * @return {string} JSON.
 */
export function profileJson( form ) {
	const { key, ...profile } = profileFromForm( form );
	return JSON.stringify( profile, null, 2 );
}

/**
 * Editor values from pasted profile JSON. Into a new profile the JSON brings its subject name; into
 * an existing profile it replaces the domains, levels and AI context but keeps that profile's name and
 * key, so the subject its skills use never changes by accident.
 * @param {string} text   JSON text.
 * @param {Object} [base] Editor values of the profile being edited.
 * @return {Object|null} Editor values, or null when the text is not a profile.
 */
export function formFromJson( text, base = {} ) {
	let data;
	try {
		data = JSON.parse( text );
	} catch {
		return null;
	}
	if (
		! data ||
		typeof data !== 'object' ||
		typeof data.label !== 'string' ||
		! Array.isArray( data.levels ) ||
		( data.domains !== undefined && ! Array.isArray( data.domains ) )
	) {
		return null;
	}
	return formFromProfile( {
		...data,
		key: base.key || '',
		label: base.key ? base.label : data.label,
		domains: ( data.domains || [] ).filter(
			( domain ) =>
				domain &&
				typeof domain.id === 'string' &&
				typeof domain.label === 'string'
		),
		levels: data.levels
			.map( ( level ) =>
				typeof level === 'object' ? level?.label : level
			)
			.filter( ( level ) => typeof level === 'string' ),
	} );
}
