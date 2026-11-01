/**
 * A required grid needs a response for every row.
 *
 * @param {Array<{id: unknown}>}                rows    Configured rows.
 * @param {Array<{id: unknown, value: string}>} answers Collected scalar answer fields.
 * @return {boolean} Whether every row is answered.
 */
export function gridRowsAnswered( rows, answers ) {
	return (
		rows.length > 0 &&
		rows.every( ( row ) =>
			answers.some(
				( answer ) => answer.id === row.id && answer.value.trim() !== ''
			)
		)
	);
}
