import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import { register } from 'node:module';
import { loadRecoveredSelects } from './controls-selects-harness.mjs';

register( './controls-selects-hooks.mjs', import.meta.url );
const model =
	await import( '../../assets/src/shared/controls/pickers/select-model.mjs' );
const { AdvancedSelectWP } =
	await import( '../../assets/src/shared/controls/pickers/AdvancedSelectWP.mjs' );
const { SearchSelectWP } =
	await import( '../../assets/src/shared/controls/pickers/SearchSelectWP.mjs' );

const { recovered, React, render } = loadRecoveredSelects();
const noop = () => {};

const courses = [
	{ label: 'Algebra', value: 1 },
	{ label: 'Biology', value: 2 },
];
const languages = [
	{ label: 'English (US)', value: 'en_US' },
	{ label: 'Arabic', value: 'ar' },
];
const currency = React.createElement( 'span', {
	dangerouslySetInnerHTML: { __html: '&#36;' },
} );

// Prop sets taken from the three real usages.
const advancedProps = {
	'community course, empty': {
		isClearable: true,
		closeMenuOnSelect: true,
		value: null,
		options: courses,
		onChange: noop,
		isDisabled: false,
		onSearch: noop,
		placeholder: 'Select a course...',
	},
	'community course, selected and disabled': {
		isClearable: true,
		closeMenuOnSelect: true,
		value: { label: 'Algebra', value: 1 },
		options: courses,
		onChange: noop,
		isDisabled: true,
		onSearch: noop,
		placeholder: 'Select a course...',
	},
	'membership courses, multi': {
		isMulti: true,
		closeMenuOnSelect: false,
		value: courses,
		options: courses,
		onChange: noop,
		onSearch: undefined,
	},
	'membership courses, multi empty': {
		isMulti: true,
		closeMenuOnSelect: false,
		value: [],
		options: courses,
		onChange: noop,
		onSearch: noop,
	},
	'extra class name': {
		className: 'custom',
		options: courses,
		value: null,
	},
};
const searchProps = {
	'setup language': {
		placeholder: 'Select Language',
		onChange: noop,
		value: [ languages[ 0 ] ],
		defaultOptions: languages,
		isClearable: false,
		isSearchable: true,
		isMulti: false,
	},
	'setup currency': {
		placeholder: 'Type to Select Currency',
		onChange: noop,
		value: [ { label: currency, value: 'USD' } ],
		defaultOptions: [ { label: currency, value: 'USD' } ],
		loadOptions: ( input, done ) => done( [] ),
		isClearable: false,
		isSearchable: true,
		isMulti: false,
	},
	'custom class': {
		customClass: 'wide',
		defaultOptions: languages,
		value: null,
	},
	'class name overrides': {
		className: 'mine',
		customClass: 'wide',
		defaultOptions: languages,
		value: null,
	},
};

// ---------------------------------------------------------------- real renders
// Both sides render with react-select 5.10.2 and Emotion: the recovered side runs factories
// 46005, 11541, 52836, 8546 and 15777 as shipped against the host React; only module 11456
// (Emotion) is the npm package because the extraction lacks it.

for ( const [ name, props ] of Object.entries( advancedProps ) ) {
	for ( const open of [ false, true ] ) {
		test( `AdvancedSelectWP markup matches the recovered control: ${ name }${
			open ? ', menu open' : ''
		}`, () => {
			const input = open ? { ...props, menuIsOpen: true } : props;
			const expected = render( recovered.AdvancedSelectWP, input );
			assert.ok( expected.includes( 'ohmylms-advanced-select' ) );
			assert.equal( render( AdvancedSelectWP, input ), expected );
		} );
	}
}

for ( const [ name, props ] of Object.entries( searchProps ) ) {
	for ( const open of [ false, true ] ) {
		test( `SearchSelectWP markup matches the recovered control: ${ name }${
			open ? ', menu open' : ''
		}`, () => {
			const input = open ? { ...props, menuIsOpen: true } : props;
			const expected = render( recovered.SearchSelectWP, input );
			assert.ok(
				expected.includes(
					props.className ? props.className : 'ohmylms-search-select'
				)
			);
			assert.equal( render( SearchSelectWP, input ), expected );
		} );
	}
}

test( 'default messages come from react-select', () => {
	const open = { menuIsOpen: true, options: [], value: null };
	assert.ok( render( AdvancedSelectWP, open ).includes( 'No options' ) );
	assert.ok( render( SearchSelectWP, open ).includes( 'No options' ) );
	assert.ok( render( AdvancedSelectWP, {} ).includes( 'Select...' ) );
} );

test( 'class names on the root element', () => {
	assert.match(
		render( AdvancedSelectWP, { className: 'x' } ),
		/class="ohmylms-advanced-select ohmylms-search-select auto-height x\b/
	);
	assert.match(
		render( SearchSelectWP, {} ),
		/class="ohmylms-search-select undefined\b/
	);
	assert.match(
		render( SearchSelectWP, { customClass: 'wide' } ),
		/class="ohmylms-search-select wide\b/
	);
} );

test( 'both are memoised components like the recovered exports', () => {
	for ( const [ authored, original ] of [
		[ AdvancedSelectWP, recovered.AdvancedSelectWP ],
		[ SearchSelectWP, recovered.SearchSelectWP ],
	] ) {
		assert.equal( authored.$$typeof, original.$$typeof );
	}
} );

// ---------------------------------------------------------------- pure logic

test( 'getAdvancedSelectProps joins the fixed classes with className', () => {
	assert.deepEqual( model.getAdvancedSelectProps( {} ), {
		className: 'ohmylms-advanced-select ohmylms-search-select auto-height ',
	} );
	const props = model.getAdvancedSelectProps( {
		className: 'a',
		isMulti: true,
		onSearch: noop,
	} );
	assert.equal(
		props.className,
		'ohmylms-advanced-select ohmylms-search-select auto-height a'
	);
	assert.equal( props.isMulti, true );
	assert.equal( props.onSearch, noop );
} );

test( 'getSearchSelectProps keeps the recovered class precedence', () => {
	assert.equal(
		model.getSearchSelectProps( {} ).className,
		'ohmylms-search-select undefined'
	);
	assert.equal(
		model.getSearchSelectProps( { customClass: 'c' } ).className,
		'ohmylms-search-select c'
	);
	const props = model.getSearchSelectProps( {
		className: 'own',
		customClass: 'c',
	} );
	assert.equal( props.className, 'own' );
	assert.equal( props.customClass, 'c' );
} );

test( 'hasAsyncSearch needs a loadOptions function', () => {
	assert.equal( model.hasAsyncSearch( { loadOptions: noop } ), true );
	assert.equal( model.hasAsyncSearch( { defaultOptions: [] } ), false );
	assert.equal( model.hasAsyncSearch( undefined ), false );
} );

test( 'getSelectedValues maps onChange payloads', () => {
	assert.deepEqual( model.getSelectedValues( null ), [] );
	assert.deepEqual( model.getSelectedValues( undefined ), [] );
	assert.deepEqual( model.getSelectedValues( courses[ 0 ] ), [ 1 ] );
	assert.deepEqual( model.getSelectedValues( courses ), [ 1, 2 ] );
	assert.deepEqual(
		model.getSelectedValues( [ null, courses[ 1 ] ] ),
		[ 2 ]
	);
} );

test( 'the picker barrel exports both controls', () => {
	const source = fs.readFileSync(
		new URL(
			'../../assets/src/shared/controls/pickers/index.js',
			import.meta.url
		),
		'utf8'
	);
	assert.match( source, /AdvancedSelectWP/ );
	assert.match( source, /SearchSelectWP/ );
} );
