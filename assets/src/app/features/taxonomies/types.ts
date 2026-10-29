/** The two read-only taxonomies. Categories are curriculum items; tags are course bundles. */
export type TaxonomyKind = 'categories' | 'tags';

/** A row of `GET /categories` or `GET /tags`. */
export interface Term {
	id: number;
	name: string;
	slug: string;
	description: string;
	/** Parent item ID; 0 for top level and always 0 for tags. */
	parent: number;
	/** Number of linked courses. */
	count: number;
}

export interface LinkedCourse {
	id: number;
	title: string;
}

/** One term with its linked courses (`context=edit`, administrators only). */
export interface TermDetail extends Term {
	courses: LinkedCourse[];
}

export interface TermQuery {
	page: number;
	search: string;
}
