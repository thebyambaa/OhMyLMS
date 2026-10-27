/**
 * Pure helpers for the Skill map page: the coherence map of a subject, the syllabus comparison and the
 * learner overlay. The server (Curriculum\SkillMap) does the reading; these only arrange what it sends.
 */

export const LEVELS = [
	'not-started',
	'attempted',
	'familiar',
	'proficient',
	'mastered',
];

/**
 * Skills of a map keyed by ID.
 *
 * @param {Object} map Skill map from the server.
 * @return {Map<number, Object>} Skills.
 */
export function skillsById( map ) {
	return new Map(
		( map?.skills || [] ).map( ( skill ) => [ skill.id, skill ] )
	);
}

/**
 * Skills by domain, then by first grade.
 *
 * @param {Object} map Skill map from the server.
 * @return {Object<string, Object<number, Object[]>>} Cells.
 */
export function cellsOf( map ) {
	const cells = {};
	for ( const skill of map?.skills || [] ) {
		if ( ! skill.domain || ! skill.grade_from ) {
			continue;
		}
		cells[ skill.domain ] = cells[ skill.domain ] || {};
		( cells[ skill.domain ][ skill.grade_from ] =
			cells[ skill.domain ][ skill.grade_from ] || [] ).push( skill );
	}
	return cells;
}

/**
 * Everything a skill builds on and everything that builds on it, as the server's chain() does.
 *
 * @param {Map<number, Object>} skills Skills keyed by ID.
 * @param {number}              id     Selected skill.
 * @return {{before: Set<number>, after: Set<number>}} Chains.
 */
export function chainOf( skills, id ) {
	const neededBy = new Map();
	for ( const skill of skills.values() ) {
		for ( const other of skill.prerequisites || [] ) {
			neededBy.set( other, [
				...( neededBy.get( other ) || [] ),
				skill.id,
			] );
		}
	}
	const walk = ( next ) => {
		const seen = new Set();
		const queue = [ ...next( id ) ];
		while ( queue.length ) {
			const current = queue.shift();
			if ( current === id || seen.has( current ) ) {
				continue;
			}
			seen.add( current );
			queue.push( ...next( current ) );
		}
		return seen;
	};
	return {
		before: walk( ( skill ) => skills.get( skill )?.prerequisites || [] ),
		after: walk( ( skill ) => neededBy.get( skill ) || [] ),
	};
}

/**
 * Skills that include the given skill ("encompasses" links pointing at it).
 *
 * @param {Map<number, Object>} skills Skills keyed by ID.
 * @param {number}              id     Skill.
 * @return {Object[]} Each: skill and weight.
 */
export function includedBy( skills, id ) {
	const result = [];
	for ( const skill of skills.values() ) {
		const link = ( skill.includes || [] ).find(
			( entry ) => entry.id === id
		);
		if ( link ) {
			result.push( { skill, weight: link.weight } );
		}
	}
	return result;
}

/**
 * The learner's level on a skill, or not-started.
 *
 * @param {Object} map Skill map with a student overlay.
 * @param {number} id  Skill.
 * @return {string} Level.
 */
export function levelOf( map, id ) {
	const level = map?.student?.levels?.[ id ];
	return LEVELS.includes( level ) ? level : 'not-started';
}

/**
 * The syllabus objectives that teach a skill, when a syllabus is laid over the map.
 *
 * @param {Object} map Skill map with a syllabus overlay.
 * @param {number} id  Skill.
 * @return {Object[]|null} Objectives, or null when no syllabus is shown.
 */
export function coverageOf( map, id ) {
	if ( ! map?.syllabus ) {
		return null;
	}
	return map.syllabus.covered?.[ id ] || [];
}

/**
 * Class names for one skill chip.
 *
 * @param {Object} map      Skill map.
 * @param {number} id       Skill.
 * @param {Object} selected Selection: id and chain.
 * @return {string} Class names.
 */
export function chipClass( map, id, selected ) {
	const names = [ 'ohmylms-map-cell-skill' ];
	if ( map?.student ) {
		names.push( `ohmylms-map-${ levelOf( map, id ) }` );
	}
	const coverage = coverageOf( map, id );
	if ( coverage ) {
		names.push( coverage.length ? 'is-covered' : 'is-uncovered' );
	}
	if ( selected?.id === id ) {
		names.push( 'is-selected' );
	} else if ( selected?.chain.before.has( id ) ) {
		names.push( 'is-before' );
	} else if ( selected?.chain.after.has( id ) ) {
		names.push( 'is-after' );
	} else if ( selected ) {
		names.push( 'is-dimmed' );
	}
	return names.join( ' ' );
}

/**
 * A skill's level band in its subject's level names, such as "Grade 7–Grade 9" or "A2".
 *
 * @param {Object}   skill    Skill with grade_from and grade_to.
 * @param {Object[]} [levels] The map's levels (value, label); without them the bare numbers.
 * @return {string} Band, or empty when not placed.
 */
export function band( skill, levels ) {
	if ( ! skill?.grade_from ) {
		return '';
	}
	const label = ( value ) =>
		levels?.find( ( level ) => level.value === value )?.label ||
		String( value );
	return skill.grade_to > skill.grade_from
		? `${ label( skill.grade_from ) }–${ label( skill.grade_to ) }`
		: label( skill.grade_from );
}

/**
 * Comparison rows split into the three groups, each row with its skill and both sides' objectives.
 *
 * @param {Object} result Comparison from the server.
 * @return {{both: Object[], onlyA: Object[], onlyB: Object[]}} Groups.
 */
export function compareGroups( result ) {
	const objectives = ( side ) =>
		new Map(
			( result?.[ side ]?.objectives || [] ).map( ( item ) => [
				item.id,
				item,
			] )
		);
	const a = objectives( 'a' );
	const b = objectives( 'b' );
	const expand = ( list, side ) =>
		Object.entries( list || {} ).map( ( [ id, relation ] ) => ( {
			...( side.get( Number( id ) ) || { id: Number( id ), name: '' } ),
			relation,
		} ) );
	const groups = { both: [], onlyA: [], onlyB: [] };
	for ( const row of result?.rows || [] ) {
		const entry = {
			skill: result.skills?.[ row.skill ] || { id: row.skill, name: '' },
			a: expand( row.a, a ),
			b: expand( row.b, b ),
		};
		if ( entry.a.length && entry.b.length ) {
			groups.both.push( entry );
		} else if ( entry.a.length ) {
			groups.onlyA.push( entry );
		} else {
			groups.onlyB.push( entry );
		}
	}
	return groups;
}
