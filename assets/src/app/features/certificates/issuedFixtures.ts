import type { IssuedCertificate } from './issuedApi';

/** A valid issued certificate as the server returns it. */
export const issued: IssuedCertificate = {
	id: 41,
	code: 'ABCD-EFGH-JKMN-PQRS',
	status: 'issued',
	source: 'completion',
	student_name: 'Ada Lovelace',
	learner: { id: 3, exists: true, email: 'ada@example.test' },
	course: { id: 12, title: 'Algebra I', exists: true },
	template: { id: 7, name: 'Completion' },
	completed_at: '2026-10-20T09:30:00Z',
	issued_at: '2026-10-20T09:31:00Z',
	revoked_at: null,
	replaces: 0,
	links: {
		pdf: 'https://example.test/?ohmylms-download-certificate=ABCD-EFGH-JKMN-PQRS&format=pdf',
		print: 'https://example.test/?ohmylms-download-certificate=ABCD-EFGH-JKMN-PQRS&format=print',
		verify: 'https://example.test/?ohmylms-verify-certificate=ABCD-EFGH-JKMN-PQRS',
	},
};

/** The same certificate after it was revoked. */
export const revoked: IssuedCertificate = {
	...issued,
	id: 42,
	code: 'WXYZ-2345-6789-ABCD',
	status: 'revoked',
	revoked_at: '2026-10-25T10:00:00Z',
	links: { pdf: '', print: '', verify: issued.links.verify },
};
