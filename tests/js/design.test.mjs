import test from 'node:test';
import assert from 'node:assert/strict';
import {
	matchingPreset,
	presetPatch,
} from '../../assets/src/features/settings/theme.mjs';

const presets = [
	{
		value: 'royal-purple',
		label: 'Royal Purple',
		colors: {
			primary: '#6E42D3',
			heading: '#000D25',
			text: '#52525B',
			progress: '#35BD4C',
		},
	},
	{
		value: 'ocean-blue',
		label: 'Ocean Blue',
		colors: {
			primary: '#2563EB',
			heading: '#0F172A',
			text: '#475569',
			progress: '#06B6D4',
		},
	},
];

test( 'learner colors map to a preset, case-insensitively, or to custom', () => {
	assert.equal(
		matchingPreset( presets, {
			primary: '#2563eb',
			heading: '#0f172a',
			text: '#475569',
			progress: '#06b6d4',
		} ),
		'ocean-blue'
	);
	assert.equal(
		matchingPreset( presets, {
			primary: '#2563eb',
			heading: '#000000',
			text: '#475569',
			progress: '#06b6d4',
		} ),
		'custom'
	);
	assert.equal( matchingPreset( [], {} ), 'custom' );
} );

test( 'choosing a preset writes all four learner colors and the preset name', () => {
	assert.deepEqual( presetPatch( presets[ 1 ] ), {
		ohmylms_color_preset: { value: 'ocean-blue' },
		ohmylms_primary_color_scheme: { value: '#2563EB' },
		ohmylms_heading_color_scheme: { value: '#0F172A' },
		ohmylms_body_text_color_scheme: { value: '#475569' },
		ohmylms_body_progress_color_scheme: { value: '#06B6D4' },
	} );
} );
