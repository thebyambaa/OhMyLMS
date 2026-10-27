/**
 * Remember a private draft independently of the public reference document.
 *
 * @param {Object} history  Local undo stacks.
 * @param {Object} question Current private draft.
 */
export function rememberQuestion( history, question ) {
	history.past.push( structuredClone( question ) );
	history.past = history.past.slice( -50 );
	history.future = [];
}

/**
 * Restore a local edit, reconciling option identities assigned during autosave.
 * Deleted options become new options; immutable historical versions stay unchanged.
 *
 * @param {Object} history   Local undo stacks.
 * @param {Object} current   Current draft with canonical option identities.
 * @param {string} direction Either past or future.
 * @return {Object|null} Restored draft.
 */
export function stepQuestionHistory( history, current, direction ) {
	const next = history[ direction ].pop();
	if ( ! next ) {
		return null;
	}
	history[ direction === 'past' ? 'future' : 'past' ].push(
		structuredClone( current )
	);
	return {
		...next,
		id: current.id,
		questions: ( next.questions || [] ).map( ( option, index ) => {
			const key = option.clientKey ?? option.id;
			const saved = ( current.questions || [] ).find(
				( candidate ) => ( candidate.clientKey ?? candidate.id ) === key
			);
			return saved
				? { ...option, id: saved.id, clientKey: key, temp: saved.temp }
				: {
						...option,
						id: Date.now() + index,
						clientKey: key,
						temp: true,
					};
		} ),
	};
}
