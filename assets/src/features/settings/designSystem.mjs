/**
 * Pure helpers for the Design system settings tab. They mirror includes/Design/DesignSystem.php so the live
 * preview matches what the server writes; the server stays the authority and re-validates everything.
 */

export const PILL = 999;
export const MAX_RADIUS = 24;
export const DENSITIES = [ 'compact', 'comfortable', 'spacious' ];

/** Named roundness choices. */
export const RADIUS_PRESETS = {
	square: 0,
	slight: 4,
	rounded: 8,
	pill: PILL,
};

/** Palette fields the tab lets people edit. */
export const COLOR_FIELDS = [
	'primary',
	'secondary',
	'success',
	'danger',
	'warning',
	'link',
	'heading',
	'text',
	'muted',
];

const SCALE = { compact: 0.75, comfortable: 1, spacious: 1.25 };
const HEIGHTS = {
	compact: [ 24, 32, 40 ],
	comfortable: [ 28, 36, 44 ],
	spacious: [ 32, 40, 48 ],
};

/**
 * A lower-case #rrggbb value, or '' when the input is not a hex color.
 * @param {string|number|null} value Candidate color.
 */
export function normalizeHex( value ) {
	if ( typeof value !== 'string' ) {
		return '';
	}
	let hex = value.trim().toLowerCase();
	if ( /^#[0-9a-f]{3}$/.test( hex ) ) {
		hex = '#' + [ ...hex.slice( 1 ) ].map( ( c ) => c + c ).join( '' );
	}
	return /^#[0-9a-f]{6}$/.test( hex ) ? hex : '';
}

/**
 * Button radius: a whole number 0..24, or 999 for pill. Non-numbers give null.
 * @param {string|number|null} value Candidate radius.
 */
export function clampRadius( value ) {
	const number = Number( value );
	if ( value === '' || value === null || ! Number.isFinite( number ) ) {
		return null;
	}
	const rounded = Math.round( number );
	return rounded >= PILL
		? PILL
		: Math.max( 0, Math.min( MAX_RADIUS, rounded ) );
}

/**
 * Name of the roundness choice for a radius: square, slight, rounded, pill, or custom.
 * @param {number} radius Button radius.
 */
export function radiusChoice( radius ) {
	const found = Object.entries( RADIUS_PRESETS ).find(
		( [ , value ] ) => value === radius
	);
	return found ? found[ 0 ] : 'custom';
}

function channels( hex ) {
	return [ 1, 3, 5 ].map( ( at ) => parseInt( hex.slice( at, at + 2 ), 16 ) );
}

/**
 * Mix a hex color toward another by 0..1 (same rounding as the PHP side).
 * @param {string} hex    Base color.
 * @param {string} toward Color to mix toward.
 * @param {number} amount Share of the other color.
 */
export function mix( hex, toward, amount ) {
	const from = channels( hex );
	const to = channels( toward );
	return (
		'#' +
		from
			.map( ( value, i ) =>
				Math.round( value + ( to[ i ] - value ) * amount )
					.toString( 16 )
					.padStart( 2, '0' )
			)
			.join( '' )
	);
}

/**
 * WCAG relative luminance.
 * @param {string} hex Color.
 */
export function luminance( hex ) {
	const color = normalizeHex( hex );
	if ( ! color ) {
		return 0;
	}
	const [ r, g, b ] = channels( color ).map( ( value ) => {
		const channel = value / 255;
		return channel <= 0.03928
			? channel / 12.92
			: Math.pow( ( channel + 0.055 ) / 1.055, 2.4 );
	} );
	return 0.2126 * r + 0.7152 * g + 0.0722 * b;
}

/**
 * WCAG contrast ratio, 1..21.
 * @param {string} a First color.
 * @param {string} b Second color.
 */
export function contrast( a, b ) {
	const la = luminance( a );
	const lb = luminance( b );
	return ( Math.max( la, lb ) + 0.05 ) / ( Math.min( la, lb ) + 0.05 );
}

/**
 * White or black, whichever reads better on the background.
 * @param {string} background Color.
 */
export function textOn( background ) {
	return contrast( background, '#ffffff' ) >=
		contrast( background, '#000000' )
		? '#ffffff'
		: '#000000';
}

/**
 * Whether white text on the color reaches AA (4.5:1). When false the system uses black text instead.
 * @param {string} background Color.
 */
export function whitePassesAA( background ) {
	return contrast( background, '#ffffff' ) >= 4.5;
}

/**
 * Name of the preset equal to the settings, or 'custom'.
 * @param {Array}  presets  Presets from the server.
 * @param {Object} settings Current settings.
 */
export function matchingPreset( presets, settings ) {
	const found = ( presets || [] ).find(
		( preset ) =>
			preset.button_radius === settings.button_radius &&
			preset.density === settings.density &&
			Object.keys( preset.colors ).every(
				( key ) => preset.colors[ key ] === settings.colors[ key ]
			)
	);
	return found ? found.preset : 'custom';
}

/**
 * Settings after choosing a preset; 'custom' keeps the current values.
 * @param {Array}  presets  Presets from the server.
 * @param {Object} settings Current settings.
 * @param {string} name     Preset name.
 */
export function applyPreset( presets, settings, name ) {
	const preset = ( presets || [] ).find( ( item ) => item.preset === name );
	if ( ! preset ) {
		return { ...settings, preset: 'custom' };
	}
	return {
		preset: preset.preset,
		colors: { ...preset.colors },
		button_radius: preset.button_radius,
		density: preset.density,
	};
}

/**
 * Settings with one color changed. Invalid hex values are ignored.
 * @param {Array}  presets  Presets from the server.
 * @param {Object} settings Current settings.
 * @param {string} key      Color key.
 * @param {string} value    New color.
 */
export function withColor( presets, settings, key, value ) {
	const hex = normalizeHex( value );
	if ( ! hex || ! ( key in settings.colors ) ) {
		return settings;
	}
	const next = { ...settings, colors: { ...settings.colors, [ key ]: hex } };
	return { ...next, preset: matchingPreset( presets, next ) };
}

/**
 * Settings with a new button radius (clamped). Invalid values are ignored.
 * @param {Array}              presets  Presets from the server.
 * @param {Object}             settings Current settings.
 * @param {string|number|null} value    New radius.
 */
export function withRadius( presets, settings, value ) {
	const radius = clampRadius( value );
	if ( radius === null ) {
		return settings;
	}
	const next = { ...settings, button_radius: radius };
	return { ...next, preset: matchingPreset( presets, next ) };
}

/**
 * Settings with a new density.
 * @param {Array}  presets  Presets from the server.
 * @param {Object} settings Current settings.
 * @param {string} value    compact, comfortable or spacious.
 */
export function withDensity( presets, settings, value ) {
	if ( ! DENSITIES.includes( value ) ) {
		return settings;
	}
	const next = { ...settings, density: value };
	return { ...next, preset: matchingPreset( presets, next ) };
}

/**
 * CSS custom properties for the live preview (the subset the shared stylesheet needs).
 * @param {Object} settings Current settings.
 */
export function previewVariables( settings ) {
	const c = settings.colors;
	const control =
		settings.button_radius === PILL
			? 8
			: Math.min( settings.button_radius, 8 );
	const heights = HEIGHTS[ settings.density ] || HEIGHTS.comfortable;
	const scale = SCALE[ settings.density ] || 1;
	const space = ( n ) => Math.round( n * scale ) + 'px';
	const vars = {
		'--oml-primary': c.primary,
		'--oml-primary-hover': mix( c.primary, '#000000', 0.15 ),
		'--oml-primary-active': mix( c.primary, '#000000', 0.3 ),
		'--oml-primary-soft': mix( c.primary, '#ffffff', 0.9 ),
		'--oml-on-primary': textOn( c.primary ),
		'--oml-secondary': c.secondary,
		'--oml-secondary-hover': mix( c.secondary, '#000000', 0.15 ),
		'--oml-success': c.success,
		'--oml-success-soft': mix( c.success, '#ffffff', 0.9 ),
		'--oml-warning': c.warning,
		'--oml-warning-soft': mix( c.warning, '#ffffff', 0.88 ),
		'--oml-danger': c.danger,
		'--oml-danger-hover': mix( c.danger, '#000000', 0.15 ),
		'--oml-danger-soft': mix( c.danger, '#ffffff', 0.92 ),
		'--oml-on-danger': textOn( c.danger ),
		'--oml-info': c.info,
		'--oml-info-soft': mix( c.info, '#ffffff', 0.92 ),
		'--oml-link': c.link,
		'--oml-link-hover': mix( c.link, '#000000', 0.2 ),
		'--oml-heading': c.heading,
		'--oml-text': c.text,
		'--oml-muted': c.muted,
		'--oml-border': c.border,
		'--oml-surface': c.surface,
		'--oml-subtle': mix( c.background, '#ffffff', 0.5 ),
		'--oml-background': c.background,
		'--oml-focus':
			contrast( c.primary, c.surface ) >= 3 ? c.primary : c.heading,
		'--oml-radius-button': settings.button_radius + 'px',
		'--oml-radius-control': control + 'px',
		'--oml-radius-card': ( control === 0 ? 0 : control + 4 ) + 'px',
		'--oml-button-height-sm': heights[ 0 ] + 'px',
		'--oml-button-height-md': heights[ 1 ] + 'px',
		'--oml-button-height-lg': heights[ 2 ] + 'px',
		'--oml-button-pad-sm': space( 8 ),
		'--oml-button-pad-md': space( 16 ),
		'--oml-button-pad-lg': space( 24 ),
	};
	[ 4, 8, 12, 16, 24, 32, 48, 64 ].forEach( ( base, i ) => {
		vars[ '--oml-space-' + ( i + 1 ) ] = space( base );
	} );
	return vars;
}
