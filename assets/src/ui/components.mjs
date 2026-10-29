// Class names for Card, Badge and Progress. includes/Design/Ui.php builds the same strings; the parity
// test (tests/js/ui-components.test.mjs) keeps the two in step.
export const BADGE_TONES = [
	'primary',
	'success',
	'warning',
	'danger',
	'info',
];
export const PROGRESS_TONES = [ 'primary', 'success' ];
export const ALERT_TONES = [ 'info', 'success', 'warning', 'danger' ];

export function alertClass( { tone = 'info', className = '' } = {} ) {
	return [
		'oml-alert',
		'oml-alert-' + ( ALERT_TONES.includes( tone ) ? tone : 'info' ),
		className,
	]
		.filter( Boolean )
		.join( ' ' );
}

export function alertRole( { tone, role } = {} ) {
	if ( [ 'alert', 'status', 'none' ].includes( role ) ) {
		return role;
	}
	return 'danger' === tone ? 'alert' : 'status';
}

const join = ( classes, extra ) =>
	( extra ? [ ...classes, extra ] : classes ).join( ' ' );

export function cardClass( {
	flat = false,
	interactive = false,
	className = '',
} = {} ) {
	const classes = [ 'oml-card' ];
	if ( flat ) {
		classes.push( 'oml-card-flat' );
	}
	if ( interactive ) {
		classes.push( 'oml-card-interactive' );
	}
	return join( classes, className );
}

export function badgeClass( { tone = 'neutral', className = '' } = {} ) {
	const classes = [ 'oml-badge' ];
	if ( BADGE_TONES.includes( tone ) ) {
		classes.push( 'oml-badge-' + tone );
	}
	return join( classes, className );
}

export function progressClass( { tone = 'primary', className = '' } = {} ) {
	const classes = [ 'oml-progress' ];
	if ( 'success' === tone ) {
		classes.push( 'oml-progress-success' );
	}
	return join( classes, className );
}

// 0..100, whole numbers; anything else becomes 0.
export function clampPercent( value ) {
	const number = Number( value );
	return Number.isFinite( number )
		? Math.min( 100, Math.max( 0, Math.round( number ) ) )
		: 0;
}
