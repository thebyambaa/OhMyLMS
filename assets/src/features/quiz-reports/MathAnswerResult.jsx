import { ExtendedResponse } from './ExtendedResponse';
import { isExtendedType } from '../question-editor/extendedModel.mjs';
import { createElement, Fragment } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { describeExpected, setPartMark, structuredRows } from './model.mjs';
import { MathDisplay } from '../math/MathDisplay';

/**
 * Report view for numerical, structured and other extension question types: the learner's
 * response, the expected answer and, for structured questions, each part with its marking
 * notes. Structured parts can be marked one by one (sent as part_marks, which take precedence);
 * a whole-question mark in the header keeps automatic part scores and gives the remainder to
 * written parts.
 * @param root0
 * @param root0.data
 * @param root0.index
 * @param root0.setData
 * @param root0.fetchData
 * @param root0.Header
 * @param root0.Controls
 */
export function MathAnswerResult( {
	data,
	index,
	setData,
	fetchData,
	Header,
	Controls,
} ) {
	const type = data?.settings?.type;
	const given = data?.given_answer;
	const response = () => {
		if ( type === 'dropdown-blanks' ) {
			return Object.entries( given || {} ).map( ( [ id, value ] ) => (
				<div key={ id }>
					{ id }:{ ' ' }
					{ Array.isArray( value )
						? value.join( ', ' )
						: String( value ) }
				</div>
			) );
		}
		if ( type === 'expression' ) {
			return (
				<MathDisplay
					source={ Object.values( given || {} )[ 0 ] || '' }
				/>
			);
		}
		if ( type === 'multi-blank' ) {
			return Object.entries( given || {} ).map( ( [ id, value ] ) => (
				<div key={ id }>
					{ id }:{ ' ' }
					{ data.settings?.blanks?.[ id ]?.kind === 'expression' ? (
						<MathDisplay source={ String( value ) } />
					) : (
						String( value )
					) }
				</div>
			) );
		}
		if ( isExtendedType( type ) ) {
			return (
				<ExtendedResponse
					type={ type }
					answer={ given || {} }
					settings={ data.settings }
				/>
			);
		}
		if ( Array.isArray( given ) && given.length ) {
			return given.join( ', ' );
		}
		if ( given && typeof given === 'object' ) {
			return JSON.stringify( given );
		}
		return __( 'No answer given', 'ohmylms' );
	};
	return (
		<div
			className={ `ohmylms-question-types ohmylms-text-type-question ohmylms-${ data?.status || '' }` }
		>
			<Header
				setData={ setData }
				data={ data }
				index={ index }
				type="text-type"
				fetchData={ fetchData }
			/>
			<Controls.CardWP
				isBorderless
				variant="secondary"
				style={ {
					padding: '16px',
				} }
			>
				{ [ 'structured', 'passage' ].includes( type ) ? (
					<table className="widefat striped ohmylms-structured-result">
						<thead>
							<tr>
								<th>{ __( 'Part', 'ohmylms' ) }</th>
								<th>
									{ __( "Student's response", 'ohmylms' ) }
								</th>
								<th>
									{ __(
										'Expected / marking notes',
										'ohmylms'
									) }
								</th>
								<th>{ __( 'Marks', 'ohmylms' ) }</th>
							</tr>
						</thead>
						<tbody>
							{ structuredRows( data ).map( ( row ) => (
								<tr key={ row.id }>
									<td>
										<strong>{ row.label }</strong>{ ' ' }
										{ row.prompt }{ ' ' }
										<span>
											{ sprintf(
												/* translators: %s: awarded marks. */ __(
													'[%s marks]',
													'ohmylms'
												),
												row.marks
											) }
										</span>
									</td>
									<td>
										{ row.given ||
											__( 'No answer given', 'ohmylms' ) }
									</td>
									<td>{ row.expected }</td>
									<td>
										{ row.max === null ? (
											'—'
										) : (
											<Fragment>
												<input
													type="number"
													min={ 0 }
													max={ row.max }
													step="0.25"
													aria-label={ sprintf(
														/* translators: %s: question part identifier. */ __(
															'Marks for part %s',
															'ohmylms'
														),
														row.label
													) }
													value={ row.awarded ?? '' }
													placeholder={
														row.kind === 'written'
															? __(
																	'to mark',
																	'ohmylms'
																)
															: ''
													}
													onChange={ ( event ) =>
														setData( ( attempt ) =>
															setPartMark(
																attempt,
																data.id,
																row.id,
																event.target
																	.value
															)
														)
													}
													style={ {
														width: 72,
													} }
												/>{ ' ' }
												/ { row.max }
											</Fragment>
										) }
									</td>
								</tr>
							) ) }
						</tbody>
					</table>
				) : (
					<Fragment>
						<Controls.TextWP as="p" size={ 14 } variant="muted">
							{ __( "Student's response:", 'ohmylms' ) }
						</Controls.TextWP>
						<div className="ohmylms-question-options ohmylms-text-type">
							{ response() }
						</div>
						<p>
							<strong>{ __( 'Expected:', 'ohmylms' ) }</strong>{ ' ' }
							{ type === 'expression' ? (
								<MathDisplay
									source={ data.settings?.answer || '' }
								/>
							) : (
								describeExpected( data )
							) }
						</p>
					</Fragment>
				) }
				{ data?.instance && (
					<p className="ohmylms-instance-note">
						{ __( 'Numbers this learner was given:', 'ohmylms' ) }{ ' ' }
						{ Object.entries( data.instance.params || {} )
							.map(
								( [ name, value ] ) => `${ name } = ${ value }`
							)
							.join( ', ' ) }
					</p>
				) }
				{ data?.version && (
					<p className="ohmylms-version-note">
						{ sprintf(
							/* translators: %d: published version number. */ __(
								'Graded against version %d of this question',
								'ohmylms'
							),
							data.version.number
						) }
						{ data.version.migration_snapshot
							? ` · ${ __( 'captured at migration', 'ohmylms' ) }`
							: '' }
					</p>
				) }
			</Controls.CardWP>
		</div>
	);
}
