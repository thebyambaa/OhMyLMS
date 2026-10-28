import { buildOutline, topicLabel } from '../curriculum/workspace.mjs';

/**
 * Reuse Curriculum's actual ordering and parent relationships for picker rows.
 * @param {Object} outline Saved syllabus outline.
 * @return {Object[]} Skills with their complete branch paths.
 */
export function syllabusSkillRows( outline ) {
	const root = buildOutline( outline ).root;
	const rows = [];
	function visit( node, path ) {
		if ( node.kind === 'skill' ) {
			rows.push( {
				...node.skill,
				syllabus_id: outline.syllabus.id,
				path,
			} );
			return;
		}
		const label =
			node.kind === 'group'
				? node.group.name
				: topicLabel( node.content );
		const next = [
			...path,
			{ id: `${ outline.syllabus.id }:${ node.key }`, label },
		];
		for ( const child of node.children ) {
			visit( child, next );
		}
	}
	if ( root ) {
		visit( root, [] );
	}
	return rows;
}

/**
 * Group the filtered rows without flattening their parent paths.
 * @param {Object[]} rows Skill rows in curriculum order.
 * @return {Object} Root with branches and leaves.
 */
export function skillPickerTree( rows ) {
	const root = { children: new Map(), rows: [], skills: [] };
	for ( const row of rows ) {
		let branch = root;
		for ( const part of row.path ) {
			if ( ! branch.children.has( part.id ) ) {
				branch.children.set( part.id, {
					...part,
					children: new Map(),
					rows: [],
					skills: [],
				} );
			}
			branch = branch.children.get( part.id );
			branch.rows.push( row );
		}
		branch.skills.push( row );
	}
	return root;
}
