import test from 'node:test';
import assert from 'node:assert/strict';
import {
	skillText,
	skillPatch,
} from '../../assets/src/features/curriculum/skillTranslations.mjs';

test( 'language labels fall back without changing canonical skill identity', () => {
	const skill = {
		term_id: 131,
		name: 'Original objective',
		objective: 'Original objective',
		description: 'Notes',
		translations: { mn: { name: 'Монгол' } },
	};
	assert.equal( skillText( skill, 'mn' ), 'Монгол' );
	assert.equal( skillText( skill, 'en' ), skill.name );
	assert.equal( skillText( skill, 'mn', 'description' ), 'Notes' );
	assert.equal( skillText( skill, 'original' ), skill.name );
	assert.equal( skill.term_id, 131 );
	skill.translations.mn.description = '';
	assert.equal( skillText( skill, 'mn', 'description' ), '' );
} );

test( 'language patches update only the edited field and locale', () => {
	assert.deepEqual( skillPatch( 'mn', 'name', 'Монгол' ), {
		translations: { mn: { name: 'Монгол' } },
	} );
	assert.deepEqual( skillPatch( 'original', 'name', 'Objective' ), {
		name: 'Objective',
	} );
} );

test( 'legacy definitions become full objectives without replacing short titles or IDs', () => {
	const skill = {
		term_id: 8,
		name: 'Sets',
		description: 'Use set notation and Venn diagrams.',
	};
	assert.equal( skillText( skill, 'original' ), skill.description );
	assert.equal( skillText( skill, 'original', 'name' ), 'Sets' );
	assert.deepEqual(
		skillPatch( 'original', 'objective', 'An edited full definition' ),
		{ objective: 'An edited full definition' }
	);
	assert.equal( skill.term_id, 8 );
	skill.objective = 'Explicit objective';
	assert.equal( skillText( skill, 'original' ), 'Explicit objective' );
	skill.translations = {
		mn: { objective: 'Монгол тайлбар', name: 'Олонлог' },
	};
	assert.equal( skillText( skill, 'mn' ), 'Монгол тайлбар' );
	assert.equal( skillText( skill, 'mn', 'name' ), 'Олонлог' );
} );
