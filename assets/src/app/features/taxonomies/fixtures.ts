import type { TermDetail, Term } from './types';

/** Test fixtures only; production components never import this module. */
export const root: Term = {
	id: 3,
	name: 'Mathematics',
	slug: 'c3',
	description: 'All maths courses',
	parent: 0,
	count: 2,
};
export const child: Term = {
	id: 4,
	name: 'Algebra',
	slug: 'c4',
	description: '',
	parent: 3,
	count: 1,
};
export const orphan: Term = { ...child, id: 5, name: 'Orphan', parent: 99 };
export const bundle: Term = {
	id: 8,
	name: 'Starter pack',
	slug: 'b8',
	description: 'Free courses',
	parent: 0,
	count: 3,
};
export const detail: TermDetail = {
	...child,
	courses: [ { id: 40, title: 'Intro to Algebra' } ],
};
