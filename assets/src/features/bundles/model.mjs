/** Pure helpers for the Course Bundle editor. The server re-validates every rule. */

export const TITLE_LIMIT = 190;
export const DESCRIPTION_LIMIT = 2000;
export const MAX_MEMBERS = 100;

export const memberKey = ( member ) => `${ member.type }:${ member.id }`;

/**
 * Append a member unless the bundle already has it.
 * @param members
 * @param member
 */
export function addMember( members, member ) {
	if (
		members.some(
			( existing ) => memberKey( existing ) === memberKey( member )
		)
	) {
		return members;
	}
	return [ ...members, member ];
}

export function removeMember( members, key ) {
	return members.filter( ( member ) => memberKey( member ) !== key );
}

/**
 * Move one member up (-1) or down (+1); out-of-range moves return the list unchanged.
 * @param members
 * @param index
 * @param delta
 */
export function moveMember( members, index, delta ) {
	const target = index + delta;
	if (
		index < 0 ||
		index >= members.length ||
		target < 0 ||
		target >= members.length
	) {
		return members;
	}
	const next = [ ...members ];
	[ next[ index ], next[ target ] ] = [ next[ target ], next[ index ] ];
	return next;
}

/**
 * Same members in the same order?
 * @param left
 * @param right
 */
export function sameMembers( left, right ) {
	return (
		left.length === right.length &&
		left.every(
			( member, index ) =>
				memberKey( member ) === memberKey( right[ index ] )
		)
	);
}

export function draftFrom( bundle ) {
	return {
		title: bundle?.title ?? '',
		description: bundle?.description ?? '',
	};
}

export function detailsDirty( draft, bundle ) {
	return (
		draft.title.trim() !== ( bundle?.title ?? '' ) ||
		draft.description !== ( bundle?.description ?? '' )
	);
}

export function validateBundle( draft ) {
	const errors = {};
	const title = String( draft?.title ?? '' ).trim();
	if ( ! title ) {
		errors.title = 'required';
	} else if ( title.length > TITLE_LIMIT ) {
		errors.title = 'too-long';
	}
	if ( String( draft?.description ?? '' ).length > DESCRIPTION_LIMIT ) {
		errors.description = 'too-long';
	}
	return errors;
}

/**
 * Why a bundle cannot be published yet, or null.
 * @param bundle
 * @param members
 * @param dirty
 */
export function publishBlocker( bundle, members, dirty ) {
	if ( dirty ) {
		return 'unsaved';
	}
	if ( ! members.length ) {
		return 'empty';
	}
	if ( ! String( bundle?.title ?? '' ).trim() ) {
		return 'title';
	}
	return null;
}

/**
 * Payload for the members endpoint.
 * @param members
 */
export function memberPayload( members ) {
	return members.map( ( member ) => ( {
		type: member.type,
		id: member.id,
	} ) );
}
