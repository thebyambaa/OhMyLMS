// Class names for the shared Button. includes/Design/Ui.php builds the same strings; the parity test
// (tests/js/ui-button.test.mjs) keeps the two in step.
export const BUTTON_VARIANTS = [ 'primary', 'secondary', 'tertiary', 'danger' ];
export const BUTTON_SIZES = [ 'sm', 'md', 'lg' ];

export function buttonClass( {
	variant = 'secondary',
	size = 'md',
	block = false,
	className = '',
} = {} ) {
	const classes = [ 'oml-btn' ];
	if ( 'secondary' !== variant && BUTTON_VARIANTS.includes( variant ) ) {
		classes.push( 'oml-btn-' + variant );
	}
	if ( 'md' !== size && BUTTON_SIZES.includes( size ) ) {
		classes.push( 'oml-btn-' + size );
	}
	if ( block ) {
		classes.push( 'oml-btn-block' );
	}
	if ( className ) {
		classes.push( className );
	}
	return classes.join( ' ' );
}
