/** OhMyLMS frontend extension API v1. Values are presentation state, never grading authority. */
export const apiVersion = 1;
const validators = new Map();
const questionMounts = new Map();
function register( registry, type, callback ) {
	if (
		! /^[a-z][a-z0-9_-]*$/.test( type ) ||
		typeof callback !== 'function'
	) {
		throw new TypeError(
			'A question type slug and callback are required.'
		);
	}
	if ( registry.has( type ) ) {
		throw new Error( `Already registered: ${ type }` );
	}
	registry.set( type, callback );
	return () => {
		if ( registry.get( type ) === callback ) {
			registry.delete( type );
		}
	};
}
export const registerAnswerValidator = ( type, callback ) =>
	register( validators, type, callback );
export const registerQuestionMount = ( type, callback ) =>
	register( questionMounts, type, callback );
export function isAnswered( root, type ) {
	if ( validators.has( type ) ) {
		return Boolean( validators.get( type )( root ) );
	}
	if (
		[ 'single-choice', 'multiple-choice', 'true-false' ].includes( type )
	) {
		return Boolean( root.querySelector( 'input:checked' ) );
	}
	if ( type === 'reorder' ) {
		return Boolean( root.querySelector( 'input[name]' ) );
	}
	if ( type === 'matching' ) {
		const fields = [ ...root.querySelectorAll( '.matching-answer-input' ) ];
		return (
			fields.length > 0 &&
			fields.every( ( field ) => field.value.trim() !== '' )
		);
	}
	const fields = [
		...root.querySelectorAll(
			'input:not([type=hidden]):not([type=radio]):not([type=checkbox]),textarea,select'
		),
	];
	return (
		fields.length > 0 &&
		fields.every( ( field ) => field.value.trim() !== '' )
	);
}
export function emit( root, name, detail = {}, cancelable = false ) {
	return root.dispatchEvent(
		new CustomEvent( `ohmylms:${ name }`, {
			bubbles: true,
			cancelable,
			detail: {
				apiVersion,
				...detail,
			},
		} )
	);
}

/**
 * Return a renderer's cleanup function, or null when no mount adapter is registered.
 * @param root
 * @param type
 * @param detail
 */
export function mountQuestion( root, type, detail ) {
	const callback = questionMounts.get( type );
	if ( ! callback ) {
		return null;
	}
	const cleanup = callback( root, detail );
	if ( cleanup === null || cleanup === void 0 ) {
		return () => {};
	}
	if ( typeof cleanup !== 'function' ) {
		throw new TypeError(
			'Question mounts must return a cleanup function.'
		);
	}
	return cleanup;
}
