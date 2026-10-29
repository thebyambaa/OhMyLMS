// Keep the PHP class helpers in includes/Design/Ui.php in step with these strings.
export const CONTROL_SIZES = [ 'sm', 'md', 'lg' ];
export const INPUT_TYPES = [
	'text',
	'email',
	'number',
	'password',
	'search',
	'tel',
	'url',
	'date',
	'datetime-local',
	'month',
	'time',
	'week',
];

function controlClass(
	kind,
	{ size = 'md', invalid = false, error = '', className = '' } = {}
) {
	const classes = [ 'oml-control', `oml-${ kind }` ];
	if ( size !== 'md' && CONTROL_SIZES.includes( size ) ) {
		classes.push( `oml-control-${ size }` );
	}
	if ( invalid || error ) {
		classes.push( 'oml-control-invalid' );
	}
	return [ ...classes, className ].filter( Boolean ).join( ' ' );
}

export const inputClass = ( args ) => controlClass( 'input', args );
export const selectClass = ( args ) => controlClass( 'select', args );
export const inputType = ( type ) =>
	INPUT_TYPES.includes( type ) ? type : 'text';

/**
 * Retain caller descriptions as well as the field's help and error messages.
 *
 * @param {string} id       Native control ID.
 * @param {string} help     Help text.
 * @param {string} error    Validation message.
 * @param {string} existing Caller-provided description IDs.
 * @return {string|undefined} Description IDs, with duplicates removed.
 */
export function describedBy( id, help, error, existing = '' ) {
	return (
		[
			...new Set(
				[
					...String( existing || '' )
						.split( /\s+/ )
						.filter( Boolean ),
					help ? `${ id }-help` : '',
					error ? `${ id }-error` : '',
				].filter( Boolean )
			),
		].join( ' ' ) || undefined
	);
}
