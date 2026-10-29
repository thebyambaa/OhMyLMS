import test from 'node:test';
import assert from 'node:assert/strict';
import { createRequire, register } from 'node:module';
import {
	LOADING_ROWS,
	cellContent,
	collectKeys,
	isAllSelected,
	nextSort,
	rowClassName,
	selectedRows,
	sortRows,
	toggleKey,
} from '../../assets/src/shared/table/model.mjs';
import {
	createLoader,
	installBrowserStubs,
} from '../../tools/recovered-modules.mjs';
import { CheckboxControl } from './table-stubs.mjs';

// The authored table is JSX and imports `@wordpress/components`, which only
// exists in the WordPress admin; the hooks compile it and stub that import.
register( './table-hooks.mjs', import.meta.url );
const { Table } = await import( '../../assets/src/shared/table/index.js' );

const hostRequire = installBrowserStubs();
const { renderToStaticMarkup } = hostRequire( 'react-dom/server' );
const { createElement } = createRequire( import.meta.url )( 'react' );

globalThis.wp = { element: hostRequire( '@wordpress/element' ) };

// Factory 38093 is the shared control barrel. Only the two controls the table
// reads are provided: the recovered skeleton (68119) as shipped, and the
// recovered checkbox wrapper (a CheckboxControl with admin styling).
const load = createLoader( {
	2214: { CheckboxControl },
	12470: {},
	38093: {
		get SkeletonWP() {
			return load( 68119 ).A;
		},
		CheckboxWP: ( props ) =>
			createElement( CheckboxControl, {
				__nextHasNoMarginBottom: true,
				className: 'ohmylms-checkbox-control',
				style: { borderColor: '#C8D2E9', borderRadius: '4px' },
				...props,
			} ),
	},
} );
const Recovered = load( 65 ).A;

const rows = [
	{ id: 1, name: 'Ada', score: 30 },
	{ id: 2, name: 'Grace', score: 10 },
	{ id: 3, name: 'Linus', score: 20 },
];
const columns = [
	{
		key: 'name',
		title: 'Name',
		sorter: ( a, b ) => a.name.localeCompare( b.name ),
	},
	{
		key: 'score',
		title: 'Score',
		width: 80,
		render: ( value, row ) =>
			createElement( 'b', null, `${ row.name }:${ value }` ),
	},
];
const emptyText = createElement( 'p', null, 'No records yet.' );

const propSets = {
	'plain list (OrderItems)': {
		rowKey: 'id',
		columns,
		dataSource: rows,
		className: 'ohmylms-order-details-summary-table',
	},
	'empty with locale text (TransactionHistory)': {
		rowKey: 'id',
		columns,
		dataSource: [],
		locale: { emptyText },
	},
	'empty without locale': { rowKey: 'id', columns, dataSource: [] },
	'loading (OrderList)': {
		rowKey: 'id',
		columns,
		dataSource: rows,
		loading: true,
		pagination: false,
		locale: { emptyText },
	},
	'loading with selection': {
		rowKey: 'id',
		columns,
		dataSource: [],
		loading: true,
		rowSelection: { selectedRowKeys: [], onChange() {} },
	},
	'row selection (StudentList)': {
		rowKey: 'id',
		columns,
		dataSource: rows,
		rowSelection: { selectedRowKeys: [ 2, 3 ], onChange() {} },
		className: 'student-listing-table',
		pagination: false,
		locale: { emptyText },
	},
	'selection without selectedRowKeys': {
		rowKey: 'id',
		columns,
		dataSource: rows,
		rowSelection: { onChange() {} },
	},
	'empty with selection column': {
		rowKey: 'id',
		columns,
		dataSource: [],
		rowSelection: { selectedRowKeys: [], onChange() {} },
		locale: { emptyText },
	},
	'row hooks and onRow (CourseStudentsReport)': {
		rowKey: 'id',
		columns,
		dataSource: rows,
		scroll: { x: 'max-content' },
		onMouseEnterOnRow() {},
		onMouseLeaveOnRow() {},
		onRow: ( row ) => ( {
			className: `custom-${ row.id }`,
			'data-id': row.id,
		} ),
	},
	'unsupported onRowMouseEnter is ignored (RecentCourses)': {
		rowKey: 'id',
		columns,
		dataSource: rows,
		onRowMouseEnter() {},
		onRowMouseLeave() {},
	},
	'missing fields and element titles': {
		rowKey: 'id',
		columns: [
			{ key: 'missing', title: 'Missing' },
			{ key: 'name', title: createElement( 'em', null, 'Name' ) },
		],
		dataSource: rows,
	},
};

// The recovered table writes the lowercase  attribute; React emits // for the authored one. HTML attribute names are case-insensitive, so the DOM is the same.
// The recovered table writes a lowercase colspan attribute and React emits
// colSpan for the authored one. HTML attribute names are case-insensitive, so
// the resulting DOM is the same.
const normalize = ( markup ) => markup.replaceAll( 'colSpan=', 'colspan=' );

for ( const [ name, props ] of Object.entries( propSets ) ) {
	test( `markup matches the recovered table: ${ name }`, () => {
		const expected = renderToStaticMarkup(
			createElement( Recovered, props )
		);
		const actual = renderToStaticMarkup( createElement( Table, props ) );
		assert.equal( normalize( actual ), normalize( expected ) );
	} );
}

test( 'the loading placeholder renders ten skeleton lines and no rows', () => {
	const markup = renderToStaticMarkup(
		createElement( Table, {
			rowKey: 'id',
			columns,
			dataSource: rows,
			loading: true,
		} )
	);
	assert.equal( LOADING_ROWS, 10 );
	assert.equal( markup.match( /ohmylms-skeleton-line/g ).length, 10 );
	assert.match( markup, /min-height:540px/ );
	assert.doesNotMatch( markup, /ohmylms-tr / );
} );

test( 'the last row is marked and every row carries a separator space', () => {
	assert.equal( rowClassName( 0, 3 ), 'ohmylms-tr ohmylms-tr-0 ' );
	assert.equal(
		rowClassName( 2, 3 ),
		'ohmylms-tr ohmylms-tr-2 ohmylms-tr-last'
	);
} );

test( 'sort cycles ascending, descending, off, and restarts on another column', () => {
	const asc = nextSort( null, 'name' );
	assert.deepEqual( asc, { key: 'name', order: 'asc' } );
	const desc = nextSort( asc, 'name' );
	assert.deepEqual( desc, { key: 'name', order: 'desc' } );
	assert.equal( nextSort( desc, 'name' ), null );
	assert.deepEqual( nextSort( desc, 'score' ), {
		key: 'score',
		order: 'asc',
	} );
} );

test( 'sortRows orders a copy using the column sorter', () => {
	const names = ( list ) => list.map( ( row ) => row.name );
	assert.deepEqual(
		names( sortRows( rows, columns, { key: 'name', order: 'desc' } ) ),
		[ 'Linus', 'Grace', 'Ada' ]
	);
	assert.deepEqual(
		names( sortRows( rows, columns, { key: 'name', order: 'asc' } ) ),
		[ 'Ada', 'Grace', 'Linus' ]
	);
	assert.deepEqual( names( rows ), [ 'Ada', 'Grace', 'Linus' ] );
} );

test( 'sortRows keeps order without a sort, a sorter function or a column', () => {
	const copy = sortRows( rows, columns, null );
	assert.notEqual( copy, rows );
	assert.deepEqual( copy, rows );
	assert.deepEqual(
		sortRows( rows, columns, { key: 'score', order: 'asc' } ),
		rows
	);
	assert.deepEqual(
		sortRows( rows, columns, { key: 'nope', order: 'asc' } ),
		rows
	);
	assert.deepEqual(
		sortRows( rows, [ { key: 'name', sorter: true } ], {
			key: 'name',
			order: 'asc',
		} ),
		rows
	);
} );

test( 'selection helpers toggle keys without mutating and resolve rows', () => {
	const keys = [ 1 ];
	assert.deepEqual( toggleKey( keys, 2 ), [ 1, 2 ] );
	assert.deepEqual( toggleKey( [ 1, 2 ], 1 ), [ 2 ] );
	assert.deepEqual( keys, [ 1 ] );
	assert.deepEqual( collectKeys( rows, 'id' ), [ 1, 2, 3 ] );
	assert.deepEqual(
		selectedRows( rows, 'id', [ 3, 1 ] ).map( ( row ) => row.id ),
		[ 1, 3 ]
	);
} );

test( 'the header checkbox is only ticked when every row is selected', () => {
	assert.equal( isAllSelected( [], 0 ), false );
	assert.equal( isAllSelected( [ 1, 2 ], 3 ), false );
	assert.equal( isAllSelected( [ 1, 2, 3 ], 3 ), true );
} );

test( 'cellContent prefers a render function over the raw field', () => {
	const row = { id: 1, name: 'Ada' };
	assert.equal( cellContent( { key: 'name' }, row ), 'Ada' );
	assert.equal(
		cellContent(
			{ key: 'name', render: ( value, r ) => `${ value }#${ r.id }` },
			row
		),
		'Ada#1'
	);
	assert.equal( cellContent( { key: 'gone' }, row ), undefined );
} );
