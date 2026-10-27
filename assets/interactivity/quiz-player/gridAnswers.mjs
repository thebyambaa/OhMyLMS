/**
 * A required grid needs a response for every row.
 *
 * @param {Array} rows Configured rows.
 * @param {Array} answers Collected scalar answer fields.
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
