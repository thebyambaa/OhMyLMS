export interface Kpi {
	id: string;
	label: string;
	available: boolean;
	value: string;
	delta: number | null;
	note: string;
}

export interface DrillRow {
	id: number;
	name: string;
	cells: string[];
}

export interface Summary {
	range: number;
	school: number;
	kpis: Kpi[];
	alerts: Array< {
		id: string;
		tone: string;
		text: string;
		action: string;
		label: string;
	} >;
	pending: Array< {
		id: number;
		role: string;
		email: string;
		created: string;
	} >;
	drill: {
		level: string;
		columns: string[];
		rows: DrillRow[];
		class?: number;
	};
	heatmap: {
		available: boolean;
		note: string;
		columns: string[];
		rows: Array< { label: string; cells: number[] } >;
	};
}

export interface Person {
	id: number;
	name: string;
	email: string;
	school: string;
}

export interface ContentItem {
	id: number;
	title: string;
	status: string;
	type: string;
	edit: string;
}
