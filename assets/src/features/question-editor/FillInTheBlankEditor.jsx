/** Reconstructed React source. Runtime dependencies are explicit in components.json. */
import { createElement, useId } from '@wordpress/element';
import * as Controls from '../../shared/controls';
export function createFillInTheBlankEditor( readRuntime ) {
	return function FillInTheBlankEditor() {
		const _controlId = useId();
		const {
			Fd,
			React,
			T: StoreModule,
			b: I18n,
			y: WordPressData,
		} = readRuntime();
		var e;
		var t;
		var questionId = ( 0, WordPressData.useSelect )( function ( _eLocal ) {
			return _eLocal( StoreModule.default ).selectSelectedQuestionId();
		}, [] );
		var a =
			( 0, WordPressData.useSelect )(
				function ( _eLocal2 ) {
					return _eLocal2(
						StoreModule.default
					).getQuestionContents();
				},
				[ questionId ]
			) || [];
		var hasValidationErrors = ( 0, WordPressData.useSelect )( function (
			_eLocal3
		) {
			return _eLocal3( StoreModule.default ).selectQuizzesError();
		}, [] );
		var i = ( 0, WordPressData.useDispatch )( StoreModule.default );
		var addContentToQuestion = i.addContentToQuestion;
		const selected = WordPressData.useSelect(
			( select ) => select( StoreModule.default ).selectQuestion(),
			[]
		);
		const blanks = [
			...( selected?.name || '' ).matchAll( /\{([^{}<>]+)\}/gu ),
		].filter( ( match ) => match[ 1 ].trim() );
		const help = I18n.__(
			'Write answers in braces in the question above: The capital is {Paris}. Each brace pair becomes an inline blank, sized to its answer.',
			'ohmylms'
		);
		const caseSensitive = ! [ false, 0, '0' ].includes(
			selected?.settings?.case_sensitive
		);
		const caseSetting = (
			<label
				style={ {
					display: 'block',
					marginBottom: 16,
				} }
				htmlFor={ _controlId + '-field-1' }
			>
				<input
					type="checkbox"
					checked={ caseSensitive }
					onChange={ ( event ) =>
						i.updateQuestionData( questionId, {
							settings: {
								...selected.settings,
								case_sensitive: event.target.checked,
							},
						} )
					}
					id={ _controlId + '-field-1' }
				/>{ ' ' }
				{ I18n.__( 'Case-sensitive answers', 'ohmylms' ) }
				<small
					style={ {
						display: 'block',
						marginTop: 4,
					} }
				>
					{ I18n.__(
						'When off, Paris and paris are both accepted.',
						'ohmylms'
					) }
				</small>
			</label>
		);
		if ( blanks.length ) {
			return (
				<React.Fragment>
					<p>{ help }</p>
					{ caseSetting }
				</React.Fragment>
			);
		}
		return (
			<React.Fragment>
				<p>{ help }</p>
				{ caseSetting }
				<div
					className={
						'ohmylms-options-list ohmylms-options-list-statement'
					}
				>
					<Controls.InputWP
						placeholder={ ( 0, I18n.__ )(
							'Enter answer(s) here, separated by commas (e.g., Dhaka, teacher, football)',
							'ohmylms'
						) }
						value={
							null === ( e = a[ 0 ] ) || void 0 === e
								? void 0
								: e.answer
						}
						onChange={ function ( _eLocal4 ) {
							var _tLocal;
							return ( function ( _eLocal5, _tLocal2 ) {
								if ( _eLocal5 ) {
									addContentToQuestion(
										questionId,
										a.map( function ( _nLocal ) {
											return ( _nLocal === null ||
											_nLocal === void 0
												? void 0
												: _nLocal.id ) === _eLocal5
												? Fd(
														Fd( {}, _nLocal ),
														{},
														{
															answer: _tLocal2,
														}
													)
												: _nLocal;
										} )
									);
								}
							} )(
								null === ( _tLocal = a[ 0 ] ) ||
									void 0 === _tLocal
									? void 0
									: _tLocal.id,
								_eLocal4
							);
						} }
					/>
					{ ! hasValidationErrors ||
					( null !== ( t = a[ 0 ] ) &&
						void 0 !== t &&
						null !== ( t = t.answer ) &&
						void 0 !== t &&
						t.trim() ) ? (
						<React.Fragment />
					) : (
						<div
							className={ 'ohmylms-option-correct' }
							style={ {
								color: 'red',
								marginTop: 4,
							} }
						>
							{ ( 0, I18n.__ )(
								'Field cannot be empty',
								'ohmylms'
							) }
						</div>
					) }
				</div>
			</React.Fragment>
		);
	};
}
