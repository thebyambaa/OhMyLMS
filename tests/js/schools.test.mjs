import test from 'node:test';
import assert from 'node:assert/strict';
import { csvCell, exportCsv } from '../../assets/src/features/schools/api.mjs';

test( 'school CSV export neutralizes formula cells and quotes names', () => {
	assert.equal( csvCell( '=HYPERLINK("x")' ), '"\'=HYPERLINK(""x"")"' );
	assert.equal( csvCell( '  +SUM(A1)' ), '"\'  +SUM(A1)"' );
	assert.equal( csvCell( 'Jane, Smith' ), '"Jane, Smith"' );
	assert.equal(
		exportCsv( [ { name: 'School A', completed: 2 } ] ),
		'"name","completed"\r\n"School A","2"'
	);
} );
