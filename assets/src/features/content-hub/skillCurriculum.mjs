import { buildTree } from '../curriculum/model.mjs';

/**
 * Load syllabus placements with at most four concurrent requests.
 * @param {()=>Promise<Object>}          loadTree     Curriculum request.
 * @param {(id:number)=>Promise<Object>} loadSyllabus Syllabus request.
 * @return {Promise<Object>} Curriculum and placements.
 */
export async function loadSkillCurriculum( loadTree, loadSyllabus ) {
	const { items = [] } = await loadTree();
	const syllabuses = items.filter( ( item ) => item.is_syllabus );
	const outlines = [];
	let next = 0;
	await Promise.all(
		Array.from( { length: Math.min( 4, syllabuses.length ) }, async () => {
			while ( next < syllabuses.length ) {
				const item = syllabuses[ next++ ];
				outlines.push( await loadSyllabus( item.id ) );
			}
		} )
	);
	return { items, outlines };
}

/**
 * Keep curriculum ancestors and syllabus topics above selectable skills.
 * @param {Object} data     Loaded curriculum.
 * @param {string} search   Search words.
 * @param {Array}  excluded Skills already in a course.
 * @return {Object} Visible branches and all placed skill IDs.
 */
export function skillCurriculumTree( data, search = '', excluded = [] ) {
	const groups = new Map();
	const placed = new Set();
	const visible = new Set();
	const omit = new Set( excluded.map( Number ) );
	for ( const outline of data.outlines || [] ) {
		for ( const item of outline.contents || [] ) {
			for ( const group of item.groups || [] ) {
				groups.set( group.id, group );
				for ( const skill of group.skills || [] ) {
					placed.add( Number( skill.term_id ) );
				}
			}
		}
	}
	const byItem = new Map();
	for ( const group of groups.values() ) {
		const skills = ( group.skills || [] )
			.filter( ( skill ) => ! omit.has( Number( skill.term_id ) ) )
			.map( ( skill ) => ( {
				key: `skill:${ group.id }:${ skill.term_id }`,
				name: skill.name,
				code: skill.code,
				skill: { ...skill, id: Number( skill.term_id ) },
				children: [],
			} ) );
		const siblings = byItem.get( group.item_id ) || [];
		siblings.push( {
			key: `group:${ group.id }`,
			name: group.name,
			code: group.code,
			children: skills,
		} );
		byItem.set( group.item_id, siblings );
	}
	const expand = ( node ) => ( {
		key: `item:${ node.item.id }`,
		name: node.item.name,
		code: node.item.code,
		children: [
			...( byItem.get( node.item.id ) || [] ),
			...node.children.map( expand ),
		],
	} );
	const words = search
		.trim()
		.toLocaleLowerCase()
		.split( /\s+/ )
		.filter( Boolean );
	const filter = ( nodes, inherited = false ) =>
		nodes.flatMap( ( node ) => {
			const text =
				`${ node.code || '' } ${ node.name || '' }`.toLocaleLowerCase();
			const match =
				inherited || words.every( ( word ) => text.includes( word ) );
			if ( node.skill ) {
				if ( match ) {
					visible.add( node.skill.id );
				}
				return match ? [ node ] : [];
			}
			const children = filter( node.children, match );
			return children.length ? [ { ...node, children } ] : [];
		} );
	return {
		tree: filter( buildTree( data.items || [] ).map( expand ) ),
		placed,
		visible,
	};
}
