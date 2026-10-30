import { __, sprintf } from '@wordpress/i18n';
import { ApiError } from '../../lib/api';
import {
	LEADERBOARD_MODES,
	METRICS,
	OPERATORS,
	TAB_IDS,
	type AwardSource,
	type Badge,
	type BadgeDraft,
	type Choice,
	type Criterion,
	type CriterionDraft,
	type DraftMap,
	type FailureView,
	type FieldErrors,
	type GamificationOptions,
	type LeaderboardDraft,
	type Level,
	type LevelDraft,
	type Limits,
	type MediaImage,
	type Metric,
	type PointRule,
	type PointRuleDraft,
	type PointsDraft,
	type SectionId,
	type SectionMap,
	type TabId,
} from './types';

/** Image types a badge may use; the server checks the attachment again. */
export const IMAGE_MIME_TYPES: readonly string[] = [
	'image/jpeg',
	'image/png',
	'image/gif',
	'image/webp',
];

const HEX_COLOR = /^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/;
const REVISION = /^[a-f0-9]{16}$/;

export const DEFAULT_LIMITS: Limits = {
	max_points: 100000,
	max_criterion_value: 1000000,
	max_levels: 50,
	max_badges: 100,
	max_criteria: 10,
};

/* ------------------------------------------------------------------ parsing */

export function invalidResponse(): ApiError {
	return new ApiError( {
		code: 'invalid_response',
		status: 502,
		message: __(
			'The server returned invalid gamification data.',
			'ohmylms'
		),
	} );
}

function record( value: unknown ): Record< string, unknown > {
	if ( ! value || 'object' !== typeof value || Array.isArray( value ) ) {
		throw invalidResponse();
	}
	return value as Record< string, unknown >;
}

function list( value: unknown ): unknown[] {
	if ( ! Array.isArray( value ) ) {
		throw invalidResponse();
	}
	return value;
}

function text( value: unknown ): string {
	if ( 'string' !== typeof value ) {
		throw invalidResponse();
	}
	return value;
}

function integer( value: unknown ): number {
	if ( 'number' !== typeof value || ! Number.isInteger( value ) ) {
		throw invalidResponse();
	}
	return value;
}

function boolean( value: unknown ): boolean {
	if ( 'boolean' !== typeof value ) {
		throw invalidResponse();
	}
	return value;
}

function oneOf< T extends string >( value: unknown, allowed: readonly T[] ): T {
	const found = allowed.find( ( item ) => item === value );
	if ( undefined === found ) {
		throw invalidResponse();
	}
	return found;
}

function parseRule( value: unknown ): PointRule {
	const rule = record( value );
	const email = record( rule.email );
	const threshold = rule.threshold;
	return {
		event: text( rule.event ),
		label: text( rule.label ),
		description: text( rule.description ),
		supports_threshold: boolean( rule.supports_threshold ),
		enabled: boolean( rule.enabled ),
		points: integer( rule.points ),
		threshold: null === threshold ? null : integer( threshold ),
		email: {
			enabled: boolean( email.enabled ),
			subject: text( email.subject ),
			body: text( email.body ),
		},
	};
}

/**
 * Validate one section of `GET /gamification/settings/<section>`.
 *
 * @param section Section id.
 * @param raw     Decoded JSON.
 * @return The typed section.
 */
export function parseSection< S extends SectionId >(
	section: S,
	raw: unknown
): SectionMap[ S ] {
	const data = record( raw );
	const kind: SectionId = section;
	let result: SectionMap[ SectionId ];
	if ( 'points' === kind ) {
		result = {
			enabled: boolean( data.enabled ),
			rules: list( data.rules ).map( parseRule ),
		};
	} else if ( 'leaderboard' === kind ) {
		result = {
			enabled: boolean( data.enabled ),
			mode: oneOf( data.mode, LEADERBOARD_MODES ),
			threshold: integer( data.threshold ),
			limit: integer( data.limit ),
		};
	} else if ( 'rewards' === kind ) {
		const revision = text( data.revision );
		if ( ! REVISION.test( revision ) ) {
			throw invalidResponse();
		}
		result = {
			enabled: boolean( data.enabled ),
			purchase_with_points: boolean( data.purchase_with_points ),
			revision,
		};
	} else {
		result = { enabled: boolean( data.enabled ) };
	}
	return result as SectionMap[ S ];
}

export function parseLevel( value: unknown ): Level {
	const level = record( value );
	return {
		slug: text( level.slug ),
		name: text( level.name ),
		description: text( level.description ),
		color: text( level.color ),
		text_color: text( level.text_color ),
		points: integer( level.points ),
		earned_count: integer( level.earned_count ),
	};
}

function parseCriterion( value: unknown ): Criterion {
	const criterion = record( value );
	return {
		metric: oneOf( criterion.metric, METRICS ),
		operator: oneOf( criterion.operator, OPERATORS ),
		value: integer( criterion.value ),
	};
}

export function parseBadge( value: unknown ): Badge {
	const badge = record( value );
	const url = badge.image_url;
	return {
		slug: text( badge.slug ),
		name: text( badge.name ),
		description: text( badge.description ),
		image_id: integer( badge.image_id ),
		image_url: null === url ? null : text( url ),
		color: text( badge.color ),
		award_source: oneOf< AwardSource >( badge.award_source, [
			'criteria',
			'streak',
		] ),
		criteria: list( badge.criteria ).map( parseCriterion ),
		source: oneOf( badge.source, [ 'custom', 'streak' ] as const ),
		earned_count: integer( badge.earned_count ),
	};
}

function parseChoices< T extends string >(
	value: unknown,
	allowed: readonly T[]
): Choice< T >[] {
	return list( value ).map( ( item ) => {
		const choice = record( item );
		return {
			value: oneOf( choice.value, allowed ),
			label: text( choice.label ),
		};
	} );
}

export function parseOptions( raw: unknown ): GamificationOptions {
	const data = record( raw );
	const limits = record( data.limits );
	return {
		integration_enabled: boolean( data.integration_enabled ),
		metrics: parseChoices( data.metrics, METRICS ),
		operators: parseChoices( data.operators, OPERATORS ),
		leaderboard_modes: parseChoices(
			data.leaderboard_modes,
			LEADERBOARD_MODES
		),
		limits: {
			max_points: integer( limits.max_points ),
			max_criterion_value: integer( limits.max_criterion_value ),
			max_levels: integer( limits.max_levels ),
			max_badges: integer( limits.max_badges ),
			max_criteria: integer( limits.max_criteria ),
		},
	};
}

/**
 * A WordPress media item restricted to the image types a badge accepts.
 *
 * @param value Decoded `wp/v2/media` item.
 * @return The image.
 */
export function parseMedia( value: unknown ): MediaImage {
	const item = record( value );
	if ( ! IMAGE_MIME_TYPES.includes( text( item.mime_type ) ) ) {
		throw invalidResponse();
	}
	const url = text( item.source_url );
	let thumbnail = url;
	const details = item.media_details;
	const sizes =
		details && 'object' === typeof details
			? ( details as Record< string, unknown > ).sizes
			: undefined;
	if ( sizes && 'object' === typeof sizes ) {
		const small = ( sizes as Record< string, unknown > ).thumbnail;
		if ( small && 'object' === typeof small ) {
			const source = ( small as Record< string, unknown > ).source_url;
			if ( 'string' === typeof source && source ) {
				thumbnail = source;
			}
		}
	}
	const title = item.title ? record( item.title ).rendered : '';
	return {
		id: integer( item.id ),
		url,
		thumbnail_url: thumbnail,
		title: 'string' === typeof title ? title : '',
	};
}

/* ------------------------------------------------------------------ drafts */

export function toDraft< S extends SectionId >(
	settings: SectionMap[ S ]
): DraftMap[ S ] {
	return JSON.parse( JSON.stringify( settings ) ) as DraftMap[ S ];
}

/**
 * Request body for a section: only fields the server accepts (no display-only fields).
 *
 * @param section Section id.
 * @param draft   Draft that passed validation.
 * @return JSON body.
 */
export function buildPayload< S extends SectionId >(
	section: S,
	draft: DraftMap[ S ]
): Record< string, unknown > {
	if ( 'points' === section ) {
		const points = draft as PointsDraft;
		return {
			enabled: points.enabled,
			rules: points.rules.map( ( rule ) => ( {
				event: rule.event,
				enabled: rule.enabled,
				points: rule.points,
				...( rule.supports_threshold
					? { threshold: rule.threshold }
					: {} ),
				email: rule.email,
			} ) ),
		};
	}
	return { ...( draft as unknown as Record< string, unknown > ) };
}

/**
 * True when two drafts differ. Both are plain JSON.
 *
 * @param saved Saved values.
 * @param draft Edited values.
 */
export function isDirty( saved: unknown, draft: unknown ): boolean {
	return JSON.stringify( saved ) !== JSON.stringify( draft );
}

export function levelToDraft( level?: Level ): LevelDraft {
	return {
		name: level?.name ?? '',
		description: level?.description ?? '',
		color: level?.color ?? '#6e42d3',
		text_color: level?.text_color ?? '#ffffff',
		points: level?.points ?? null,
	};
}

export function newCriterion(): CriterionDraft {
	return { metric: 'points', operator: '>=', value: null };
}

export function badgeToDraft( badge?: Badge ): BadgeDraft {
	return {
		name: badge?.name ?? '',
		description: badge?.description ?? '',
		image_id: badge?.image_id ?? 0,
		image_url: badge?.image_url ?? null,
		color: badge?.color ?? '#6e42d3',
		award_source: badge?.award_source ?? 'criteria',
		criteria: badge?.criteria.map( ( c ) => ( { ...c } ) ) ?? [
			newCriterion(),
		],
	};
}

/**
 * Coerce a text field to a number, or `null` while it is empty or not a number.
 *
 * @param value Field value.
 */
export function toNumber( value: string | number | undefined ): number | null {
	if ( undefined === value || '' === value ) {
		return null;
	}
	const number = Number( value );
	return Number.isNaN( number ) ? null : number;
}

/* -------------------------------------------------------------- validation */

function intInRange( value: number | null, min: number, max: number ): boolean {
	return (
		null !== value &&
		Number.isInteger( value ) &&
		value >= min &&
		value <= max
	);
}

function rangeMessage( min: number, max: number ): string {
	return sprintf(
		/* translators: 1: smallest allowed number, 2: largest allowed number. */
		__( 'Enter a whole number from %1$d to %2$d.', 'ohmylms' ),
		min,
		max
	);
}

function validateRule(
	rule: PointRuleDraft,
	limits: Limits,
	errors: FieldErrors
): void {
	const key = `rules.${ rule.event }`;
	if ( ! intInRange( rule.points, 0, limits.max_points ) ) {
		errors[ `${ key }.points` ] = rangeMessage( 0, limits.max_points );
	}
	if ( rule.supports_threshold && ! intInRange( rule.threshold, 0, 100 ) ) {
		errors[ `${ key }.threshold` ] = rangeMessage( 0, 100 );
	}
	if ( ! rule.email.enabled ) {
		return;
	}
	if ( '' === rule.email.subject.trim() ) {
		errors[ `${ key }.email.subject` ] = __(
			'Enter the email subject.',
			'ohmylms'
		);
	} else if ( rule.email.subject.length > 150 ) {
		errors[ `${ key }.email.subject` ] = __(
			'Use at most 150 characters.',
			'ohmylms'
		);
	}
	if ( '' === rule.email.body.replace( /<[^>]*>/g, '' ).trim() ) {
		errors[ `${ key }.email.body` ] = __(
			'Enter the email message.',
			'ohmylms'
		);
	} else if ( rule.email.body.length > 5000 ) {
		errors[ `${ key }.email.body` ] = __(
			'Use at most 5000 characters.',
			'ohmylms'
		);
	}
}

export function validatePoints(
	draft: PointsDraft,
	limits: Limits = DEFAULT_LIMITS
): FieldErrors {
	const errors: FieldErrors = {};
	draft.rules.forEach( ( rule ) => validateRule( rule, limits, errors ) );
	return errors;
}

export function validateLeaderboard( draft: LeaderboardDraft ): FieldErrors {
	const errors: FieldErrors = {};
	if ( ! intInRange( draft.threshold, 0, 100 ) ) {
		errors.threshold = rangeMessage( 0, 100 );
	}
	if ( ! intInRange( draft.limit, 1, 100 ) ) {
		errors.limit = rangeMessage( 1, 100 );
	}
	return errors;
}

function validateColor(
	value: string,
	field: string,
	errors: FieldErrors
): void {
	if ( ! HEX_COLOR.test( value ) ) {
		errors[ field ] = __( 'Enter a colour like #6e42d3.', 'ohmylms' );
	}
}

/**
 * Validate a level the way the server does. Thresholds must be strictly increasing, so two
 * levels cannot start at the same number of points.
 *
 * @param draft   Level being edited.
 * @param levels  All levels.
 * @param editing Slug of the level being edited, or `null` when creating.
 * @param limits  Server limits.
 */
export function validateLevel(
	draft: LevelDraft,
	levels: readonly Level[],
	editing: string | null,
	limits: Limits = DEFAULT_LIMITS
): FieldErrors {
	const errors: FieldErrors = {};
	if ( '' === draft.name.trim() ) {
		errors.name = __( 'Enter a name.', 'ohmylms' );
	} else if ( draft.name.length > 60 ) {
		errors.name = __( 'Use at most 60 characters.', 'ohmylms' );
	}
	if ( draft.description.length > 200 ) {
		errors.description = __( 'Use at most 200 characters.', 'ohmylms' );
	}
	validateColor( draft.color, 'color', errors );
	validateColor( draft.text_color, 'text_color', errors );
	if ( ! intInRange( draft.points, 0, limits.max_criterion_value ) ) {
		errors.points = rangeMessage( 0, limits.max_criterion_value );
	} else {
		const clash = levels.find(
			( level ) => level.slug !== editing && level.points === draft.points
		);
		if ( clash ) {
			errors.points = sprintf(
				/* translators: %s: level name. */
				__(
					'Level thresholds must increase. "%s" already starts at this number of points.',
					'ohmylms'
				),
				clash.name
			);
		}
	}
	if ( null === editing && levels.length >= limits.max_levels ) {
		errors.name = sprintf(
			/* translators: %d: number of levels. */
			__( 'You can have at most %d levels.', 'ohmylms' ),
			limits.max_levels
		);
	}
	return errors;
}

export function validateBadge(
	draft: BadgeDraft,
	limits: Limits = DEFAULT_LIMITS
): FieldErrors {
	const errors: FieldErrors = {};
	if ( '' === draft.name.trim() ) {
		errors.name = __( 'Enter a name.', 'ohmylms' );
	} else if ( draft.name.length > 60 ) {
		errors.name = __( 'Use at most 60 characters.', 'ohmylms' );
	}
	if ( draft.description.length > 200 ) {
		errors.description = __( 'Use at most 200 characters.', 'ohmylms' );
	}
	validateColor( draft.color, 'color', errors );
	if ( 'streak' === draft.award_source ) {
		return errors;
	}
	if ( 0 === draft.criteria.length ) {
		errors.criteria = __( 'Add at least one condition.', 'ohmylms' );
	} else if ( draft.criteria.length > limits.max_criteria ) {
		errors.criteria = sprintf(
			/* translators: %d: number of conditions. */
			__( 'Use at most %d conditions.', 'ohmylms' ),
			limits.max_criteria
		);
	}
	draft.criteria.forEach( ( criterion, index ) => {
		if ( ! intInRange( criterion.value, 0, limits.max_criterion_value ) ) {
			errors[ `criteria.${ index }.value` ] = rangeMessage(
				0,
				limits.max_criterion_value
			);
		}
	} );
	return errors;
}

/* ---------------------------------------------------------------- payloads */

export function levelPayload( draft: LevelDraft ): Record< string, unknown > {
	return {
		name: draft.name.trim(),
		description: draft.description,
		color: draft.color,
		text_color: draft.text_color,
		points: draft.points,
	};
}

export function badgePayload( draft: BadgeDraft ): Record< string, unknown > {
	return {
		name: draft.name.trim(),
		description: draft.description,
		image_id: draft.image_id > 0 ? draft.image_id : null,
		color: draft.color,
		award_source: draft.award_source,
		criteria:
			'streak' === draft.award_source
				? []
				: draft.criteria.map( ( criterion ) => ( {
						metric: criterion.metric,
						operator: criterion.operator,
						value: criterion.value,
					} ) ),
	};
}

/* ----------------------------------------------------------------- display */

/**
 * Sentence for a badge criterion, for example "Bonus points >= 10".
 *
 * @param criterion Criterion.
 * @param metrics   Metric labels from the server.
 */
export function describeCriterion(
	criterion: Criterion,
	metrics: readonly Choice< Metric >[]
): string {
	const label =
		metrics.find( ( metric ) => metric.value === criterion.metric )
			?.label ?? criterion.metric;
	return `${ label } ${ criterion.operator } ${ criterion.value }`;
}

/* -------------------------------------------------------------------- misc */

function messagesFrom( details: unknown ): FieldErrors {
	if ( ! details || 'object' !== typeof details ) {
		return {};
	}
	const params = ( details as Record< string, unknown > ).params;
	if ( ! params || 'object' !== typeof params ) {
		return {};
	}
	const errors: FieldErrors = {};
	for ( const [ name, message ] of Object.entries( params ) ) {
		if ( 'string' === typeof message ) {
			errors[ name ] = message;
		}
	}
	return errors;
}

/**
 * Reduce a failed request to what the screen shows.
 *
 * @param error Typed API error.
 * @return Message, permission flag, code and per-field messages.
 */
export function describeFailure( error: ApiError ): FailureView {
	return {
		message: error.message,
		permission: 401 === error.status || 403 === error.status,
		code: error.code,
		fieldErrors: messagesFrom( error.details ),
	};
}

/**
 * Whether a failed save was refused because someone else saved first.
 *
 * @param failure Reduced failure, if any.
 * @return True for 409 `ohmylms_gamification_conflict`.
 */
export function isStale( failure: FailureView | null ): boolean {
	return 'ohmylms_gamification_conflict' === failure?.code;
}

/**
 * Tab id from the `tab` query parameter.
 *
 * @param value Raw parameter.
 * @return A known tab id, `points` otherwise.
 */
export function tabFromParam( value: string | null ): TabId {
	return TAB_IDS.find( ( id ) => id === value ) ?? 'points';
}
