import {
	csvProblem,
	parseCreatedLink,
	parseImportReport,
	parseRosterInvitation,
} from './roster';

describe( 'roster model', () => {
	it( 'rejects links that are not http(s) and invalid statuses', () => {
		expect( () =>
			parseCreatedLink( { id: 1, url: 'javascript:alert(1)' } )
		).toThrow();
		expect( () =>
			parseRosterInvitation( {
				id: 1,
				role: 'teacher',
				email: null,
				email_hint: 'a***@b.c',
				class_id: null,
				class_name: null,
				student_id: null,
				student_name: null,
				status: 'unknown',
				expires_at: null,
				created_at: null,
			} )
		).toThrow();
	} );

	it( 'requires a review token on a dry run', () => {
		const report = {
			dry_run: true,
			review: null,
			counts: {},
			ignored: [],
			rows: [],
		};
		expect( () => parseImportReport( report ) ).toThrow();
		expect(
			parseImportReport( { ...report, review: 'f'.repeat( 64 ) } ).review
		).toBe( 'f'.repeat( 64 ) );
	} );

	it( 'checks size and header before sending', () => {
		expect( csvProblem( '' ) ).toMatch( /Paste CSV/ );
		expect( csvProblem( 'email\nx@y.z' ) ).toMatch( /name column/ );
		expect( csvProblem( '\uFEFFName,Email\nA,a@b.c' ) ).toBeNull();
		expect( csvProblem( 'name\n' + 'x'.repeat( 100001 ) ) ).toMatch(
			/100 KB/
		);
	} );
} );
