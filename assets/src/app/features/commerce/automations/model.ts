import { __, sprintf } from '@wordpress/i18n';
import { ApiError } from '../../../lib/api';

export const RULE_PER_PAGE = 20;
export const RULE_NAME_MAX = 190;

export type RuleStatus = 'active' | 'paused';
export type IdCondition =
	| 'course_ids'
	| 'membership_ids'
	| 'lesson_ids'
	| 'quiz_ids'
	| 'assignment_ids';
export type ChoiceType =
	| 'course'
	| 'membership'
	| 'lesson'
	| 'quiz'
	| 'assignment'
	| 'coupon'
	| 'cohort';

export const ID_CONDITIONS: Readonly< Record< IdCondition, ChoiceType > > = {
	course_ids: 'course',
	membership_ids: 'membership',
	lesson_ids: 'lesson',
	quiz_ids: 'quiz',
	assignment_ids: 'assignment',
};

export interface Conditions {
	course_ids?: number[];
	membership_ids?: number[];
	lesson_ids?: number[];
	quiz_ids?: number[];
	assignment_ids?: number[];
	operator?: 'gte' | 'lte' | 'eq';
	rate?: number;
	result?: 'pass' | 'fail';
}

export type RuleAction =
	| {
			type: 'send_email';
			to: 'learner' | 'admin';
			subject: string;
			body: string;
	  }
	| { type: 'enrol_course'; course_id: number }
	| { type: 'add_to_cohort'; cohort_id: number }
	| { type: 'grant_membership'; membership_id: number }
	| { type: 'send_coupon'; coupon_id: number; subject: string; body: string };

export type ActionType = RuleAction[ 'type' ];

export interface Rule {
	id: number;
	name: string;
	status: RuleStatus;
	event: string;
	conditions: Conditions;
	actions: RuleAction[];
	run_count: number;
	last_run_at: string | null;
}

export interface RuleOptions {
	events: { id: string; label: string; conditions: string[] }[];
	actions: { id: ActionType; label: string; params: string[] }[];
	mergeTags: string[];
	maxActions: number;
	maxIds: number;
	maxSubject: number;
	maxBody: number;
}

export interface LogEntry {
	id: number;
	automation_id: number;
	rule_name: string;
	event: string;
	user_name: string;
	object_title: string;
	action: string;
	status: 'success' | 'skipped' | 'failed';
	message: string;
	created_at: string;
}

/** The editor's state. */
export interface RuleDraft {
	name: string;
	status: RuleStatus;
	event: string;
	conditions: Conditions;
	actions: RuleAction[];
}

export const ACTION_TYPES: readonly ActionType[] = [
	'send_email',
	'enrol_course',
	'add_to_cohort',
	'grant_membership',
	'send_coupon',
];

export function invalidRuleResponse(): ApiError {
	return new ApiError( {
		code: 'invalid_response',
		status: 502,
		message: __(
			'The server returned invalid automation data.',
			'ohmylms'
		),
	} );
}

function record( value: unknown ): Record< string, unknown > {
	if ( ! value || 'object' !== typeof value || Array.isArray( value ) ) {
		throw invalidRuleResponse();
	}
	return value as Record< string, unknown >;
}
function text( value: unknown ): string {
	if ( 'string' !== typeof value ) {
		throw invalidRuleResponse();
	}
	return value;
}
function int( value: unknown ): number {
	if ( 'number' !== typeof value || ! Number.isSafeInteger( value ) ) {
		throw invalidRuleResponse();
	}
	return value;
}
function ids( value: unknown ): number[] {
	if ( ! Array.isArray( value ) ) {
		throw invalidRuleResponse();
	}
	return value.map( int );
}
function texts( value: unknown ): string[] {
	if ( ! Array.isArray( value ) ) {
		throw invalidRuleResponse();
	}
	return value.map( text );
}
function oneOf< T extends string >( value: unknown, allowed: readonly T[] ): T {
	const found = allowed.find( ( option ) => option === value );
	if ( undefined === found ) {
		throw invalidRuleResponse();
	}
	return found;
}

export function parseConditions( value: unknown ): Conditions {
	const row = Array.isArray( value ) && ! value.length ? {} : record( value );
	const conditions: Conditions = {};
	for ( const key of Object.keys( ID_CONDITIONS ) as IdCondition[] ) {
		if ( undefined !== row[ key ] ) {
			conditions[ key ] = ids( row[ key ] );
		}
	}
	if ( undefined !== row.operator ) {
		conditions.operator = oneOf( row.operator, [
			'gte',
			'lte',
			'eq',
		] as const );
	}
	if ( undefined !== row.rate ) {
		conditions.rate = int( row.rate );
	}
	if ( undefined !== row.result ) {
		conditions.result = oneOf( row.result, [ 'pass', 'fail' ] as const );
	}
	return conditions;
}

export function parseAction( value: unknown ): RuleAction {
	const row = record( value );
	const type = oneOf( row.type, ACTION_TYPES );
	switch ( type ) {
		case 'send_email':
			return {
				type,
				to: oneOf( row.to, [ 'learner', 'admin' ] as const ),
				subject: text( row.subject ),
				body: text( row.body ),
			};
		case 'send_coupon':
			return {
				type,
				coupon_id: int( row.coupon_id ),
				subject: text( row.subject ),
				body: text( row.body ),
			};
		case 'enrol_course':
			return { type, course_id: int( row.course_id ) };
		case 'add_to_cohort':
			return { type, cohort_id: int( row.cohort_id ) };
		default:
			return { type, membership_id: int( row.membership_id ) };
	}
}

export function parseRule( value: unknown ): Rule {
	const row = record( value );
	return {
		id: int( row.id ),
		name: text( row.name ),
		status: oneOf( row.status, [ 'active', 'paused' ] as const ),
		event: text( row.event ),
		conditions: parseConditions( row.conditions ),
		actions: ( Array.isArray( row.actions ) ? row.actions : [] ).map(
			parseAction
		),
		run_count: int( row.run_count ),
		last_run_at: null === row.last_run_at ? null : text( row.last_run_at ),
	};
}

export function parseOptions( value: unknown ): RuleOptions {
	const row = record( value );
	const list = ( raw: unknown ) => {
		if ( ! Array.isArray( raw ) ) {
			throw invalidRuleResponse();
		}
		return raw.map( record );
	};
	return {
		events: list( row.events ).map( ( event ) => ( {
			id: text( event.id ),
			label: text( event.label ),
			conditions: texts( event.conditions ),
		} ) ),
		actions: list( row.actions ).map( ( action ) => ( {
			id: oneOf( action.id, ACTION_TYPES ),
			label: text( action.label ),
			params: texts( action.params ),
		} ) ),
		mergeTags: texts( row.merge_tags ),
		maxActions: int( row.max_actions ),
		maxIds: int( row.max_ids ),
		maxSubject: int( row.max_subject ),
		maxBody: int( row.max_body ),
	};
}

export function parseLogEntry( value: unknown ): LogEntry {
	const row = record( value );
	return {
		id: int( row.id ),
		automation_id: int( row.automation_id ),
		rule_name: text( row.rule_name ),
		event: text( row.event ),
		user_name: text( row.user_name ),
		object_title: text( row.object_title ),
		action: text( row.action ),
		status: oneOf( row.status, [
			'success',
			'skipped',
			'failed',
		] as const ),
		message: text( row.message ),
		created_at: text( row.created_at ),
	};
}

export function parseChoices(
	value: unknown
): { id: number; title: string }[] {
	if ( ! Array.isArray( value ) ) {
		throw invalidRuleResponse();
	}
	return value.map( ( item ) => {
		const row = record( item );
		return { id: int( row.id ), title: text( row.title ) };
	} );
}

export function emptyRuleDraft( options: RuleOptions | null ): RuleDraft {
	return {
		name: '',
		status: 'paused',
		event: options?.events[ 0 ]?.id ?? 'course_enrolled',
		conditions: {},
		actions: [ newAction( 'send_email' ) ],
	};
}

export function ruleDraft( rule: Rule ): RuleDraft {
	return {
		name: rule.name,
		status: rule.status,
		event: rule.event,
		conditions: { ...rule.conditions },
		actions: rule.actions.map( ( action ) => ( { ...action } ) ),
	};
}

/**
 * A blank action of a type.
 *
 * @param type Action type.
 * @return Action with empty parameters.
 */
export function newAction( type: ActionType ): RuleAction {
	switch ( type ) {
		case 'send_email':
			return { type, to: 'learner', subject: '', body: '' };
		case 'send_coupon':
			return {
				type,
				coupon_id: 0,
				subject: __( 'A coupon for you', 'ohmylms' ),
				body: __(
					'Hi {first_name}, use the code {coupon_code} at checkout.',
					'ohmylms'
				),
			};
		case 'enrol_course':
			return { type, course_id: 0 };
		case 'add_to_cohort':
			return { type, cohort_id: 0 };
		default:
			return { type: 'grant_membership', membership_id: 0 };
	}
}

/**
 * Keep only the conditions an event supports (switching the event).
 *
 * @param conditions Conditions.
 * @param supported  Condition names of the event.
 * @return Filtered conditions.
 */
export function keepConditions(
	conditions: Conditions,
	supported: readonly string[]
): Conditions {
	return Object.fromEntries(
		Object.entries( conditions ).filter( ( [ key ] ) =>
			supported.includes( key )
		)
	) as Conditions;
}

/**
 * Validate a draft like the server; keys match the server's (`name`, `conditions.rate`, `actions.0.subject`).
 *
 * @param draft   Draft.
 * @param options Editor options.
 * @return Field to message.
 */
export function validateRule(
	draft: RuleDraft,
	options: RuleOptions
): Record< string, string > {
	const errors: Record< string, string > = {};
	const name = draft.name.trim();
	if ( ! name || Array.from( name ).length > RULE_NAME_MAX ) {
		errors.name = sprintf(
			/* translators: %d: maximum length. */
			__( 'Enter a name of 1 to %d characters.', 'ohmylms' ),
			RULE_NAME_MAX
		);
	}
	if ( ! options.events.some( ( event ) => event.id === draft.event ) ) {
		errors.event = __( 'Choose an event from the list.', 'ohmylms' );
	}
	if (
		'course_progress' === draft.event &&
		undefined === draft.conditions.rate
	) {
		errors[ 'conditions.rate' ] = __(
			'Enter the progress rate.',
			'ohmylms'
		);
	}
	if (
		undefined !== draft.conditions.rate &&
		( ! Number.isInteger( draft.conditions.rate ) ||
			draft.conditions.rate < 0 ||
			draft.conditions.rate > 100 )
	) {
		errors[ 'conditions.rate' ] = __(
			'Enter a whole percentage from 0 to 100.',
			'ohmylms'
		);
	}
	if ( ! draft.actions.length || draft.actions.length > options.maxActions ) {
		errors.actions = sprintf(
			/* translators: %d: maximum number of actions. */
			__( 'Add 1 to %d actions.', 'ohmylms' ),
			options.maxActions
		);
	}
	draft.actions.forEach( ( action, index ) => {
		const key = ( field: string ) => `actions.${ index }.${ field }`;
		if ( 'send_email' === action.type || 'send_coupon' === action.type ) {
			if ( ! action.subject.trim() ) {
				errors[ key( 'subject' ) ] = __(
					'Enter a subject.',
					'ohmylms'
				);
			}
			if ( ! action.body.trim() ) {
				errors[ key( 'body' ) ] = __( 'Enter a message.', 'ohmylms' );
			} else if (
				'send_coupon' === action.type &&
				! action.body.includes( '{coupon_code}' )
			) {
				errors[ key( 'body' ) ] = __(
					'Include {coupon_code} in the message.',
					'ohmylms'
				);
			}
		}
		if ( 'send_coupon' === action.type && ! action.coupon_id ) {
			errors[ key( 'coupon_id' ) ] = __( 'Choose a coupon.', 'ohmylms' );
		}
		if ( 'enrol_course' === action.type && ! action.course_id ) {
			errors[ key( 'course_id' ) ] = __( 'Choose a course.', 'ohmylms' );
		}
		if ( 'add_to_cohort' === action.type && ! action.cohort_id ) {
			errors[ key( 'cohort_id' ) ] = __( 'Choose a cohort.', 'ohmylms' );
		}
		if ( 'grant_membership' === action.type && ! action.membership_id ) {
			errors[ key( 'membership_id' ) ] = __(
				'Choose a membership plan.',
				'ohmylms'
			);
		}
	} );
	return errors;
}

/**
 * JSON body of a draft.
 *
 * @param draft Draft.
 * @return Body for create or replace.
 */
export function ruleBody( draft: RuleDraft ): Record< string, unknown > {
	const conditions: Record< string, unknown > = {};
	for ( const [ key, value ] of Object.entries( draft.conditions ) ) {
		if ( Array.isArray( value ) ? value.length : undefined !== value ) {
			conditions[ key ] = value;
		}
	}
	return {
		name: draft.name.trim(),
		status: draft.status,
		event: draft.event,
		conditions,
		actions: draft.actions,
	};
}

export function eventLabel( options: RuleOptions | null, id: string ): string {
	return options?.events.find( ( event ) => event.id === id )?.label ?? id;
}

export function actionLabel( options: RuleOptions | null, id: string ): string {
	return options?.actions.find( ( action ) => action.id === id )?.label ?? id;
}

export function resultLabel( status: LogEntry[ 'status' ] ): string {
	switch ( status ) {
		case 'success':
			return __( 'Done', 'ohmylms' );
		case 'skipped':
			return __( 'Skipped', 'ohmylms' );
		default:
			return __( 'Failed', 'ohmylms' );
	}
}
