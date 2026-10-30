/** Content Hub areas, in the order the overview lists them. */
export type AreaId =
	| 'courses'
	| 'lessons'
	| 'quizzes'
	| 'exams'
	| 'question-bank'
	| 'assignments'
	| 'skills'
	| 'curriculum'
	| 'bundles';

/** Figures an area can report besides its total. */
export type CountKey =
	'published' | 'drafts' | 'to_grade' | 'archived' | 'syllabuses';

/** One area of `GET /content-hub/overview`: only areas the user may open are sent. */
export interface AreaSummary {
	id: AreaId;
	total: number;
	counts: Partial< Record< CountKey, number > >;
}

/** Areas whose items have their own editor. */
export type RecentArea = Extract<
	AreaId,
	| 'courses'
	| 'lessons'
	| 'quizzes'
	| 'exams'
	| 'question-bank'
	| 'assignments'
>;

/** A recently changed item. `updated_at` is UTC ISO 8601. */
export interface RecentItem {
	area: RecentArea;
	id: number;
	title: string;
	status: 'publish' | 'draft' | 'future' | 'private';
	updated_at: string | null;
}

/** `GET /content-hub/overview`. */
export interface Overview {
	areas: AreaSummary[];
	recent: RecentItem[];
}
