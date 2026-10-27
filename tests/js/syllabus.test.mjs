import test from 'node:test';
import assert from 'node:assert/strict';
import {
	buildRows,
	csvCell,
	detectDelimiter,
	guessRoles,
	looksLikeHeader,
	mappingProblems,
	outlineCsv,
	parseCsv,
	parseRecords,
	templateCsv,
	toCsv,
} from '../../assets/src/features/curriculum/csv.mjs';
import {
	allGroups,
	changesNothing,
	containerOptions,
	fileName,
	findGroup,
	groupLabel,
	groupSiblings,
	reportRows,
	skillLabel,
	stepPosition,
	validateContent,
	validateGroup,
	validateSkill,
} from '../../assets/src/features/curriculum/syllabus.mjs';
import { needsConfirmation } from '../../assets/src/features/curriculum/model.mjs';

// ---- Parsing ---------------------------------------------------------------------------------------

test( 'quotes, embedded delimiters, doubled quotes and line breaks inside a cell', () => {
	const text = 'a,b,c\r\n"x, y","say ""hi""","line 1\nline 2"\r\n';
	assert.deepEqual( parseRecords( text, ',' ), [
		[ 'a', 'b', 'c' ],
		[ 'x, y', 'say "hi"', 'line 1\nline 2' ],
	] );
} );

test( 'a byte order mark, CR-only line ends and a missing final newline are accepted', () => {
	assert.deepEqual( parseRecords( '﻿a;b\rc;d', ';' ), [
		[ 'a', 'b' ],
		[ 'c', 'd' ],
	] );
	assert.deepEqual( parseRecords( '', ',' ), [] );
	assert.deepEqual( parseRecords( 'only', ',' ), [ [ 'only' ] ] );
	assert.deepEqual( parseRecords( 'a,,\n', ',' ), [ [ 'a', '', '' ] ] );
} );

test( 'the delimiter is found from the file: comma, semicolon or tab', () => {
	assert.equal( detectDelimiter( 'a,b,c\n1,2,3\n4,5,6' ), ',' );
	assert.equal( detectDelimiter( 'a;b;c\n1;2;3\n4;5;6' ), ';' );
	assert.equal( detectDelimiter( 'a\tb\tc\n1\t2\t3' ), '\t' );
	assert.equal(
		detectDelimiter(
			'Skill ID;Ref;Objective\nМ11.1А1;М11.1А;"Графикийг зөв зурах, дараа нь шалгах"'
		),
		';'
	);
	assert.equal( detectDelimiter( 'just one column\nof words' ), ',' );
} );

test( 'parseCsv drops blank lines, pads short rows and reports an empty file', () => {
	const parsed = parseCsv( 'a,b,c\n\n1,2\n   ,  ,\n3,4,5\n' );
	assert.deepEqual( parsed.records, [
		[ 'a', 'b', 'c' ],
		[ '1', '2', '' ],
		[ '3', '4', '5' ],
	] );
	assert.equal( parsed.width, 3 );
	assert.equal( parseCsv( '\n\n  \n' ).error, 'empty' );
	assert.equal( parseCsv( 'a,b\n�,2' ).replaced, true );
} );

// ---- Mapping the columns --------------------------------------------------------------------------

test( 'headers are recognised for the layout of the Mongolian grade 11 reference', () => {
	const roles = guessRoles( [
		'Skill ID',
		'Ref',
		'Atomic Learning Objective (Mongolian)',
	] );
	assert.deepEqual( roles, [ 'skill_code', 'group_code', 'skill' ] );
} );

test( 'headers are recognised for a Cambridge-style sheet and for our own template', () => {
	assert.deepEqual(
		guessRoles( [
			'Topic',
			'Section code',
			'Section',
			'Learning objective',
			'Notes and examples',
		] ),
		[ 'content', 'group_code', 'group', 'skill', 'description' ]
	);
	assert.deepEqual(
		guessRoles( [
			'content_code',
			'content',
			'group_code',
			'group',
			'skill_code',
			'skill',
			'description',
		] ),
		[
			'content_code',
			'content',
			'group_code',
			'group',
			'skill_code',
			'skill',
			'description',
		]
	);
	assert.deepEqual( guessRoles( [ 'Chapter', 'Group', 'Code', 'Name' ] ), [
		'content',
		'group',
		'skill_code',
		'skill',
	] );
} );

test( 'a role is given to one column only and unknown headers are left alone', () => {
	const roles = guessRoles( [
		'Code',
		'Skill code',
		'Skill',
		'Weight',
		'Skill name',
	] );
	assert.equal( roles.filter( ( role ) => role === 'skill_code' ).length, 1 );
	assert.equal( roles.filter( ( role ) => role === 'skill' ).length, 1 );
	assert.equal( roles[ 3 ], '' );
	assert.deepEqual( mappingProblems( [ 'skill_code', 'group_code' ] ), [
		'skill',
	] );
	assert.deepEqual( mappingProblems( [ 'skill', 'group' ] ), [] );
	assert.deepEqual( mappingProblems( [ 'skill', 'skill' ] ), [
		'duplicate:skill',
	] );
} );

test( 'a first row of titles is told apart from a first row of data', () => {
	assert.equal( looksLikeHeader( [ 'Skill ID', 'Ref', 'Objective' ] ), true );
	assert.equal( looksLikeHeader( [ 'content_code', 'content' ] ), true );
	assert.equal(
		looksLikeHeader( [ 'М11.1А1', 'М11.1А', 'Квадрат тэгшитгэл' ] ),
		false
	);
	assert.equal( looksLikeHeader( [ '1', 'Number', 'C1.1' ] ), false );
} );

// ---- Building rows ---------------------------------------------------------------------------------

test( 'rows carry the mapped columns, the sheet row number and empty strings for the rest', () => {
	const records = [
		[ 'Skill ID', 'Ref', 'Objective' ],
		[ 'М11.1А1', 'М11.1А', '  Квадрат   тэгшитгэлийн графикийг зурах. ' ],
		[ 'М11.1А2', 'М11.1А', 'Шийдийн тоог тодорхойлох.' ],
	];
	const rows = buildRows( records, [ 'skill_code', 'group_code', 'skill' ] );
	assert.equal( rows.length, 2 );
	assert.deepEqual( rows[ 0 ], {
		line: 2,
		skill_code: 'М11.1А1',
		group_code: 'М11.1А',
		skill: 'Квадрат тэгшитгэлийн графикийг зурах.',
		content_code: '',
		content: '',
		group: '',
		description: '',
		category: '',
	} );
	assert.equal( rows[ 1 ].line, 3 );
	assert.equal(
		buildRows( records, [ 'skill_code', 'group_code', 'skill' ], {
			header: false,
		} ).length,
		3
	);
} );

test( 'cells left empty by merged cells repeat the value above, but only on rows with a skill', () => {
	const records = [
		[ 'Chapter', 'Group', 'Skill' ],
		[ '1 Number', 'C1.1', 'Natural numbers' ],
		[ '', '', 'Prime numbers' ],
		[ '', 'C1.2', 'Set language' ],
		[ '2 Algebra', '', 'Simplify' ],
		[ '3 Heading only', '', '' ],
		[ '', '', 'Skill under the heading' ],
	];
	const roles = [ 'content', 'group', 'skill' ];
	const rows = buildRows( records, roles );
	assert.deepEqual(
		rows.map( ( row ) => [ row.content, row.group, row.skill ] ),
		[
			[ '1 Number', 'C1.1', 'Natural numbers' ],
			[ '1 Number', 'C1.1', 'Prime numbers' ],
			[ '1 Number', 'C1.2', 'Set language' ],
			[ '2 Algebra', '', 'Simplify' ],
			[ '3 Heading only', '', '' ],
			[ '3 Heading only', '', 'Skill under the heading' ],
		]
	);
	assert.equal(
		rows[ 3 ].group,
		'',
		'a group is not carried into a different content item'
	);
	const plain = buildRows( records, roles, { fillDown: false } );
	assert.equal( plain[ 1 ].content, '' );
} );

test( 'rows with nothing mapped in them are skipped', () => {
	const rows = buildRows(
		[
			[ 'a', 'b' ],
			[ '', '' ],
			[ 'x', 'y' ],
		],
		[ 'skill', '' ],
		{ header: false }
	);
	assert.deepEqual(
		rows.map( ( row ) => row.skill ),
		[ 'a', 'x' ]
	);
} );

// ---- Writing CSV ----------------------------------------------------------------------------------

test( 'cells are quoted only when they need to be', () => {
	assert.equal( csvCell( 'plain' ), 'plain' );
	assert.equal( csvCell( 'a,b' ), '"a,b"' );
	assert.equal( csvCell( 'say "hi"' ), '"say ""hi"""' );
	assert.equal( csvCell( 'two\nlines' ), '"two\nlines"' );
	assert.equal( csvCell( ' padded ' ), '" padded "' );
	assert.equal( csvCell( 'a;b', ';' ), '"a;b"' );
	assert.equal( csvCell( undefined ), '' );
} );

test( 'the template parses back to the layout the importer reads', () => {
	const parsed = parseCsv( templateCsv() );
	assert.equal( parsed.delimiter, ',' );
	assert.deepEqual( guessRoles( parsed.records[ 0 ] ), [
		'content_code',
		'content',
		'group_code',
		'group',
		'skill_code',
		'skill',
		'category',
	] );
	const rows = buildRows( parsed.records, guessRoles( parsed.records[ 0 ] ) );
	assert.equal( rows.length, 4 );
	assert.equal( rows[ 0 ].group_code, 'C1.1' );
	assert.equal( rows[ 0 ].category, 'Core' );
} );

const outline = {
	syllabus: { id: 1, name: 'Mathematics 0580' },
	contents: [
		{
			id: 1,
			parent_id: 0,
			depth: 0,
			name: 'Mathematics 0580',
			code: '',
			groups: [
				{ id: 9, item_id: 1, code: '', name: 'Loose', skills: [] },
			],
		},
		{
			id: 2,
			parent_id: 1,
			depth: 1,
			name: 'Paper 1',
			code: '',
			groups: [],
		},
		{
			id: 3,
			parent_id: 2,
			depth: 2,
			name: '1 Number',
			code: 'N',
			groups: [
				{
					id: 10,
					item_id: 3,
					code: 'C1.1',
					name: 'Types of number',
					skills: [
						{
							term_id: 100,
							code: 'C1.1.1',
							name: 'Identify "natural" numbers',
							description: 'e.g. six, billion\nand more',
							category: 'Advanced',
						},
						{
							term_id: 101,
							code: '',
							name: 'Use a > 0 and D < 0',
							description: '',
						},
					],
				},
				{ id: 11, item_id: 3, code: 'C1.2', name: 'Sets', skills: [] },
			],
		},
		{
			id: 4,
			parent_id: 1,
			depth: 1,
			name: 'Empty topic',
			code: 'E',
			groups: [],
		},
	],
};

test( 'an export preserves paths, codes and categories without legacy skill notes', () => {
	const text = outlineCsv( outline );
	const parsed = parseCsv( text );
	assert.deepEqual( parsed.records[ 0 ], [
		'content_code',
		'content',
		'group_code',
		'group',
		'skill_code',
		'skill',
		'category',
	] );
	const rows = buildRows( parsed.records, guessRoles( parsed.records[ 0 ] ), {
		fillDown: false,
	} );
	const skill = rows.find( ( row ) => row.skill_code === 'C1.1.1' );
	assert.equal( skill.content, 'Paper 1 > 1 Number' );
	assert.equal( skill.content_code, 'N' );
	assert.equal( skill.skill, 'Identify "natural" numbers' );
	assert.equal( skill.description, '' );
	assert.ok( ! text.includes( 'e.g. six, billion' ) );
	assert.ok( parsed.records.every( ( record ) => record.length === 7 ) );
	assert.equal( skill.category, 'Advanced' );
	assert.equal(
		rows.find( ( row ) => row.skill === 'Use a > 0 and D < 0' ).group,
		'Types of number'
	);
	assert.ok(
		rows.some( ( row ) => row.group_code === 'C1.2' && row.skill === '' ),
		'an empty group is still exported'
	);
	assert.ok(
		rows.some(
			( row ) => row.content === 'Empty topic' && row.group === ''
		),
		'an empty content item is still exported'
	);
	assert.ok(
		rows.some( ( row ) => row.group === 'Loose' && row.content === '' ),
		'a group on the syllabus itself has no content'
	);
	assert.equal( toCsv( [ [ 'a', 'b' ] ] ), 'a,b\r\n' );
} );

// ---- Outline helpers ------------------------------------------------------------------------------

test( 'groups, labels and containers come from the outline in order', () => {
	assert.deepEqual(
		allGroups( outline ).map( ( entry ) => entry.group.id ),
		[ 9, 10, 11 ]
	);
	assert.equal(
		groupLabel( { code: 'C1.1', name: 'Types of number' } ),
		'C1.1 · Types of number'
	);
	assert.equal( groupLabel( { code: 'М11.1А', name: 'М11.1А' } ), 'М11.1А' );
	assert.equal( groupLabel( { code: '', name: 'Loose' } ), 'Loose' );
	assert.equal(
		skillLabel( { code: 'C1.1.1', name: 'Natural' } ),
		'C1.1.1 · Natural'
	);
	assert.deepEqual(
		containerOptions( outline ).map( ( option ) => [
			option.id,
			option.depth,
			option.isRoot,
		] ),
		[
			[ 1, 0, true ],
			[ 2, 1, false ],
			[ 3, 2, false ],
			[ 4, 1, false ],
		]
	);
	assert.equal( findGroup( outline, 11 ).content.id, 3 );
	assert.equal( findGroup( outline, 99 ), null );
	assert.deepEqual( groupSiblings( outline, 11 ), {
		content: outline.contents[ 2 ],
		ids: [ 10, 11 ],
	} );
	assert.deepEqual( groupSiblings( outline, 99 ).ids, [] );
} );

test( 'stepping moves one place and stops at the ends', () => {
	assert.equal( stepPosition( [ 5, 6, 7 ], 6, -1 ), 0 );
	assert.equal( stepPosition( [ 5, 6, 7 ], 6, 1 ), 2 );
	assert.equal( stepPosition( [ 5, 6, 7 ], 5, -1 ), null );
	assert.equal( stepPosition( [ 5, 6, 7 ], 7, 1 ), null );
	assert.equal( stepPosition( [ 5, 6, 7 ], 99, 1 ), null );
} );

test( 'group and skill forms mirror the server rules', () => {
	assert.deepEqual( validateGroup( { name: '', code: '' } ), {
		name: 'required',
	} );
	assert.deepEqual(
		validateGroup( { name: '', code: 'C1.1' } ),
		{},
		'a code alone is enough'
	);
	assert.equal(
		validateGroup( { name: 'x'.repeat( 191 ) } ).name,
		'too-long'
	);
	assert.equal(
		validateGroup( { name: 'g', code: 'c'.repeat( 61 ) } ).code,
		'too-long'
	);
	assert.deepEqual(
		validateSkill( { name: 'ok', code: 'C1', description: '' } ),
		{}
	);
	assert.equal( validateSkill( { name: '' } ).name, 'required' );
	assert.equal(
		validateSkill( { name: 'ok', code: 'c'.repeat( 41 ) } ).code,
		'too-long'
	);
	assert.equal(
		validateSkill( { name: 'ok', description: 'd'.repeat( 2001 ) } )
			.description,
		'too-long'
	);
} );

test( 'a content item needs a name and may have a code', () => {
	assert.deepEqual( validateContent( { name: 'Number', code: '1' } ), {} );
	assert.deepEqual(
		validateContent( { name: '  ', code: 'C' } ),
		{ name: 'required' },
		'a code alone is not enough'
	);
	assert.equal(
		validateContent( { name: 'x'.repeat( 191 ) } ).name,
		'too-long'
	);
	assert.equal(
		validateContent( { name: 'ok', code: 'c'.repeat( 61 ) } ).code,
		'too-long'
	);
} );

test( 'reports summarize what an import would do', () => {
	const report = {
		contents: { create: 2, update: 0, unchanged: 1 },
		groups: { create: 3, update: 1, unchanged: 0 },
		skills: { create: 10, update: 2, move: 1, unchanged: 4 },
	};
	assert.deepEqual(
		reportRows( report ).map( ( row ) => [
			row.id,
			row.create,
			row.update,
			row.same,
		] ),
		[
			[ 'contents', 2, 0, 1 ],
			[ 'groups', 3, 1, 0 ],
			[ 'skills', 10, 2, 4 ],
		]
	);
	assert.equal( changesNothing( report ), false );
	assert.equal(
		changesNothing( {
			contents: { unchanged: 3 },
			groups: { unchanged: 2 },
			skills: { unchanged: 9 },
		} ),
		true
	);
	assert.equal( changesNothing( {} ), true );
} );

test( 'download names are safe and keep other scripts', () => {
	assert.equal(
		fileName( 'Mathematics 0580', 'export' ),
		'Mathematics-0580-export.csv'
	);
	assert.equal(
		fileName( '11-р анги: Математик', 'template' ),
		'11-р-анги-Математик-template.csv'
	);
	assert.equal( fileName( '', 'export' ), 'syllabus-export.csv' );
	assert.equal( fileName( '///', 'export' ), 'syllabus-export.csv' );
} );

test( 'deleting an item with skill groups asks for confirmation', () => {
	const base = {
		children: 0,
		links: 0,
		own_links: 0,
		bundles: 0,
		own_bundles: 0,
		groups: 0,
		own_groups: 0,
	};
	assert.equal( needsConfirmation( base, '' ), false );
	assert.equal( needsConfirmation( { ...base, own_groups: 2 }, '' ), true );
	assert.equal(
		needsConfirmation( { ...base, groups: 3 }, 'delete' ) ||
			needsConfirmation( { ...base, children: 1, groups: 3 }, 'delete' ),
		true
	);
	assert.equal(
		needsConfirmation( { ...base, groups: 3 }, 'promote' ),
		false,
		"promoting only removes the item's own groups"
	);
} );
