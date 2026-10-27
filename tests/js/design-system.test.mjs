import test from 'node:test';
import assert from 'node:assert/strict';
import {
	applyPreset,
	clampRadius,
	contrast,
	matchingPreset,
	normalizeHex,
	previewVariables,
	radiusChoice,
	textOn,
	whitePassesAA,
	withColor,
	withDensity,
	withRadius,
} from '../../assets/src/features/settings/designSystem.mjs';

const khanColors = {
	primary: '#1865f2',
	secondary: '#909296',
	success: '#00a60e',
	danger: '#d92916',
	warning: '#ffb100',
	link: '#1865f2',
	heading: '#0b2149',
	text: '#21242c',
	muted: '#5f6167',
	info: '#1865f2',
	border: '#dcdcdd',
	surface: '#ffffff',
	background: '#f7f8fa',
};
const presets = [
	{
		preset: 'khan',
		colors: khanColors,
		button_radius: 4,
		density: 'comfortable',
	},
	{
		preset: 'classic',
		colors: { ...khanColors, primary: '#6e42d3', link: '#6e42d3' },
		button_radius: 4,
		density: 'comfortable',
	},
];

test( 'hex colors are normalised and bad values rejected', () => {
	assert.equal( normalizeHex( '#ABC' ), '#aabbcc' );
	assert.equal( normalizeHex( ' #1865F2 ' ), '#1865f2' );
	assert.equal( normalizeHex( 'red' ), '' );
	assert.equal( normalizeHex( 'url(x)' ), '' );
	assert.equal( normalizeHex( null ), '' );
} );

test( 'radius is clamped to 0-24 with 999 meaning pill', () => {
	assert.equal( clampRadius( -3 ), 0 );
	assert.equal( clampRadius( 99 ), 24 );
	assert.equal( clampRadius( '8' ), 8 );
	assert.equal( clampRadius( 999 ), 999 );
	assert.equal( clampRadius( 4000 ), 999 );
	assert.equal( clampRadius( 'abc' ), null );
	assert.equal( clampRadius( '' ), null );
	assert.equal( radiusChoice( 0 ), 'square' );
	assert.equal( radiusChoice( 4 ), 'slight' );
	assert.equal( radiusChoice( 8 ), 'rounded' );
	assert.equal( radiusChoice( 999 ), 'pill' );
	assert.equal( radiusChoice( 13 ), 'custom' );
} );

test( 'contrast picks white or black text and flags colors that fail with white', () => {
	assert.equal( textOn( '#1865f2' ), '#ffffff' );
	assert.equal( textOn( '#ffb100' ), '#000000' );
	assert.equal( whitePassesAA( '#1865f2' ), true );
	assert.equal( whitePassesAA( '#ffb100' ), false );
	assert.ok( Math.abs( contrast( '#000000', '#ffffff' ) - 21 ) < 0.01 );
} );

test( 'switching presets applies all values; edits turn the preset to custom', () => {
	const khan = applyPreset( presets, presets[ 1 ], 'khan' );
	assert.equal( khan.preset, 'khan' );
	assert.equal( khan.colors.primary, '#1865f2' );
	assert.equal( matchingPreset( presets, khan ), 'khan' );
	const edited = withColor( presets, khan, 'primary', '#ff0066' );
	assert.equal( edited.preset, 'custom' );
	assert.equal( edited.colors.primary, '#ff0066' );
	assert.equal( withColor( presets, khan, 'primary', 'nope' ), khan );
	assert.equal( withColor( presets, khan, 'unknown', '#ffffff' ), khan );
	assert.equal(
		withColor( presets, edited, 'primary', '#1865f2' ).preset,
		'khan'
	);
	assert.equal( applyPreset( presets, khan, 'custom' ).preset, 'custom' );
} );

test( 'roundness and density edits clamp, validate and mark custom', () => {
	const khan = applyPreset( presets, presets[ 1 ], 'khan' );
	assert.equal( withRadius( presets, khan, 999 ).button_radius, 999 );
	assert.equal( withRadius( presets, khan, 100 ).button_radius, 24 );
	assert.equal( withRadius( presets, khan, 'x' ), khan );
	assert.equal( withRadius( presets, khan, 8 ).preset, 'custom' );
	assert.equal( withDensity( presets, khan, 'compact' ).preset, 'custom' );
	assert.equal( withDensity( presets, khan, 'huge' ), khan );
} );

test( 'preview variables follow the settings', () => {
	const khan = applyPreset( presets, presets[ 1 ], 'khan' );
	const vars = previewVariables(
		withDensity( presets, withRadius( presets, khan, 999 ), 'compact' )
	);
	assert.equal( vars[ '--oml-radius-button' ], '999px' );
	assert.equal( vars[ '--oml-radius-control' ], '8px' );
	assert.equal( vars[ '--oml-button-height-md' ], '32px' );
	assert.equal( vars[ '--oml-space-4' ], '12px' );
	assert.equal( vars[ '--oml-on-primary' ], '#ffffff' );
	assert.equal( previewVariables( khan )[ '--oml-space-8' ], '64px' );
	Object.values( previewVariables( khan ) ).forEach( ( value ) =>
		assert.match( value, /^(#[0-9a-f]{6}|\d+px)$/ )
	);
} );
