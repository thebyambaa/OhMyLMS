import { ApiError, get, post, put } from '../../lib/api';
import {
	invalid,
	positiveId,
	record,
	text,
	type Row,
} from '../../lib/authoring-validation';

const BASE = '/ohmylms/v1';
const WORKSPACE = `${ BASE }/skill-workspace`;
const AI = `${ BASE }/syllabus-comparison/ai`;

/** Sections of the skill workspace, as `?tab=` values of the comparison screen. */
export const WORKSPACE_SECTIONS = [
	'mappings',
	'ai',
	'content',
	'placement',
	'quizzes',
	'provenance',
] as const;
export type WorkspaceSection = ( typeof WORKSPACE_SECTIONS )[ number ];

export const CONTENT_KINDS = [
	'lesson',
	'quiz',
	'assignment',
	'course',
	'question',
] as const;
export type ContentKind = ( typeof CONTENT_KINDS )[ number ];

export const REVIEW_STATES = [ 'pending', 'rejected', 'approved' ] as const;
export type ReviewState = ( typeof REVIEW_STATES )[ number ];

/** Most skills placed in one batch (fixed by the server). */
export const MAX_PLACEMENT = 50;

function count( value: unknown ): number {
	return Number.isSafeInteger( value ) && Number( value ) >= 0
		? Number( value )
		: invalid();
}
function list( value: unknown ): unknown[] {
	return Array.isArray( value ) ? value : invalid();
}
function bool( value: unknown ): boolean {
	return typeof value === 'boolean' ? value : invalid();
}
function confidence( value: unknown ): number | null {
	if ( null === value || undefined === value ) {
		return null;
	}
	return typeof value === 'number' && value >= 0 && value <= 1
		? value
		: invalid();
}
function sha( value: unknown ): string {
	const found = text( value );
	return /^[a-f0-9]{64}$/.test( found ) ? found : invalid();
}
function optionalText( value: unknown ): string {
	return undefined === value || null === value ? '' : text( value );
}

export interface SkillRef {
	id: number;
	name: string;
	code: string;
}
function skillRef( value: unknown ): SkillRef {
	const row = record( value );
	return {
		id: positiveId( row.id ),
		name: text( row.name ),
		code: optionalText( row.code ),
	};
}
export interface PostRef {
	id: number;
	title: string;
	status: string;
}
function postRef( value: unknown ): PostRef {
	const row = record( value );
	return {
		id: positiveId( row.id ),
		title: text( row.title ),
		status: text( row.status ),
	};
}
function pagedPosts( value: unknown ) {
	const row = record( value );
	return {
		items: list( row.items ).map( postRef ),
		total: count( row.total ),
	};
}
export interface UserRef {
	id: number;
	name: string;
}
function userRef( value: unknown ): UserRef {
	const row = record( value );
	return { id: count( row.id ), name: text( row.name ) };
}

/* ---------- Combined mappings ---------- */

export interface MappingRow {
	id: number;
	specific: SkillRef;
	shared: SkillRef;
	relation: string;
	note: string;
	source: string;
	confidence: number | null;
	groupUuid: string;
	revision: string;
	syllabuses: { id: number; name: string }[];
}
export interface CombinedView {
	skill: {
		id: number;
		name: string;
		code: string;
		shared: boolean;
		subject: string;
		source: string;
		review: string;
	};
	placements: {
		syllabusId: number;
		syllabus: string;
		item: string;
		group: string;
	}[];
	mappings: MappingRow[];
	groups: {
		uuid: string;
		sourceId: number;
		source: string;
		shared: SkillRef;
		members: SkillRef[];
	}[];
	courses: { items: ( PostRef & { usage: string } )[]; total: number };
	content: Record<
		'lesson' | 'quiz' | 'assignment',
		{ items: PostRef[]; total: number }
	>;
	questions: { items: ( PostRef & { role: string } )[]; total: number };
}

export function parseCombined( value: unknown ): CombinedView {
	const row = record( value );
	const skill = record( row.skill );
	const provenance = record( skill.provenance );
	const content = record( row.content );
	const courses = record( row.courses );
	const questions = record( row.questions );
	return {
		skill: {
			id: positiveId( skill.id ),
			name: text( skill.name ),
			code: optionalText( skill.code ),
			shared: bool( skill.shared ),
			subject: optionalText( skill.subject ),
			source: text( provenance.source ),
			review: text( provenance.review ),
		},
		placements: list( row.placements ).map( ( raw ) => {
			const place = record( raw );
			return {
				syllabusId: count( place.syllabus_id ),
				syllabus: text( place.syllabus ),
				item: text( place.item ),
				group: text( place.group ),
			};
		} ),
		mappings: list( row.mappings ).map( ( raw ) => {
			const mapping = record( raw );
			return {
				id: positiveId( mapping.id ),
				specific: skillRef( mapping.specific ),
				shared: skillRef( mapping.shared ),
				relation: text( mapping.relation ),
				note: optionalText( mapping.note ),
				source: text( mapping.source ),
				confidence: confidence( mapping.confidence ),
				groupUuid: optionalText( mapping.group_uuid ),
				revision: sha( mapping.revision ),
				syllabuses: list( mapping.syllabuses ).map( ( entry ) => {
					const syllabus = record( entry );
					return {
						id: positiveId( syllabus.id ),
						name: text( syllabus.name ),
					};
				} ),
			};
		} ),
		groups: list( row.groups ).map( ( raw ) => {
			const group = record( raw );
			return {
				uuid: text( group.uuid ),
				sourceId: count( group.source_id ),
				source: text( group.source ),
				shared: skillRef( group.shared ),
				members: list( group.members ).map( skillRef ),
			};
		} ),
		courses: {
			items: list( courses.items ).map( ( raw ) => ( {
				...postRef( raw ),
				usage: text( record( raw ).usage ),
			} ) ),
			total: count( courses.total ),
		},
		content: {
			lesson: pagedPosts( content.lesson ),
			quiz: pagedPosts( content.quiz ),
			assignment: pagedPosts( content.assignment ),
		},
		questions: {
			items: list( questions.items ).map( ( raw ) => ( {
				...postRef( raw ),
				role: text( record( raw ).role ),
			} ) ),
			total: count( questions.total ),
		},
	};
}

export async function readCombined( skill: number, signal?: AbortSignal ) {
	return parseCombined(
		await get< unknown >(
			`${ WORKSPACE }/skills/${ positiveId( skill ) }/mappings`,
			{ signal }
		)
	);
}

/**
 * Remove a whole combined group with the revisions of its pairs.
 * @param uuid
 * @param pairs
 * @param signal
 */
export async function removeCombinedGroup(
	uuid: string,
	pairs: { specific_id: number; expected_revision: string }[],
	signal?: AbortSignal
) {
	await post(
		`${ BASE }/syllabus-comparison/mapping-groups/remove`,
		{ uuid, pairs },
		{ signal }
	);
}

/**
 * Remove one individual mapping with its revision.
 * @param row
 * @param signal
 */
export async function removeMapping( row: MappingRow, signal?: AbortSignal ) {
	await post(
		`${ BASE }/syllabus-comparison/mapping/remove`,
		{
			specific_id: row.specific.id,
			shared_id: row.shared.id,
			expected_revision: row.revision,
		},
		{ signal }
	);
}

/* ---------- AI runs ---------- */

export interface AiRun {
	id: number;
	kind: string;
	syllabusId: number;
	syllabus: string;
	subject: string;
	status: string;
	total: number;
	processed: number;
	suggested: number;
	failures: number;
	error: string;
	model: string;
	pending: number;
	accepted: number;
	rejected: number;
	createdAt: string;
}
export function parseRun( value: unknown ): AiRun {
	const row = record( value );
	return {
		id: positiveId( row.id ),
		kind: text( row.kind ),
		syllabusId: count( row.syllabus_id ),
		syllabus: optionalText( row.syllabus ),
		subject: optionalText( row.subject ),
		status: text( row.status ),
		total: count( row.total ),
		processed: count( row.processed ),
		suggested: count( row.suggested ),
		failures: count( row.failures ),
		error: optionalText( row.error ),
		model: optionalText( row.model ),
		pending: count( row.pending ),
		accepted: count( row.accepted ),
		rejected: count( row.rejected ),
		createdAt: text( row.created_at ),
	};
}
export const isActiveRun = ( run: AiRun ) =>
	'queued' === run.status || 'running' === run.status;

export async function listRuns( syllabus: number, signal?: AbortSignal ) {
	const query = syllabus > 0 ? { syllabus_id: syllabus } : {};
	return list(
		await get< unknown >( `${ AI }/runs`, { query, signal } )
	).map( parseRun );
}
export async function startRun(
	input:
		| { kind: 'syllabus'; syllabus_id: number; include_mapped: boolean }
		| { kind: 'placement' | 'links'; subject: string },
	signal?: AbortSignal
) {
	return parseRun(
		await post< unknown >( `${ AI }/runs`, input, { signal } )
	);
}
export async function runAction(
	id: number,
	action: 'step' | 'cancel',
	signal?: AbortSignal
) {
	return parseRun(
		await post< unknown >(
			`${ AI }/runs/${ positiveId( id ) }/${ action }`,
			{},
			{ signal }
		)
	);
}
export async function acceptConfident(
	id: number,
	minConfidence: number,
	signal?: AbortSignal
) {
	const row = record(
		await post< unknown >(
			`${ AI }/runs/${ positiveId( id ) }/accept-confident`,
			{ min_confidence: minConfidence },
			{ signal }
		)
	);
	return { accepted: count( row.accepted ), failed: count( row.failed ) };
}

export interface Suggestion {
	id: number;
	runId: number;
	kind: string;
	status: string;
	relation: string;
	confidence: number;
	reason: string;
	objective: SkillRef | null;
	shared: { id: number; name: string } | null;
	proposal: { name: string; status: string } | null;
	placement: { domain: string; gradeFrom: number; gradeTo: number } | null;
	weight: number | null;
}
function nullable< T >( value: unknown, parse: ( raw: unknown ) => T ) {
	return null === value || undefined === value ? null : parse( value );
}
export function parseSuggestion( value: unknown ): Suggestion {
	const row = record( value );
	return {
		id: positiveId( row.id ),
		runId: count( row.run_id ),
		kind: text( row.kind ),
		status: text( row.status ),
		relation: optionalText( row.relation ),
		confidence: confidence( row.confidence ) ?? 0,
		reason: optionalText( row.reason ),
		objective: nullable( row.objective, skillRef ),
		shared: nullable( row.shared ?? row.skill, ( raw ) => {
			const shared = record( raw );
			return { id: positiveId( shared.id ), name: text( shared.name ) };
		} ),
		proposal: nullable( row.proposal, ( raw ) => {
			const proposal = record( raw );
			return {
				name: optionalText( proposal.name ),
				status: optionalText( proposal.status ),
			};
		} ),
		placement: nullable( row.placement, ( raw ) => {
			const placement = record( raw );
			return {
				domain: optionalText( placement.domain ),
				gradeFrom: count( placement.grade_from ?? 0 ),
				gradeTo: count( placement.grade_to ?? 0 ),
			};
		} ),
		weight:
			null === row.weight || undefined === row.weight
				? null
				: Number( row.weight ),
	};
}
export async function listSuggestions(
	run: number,
	status: 'pending' | 'accepted' | 'rejected' | 'all',
	page: number,
	signal?: AbortSignal
) {
	const row = record(
		await get< unknown >( `${ AI }/suggestions`, {
			query: { run_id: run, status, page },
			signal,
		} )
	);
	return {
		items: list( row.items ).map( parseSuggestion ),
		total: count( row.total ),
	};
}
export type ReviewOutcome =
	| { ok: true; suggestion: Suggestion }
	| { ok: false; id: number; error: string };
export async function reviewSuggestions(
	ids: number[],
	action: 'accept' | 'reject',
	overrides: Record< number, { relation?: string } > = {},
	signal?: AbortSignal
): Promise< ReviewOutcome[] > {
	const row = record(
		await post< unknown >(
			`${ AI }/suggestions/review`,
			{
				ids,
				action,
				...( Object.keys( overrides ).length ? { overrides } : {} ),
			},
			{ signal }
		)
	);
	const results = row.results;
	if ( Array.isArray( results ) && ! results.length ) {
		return [];
	}
	return Object.values( record( results ) ).map( ( raw ) => {
		const result = record( raw );
		return typeof result.error === 'string'
			? {
					ok: false as const,
					id: positiveId( result.id ),
					error: result.error,
				}
			: { ok: true as const, suggestion: parseSuggestion( raw ) };
	} );
}

/* ---------- Content links ---------- */

export interface LinkedSkill {
	id: number;
	name: string;
	code: string;
	shared: boolean;
	role: 'linked' | 'primary' | 'supporting';
	archived: boolean;
	syllabuses: string[];
}
export interface ContentLinks {
	content: PostRef & { kind: ContentKind };
	skills: LinkedSkill[];
	revision: string;
}
function contentKind( value: unknown ): ContentKind {
	return CONTENT_KINDS.find( ( kind ) => kind === value ) ?? invalid();
}
export function parseContentLinks( value: unknown ): ContentLinks {
	const row = record( value );
	const content = record( row.content );
	return {
		content: { ...postRef( content ), kind: contentKind( content.kind ) },
		skills: list( row.skills ).map( ( raw ) => {
			const skill = record( raw );
			const role = text( skill.role );
			if ( ! [ 'linked', 'primary', 'supporting' ].includes( role ) ) {
				return invalid();
			}
			return {
				id: positiveId( skill.id ),
				name: text( skill.name ),
				code: optionalText( skill.code ),
				shared: bool( skill.shared ),
				role: role as LinkedSkill[ 'role' ],
				archived: bool( skill.archived ),
				syllabuses: list( skill.syllabuses ).map( text ),
			};
		} ),
		revision: sha( row.revision ),
	};
}
export async function searchContent(
	kind: ContentKind,
	search: string,
	page: number,
	signal?: AbortSignal
) {
	const row = record(
		await get< unknown >( `${ WORKSPACE }/content`, {
			query: { kind, search, page },
			signal,
		} )
	);
	return {
		items: list( row.items ).map( ( raw ) => ( {
			...postRef( raw ),
			editable: bool( record( raw ).editable ),
		} ) ),
		total: count( row.total ),
		pages: count( row.pages ),
	};
}
export async function readContentLinks(
	kind: ContentKind,
	id: number,
	signal?: AbortSignal
) {
	return parseContentLinks(
		await get< unknown >(
			`${ WORKSPACE }/content/${ kind }/${ positiveId( id ) }`,
			{ signal }
		)
	);
}
export async function updateContentLinks(
	current: ContentLinks,
	add: number[],
	remove: number[],
	signal?: AbortSignal
) {
	return parseContentLinks(
		await put< unknown >(
			`${ WORKSPACE }/content/${ current.content.kind }/${ current.content.id }`,
			{ add, remove, expected_revision: current.revision },
			{ signal }
		)
	);
}

/* ---------- Domain placement ---------- */

export interface PlacementRow {
	id: number;
	name: string;
	subject: string;
	topic: string;
	domain: string;
	gradeFrom: number;
	gradeTo: number;
	revision: string;
}
export interface PlacementPage {
	items: PlacementRow[];
	total: number;
	domains: { id: string; label: string }[];
	levels: { value: number; label: string }[];
}
export function parsePlacement( value: unknown ): PlacementPage {
	const row = record( value );
	const domains = row.domains;
	return {
		items: list( row.items ).map( ( raw ) => {
			const skill = record( raw );
			return {
				id: positiveId( skill.id ),
				name: text( skill.name ),
				subject: optionalText( skill.subject ),
				topic: optionalText( skill.topic ),
				domain: optionalText( skill.domain ),
				gradeFrom: count( skill.grade_from ),
				gradeTo: count( skill.grade_to ),
				revision: sha( skill.revision ),
			};
		} ),
		total: count( row.total ),
		domains:
			Array.isArray( domains ) && ! domains.length
				? []
				: Object.entries( record( domains ) ).map(
						( [ id, label ] ) => ( { id, label: text( label ) } )
					),
		levels: list( row.levels ).map( ( raw ) => {
			const level = record( raw );
			return {
				value: positiveId( level.value ),
				label: text( level.label ),
			};
		} ),
	};
}
export async function readPlacement(
	query: {
		subject: string;
		search: string;
		domain: string;
		unplaced: boolean;
		page: number;
	},
	signal?: AbortSignal
) {
	return parsePlacement(
		await get< unknown >( `${ WORKSPACE }/placement`, {
			query: { ...query, unplaced: query.unplaced ? 1 : 0 },
			signal,
		} )
	);
}
/**
 * A placement problem a reviewer can fix before saving, or null.
 * @param row
 */
export function placementProblem(
	row: Pick< PlacementRow, 'domain' | 'gradeFrom' | 'gradeTo' >
): string | null {
	if ( row.gradeTo && ! row.gradeFrom ) {
		return 'first';
	}
	if ( row.gradeFrom && row.gradeTo && row.gradeTo < row.gradeFrom ) {
		return 'order';
	}
	return null;
}
export async function savePlacement(
	rows: PlacementRow[],
	signal?: AbortSignal
) {
	if ( ! rows.length || rows.length > MAX_PLACEMENT ) {
		return invalid();
	}
	const row = record(
		await put< unknown >(
			`${ WORKSPACE }/placement`,
			{
				items: rows.map( ( item ) => ( {
					id: item.id,
					domain: item.domain,
					grade_from: item.gradeFrom,
					grade_to: item.gradeTo || item.gradeFrom,
					expected_revision: item.revision,
				} ) ),
			},
			{ signal }
		)
	);
	return list( row.items ).length;
}

/* ---------- Skill-owned quizzes ---------- */

export interface OwnedQuiz extends PostRef {
	style: string;
	connected: boolean;
	drawn: number;
	skills: number;
	editable: boolean;
}
export interface SkillQuizzes {
	skill: { id: number; name: string };
	available: number;
	quizzes: OwnedQuiz[];
	created?: { id: number; title: string };
}
export function parseSkillQuizzes( value: unknown ): SkillQuizzes {
	const row = record( value );
	const skill = record( row.skill );
	return {
		skill: { id: positiveId( skill.id ), name: text( skill.name ) },
		available: count( row.available ),
		quizzes: list( row.quizzes ).map( ( raw ) => {
			const quiz = record( raw );
			return {
				...postRef( raw ),
				style: text( quiz.style ),
				connected: bool( quiz.connected ),
				drawn: count( quiz.drawn ),
				skills: count( quiz.skills ),
				editable: bool( quiz.editable ),
			};
		} ),
		...( row.created
			? {
					created: {
						id: positiveId( record( row.created ).id ),
						title: text( record( row.created ).title ),
					},
				}
			: {} ),
	};
}
export async function readSkillQuizzes( skill: number, signal?: AbortSignal ) {
	return parseSkillQuizzes(
		await get< unknown >(
			`${ WORKSPACE }/skills/${ positiveId( skill ) }/quizzes`,
			{ signal }
		)
	);
}
export async function linkQuiz(
	skill: number,
	quiz: number,
	linked: boolean,
	signal?: AbortSignal
) {
	return parseSkillQuizzes(
		await put< unknown >(
			`${ WORKSPACE }/skills/${ positiveId( skill ) }/quizzes/${ positiveId(
				quiz
			) }`,
			{ linked },
			{ signal }
		)
	);
}
export async function generateQuiz(
	skill: number,
	input: { title: string; per_skill: number; time_limit: number },
	signal?: AbortSignal
) {
	return parseSkillQuizzes(
		await post< unknown >(
			`${ WORKSPACE }/skills/${ positiveId( skill ) }/quizzes`,
			input,
			{ signal }
		)
	);
}

/* ---------- Provenance review ---------- */

export interface Provenance {
	source: string;
	ref: string;
	review: ReviewState;
	status: string;
	model: string;
	confidence: number | null;
	createdBy: UserRef;
	createdAt: string;
	reviewedBy: UserRef;
}
export interface AuditEntry {
	action: string;
	previous: string;
	note: string;
	by: UserRef;
	at: string;
}
export interface ProvenanceRow {
	id: number;
	name: string;
	code: string;
	shared: boolean;
	subject: string;
	provenance: Provenance;
	lastAudit: AuditEntry | null;
}
function reviewState( value: unknown ): ReviewState {
	return REVIEW_STATES.find( ( state ) => state === value ) ?? invalid();
}
function auditEntry( value: unknown ): AuditEntry {
	const row = record( value );
	return {
		action: text( row.action ),
		previous: optionalText( row.previous ),
		note: optionalText( row.note ),
		by: userRef( row.by ),
		at: text( row.at ),
	};
}
function provenanceRow( value: unknown ): ProvenanceRow {
	const row = record( value );
	const origin = record( row.provenance );
	return {
		id: positiveId( row.id ),
		name: text( row.name ),
		code: optionalText( row.code ),
		shared: bool( row.shared ),
		subject: optionalText( row.subject ),
		provenance: {
			source: text( origin.source ),
			ref: optionalText( origin.ref ),
			review: reviewState( origin.review ),
			status: text( origin.status ),
			model: optionalText( origin.model ),
			confidence: confidence( origin.confidence ),
			createdBy: userRef( origin.created_by ),
			createdAt: optionalText( origin.created_at ),
			reviewedBy: userRef( origin.reviewed_by ),
		},
		lastAudit: nullable( row.last_audit, auditEntry ),
	};
}
export function parseQueue( value: unknown ) {
	const row = record( value );
	const counts = record( row.counts );
	return {
		items: list( row.items ).map( provenanceRow ),
		total: count( row.total ),
		counts: {
			pending: count( counts.pending ),
			rejected: count( counts.rejected ),
		},
		sources: list( row.sources ).map( text ),
	};
}
export type ProvenanceQueue = ReturnType< typeof parseQueue >;
export async function readQueue(
	query: {
		review: ReviewState | 'all';
		source: string;
		search: string;
		page: number;
	},
	signal?: AbortSignal
) {
	return parseQueue(
		await get< unknown >( `${ WORKSPACE }/provenance`, { query, signal } )
	);
}
export interface ProvenanceDetail extends ProvenanceRow {
	audit: AuditEntry[];
	mappings: {
		id: number;
		direction: string;
		other: { id: number; name: string };
		relation: string;
		source: string;
		confidence: number | null;
		createdBy: UserRef;
	}[];
}
export async function readProvenance(
	id: number,
	signal?: AbortSignal
): Promise< ProvenanceDetail > {
	const value = await get< unknown >(
		`${ WORKSPACE }/provenance/${ positiveId( id ) }`,
		{ signal }
	);
	const row = record( value );
	return {
		...provenanceRow( value ),
		audit: list( row.audit ).map( auditEntry ),
		mappings: list( row.mappings ).map( ( raw ) => {
			const mapping = record( raw );
			const other = record( mapping.other );
			return {
				id: positiveId( mapping.id ),
				direction: text( mapping.direction ),
				other: { id: positiveId( other.id ), name: text( other.name ) },
				relation: text( mapping.relation ),
				source: text( mapping.source ),
				confidence: confidence( mapping.confidence ),
				createdBy: userRef( mapping.created_by ),
			};
		} ),
	};
}
export interface DecisionResult {
	id: number;
	review?: ReviewState;
	error?: string;
	stale: boolean;
}
export async function reviewSkills(
	rows: Pick< ProvenanceRow, 'id' | 'provenance' >[],
	review: 'approved' | 'rejected',
	note: string,
	signal?: AbortSignal
): Promise< DecisionResult[] > {
	const row = record(
		await post< unknown >(
			`${ WORKSPACE }/provenance/review`,
			{
				items: rows.map( ( item ) => ( {
					id: item.id,
					expected_review: item.provenance.review,
				} ) ),
				review,
				note,
			},
			{ signal }
		)
	);
	return Object.entries( record( row.results ) ).map( ( [ id, raw ] ) => {
		const result: Row = record( raw );
		return {
			id: positiveId( Number( id ) ),
			...( typeof result.review === 'string'
				? { review: reviewState( result.review ) }
				: {} ),
			...( typeof result.error === 'string'
				? { error: result.error }
				: {} ),
			stale: result.code === 'ohmylms_skill_review_stale',
		};
	} );
}

/**
 * True for a refusal because something changed since it was read.
 * @param failure
 */
export function isConflict( failure: unknown ): boolean {
	return failure instanceof ApiError && 409 === failure.status;
}
