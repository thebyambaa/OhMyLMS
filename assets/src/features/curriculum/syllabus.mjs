/**
 * Pure helpers for the syllabus outline editor. The outline is what the server returns:
 * `contents` (the syllabus first, then its content items in tree order with a `depth`), each with its
 * `groups`, each with its `skills`. The server validates every change again.
 */

export const GROUP_NAME_LIMIT = 190;
export const SKILL_CODE_LIMIT = 40;

/**
 * Every skill group in outline order, with the content item it sits under.
 * @param outline
 */
export function allGroups( outline ) {
	return ( outline?.contents || [] ).flatMap( ( content ) =>
		content.groups.map( ( group ) => ( { group, content } ) )
	);
}

/**
 * "C1.1 · Types of number", or just the name when the group has no code or the name is its code.
 * @param group
 */
export function groupLabel( group ) {
	if ( ! group.code || group.code === group.name ) {
		return group.name;
	}
	return `${ group.code } · ${ group.name }`;
}

export function skillLabel( skill ) {
	return skill.code ? `${ skill.code } · ${ skill.name }` : skill.name;
}

/**
 * Where a group can sit: the syllabus itself and each content item, indented by depth.
 * @param outline
 */
export function containerOptions( outline ) {
	return ( outline?.contents || [] ).map( ( content ) => ( {
		id: content.id,
		depth: content.depth,
		name: content.name,
		isRoot: content.depth === 0,
	} ) );
}

/**
 * Position to ask for when moving one step (-1 up, +1 down) in an ordered list of IDs, or null at an edge.
 * @param ids
 * @param id
 * @param direction
 */
export function stepPosition( ids, id, direction ) {
	const index = ids.indexOf( id );
	const target = index + direction;
	if ( index < 0 || target < 0 || target >= ids.length ) {
		return null;
	}
	return target;
}

/**
 * The content item holding a group, and the group's ordered siblings under it.
 * @param outline
 * @param groupId
 */
export function groupSiblings( outline, groupId ) {
	for ( const content of outline?.contents || [] ) {
		if ( content.groups.some( ( group ) => group.id === groupId ) ) {
			return {
				content,
				ids: content.groups.map( ( group ) => group.id ),
			};
		}
	}
	return { content: null, ids: [] };
}

export function findGroup( outline, groupId ) {
	return (
		allGroups( outline ).find( ( entry ) => entry.group.id === groupId ) ||
		null
	);
}

/**
 * Client-side hints that mirror the server's rules for a group form.
 * @param draft
 */
export function validateGroup( draft ) {
	const errors = {};
	const name = String( draft?.name ?? '' ).trim();
	const code = String( draft?.code ?? '' ).trim();
	if ( ! name && ! code ) {
		errors.name = 'required';
	} else if ( name.length > GROUP_NAME_LIMIT ) {
		errors.name = 'too-long';
	}
	if ( code.length > 60 ) {
		errors.code = 'too-long';
	}
	return errors;
}

/**
 * A content item (topic or chapter) needs a name; its code is optional.
 * @param draft
 */
export function validateContent( draft ) {
	const errors = {};
	const name = String( draft?.name ?? '' ).trim();
	if ( ! name ) {
		errors.name = 'required';
	} else if ( name.length > GROUP_NAME_LIMIT ) {
		errors.name = 'too-long';
	}
	if ( String( draft?.code ?? '' ).trim().length > 60 ) {
		errors.code = 'too-long';
	}
	return errors;
}

export function validateSkill( draft ) {
	const errors = {};
	if ( ! String( draft?.name ?? '' ).trim() ) {
		errors.name = 'required';
	} else if ( String( draft.name ).trim().length > GROUP_NAME_LIMIT ) {
		errors.name = 'too-long';
	}
	if ( String( draft?.code ?? '' ).trim().length > SKILL_CODE_LIMIT ) {
		errors.code = 'too-long';
	}
	if ( String( draft?.description ?? '' ).length > 2000 ) {
		errors.description = 'too-long';
	}
	return errors;
}

/**
 * The counts line under the heading, e.g. "3 contents · 5 skill groups · 40 skills".
 * @param totals
 */
export function totalsParts( totals ) {
	return {
		contents: totals?.contents || 0,
		groups: totals?.groups || 0,
		skills: totals?.skills || 0,
	};
}

/**
 * Where a download should go: a file name made from the syllabus name, safe on every system.
 * @param name
 * @param suffix
 */
export function fileName( name, suffix ) {
	const base =
		String( name || 'syllabus' )
			.normalize( 'NFKD' )
			.replace( /[^\p{L}\p{N}]+/gu, '-' )
			.replace( /^-+|-+$/g, '' )
			.slice( 0, 60 ) || 'syllabus';
	return `${ base }-${ suffix }.csv`;
}

/**
 * Summary lines for a dry-run or applied report, in the order the dialog shows them.
 * @param report
 */
export function reportRows( report ) {
	const n = ( value ) => Number( value || 0 );
	return [
		{
			id: 'contents',
			create: n( report.contents?.create ),
			update: n( report.contents?.update ),
			same: n( report.contents?.unchanged ),
		},
		{
			id: 'groups',
			create: n( report.groups?.create ),
			update: n( report.groups?.update ),
			same: n( report.groups?.unchanged ),
		},
		{
			id: 'skills',
			create: n( report.skills?.create ),
			update: n( report.skills?.update ),
			move: n( report.skills?.move ),
			same: n( report.skills?.unchanged ),
		},
	];
}

export function changesNothing( report ) {
	return reportRows( report ).every(
		( row ) => ! row.create && ! row.update && ! row.move
	);
}
