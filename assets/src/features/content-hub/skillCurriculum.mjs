import { buildTree } from '../curriculum/public.mjs';

/** @typedef {ReturnType<typeof buildTree>[number]} CurriculumNode */
/** @typedef {CurriculumNode['item']} CurriculumItem */

/**
 * @typedef {Object} PlacedSkill
 * @property {number|string} term_id Skill term ID.
 * @property {string}        [name]  Skill name.
 * @property {string}        [code]  Skill code.
 */

/**
 * @typedef {Object} SkillGroup
 * @property {number}        id       Group ID.
 * @property {number}        item_id  Curriculum item the group sits under.
 * @property {string}        [name]   Group name.
 * @property {string}        [code]   Group code.
 * @property {PlacedSkill[]} [skills] Skills in the group.
 */

/**
 * @typedef {Object} SyllabusOutline
 * @property {Array<{groups?: SkillGroup[]}>} [contents] Syllabus contents.
 */

/**
 * @typedef {Object} SkillCurriculum
 * @property {CurriculumItem[]}  [items]    Curriculum items.
 * @property {SyllabusOutline[]} [outlines] Syllabus placements.
 */

/**
 * @typedef {Object} SkillBranch
 * @property {string}                     key      Unique key.
 * @property {string}                     [name]   Display name.
 * @property {string}                     [code]   Display code.
 * @property {PlacedSkill & {id: number}} [skill]  The skill, on skill leaves.
 * @property {SkillBranch[]}              children Nested branches.
 */

/**
 * Load syllabus placements with at most four concurrent requests.
 * @param {()=>Promise<unknown>}          loadTree     Curriculum request.
 * @param {(id:number)=>Promise<unknown>} loadSyllabus Syllabus request.
 * @return {Promise<{items: CurriculumItem[], outlines: SyllabusOutline[]}>} Curriculum and placements.
 */
export async function loadSkillCurriculum( loadTree, loadSyllabus ) {
	const { items = [] } = /** @type {SkillCurriculum} */ ( await loadTree() );
	const syllabuses = items.filter( ( item ) => item.is_syllabus );
	/** @type {SyllabusOutline[]} */
	const outlines = [];
	let next = 0;
	await Promise.all(
		Array.from( { length: Math.min( 4, syllabuses.length ) }, async () => {
			while ( next < syllabuses.length ) {
				const item = syllabuses[ next++ ];
				outlines.push(
					/** @type {SyllabusOutline} */ (
						await loadSyllabus( item.id )
					)
				);
			}
		} )
	);
	return { items, outlines };
}

/**
 * Keep curriculum ancestors and syllabus topics above selectable skills.
 * @param {SkillCurriculum}      data       Loaded curriculum.
 * @param {string}               [search]   Search words.
 * @param {Array<number|string>} [excluded] Skills already in a course.
 * @return {{tree: SkillBranch[], placed: Set<number>, visible: Set<number>}} Visible branches and all placed skill IDs.
 */
export function skillCurriculumTree( data, search = '', excluded = [] ) {
	/** @type {Map<number, SkillGroup>} */
	const groups = new Map();
	/** @type {Set<number>} */
	const placed = new Set();
	/** @type {Set<number>} */
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
	/** @type {Map<number, SkillBranch[]>} */
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
	/** @type {( node: CurriculumNode ) => SkillBranch} */
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
	/** @type {( nodes: SkillBranch[], inherited?: boolean ) => SkillBranch[]} */
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
