/** Shapes of the gradebook REST API (docs/api/gradebook.md). */

export type CellState =
	'score' | 'pending' | 'missing' | 'none' | 'not_assigned' | 'excused';

export type ItemKind = 'quiz' | 'exam' | 'assignment';
export type Audience = 'all' | 'classes' | 'individuals';

export interface GradebookScope {
	id: string;
	type: 'all' | 'school' | 'class';
	label: string;
	school_id: number;
	school_name: string;
}

export interface ScaleStep {
	letter: string;
	min: number;
}

export interface ClassRef {
	id: number;
	name: string;
	school_name?: string;
}

export interface GradebookContext {
	scope: GradebookScope;
	scopes: GradebookScope[];
	courses: { id: number; title: string }[];
	classes: ClassRef[];
	scale: ScaleStep[];
	site_admin: boolean;
}

export interface GradeItem {
	id: number;
	title: string;
	kind: ItemKind;
	category: string;
	module_id: number;
	module: string;
	max: number;
	audience: Audience;
	class_ids: number[];
	due_at: string | null;
}

export interface CellValue {
	state: CellState;
	score: number | null;
	max: number | null;
	percent: number | null;
}

export interface Cell extends CellValue {
	overridden: boolean;
	version: number;
	computed: CellValue;
}

export interface RowTotal {
	percent: number | null;
	letter: string | null;
	computed_percent: number | null;
	mode: 'points' | 'weighted';
	weights_class: number;
}

export interface LearnerRow {
	id: number;
	name: string;
	status: string;
	source: string;
	sources: string[];
	via: ClassRef | null;
	rosters: ClassRef[];
	total: RowTotal;
	counts: {
		missing: number;
		pending: number;
		excused: number;
		overridden: number;
	};
	cells: Record< string, Cell >;
}

export type Weights = Record< string, number >;

export interface CourseWeights {
	course: { weights: Weights; version: number };
	overrides: { class_id: number; weights: Weights; version: number }[];
}

export interface CourseGrid {
	course: { id: number; title: string };
	items: GradeItem[];
	all_items: number;
	modules: { id: number; title: string }[];
	categories: string[];
	classes: ClassRef[];
	weights: CourseWeights;
	scale: ScaleStep[];
	missing_as_zero: boolean;
	rows: LearnerRow[];
	summary: {
		learners: number;
		average: number | null;
		items: Record<
			string,
			{
				average: number | null;
				states: Partial< Record< CellState, number > >;
			}
		>;
	};
	total: number;
	total_pages: number;
	page: number;
	per_page: number;
	generated_at: string;
	can: {
		grade: boolean;
		manage_course: boolean;
		weight_classes: number[];
	};
}

export interface HistoryEntry {
	id: number;
	action: 'override' | 'excuse' | 'revert' | 'import';
	old: { kind: string; score: number | null };
	new: { kind: string; score: number | null };
	max: number | null;
	computed_score: number | null;
	reason: string;
	by: { id: number; name: string } | null;
	at: string | null;
}

export interface CellDetail {
	course: { id: number; title: string };
	item: GradeItem;
	learner: { id: number; name: string };
	cell: Cell & {
		reason: string;
		updated_by: { id: number; name: string } | null;
		updated_at: string | null;
	};
	total: Omit< RowTotal, 'weights_class' >;
	history: HistoryEntry[];
}

export interface RosterCourse {
	id: number;
	title: string;
	items: GradeItem[];
	average: number | null;
}

export interface RosterView {
	class: ClassRef & { school_id: number; school_name: string };
	courses: RosterCourse[];
	learners: {
		id: number;
		name: string;
		totals: Record<
			string,
			{
				percent: number | null;
				letter: string | null;
				status: string;
				source: string;
			} | null
		>;
		cells: Record< string, Cell >;
	}[];
	truncated: boolean;
	scale: ScaleStep[];
}

export interface MasterySkill {
	id: number;
	name: string;
	code: string;
	subject: string;
}

export interface MasteryLevel {
	id: string;
	label: string;
	min: number | null;
}

export interface Transcript {
	learner: { id: number; name: string };
	enrolments: {
		course: { id: number; title: string };
		items: GradeItem[];
		weights: Weights;
		row: LearnerRow;
	}[];
	truncated: boolean;
	mastery: {
		skills: ( MasterySkill & { percent: number; level: string } )[];
		levels: MasteryLevel[];
	};
	scale: ScaleStep[];
}

export interface MasteryView {
	skills: ( MasterySkill & { assessed: number } )[];
	learners: {
		id: number;
		name: string;
		source: string;
		rosters: ClassRef[];
		cells: Record< string, { percent: number | null; level: string } >;
	}[];
	levels: MasteryLevel[];
	total: number;
	total_pages: number;
	page: number;
}

export interface RollupNode {
	type: 'school' | 'class' | 'independent' | 'individuals';
	id: number;
	name: string;
	learners: number;
	enrolments: number;
	average: number | null;
	missing_learners: number;
	pending: number;
	children: RollupNode[];
}

export interface RollupView {
	nodes: RollupNode[];
	truncated: boolean;
}

export type Section = 'course' | 'roster' | 'student' | 'mastery' | 'rollup';

export interface SavedView {
	id: string;
	name: string;
	section: Section;
	query: string;
	created_at: string;
}
