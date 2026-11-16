import test from 'node:test';
import assert from 'node:assert/strict';
import {
	SUGGEST_BATCH,
	chunk,
	defaultChoice,
	suggestSkills,
	withPrimarySkill,
} from '../../assets/src/features/learning/aiSkillConnect.mjs';

test( 'questions are sent in batches the server accepts, in order', async () => {
	const ids = Array.from( { length: SUGGEST_BATCH * 2 + 3 }, ( _, i ) => i + 1 );
	const sent = [];
	const progress = [];
	const rows = await suggestSkills(
		ids,
		7,
		false,
		( done, total ) => progress.push( [ done, total ] ),
		async ( options ) => {
			sent.push( options );
			return {
				rows: options.data.question_ids.map( ( question ) => ( {
					question,
					status: 'no-match',
				} ) ),
			};
		}
	);
	assert.deepEqual(
		sent.map( ( options ) => options.data.question_ids.length ),
		[ SUGGEST_BATCH, SUGGEST_BATCH, 3 ]
	);
	assert.ok(
		sent.every(
			( options ) =>
				'POST' === options.method &&
				7 === options.data.item_id &&
				false === options.data.include_mapped
		)
	);
	assert.deepEqual(
		rows.map( ( row ) => row.question ),
		ids
	);
	assert.deepEqual( progress.at( -1 ), [ ids.length, ids.length ] );
	assert.deepEqual( chunk( [], 3 ), [] );
} );

test( 'only a question without a skill is ticked by default', () => {
	const skill = { id: 5, name: 'Fractions' };
	assert.equal( defaultChoice( { status: 'suggested', skill, current: null } ), 5 );
	assert.equal(
		defaultChoice( {
			status: 'suggested',
			skill,
			current: { id: 9, name: 'Decimals' },
		} ),
		0
	);
	assert.equal( defaultChoice( { status: 'no-match', skill: null, current: null } ), 0 );
	assert.equal( defaultChoice( { status: 'has-skill', skill: null, current: skill } ), 0 );
} );

test( 'saving a choice sets the primary skill and keeps the other skills', () => {
	assert.deepEqual( withPrimarySkill( null, 4 ), {
		p1: { primary: 4, supporting: [] },
	} );
	assert.deepEqual(
		withPrimarySkill(
			{
				p1: { primary: 2, supporting: [ 4, 6 ] },
				p2: { primary: 8, supporting: [] },
			},
			4
		),
		{
			p1: { primary: 4, supporting: [ 6 ] },
			p2: { primary: 8, supporting: [] },
		}
	);
} );
