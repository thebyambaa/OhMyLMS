export type Provider = 'zoom' | 'googlemeet';
export type SessionStatus = 'draft' | 'publish';
export type SessionState = 'unscheduled' | 'upcoming' | 'live' | 'ended';
export type OptionName =
	| 'host_video'
	| 'participant_video'
	| 'join_before_host'
	| 'mute_upon_entry'
	| 'auto_record';

/** A row of `GET /sessions`. */
export interface SessionSummary {
	id: number;
	title: string;
	status: SessionStatus;
	provider: Provider | null;
	/** Local start in the session time zone, `YYYY-MM-DDTHH:MM`. */
	start: string | null;
	timezone: string;
	duration: number;
	state: SessionState;
	course: { id: number; title: string } | null;
	meeting: { created: boolean; join_url: string | null };
}

/** Body of `GET /sessions/{id}`. */
export interface Session extends SessionSummary {
	description: string;
	password: string;
	options: Record< OptionName, boolean >;
	can_delete: boolean;
}

/** Editable values of the editor form. */
export interface SessionDraft {
	title: string;
	description: string;
	status: SessionStatus;
	provider: Provider;
	start: string;
	timezone: string;
	duration: number;
	course_id: number | null;
	password: string;
	options: Record< OptionName, boolean >;
}

export interface SessionQuery {
	page: number;
	search: string;
	provider: 'all' | Provider;
}

export interface ProviderStatus {
	id: Provider;
	label: string;
	available: boolean;
	configured: boolean;
}

export interface Attendee {
	user_id: number;
	name: string;
	status: 'enrolled' | 'banned';
	completed: boolean;
}

export interface CourseChoice {
	id: number;
	title: string;
}
