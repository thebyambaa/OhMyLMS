/** Pure helpers for randomised question templates ("Randomize numbers"). */

/** @typedef {import('./editorTypes').Data} Data */

/** Variable names are single letters; "e" is Euler's number and is not available. */
export const VARIABLE_LETTERS = 'abcdfghijklmnopqrstuvwxyz'.split( '' );

export const VARIABLE_TYPES = [
	[ 'int', 'Whole number' ],
	[ 'decimal', 'Decimal' ],
	[ 'choice', 'Pick from a list' ],
	[ 'expr', 'Calculated' ],
];

/**
 * Does this question draw new numbers each time it is issued?
 * @param {Data|null|undefined} settings
 * @return {boolean} Whether the question has template variables.
 */
export function hasTemplate( settings ) {
	return (
		Array.isArray( settings?.template?.variables ) &&
		settings.template.variables.length > 0
	);
}

/**
 * A starting template: two whole numbers and nothing else.
 */
export function defaultTemplate() {
	return {
		variables: [
			{ name: 'a', type: 'int', min: 2, max: 9 },
			{ name: 'b', type: 'int', min: 2, max: 9 },
		],
		constraints: [],
		set: [],
	};
}

/**
 * The first unused variable letter, or '' when all are taken.
 * @param {Data[]|null|undefined} variables
 * @return {string} Variable name.
 */
export function nextVariableName( variables ) {
	const used = new Set( ( variables || [] ).map( ( v ) => v.name ) );
	return VARIABLE_LETTERS.find( ( letter ) => ! used.has( letter ) ) || '';
}

/**
 * A new variable of a type with sensible limits.
 * @param {string} type
 * @param {string} name
 * @return {Data} Variable.
 */
export function variableDefaults( type, name ) {
	switch ( type ) {
		case 'decimal':
			return { name, type, min: 1, max: 9, places: 1 };
		case 'choice':
			return { name, type, values: [ 'apples', 'pears' ] };
		case 'expr':
			return { name, type, expr: '', places: 0 };
		default:
			return { name, type: 'int', min: 2, max: 9 };
	}
}

/**
 * "apples, 3, pears" as a list; numeric entries become numbers.
 * @param {string|null|undefined} text
 * @return {(number|string)[]} Values.
 */
export function parseValues( text ) {
	return String( text || '' )
		.split( ',' )
		.map( ( part ) => part.trim() )
		.filter( ( part ) => part !== '' )
		.map( ( part ) =>
			/^-?\d+(\.\d+)?$/.test( part ) ? Number( part ) : part
		);
}

/**
 * Conditions typed one per line.
 * @param {string|null|undefined} text
 * @return {string[]} Lines.
 */
export function parseLines( text ) {
	return String( text || '' )
		.split( '\n' )
		.map( ( line ) => line.trim() )
		.filter( Boolean );
}

/**
 * Setting names a learner's answer or target usually lives under, per question type.
 * Authors can still type any other path (for example "blanks.a.answer" or "parts.0.answer").
 * @param {string} type
 * @return {string[]} Setting paths.
 */
export function setPathSuggestions( type ) {
	switch ( type ) {
		case 'text-entry':
			return [ 'accepted.0' ];
		case 'numerical':
		case 'expression':
		case 'shade-model':
			return [ 'answer' ];
		case 'number-line':
		case 'fill-level':
		case 'count-blocks':
		case 'make-amount':
			return [ 'target' ];
		case 'set-clock':
			return [ 'hour', 'minute' ];
		case 'grid-build':
			return [ 'constraints.area', 'constraints.perimeter' ];
		case 'build-chart':
			return [ 'values.a', 'values.b' ];
		case 'multi-blank':
			return [ 'blanks.a.answer', 'blanks.b.answer' ];
		case 'structured':
			return [ 'parts.0.answer', 'parts.1.answer' ];
		default:
			return [];
	}
}

/**
 * Quick problems to show before the server's own check: duplicate or unusable names,
 * reversed ranges, missing formulas.
 * @param {Data|null|undefined} template
 * @return {string[]} Issue codes.
 */
export function templateIssues( template ) {
	const issues = [];
	/** @type {Data[]} */
	const variables = template?.variables || [];
	const names = variables.map( ( v ) => v.name );
	if (
		names.some( ( name ) => ! VARIABLE_LETTERS.includes( name ) ) ||
		new Set( names ).size !== names.length
	) {
		issues.push( 'names' );
	}
	for ( const v of variables ) {
		if (
			[ 'int', 'decimal' ].includes( v.type ) &&
			! ( Number( v.max ) >= Number( v.min ) )
		) {
			issues.push( 'range' );
			break;
		}
	}
	if (
		variables.some(
			( v ) =>
				( v.type === 'expr' && ! String( v.expr || '' ).trim() ) ||
				( v.type === 'choice' && ! ( v.values || [] ).length )
		)
	) {
		issues.push( 'values' );
	}
	if (
		( template?.set || [] ).some(
			( /** @type {Data} */ rule ) =>
				! String( rule.path || '' ).trim() ||
				! String( rule.expr || '' ).trim()
		)
	) {
		issues.push( 'set' );
	}
	return issues;
}
