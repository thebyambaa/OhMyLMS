/**
 * Loaders of the Account Hub screens other than the list. This module ships in the list's chunk, so
 * each screen's chunk reuses the list's API and model code; imported straight from the route
 * definitions, webpack would move that shared code into unnamed chunks outside the asset budgets.
 */
export const loadSchoolEditor = () =>
	import(
		/* webpackChunkName: "admin-app-account-hub-school" */ './SchoolEditorPage'
	);

export const loadSchoolRoster = () =>
	import(
		/* webpackChunkName: "admin-app-account-hub-roster" */ './SchoolRosterPage'
	);

export const loadClassEditor = () =>
	import(
		/* webpackChunkName: "admin-app-account-hub-class" */ './ClassEditorPage'
	);
