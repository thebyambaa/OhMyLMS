/**
 * Pure helpers for the Content Hub catalog. The catalog is the course's learning-program draft viewed as
 * chapters, the skills placed in them, and the lessons, quizzes and assessments attached to a chapter or
 * to chosen skills. Everything returns new arrays; nothing mutates its input.
 */
export const MODES = [ 'skill-based', 'blended', 'traditional' ];
export const CONTENT_TYPES = [ 'lesson', 'quiz', 'assignment' ];
export const DEFAULT_PASS_PERCENT = 80;

const UUID = /^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/;
export const isUuid = ( value ) =>
	typeof value === 'string' && UUID.test( value );

let counter = 0;
/** A client-only key for something not saved yet; the server assigns the real identifier. */
export const temporaryId = () => `tmp-${ ++counter }`;

const chapterOf = ( row ) => Number( row.chapter_id ) || 0;

// ---- Skills ------------------------------------------------------------------------------------------

export const skillsIn = ( skills, chapterId ) =>
	skills.filter(
		( skill ) => chapterOf( skill ) === ( Number( chapterId ) || 0 )
	);

/**
 * Add library skills to a chapter (0 = unplaced). Skills already in the course are ignored.
 * @param skills
 * @param picked
 * @param chapterId
 * @param mode
 */
export function addSkills( skills, picked, chapterId, mode ) {
	const have = new Set( skills.map( ( skill ) => skill.term_id ) );
	const fresh = [];
	for ( const pick of picked ) {
		if ( have.has( pick.id ) ) {
			continue;
		}
		have.add( pick.id );
		fresh.push( {
			term_id: pick.id,
			name: pick.name,
			code: pick.code || '',
			questions: pick.questions || 0,
			target: 'proficient',
			required: mode !== 'traditional',
			chapter_id: Number( chapterId ) || 0,
			group_id: '',
			missing: false,
		} );
	}
	return [ ...skills, ...fresh ];
}

/**
 * Apply a patch to a row. A row that changes unit leaves its lesson group, which belongs to one unit.
 * @param row
 * @param patch
 */
function patchRow( row, patch ) {
	const next = { ...row, ...patch };
	if ( 'chapter_id' in patch && chapterOf( patch ) !== chapterOf( row ) ) {
		next.group_id = '';
	}
	return next;
}

export const updateSkill = ( skills, termId, patch ) =>
	skills.map( ( skill ) =>
		skill.term_id === termId ? patchRow( skill, patch ) : skill
	);

/**
 * Move a skill up (-1) or down (+1) among the skills of its own chapter.
 * @param skills
 * @param termId
 * @param delta
 */
export function moveSkill( skills, termId, delta ) {
	const skill = skills.find( ( row ) => row.term_id === termId );
	if ( ! skill ) {
		return skills;
	}
	const siblings = skillsIn( skills, chapterOf( skill ) );
	const from = siblings.findIndex( ( row ) => row.term_id === termId );
	const to = from + delta;
	if ( to < 0 || to >= siblings.length ) {
		return skills;
	}
	const other = siblings[ to ];
	const next = skills.slice();
	const a = next.findIndex( ( row ) => row.term_id === termId );
	const b = next.findIndex( ( row ) => row.term_id === other.term_id );
	[ next[ a ], next[ b ] ] = [ next[ b ], next[ a ] ];
	return next;
}

/**
 * Move a skill to the end of another chapter.
 * @param skills
 * @param termId
 * @param chapterId
 */
export function placeSkill( skills, termId, chapterId ) {
	const skill = skills.find( ( row ) => row.term_id === termId );
	if ( ! skill || chapterOf( skill ) === ( Number( chapterId ) || 0 ) ) {
		return skills;
	}
	return [
		...skills.filter( ( row ) => row.term_id !== termId ),
		patchRow( skill, { chapter_id: Number( chapterId ) || 0 } ),
	];
}

/**
 * Remove a skill from the course. Content scoped to it loses that scope but stays attached.
 * @param skills
 * @param attachments
 * @param termId
 */
export function removeSkill( skills, attachments, termId ) {
	return {
		skills: skills.filter( ( skill ) => skill.term_id !== termId ),
		attachments: attachments.map( ( item ) =>
			item.skill_ids.includes( termId )
				? {
						...item,
						skill_ids: item.skill_ids.filter(
							( id ) => id !== termId
						),
					}
				: item
		),
	};
}

// ---- Attachments -------------------------------------------------------------------------------------

/**
 * Content shown under a chapter heading (no skill scope) or under a skill row (scoped to that skill).
 * Content scoped to several skills appears under each of them.
 * @param attachments
 * @param root0
 * @param root0.chapterId
 * @param root0.skillId
 */
export function attachmentsFor( attachments, { chapterId, skillId } ) {
	if ( skillId ) {
		return attachments.filter( ( item ) =>
			item.skill_ids.includes( skillId )
		);
	}
	return attachments.filter(
		( item ) =>
			! item.skill_ids.length &&
			chapterOf( item ) === ( Number( chapterId ) || 0 )
	);
}

/**
 * Attach picked content once each. `scope` is `{chapterId, skillIds}`; a skill scope wins over the chapter.
 * @param attachments
 * @param picks
 * @param scope
 * @param options
 */
export function addAttachments( attachments, picks, scope, options = {} ) {
	const have = new Set( attachments.map( ( item ) => item.content_id ) );
	const fresh = [];
	for ( const pick of picks ) {
		if ( have.has( pick.id ) ) {
			continue;
		}
		have.add( pick.id );
		fresh.push( {
			id: temporaryId(),
			type: pick.type,
			content_id: pick.id,
			title: pick.title,
			status: pick.status || 'draft',
			required: Boolean( options.required ),
			pass_percent:
				pick.type === 'quiz'
					? Number( options.passPercent ?? DEFAULT_PASS_PERCENT )
					: null,
			chapter_id: Number( scope.chapterId ) || 0,
			skill_ids: [ ...new Set( scope.skillIds || [] ) ],
			group_id: '',
			also_in: [],
		} );
	}
	return [ ...attachments, ...fresh ];
}

export const updateAttachment = ( attachments, id, patch ) =>
	attachments.map( ( item ) =>
		item.id === id ? patchRow( item, patch ) : item
	);

export const removeAttachment = ( attachments, id ) =>
	attachments.filter( ( item ) => item.id !== id );

// ---- Saving ------------------------------------------------------------------------------------------

/**
 * What the server stores: skills in order with their chapter, and attachments with their scope.
 * @param root0
 * @param root0.mode
 * @param root0.skills
 * @param root0.attachments
 */
export function toSavePayload( { mode, skills, attachments } ) {
	return {
		mode,
		outcomes: skills.map( ( skill ) => ( {
			term_id: skill.term_id,
			target: skill.target,
			required: Boolean( skill.required ),
			chapter_id: chapterOf( skill ),
			...( skill.group_id ? { group_id: skill.group_id } : {} ),
		} ) ),
		attachments: attachments.map( ( item ) => ( {
			...( isUuid( item.id ) ? { id: item.id } : {} ),
			type: item.type,
			content_id: item.content_id,
			chapter_id: chapterOf( item ),
			...( item.group_id ? { group_id: item.group_id } : {} ),
			skill_ids: item.skill_ids,
			required: Boolean( item.required ),
			...( item.type === 'quiz'
				? {
						pass_percent: Number(
							item.pass_percent ?? DEFAULT_PASS_PERCENT
						),
					}
				: {} ),
		} ) ),
	};
}

/**
 * After a save, keep what the person is looking at and take only what the server decided: identifiers of
 * newly attached content (matched by content, which a course places once) and the publish state.
 * @param current
 * @param saved
 */
export function mergeSaved( current, saved ) {
	const ids = new Map(
		saved.attachments.map( ( item ) => [ item.content_id, item.id ] )
	);
	return {
		...current,
		course: saved.course,
		published_version: saved.published_version,
		unpublished_changes: saved.unpublished_changes,
		attachments: current.attachments.map( ( item ) =>
			isUuid( item.id )
				? item
				: { ...item, id: ids.get( item.content_id ) || item.id }
		),
	};
}

export function stats( catalog ) {
	const chapters = catalog.chapters.length;
	return {
		chapters,
		skills: catalog.skills.length,
		attachments: catalog.attachments.length,
		unplacedSkills: skillsIn( catalog.skills, 0 ).length,
	};
}
