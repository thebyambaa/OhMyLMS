import { __ } from '@wordpress/i18n';
import { del, post } from '../../lib/api';
import { getCollection } from '../../lib/api/collection';
import { confirmed, invalidResponse, positiveId, record } from './model';

/** Roles a roster invitation can grant. */
export const INVITE_ROLES = [ 'school_admin', 'teacher', 'student' ] as const;
export type InviteRole = ( typeof INVITE_ROLES )[ number ];

/** Largest CSV the server accepts, in bytes. */
export const MAX_CSV_BYTES = 100000;

/** Most people in one CSV. */
export const MAX_CSV_ROWS = 200;

export type InvitationStatus = 'pending' | 'accepted' | 'revoked' | 'expired';

/** A listed invitation. `email` is null unless the user may list users; `email_hint` is masked. */
export interface RosterInvitation {
	id: number;
	role: string;
	email: string | null;
	email_hint: string;
	class_id: number | null;
	class_name: string | null;
	student_id: number | null;
	student_name: string | null;
	status: InvitationStatus;
	expires_at: string | null;
	created_at: string | null;
}

export interface InvitationInput {
	role: InviteRole;
	email: string;
	send_email: boolean;
}

export interface ManagedStudentInput {
	name: string;
	external_student_id: string;
}

/** A single-use link, shown once. */
export interface CreatedLink {
	id: number;
	url: string;
	expires_at: string | null;
	email_sent: boolean;
}

/** A school-managed student account (no email). */
export interface ManagedStudent {
	id: number;
	display_name: string;
	username: string;
	external_student_id: string;
	joined_at: string | null;
	setup_expires_at: string | null;
}

export type PlannedAction =
	'create_student' | 'invite' | 'existing' | 'pending' | 'error';
export type AppliedAction =
	'created' | 'invited' | 'existing' | 'pending' | 'error';

export interface ImportRow {
	line: number;
	role: string;
	name: string;
	email: string;
	external_student_id: string;
	action: PlannedAction | AppliedAction;
	error: string | null;
	user_id: number | null;
	username: string | null;
	url: string | null;
}

/** Review (dry run, with `review` token) or applied import. */
export interface ImportReport {
	dry_run: boolean;
	review: string | null;
	counts: Record< string, number >;
	ignored: string[];
	rows: ImportRow[];
}

const ACTIONS: readonly string[] = [
	'create_student',
	'invite',
	'existing',
	'pending',
	'error',
	'created',
	'invited',
];
const STATUSES: readonly InvitationStatus[] = [
	'pending',
	'accepted',
	'revoked',
	'expired',
];

function text( value: unknown ): string {
	if ( 'string' !== typeof value ) {
		throw invalidResponse();
	}
	return value;
}

function nullableText( value: unknown ): string | null {
	return null === value ? null : text( value );
}

function nullableId( value: unknown ): number | null {
	return null === value ? null : positiveId( value );
}

function link( value: unknown ): string {
	const url = text( value );
	if ( ! /^https?:\/\//.test( url ) ) {
		throw invalidResponse();
	}
	return url;
}

function count( value: unknown ): number {
	if (
		'number' !== typeof value ||
		! Number.isSafeInteger( value ) ||
		value < 0
	) {
		throw invalidResponse();
	}
	return value;
}

/**
 * Parse a listed invitation.
 *
 * @param value Parsed JSON.
 * @return The invitation.
 */
export function parseRosterInvitation( value: unknown ): RosterInvitation {
	const item = record( value );
	const status = STATUSES.find( ( known ) => known === item.status );
	if ( ! status ) {
		throw invalidResponse();
	}
	return {
		id: positiveId( item.id ),
		role: text( item.role ),
		email: nullableText( item.email ),
		email_hint: text( item.email_hint ),
		class_id: nullableId( item.class_id ),
		class_name: nullableText( item.class_name ),
		student_id: nullableId( item.student_id ),
		student_name: nullableText( item.student_name ),
		status,
		expires_at: nullableText( item.expires_at ),
		created_at: nullableText( item.created_at ),
	};
}

/**
 * Parse a created invitation or setup link.
 *
 * @param value Parsed JSON.
 * @return The link.
 */
export function parseCreatedLink( value: unknown ): CreatedLink {
	const item = record( value );
	return {
		id: positiveId( item.id ),
		url: link( item.url ),
		expires_at: nullableText( item.expires_at ),
		email_sent: true === item.email_sent,
	};
}

/**
 * Parse a managed student.
 *
 * @param value Parsed JSON.
 * @return The student.
 */
export function parseManagedStudent( value: unknown ): ManagedStudent {
	const item = record( value );
	return {
		id: positiveId( item.id ),
		display_name: text( item.display_name ),
		username: text( item.username ),
		external_student_id: text( item.external_student_id ),
		joined_at: nullableText( item.joined_at ),
		setup_expires_at: nullableText( item.setup_expires_at ),
	};
}

function parseImportRow( value: unknown ): ImportRow {
	const item = record( value );
	const action = text( item.action );
	if ( ! ACTIONS.includes( action ) ) {
		throw invalidResponse();
	}
	return {
		line: positiveId( item.line ),
		role: text( item.role ),
		name: text( item.name ),
		email: text( item.email ),
		external_student_id: text( item.external_student_id ),
		action: action as ImportRow[ 'action' ],
		error: nullableText( item.error ),
		user_id: nullableId( item.user_id ),
		username: nullableText( item.username ),
		url: null === item.url ? null : link( item.url ),
	};
}

/**
 * Parse an import report.
 *
 * @param value Parsed JSON.
 * @return The report.
 */
export function parseImportReport( value: unknown ): ImportReport {
	const item = record( value );
	const counts = record( item.counts );
	if (
		'boolean' !== typeof item.dry_run ||
		! Array.isArray( item.rows ) ||
		! Array.isArray( item.ignored )
	) {
		throw invalidResponse();
	}
	const review = nullableText( item.review );
	if ( item.dry_run && ( ! review || ! /^[a-f0-9]{64}$/.test( review ) ) ) {
		throw invalidResponse();
	}
	return {
		dry_run: item.dry_run,
		review,
		counts: Object.fromEntries(
			Object.entries( counts ).map( ( [ key, total ] ) => [
				key,
				count( total ),
			] )
		),
		ignored: item.ignored.map( text ),
		rows: item.rows.map( parseImportRow ),
	};
}

/**
 * Translated roster role.
 *
 * @param role Role id.
 * @return Label.
 */
export function inviteRoleLabel( role: string ): string {
	switch ( role ) {
		case 'school_admin':
			return __( 'School administrator', 'ohmylms' );
		case 'teacher':
			return __( 'Teacher', 'ohmylms' );
		case 'student':
			return __( 'Student', 'ohmylms' );
		case 'guardian':
			return __( 'Parent', 'ohmylms' );
		case 'activation':
			return __( 'Password setup', 'ohmylms' );
		default:
			return role;
	}
}

/**
 * Translated invitation status.
 *
 * @param status Status.
 * @return Label.
 */
export function invitationStatusLabel( status: InvitationStatus ): string {
	return {
		pending: __( 'Waiting', 'ohmylms' ),
		accepted: __( 'Accepted', 'ohmylms' ),
		revoked: __( 'Revoked', 'ohmylms' ),
		expired: __( 'Expired', 'ohmylms' ),
	}[ status ];
}

/**
 * Translated import action.
 *
 * @param action Planned or applied action.
 * @return Label.
 */
export function importActionLabel( action: ImportRow[ 'action' ] ): string {
	return {
		create_student: __( 'Create managed student', 'ohmylms' ),
		invite: __( 'Send invitation', 'ohmylms' ),
		existing: __( 'Already on the roster', 'ohmylms' ),
		pending: __( 'Invitation already waiting', 'ohmylms' ),
		error: __( 'Not imported', 'ohmylms' ),
		created: __( 'Created', 'ohmylms' ),
		invited: __( 'Invited', 'ohmylms' ),
	}[ action ];
}

/**
 * Check a CSV before it is sent: size and a header with a name column.
 *
 * @param csv CSV text.
 * @return An error message, or null when it can be reviewed.
 */
export function csvProblem( csv: string ): string | null {
	if ( ! csv.trim() ) {
		return __( 'Paste CSV text or choose a file.', 'ohmylms' );
	}
	if ( new TextEncoder().encode( csv ).length > MAX_CSV_BYTES ) {
		return __( 'The CSV must be smaller than 100 KB.', 'ohmylms' );
	}
	const header = ( csv.replace( /^\uFEFF/, '' ).split( /\r?\n/ )[ 0 ] ?? '' )
		.split( ',' )
		.map( ( cell ) => cell.trim().toLowerCase() );
	if ( ! header.includes( 'name' ) ) {
		return __(
			'The first row must be a header with a name column.',
			'ohmylms'
		);
	}
	return null;
}

const school = ( id: number ) =>
	`/ohmylms/v1/account-hub/schools/${ positiveId( id ) }`;

/** Rows per page in the roster lists. */
export const ROSTER_PER_PAGE = 20;

/**
 * List a school's invitations, newest first.
 *
 * @param schoolId School id.
 * @param page     Page number, from 1.
 * @param search   Email search.
 * @param all      Include accepted, revoked and expired invitations.
 * @param signal   Cancels the request.
 * @return Validated invitations and totals.
 */
export function listInvitations(
	schoolId: number,
	page: number,
	search: string,
	all: boolean,
	signal?: AbortSignal
) {
	return getCollection(
		`${ school( schoolId ) }/invitations`,
		parseRosterInvitation,
		{
			signal,
			query: {
				page,
				per_page: ROSTER_PER_PAGE,
				search,
				status: all ? 'all' : 'pending',
			},
		}
	);
}

/**
 * Create a roster invitation. The link is returned once.
 *
 * @param schoolId School id.
 * @param input    Role, email and whether to email it.
 * @param signal   Cancels the request.
 * @return The link.
 */
export async function createInvitation(
	schoolId: number,
	input: InvitationInput,
	signal?: AbortSignal
) {
	return parseCreatedLink(
		await post< unknown >(
			`${ school( schoolId ) }/invitations`,
			{ ...input, email: input.email.trim() },
			{ signal }
		)
	);
}

/**
 * Revoke an open invitation.
 *
 * @param schoolId     School id.
 * @param invitationId Invitation id.
 * @param signal       Cancels the request.
 */
export async function revokeRosterInvitation(
	schoolId: number,
	invitationId: number,
	signal?: AbortSignal
) {
	confirmed(
		await del< unknown >(
			`${ school( schoolId ) }/invitations/${ positiveId(
				invitationId
			) }`,
			{ signal }
		),
		'revoked'
	);
}

/**
 * List the school-managed student accounts.
 *
 * @param schoolId School id.
 * @param page     Page number, from 1.
 * @param search   Name or school student ID.
 * @param signal   Cancels the request.
 * @return Validated students and totals.
 */
export function listManagedStudents(
	schoolId: number,
	page: number,
	search: string,
	signal?: AbortSignal
) {
	return getCollection(
		`${ school( schoolId ) }/managed-students`,
		parseManagedStudent,
		{ signal, query: { page, per_page: ROSTER_PER_PAGE, search } }
	);
}

/**
 * Create a managed student (no email).
 *
 * @param schoolId School id.
 * @param input    Name and school student ID.
 * @param signal   Cancels the request.
 * @return The student with their sign-in name.
 */
export async function createManagedStudent(
	schoolId: number,
	input: ManagedStudentInput,
	signal?: AbortSignal
) {
	return parseManagedStudent(
		await post< unknown >(
			`${ school( schoolId ) }/managed-students`,
			{
				name: input.name.trim(),
				external_student_id: input.external_student_id.trim(),
			},
			{ signal }
		)
	);
}

/**
 * Replace a managed student's password-setup link.
 *
 * @param schoolId  School id.
 * @param studentId Student user id.
 * @param signal    Cancels the request.
 * @return The new link.
 */
export async function createSetupLink(
	schoolId: number,
	studentId: number,
	signal?: AbortSignal
) {
	return parseCreatedLink(
		await post< unknown >(
			`${ school( schoolId ) }/managed-students/${ positiveId(
				studentId
			) }/setup-link`,
			undefined,
			{ signal }
		)
	);
}

/**
 * Review a roster CSV without writing.
 *
 * @param schoolId School id.
 * @param csv      CSV text.
 * @param signal   Cancels the request.
 * @return The planned rows and the review token.
 */
export async function reviewRosterImport(
	schoolId: number,
	csv: string,
	signal?: AbortSignal
) {
	return parseImportReport(
		await post< unknown >(
			`${ school( schoolId ) }/roster-import`,
			{ csv, dry_run: true },
			{ signal }
		)
	);
}

/**
 * Apply a reviewed roster CSV. A changed roster or file answers 409 `ohmylms_account_hub_review_stale`.
 *
 * @param schoolId  School id.
 * @param csv       The reviewed CSV text.
 * @param review    Review token.
 * @param sendEmail Email the invitations.
 * @param signal    Cancels the request.
 * @return The applied rows.
 */
export async function applyRosterImport(
	schoolId: number,
	csv: string,
	review: string,
	sendEmail: boolean,
	signal?: AbortSignal
) {
	return parseImportReport(
		await post< unknown >(
			`${ school( schoolId ) }/roster-import`,
			{ csv, dry_run: false, review, send_email: sendEmail },
			{ signal }
		)
	);
}
