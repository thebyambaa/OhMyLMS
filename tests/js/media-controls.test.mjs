import test from 'node:test';
import assert from 'node:assert/strict';
import { createRequire, register } from 'node:module';
import { loadRecovered } from './recovered-fragments.mjs';

// `@wordpress/components` only exists in the WordPress admin. The layout probes print every
// prop they receive, so the recovered and the authored component are compared on a real render
// against identical stand-ins. Both use the authored controls kit for `I`, `D` and `UG`.
register( './controls-layout-hooks.mjs', import.meta.url );

const hostRequire = createRequire( import.meta.url );
const React = hostRequire( 'react' );
const i18n = hostRequire( '@wordpress/i18n' );
const { renderToStaticMarkup } = hostRequire( 'react-dom/server' );

const media = await import( '../../assets/src/shared/media/index.js' );
const layout =
	await import( '../../assets/src/shared/controls/layout/index.js' );
const { ButtonWP } =
	await import( '../../assets/src/shared/controls/actions/ButtonWP.mjs' );

const recovered = loadRecovered(
	[
		{
			fragment: 'shared/course-thumbnail-controls.js',
			names: [
				'tf',
				'nf',
				'rf',
				'af',
				'of',
				'lf',
				'cf',
				'uf',
				'sf',
				'df',
			],
		},
		{
			fragment: 'shared/dashboard-income-overview.js',
			names: [ 'qG', 'YG' ],
		},
	],
	{
		React,
		g: React,
		b: i18n,
		I: { ...layout, ButtonWP },
		D: { A: ButtonWP },
		UG: { A: layout.SpacerWP },
	}
);

const render = ( type, props ) =>
	renderToStaticMarkup( React.createElement( type, props ) );

/**
 * Assert that both components render the same markup for each prop set.
 *
 * @param {object}   authored  Authored component.
 * @param {object}   original  Recovered component.
 * @param {object[]} propsList Prop sets.
 */
function assertSameMarkup( authored, original, propsList ) {
	for ( const props of propsList ) {
		assert.equal(
			render( authored, props ),
			render( original, props ),
			JSON.stringify( props, ( key, value ) =>
				'function' === typeof value ? '[fn]' : value
			)
		);
	}
}

test( 'icons match the recovered ones', () => {
	assertSameMarkup( media.PlusIcon, recovered.nf, [ {}, { ignored: 1 } ] );
	assertSameMarkup( media.NoDataIllustration, recovered.df, [ {} ] );
	assert.match( render( media.PlusIcon, {} ), /^<svg [^>]*width="14"/ );
} );

test( 'AddButton matches the recovered add button', () => {
	const onClick = () => {};
	assertSameMarkup( media.AddButton, recovered.lf, [
		{},
		{ label: 'Add lesson' },
		{ label: 'Add', onClick },
		{ className: 'dropped', label: 'Add' },
		{ disabled: true, variant: 'secondary' },
		{ icon: 'custom', loading: true, size: 'small' },
		{ label: '', onClick },
	] );
	assert.ok(
		! render( media.AddButton, { className: 'dropped' } ).includes(
			'dropped'
		)
	);
} );

test( 'EmptyState matches the recovered empty state', () => {
	const ctaHandler = () => {};
	const icon = React.createElement( 'i', { className: 'pic' } );
	assertSameMarkup( media.EmptyState, recovered.uf, [
		{},
		{ title: 'No courses' },
		{ title: 'No courses', description: 'Create one first.' },
		{ icon },
		{ icon: 'text icon' },
		{ icon: null, title: 'x' },
		{ icon, title: 'x', description: 'y', ctaText: 'Add', ctaHandler },
		{ ctaText: 'Add' },
		{ description: 'only text' },
		{ icon: React.createElement( media.NoDataIllustration ), title: 'T' },
	] );
} );

test( 'PageHeader matches the recovered page header', () => {
	const onClick = () => {};
	const child = React.createElement( 'span', { id: 'filter' }, 'Filter' );
	assertSameMarkup( media.PageHeader, recovered.YG, [
		{},
		{ title: 'Quizzes' },
		{ title: 'Quizzes', description: 'All quizzes' },
		{ showAddButton: true },
		{
			showAddButton: true,
			addButtonConfig: { label: 'Add quiz', onClick },
		},
		{ showAddButton: true, addButtonConfig: { disabled: true } },
		{ children: child },
		{ children: [ child, child ], showAddButton: true },
		{ paddingY: 2 },
		{ paddingY: 0, title: '', description: '' },
	] );
} );
