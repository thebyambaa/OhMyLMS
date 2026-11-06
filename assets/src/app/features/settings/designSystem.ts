import { __ } from '@wordpress/i18n';
import { del, get, post } from '../../lib/api';
import {
	asArray,
	asBoolean,
	asNumber,
	asRecord,
	asString,
	invalidResponse,
} from './model';
import type { FieldErrors } from './types';

const PATH = '/ohmylms/v1/design-system';

/** Every colour of the design system, in server order (`DesignSystem::COLOR_KEYS`). */
export const COLOR_KEYS = [
	'primary',
	'secondary',
	'success',
	'danger',
	'warning',
	'link',
	'heading',
	'text',
	'muted',
	'info',
	'border',
	'surface',
	'background',
] as const;

/** Colours the tab lets administrators edit; the others follow the preset. */
export const EDITABLE_COLOR_KEYS = COLOR_KEYS.slice( 0, 9 );

export type ColorKey = ( typeof COLOR_KEYS )[ number ];
export type Density = 'compact' | 'comfortable' | 'spacious';

export const DENSITIES: readonly Density[] = [
	'compact',
	'comfortable',
	'spacious',
];
export const PILL = 999;
export const MAX_RADIUS = 24;
const HEX = /^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/;

export const MASCOT_KINDS = [
	'none',
	'owl',
	'robot',
	'horse',
	'star',
	'upload',
] as const;

export type MascotKind = ( typeof MASCOT_KINDS )[ number ];

export const MASCOT_PLACEMENTS = [
	'profile',
	'empty',
	'celebrate',
	'quiz',
] as const;

export type MascotPlacement = ( typeof MASCOT_PLACEMENTS )[ number ];

export interface MascotValues {
	kind: MascotKind;
	attachment_id: number;
	attachment_url: string;
	name: string;
	greeting: string;
	placements: Record< MascotPlacement, boolean >;
}

export interface MascotDrawing {
	kind: string;
	label: string;
	svg: string;
}

export interface SchoolBrand {
	id: number;
	name: string;
	accent: string;
	logo_id: number;
	logo_url: string;
	mascot: MascotValues | null;
}

export interface DesignValues {
	preset: string;
	colors: Record< ColorKey, string >;
	button_radius: number;
	density: Density;
	font: string;
	mascot: MascotValues;
}

export interface DesignPreset extends DesignValues {
	label: string;
}

/** Preset whose admin screens follow the user's WordPress admin colour scheme (the default). */
export const WORDPRESS_PRESET = 'wordpress';

/**
 * `GET /design-system`: the values, the contrast verdict for the primary colour, the presets and the
 * colours admin screens use under the WordPress preset (the requesting user's admin colour scheme).
 */
export interface DesignSnapshot extends DesignValues {
	on_primary: string;
	primary_passes: boolean;
	white_passes: boolean;
	admin_scheme: string;
	admin_colors: Record< ColorKey, string >;
	fonts: string[];
	mascots: MascotDrawing[];
	schools: SchoolBrand[];
	presets: DesignPreset[];
}

function parseColors( raw: unknown ): Record< ColorKey, string > {
	const source = asRecord( raw );
	const colors = {} as Record< ColorKey, string >;
	for ( const key of COLOR_KEYS ) {
		colors[ key ] = asString( source[ key ] );
	}
	return colors;
}

function parseDensity( raw: unknown ): Density {
	const value = asString( raw ) as Density;
	if ( ! DENSITIES.includes( value ) ) {
		throw invalidResponse();
	}
	return value;
}

function defaultMascot(): MascotValues {
	return {
		kind: 'none',
		attachment_id: 0,
		attachment_url: '',
		name: '',
		greeting: '',
		placements: {
			profile: true,
			empty: true,
			celebrate: true,
			quiz: false,
		},
	};
}

function parseMascot( raw: unknown ): MascotValues {
	const source = asRecord( raw );
	const fallback = defaultMascot();
	const kind = asString( source.kind );
	const placements = asRecord( source.placements );
	return {
		kind: ( MASCOT_KINDS as readonly string[] ).includes( kind )
			? ( kind as MascotKind )
			: 'none',
		attachment_id: asNumber( source.attachment_id ),
		attachment_url: asString( source.attachment_url ),
		name: asString( source.name ),
		greeting: asString( source.greeting ),
		placements: {
			profile:
				'profile' in placements
					? asBoolean( placements.profile )
					: fallback.placements.profile,
			empty:
				'empty' in placements
					? asBoolean( placements.empty )
					: fallback.placements.empty,
			celebrate:
				'celebrate' in placements
					? asBoolean( placements.celebrate )
					: fallback.placements.celebrate,
			quiz:
				'quiz' in placements
					? asBoolean( placements.quiz )
					: fallback.placements.quiz,
		},
	};
}

function parseValues( raw: Record< string, unknown > ): DesignValues {
	return {
		preset: asString( raw.preset ),
		colors: parseColors( raw.colors ),
		button_radius: asNumber( raw.button_radius ),
		density: parseDensity( raw.density ),
		font: 'string' === typeof raw.font && raw.font ? raw.font : 'inherit',
		mascot: raw.mascot ? parseMascot( raw.mascot ) : defaultMascot(),
	};
}

export function parseDesign( raw: unknown ): DesignSnapshot {
	const body = asRecord( raw );
	return {
		...parseValues( body ),
		on_primary: asString( body.on_primary ),
		primary_passes: asBoolean( body.primary_passes ),
		white_passes: asBoolean( body.white_passes ),
		admin_scheme:
			'string' === typeof body.admin_scheme ? body.admin_scheme : '',
		admin_colors: parseColors( body.admin_colors ?? body.colors ),
		presets: asArray( body.presets ).map( ( item ) => {
			const row = asRecord( item );
			return { ...parseValues( row ), label: asString( row.label ) };
		} ),
		fonts: Array.isArray( body.fonts )
			? body.fonts.map( ( item ) => asString( item ) )
			: [],
		mascots: Array.isArray( body.mascots )
			? body.mascots.map( ( item ) => {
					const row = asRecord( item );
					return {
						kind: asString( row.kind ),
						label: asString( row.label ),
						svg: asString( row.svg ),
					};
				} )
			: [],
		schools: Array.isArray( body.schools )
			? body.schools.map( ( item ) => {
					const row = asRecord( item );
					return {
						id: asNumber( row.id ),
						name: asString( row.name ),
						accent: asString( row.accent ),
						logo_id: asNumber( row.logo_id ),
						logo_url: asString( row.logo_url ),
						mascot: row.mascot ? parseMascot( row.mascot ) : null,
					};
				} )
			: [],
	};
}

export async function getDesign( signal?: AbortSignal ) {
	return parseDesign( await get< unknown >( PATH, { signal } ) );
}

export async function saveDesign(
	values: DesignValues,
	signal?: AbortSignal
): Promise< DesignSnapshot > {
	return parseDesign( await post< unknown >( PATH, values, { signal } ) );
}

/**
 * Save one school's accent, logo and mascot. Returns the design snapshot, including every school.
 *
 * @param id     School ID.
 * @param body   Override fields accepted by Schools\Settings::save().
 * @param signal Cancels the request.
 */
export async function saveSchoolBrand(
	id: number,
	body: Record< string, unknown >,
	signal?: AbortSignal
): Promise< DesignSnapshot > {
	return parseDesign(
		await post< unknown >( `${ PATH }/schools/${ id }`, body, { signal } )
	);
}

/**
 * Go back to the default preset.
 *
 * @param signal Cancels the request.
 * @return The stored settings after the reset.
 */
export async function resetDesign(
	signal?: AbortSignal
): Promise< DesignSnapshot > {
	return parseDesign( await del< unknown >( PATH, { signal } ) );
}

export function designDraft( snapshot: DesignSnapshot ): DesignValues {
	return {
		preset: snapshot.preset,
		colors: { ...snapshot.colors },
		button_radius: snapshot.button_radius,
		density: snapshot.density,
		font: snapshot.font,
		mascot: {
			...snapshot.mascot,
			placements: { ...snapshot.mascot.placements },
		},
	};
}

/**
 * Name of the preset whose values equal these, else `custom` (as the server computes it). The
 * WordPress and Khan Academy presets share a palette, so the chosen name wins when it matches.
 *
 * @param values  Values.
 * @param presets Presets.
 * @return Preset name.
 */
export function matchingPreset(
	values: DesignValues,
	presets: readonly DesignPreset[]
): string {
	const same = ( preset: DesignPreset ) =>
		preset.button_radius === values.button_radius &&
		preset.density === values.density &&
		COLOR_KEYS.every(
			( key ) =>
				preset.colors[ key ] === values.colors[ key ].toLowerCase()
		);
	const chosen = presets.find(
		( preset ) => preset.preset === values.preset
	);
	if ( chosen && same( chosen ) ) {
		return chosen.preset;
	}
	const match = presets.find( same );
	return match ? match.preset : 'custom';
}

export function applyPreset(
	values: DesignValues,
	presets: readonly DesignPreset[],
	name: string
): DesignValues {
	const preset = presets.find( ( item ) => item.preset === name );
	return preset
		? {
				...values,
				preset: preset.preset,
				colors: { ...preset.colors },
				button_radius: preset.button_radius,
				density: preset.density,
			}
		: values;
}

export function designChanged( a: DesignValues, b: DesignValues ): boolean {
	return (
		a.preset !== b.preset ||
		a.button_radius !== b.button_radius ||
		a.density !== b.density ||
		a.font !== b.font ||
		JSON.stringify( a.mascot ) !== JSON.stringify( b.mascot ) ||
		COLOR_KEYS.some(
			( key ) =>
				a.colors[ key ].toLowerCase() !== b.colors[ key ].toLowerCase()
		)
	);
}

export function validateDesign( values: DesignValues ): FieldErrors {
	const errors: FieldErrors = {};
	for ( const key of COLOR_KEYS ) {
		if ( ! HEX.test( values.colors[ key ] ) ) {
			errors[ `colors.${ key }` ] = __(
				'Use a color like #1865f2.',
				'ohmylms'
			);
		}
	}
	const radius = values.button_radius;
	if (
		! Number.isInteger( radius ) ||
		( PILL !== radius && ( radius < 0 || radius > MAX_RADIUS ) )
	) {
		errors.button_radius = __(
			'Button roundness must be a number from 0 to 24, or pill.',
			'ohmylms'
		);
	}
	if ( ! DENSITIES.includes( values.density ) ) {
		errors.density = __(
			'Choose Compact, Comfortable or Spacious.',
			'ohmylms'
		);
	}
	if ( values.mascot.name.length > 40 ) {
		errors[ 'mascot.name' ] = __(
			'The mascot name must be 40 characters or fewer.',
			'ohmylms'
		);
	}
	if ( values.mascot.greeting.length > 160 ) {
		errors[ 'mascot.greeting' ] = __(
			'The greeting must be 160 characters or fewer.',
			'ohmylms'
		);
	}
	if ( 'upload' === values.mascot.kind && values.mascot.attachment_id < 1 ) {
		errors[ 'mascot.attachment_id' ] = __(
			'Choose an image from the media library.',
			'ohmylms'
		);
	}
	return errors;
}

/**
 * WCAG contrast of a colour against white (same formula as DesignSystem::contrast()).
 *
 * @param color Hex colour.
 * @return Contrast ratio from 1 to 21.
 */
export function contrastOnWhite( color: string ): number {
	const foreground = luminance( color );
	const white = 1;
	const lighter = Math.max( foreground, white );
	const darker = Math.min( foreground, white );
	return ( lighter + 0.05 ) / ( darker + 0.05 );
}

const SYSTEM_STACK =
	'-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif';

/**
 * CSS font-family for a learner font choice. Mirrors Tokens::stack().
 *
 * @param font Font choice.
 * @return A CSS font-family list.
 */
export function fontStack( font: string ): string {
	if ( 'inherit' === font ) {
		return 'inherit';
	}
	if ( 'system' === font || '' === font ) {
		return SYSTEM_STACK;
	}
	return `"${ font }", ${ SYSTEM_STACK }`;
}

/**
 * Six-digit lower-case hex, or `null`.
 *
 * @param value Colour.
 * @return Normalised colour.
 */
function hex( value: string ): string | null {
	const short = /^#([0-9a-f])([0-9a-f])([0-9a-f])$/i.exec( value );
	if ( short ) {
		return `#${ short[ 1 ] }${ short[ 1 ] }${ short[ 2 ] }${ short[ 2 ] }${ short[ 3 ] }${ short[ 3 ] }`.toLowerCase();
	}
	return /^#[0-9a-f]{6}$/i.test( value ) ? value.toLowerCase() : null;
}

/**
 * WCAG relative luminance (same formula as `DesignSystem::luminance()`).
 *
 * @param color Hex colour.
 * @return 0 to 1.
 */
export function luminance( color: string ): number {
	const value = hex( color );
	if ( ! value ) {
		return 0;
	}
	const weights = [ 0.2126, 0.7152, 0.0722 ];
	return [ 1, 3, 5 ].reduce( ( sum, offset, index ) => {
		const channel = parseInt( value.slice( offset, offset + 2 ), 16 ) / 255;
		const linear =
			channel <= 0.03928
				? channel / 12.92
				: Math.pow( ( channel + 0.055 ) / 1.055, 2.4 );
		return sum + ( weights[ index ] ?? 0 ) * linear;
	}, 0 );
}

export function contrast( a: string, b: string ): number {
	const la = luminance( a );
	const lb = luminance( b );
	return ( Math.max( la, lb ) + 0.05 ) / ( Math.min( la, lb ) + 0.05 );
}

/** Text colours `contrastOn()` chooses between. */
export const WHITE = '#ffffff';
export const BLACK = '#000000';

/**
 * Contrast verdict for button text on a background, as the server computes it.
 *
 * @param background Hex colour.
 * @return Text colour, its ratio and whether it reaches WCAG AA (4.5:1).
 */
export function contrastOn( background: string ): {
	text: string;
	ratio: number;
	passes: boolean;
} {
	const white = contrast( background, '#ffffff' );
	const black = contrast( background, '#000000' );
	const text = white >= black ? '#ffffff' : '#000000';
	const ratio = Math.max( white, black );
	return { text, ratio, passes: ratio >= 4.5 };
}
