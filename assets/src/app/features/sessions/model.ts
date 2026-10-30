import { __ } from '@wordpress/i18n';
import { ApiError } from '../../lib/api';
import type { QueryParams } from '../../lib/api';
import type {
	Attendee,
	CourseChoice,
	OptionName,
	Provider,
	ProviderStatus,
	Session,
	SessionDraft,
	SessionQuery,
	SessionState,
	SessionSummary,
} from './types';

export const PER_PAGE = 20;
export const OPTION_NAMES: readonly OptionName[] = [
	'host_video',
	'participant_video',
	'join_before_host',
	'mute_upon_entry',
	'auto_record',
];
const PROVIDERS: readonly Provider[] = [ 'zoom', 'googlemeet' ];
const STATES: readonly SessionState[] = [
	'unscheduled',
	'upcoming',
	'live',
	'ended',
];

export const EMPTY_QUERY: SessionQuery = {
	page: 1,
	search: '',
	provider: 'all',
};

export function invalidResponse(): ApiError {
	return new ApiError( {
		code: 'invalid_response',
		status: 502,
		message: __( 'The server returned invalid session data.', 'ohmylms' ),
	} );
}
function record( value: unknown ): Record< string, unknown > {
	if ( ! value || 'object' !== typeof value || Array.isArray( value ) ) {
		throw invalidResponse();
	}
	return value as Record< string, unknown >;
}
function text( value: unknown ): string {
	if ( 'string' !== typeof value ) {
		throw invalidResponse();
	}
	return value;
}
function nullableText( value: unknown ): string | null {
	return null === value ? null : text( value );
}
function flag( value: unknown ): boolean {
	if ( 'boolean' !== typeof value ) {
		throw invalidResponse();
	}
	return value;
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
export function positiveId( value: unknown ): number {
	const id = count( value );
	if ( id < 1 ) {
		throw invalidResponse();
	}
	return id;
}
function oneOf< T extends string >( value: unknown, allowed: readonly T[] ): T {
	const found = allowed.find( ( option ) => option === value );
	if ( undefined === found ) {
		throw invalidResponse();
	}
	return found;
}
function choice( value: unknown ): CourseChoice {
	const item = record( value );
	return { id: positiveId( item.id ), title: text( item.title ) };
}

export function parseSummary( value: unknown ): SessionSummary {
	const item = record( value );
	const meeting = record( item.meeting );
	const joinUrl = nullableText( meeting.join_url );
	if ( null !== joinUrl && ! joinUrl.startsWith( 'https://' ) ) {
		throw invalidResponse();
	}
	return {
		id: positiveId( item.id ),
		title: text( item.title ),
		status: oneOf( item.status, [ 'draft', 'publish' ] as const ),
		provider:
			null === item.provider ? null : oneOf( item.provider, PROVIDERS ),
		start: nullableText( item.start ),
		timezone: text( item.timezone ),
		duration: count( item.duration ),
		state: oneOf( item.state, STATES ),
		course: null === item.course ? null : choice( item.course ),
		meeting: { created: flag( meeting.created ), join_url: joinUrl },
	};
}

export function parseSession( value: unknown ): Session {
	const item = record( value );
	const options = record( item.options );
	return {
		...parseSummary( value ),
		description: text( item.description ),
		password: text( item.password ),
		options: Object.fromEntries(
			OPTION_NAMES.map( ( name ) => [ name, flag( options[ name ] ) ] )
		) as Record< OptionName, boolean >,
		can_delete: flag( item.can_delete ),
	};
}

export function parseProvider( value: unknown ): ProviderStatus {
	const item = record( value );
	return {
		id: oneOf( item.id, PROVIDERS ),
		label: text( item.label ),
		available: flag( item.available ),
		configured: flag( item.configured ),
	};
}

export function parseAttendee( value: unknown ): Attendee {
	const item = record( value );
	return {
		user_id: positiveId( item.user_id ),
		name: text( item.name ),
		status: oneOf( item.status, [ 'enrolled', 'banned' ] as const ),
		completed: flag( item.completed ),
	};
}

export function parseCourseChoice( value: unknown ): CourseChoice {
	return choice( value );
}

export function parseDeleted( value: unknown ): SessionSummary {
	const item = record( value );
	if ( true !== item.deleted ) {
		throw invalidResponse();
	}
	return parseSummary( item.previous );
}

export function buildQuery( query: SessionQuery ): QueryParams {
	const search = query.search.trim();
	return {
		page: query.page,
		per_page: PER_PAGE,
		...( search ? { search } : {} ),
		...( 'all' === query.provider ? {} : { provider: query.provider } ),
	};
}

/**
 * The browser's time zone when it is a valid IANA name, else UTC.
 *
 * @return Time zone name.
 */
export function defaultTimezone(): string {
	const zone = Intl.DateTimeFormat().resolvedOptions().timeZone;
	return zone || 'UTC';
}

/**
 * Time zone choices: the IANA names the browser knows, always including `current`.
 *
 * @param current Selected zone.
 * @return Sorted names.
 */
export function timezoneChoices( current: string ): string[] {
	const supported = (
		Intl as unknown as { supportedValuesOf?: ( key: string ) => string[] }
	 ).supportedValuesOf;
	const zones = supported ? supported( 'timeZone' ) : [ 'UTC' ];
	return Array.from( new Set( [ ...zones, 'UTC', current ] ) )
		.filter( Boolean )
		.sort();
}

export function emptyDraft(): SessionDraft {
	return {
		title: '',
		description: '',
		status: 'draft',
		provider: 'zoom',
		start: '',
		timezone: defaultTimezone(),
		duration: 60,
		course_id: null,
		password: '',
		options: {
			host_video: true,
			participant_video: true,
			join_before_host: false,
			mute_upon_entry: true,
			auto_record: false,
		},
	};
}

export function draftFromSession( session: Session ): SessionDraft {
	return {
		title: session.title,
		description: session.description,
		status: session.status,
		provider: session.provider ?? 'zoom',
		start: session.start ?? '',
		timezone: session.timezone,
		duration: session.duration || 60,
		course_id: session.course?.id ?? null,
		password: session.password,
		options: { ...session.options },
	};
}

export type DraftErrors = Partial< Record< keyof SessionDraft, string > >;

/**
 * Check the editor form before saving; the server repeats every check.
 *
 * @param draft Form values.
 * @return Messages by field; empty when the form can be saved.
 */
export function validateDraft( draft: SessionDraft ): DraftErrors {
	const errors: DraftErrors = {};
	if ( ! draft.title.trim() ) {
		errors.title = __( 'Enter a title.', 'ohmylms' );
	} else if ( draft.title.length > 200 ) {
		errors.title = __( 'Use at most 200 characters.', 'ohmylms' );
	}
	const match = /^(\d{4})-(\d{2})-(\d{2})T(\d{2}):(\d{2})$/.exec(
		draft.start
	);
	const date = match
		? new Date(
				Date.UTC(
					Number( match[ 1 ] ),
					Number( match[ 2 ] ) - 1,
					Number( match[ 3 ] ),
					Number( match[ 4 ] ),
					Number( match[ 5 ] )
				)
			)
		: null;
	if (
		! match ||
		! date ||
		date.getUTCMonth() !== Number( match[ 2 ] ) - 1 ||
		date.getUTCDate() !== Number( match[ 3 ] ) ||
		Number( match[ 4 ] ) > 23
	) {
		errors.start = __( 'Enter a valid start date and time.', 'ohmylms' );
	}
	if (
		! Number.isInteger( draft.duration ) ||
		draft.duration < 5 ||
		draft.duration > 1440
	) {
		errors.duration = __(
			'Enter a duration from 5 to 1440 minutes.',
			'ohmylms'
		);
	}
	if ( ! draft.timezone ) {
		errors.timezone = __( 'Choose a time zone.', 'ohmylms' );
	}
	if ( 'zoom' === draft.provider ) {
		if ( ! /^[A-Za-z0-9@_*-]{0,10}$/.test( draft.password ) ) {
			errors.password = __(
				'Use up to 10 letters, digits or @ - _ *.',
				'ohmylms'
			);
		}
	}
	return errors;
}

/**
 * Request body of a save. Google Meet takes no passcode; the provider of a saved session is fixed.
 *
 * @param draft    Form values.
 * @param existing Whether the session already exists.
 * @return JSON body.
 */
export function toPayload(
	draft: SessionDraft,
	existing: boolean
): Record< string, unknown > {
	return {
		title: draft.title.trim(),
		description: draft.description,
		status: draft.status,
		...( existing ? {} : { provider: draft.provider } ),
		start: draft.start,
		timezone: draft.timezone,
		duration: draft.duration,
		course_id: draft.course_id,
		password: 'zoom' === draft.provider ? draft.password : '',
		options: { ...draft.options },
	};
}

export function stateLabel( state: SessionState ): string {
	switch ( state ) {
		case 'upcoming':
			return __( 'Upcoming', 'ohmylms' );
		case 'live':
			return __( 'Live now', 'ohmylms' );
		case 'ended':
			return __( 'Ended', 'ohmylms' );
		default:
			return __( 'Not scheduled', 'ohmylms' );
	}
}

export function providerLabel( provider: Provider | null ): string {
	if ( 'zoom' === provider ) {
		return __( 'Zoom', 'ohmylms' );
	}
	return 'googlemeet' === provider
		? __( 'Google Meet', 'ohmylms' )
		: __( 'No provider', 'ohmylms' );
}

export function optionLabel( name: OptionName ): string {
	return {
		host_video: __( 'Start with host video on', 'ohmylms' ),
		participant_video: __( 'Start with participant video on', 'ohmylms' ),
		join_before_host: __( 'Allow joining before the host', 'ohmylms' ),
		mute_upon_entry: __( 'Mute participants on entry', 'ohmylms' ),
		auto_record: __( 'Record automatically', 'ohmylms' ),
	}[ name ];
}

/**
 * Format a session start for display, in its own time zone.
 *
 * @param start    Local start, `YYYY-MM-DDTHH:MM`, or null.
 * @param timezone IANA name.
 * @return Text such as "4 Mar 2030, 10:00 (Europe/Berlin)".
 */
export function formatStart( start: string | null, timezone: string ): string {
	if ( ! start ) {
		return '—';
	}
	const [ day, time ] = start.split( 'T' );
	const date = new Date( `${ day }T00:00:00Z` );
	const label = Number.isNaN( date.getTime() )
		? day
		: new Intl.DateTimeFormat( undefined, {
				dateStyle: 'medium',
				timeZone: 'UTC',
			} ).format( date );
	return `${ label }, ${ time } (${ timezone })`;
}

/**
 * Parse the `:id` route segment.
 *
 * @param value Raw segment.
 * @return `new`, a positive id, or null when invalid.
 */
export function parseRouteId(
	value: string | undefined
): 'new' | number | null {
	if ( 'new' === value ) {
		return 'new';
	}
	return value && /^[1-9]\d{0,14}$/.test( value ) ? Number( value ) : null;
}
