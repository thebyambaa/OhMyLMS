import test from 'node:test';
import assert from 'node:assert/strict';
import { describedBy, inputType } from '../../assets/src/ui/controls.mjs';

test( 'field descriptions retain external IDs and deduplicate generated associations', () => {
	assert.equal(
		describedBy( 'email', 'Help', 'Invalid', 'outside email-help outside' ),
		'outside email-help email-error'
	);
	assert.equal(
		describedBy( 'email', '', '', ' outside  note ' ),
		'outside note'
	);
	assert.equal( describedBy( 'email', '', '' ), undefined );
} );

test( 'Input accepts the supported native text-like controls and rejects unrelated kinds', () => {
	for ( const type of [
		'text',
		'email',
		'number',
		'password',
		'search',
		'tel',
		'url',
		'date',
		'datetime-local',
		'month',
		'time',
		'week',
	] )
		assert.equal( inputType( type ), type );
	for ( const type of [
		'checkbox',
		'radio',
		'submit',
		'image',
		undefined,
		null,
	] )
		assert.equal( inputType( type ), 'text' );
} );
