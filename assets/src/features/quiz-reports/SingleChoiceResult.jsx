/** Reconstructed React source. Runtime dependencies are explicit in components.json. */
import { createElement } from '@wordpress/element';
import * as Controls from '../../shared/controls';
export function createSingleChoiceResult( readRuntime ) {
	return function SingleChoiceResult( props ) {
		const { React, b: I18n, p$: QuizQuestionHeader } = readRuntime();
		var t,
			n,
			data = props.data,
			index = props.index,
			o = ( function ( e ) {
				var _tLocal = e.given_answer,
					_nLocal = e.questions;
				if ( ! _tLocal || ! Array.isArray( _tLocal ) ) {
					return 'in-review';
				}
				var r = _nLocal
						.filter( function ( _eLocal ) {
							return '1' === _eLocal.is_correct;
						} )
						.map( function ( _eLocal2 ) {
							return _eLocal2.id;
						} ),
					a = _tLocal.every( function ( _eLocal3 ) {
						return r.includes( _eLocal3 );
					} );
				return ( () => {
					if ( 'single-choice' === e.settings.type ) {
						if ( a && 1 === _tLocal.length ) {
							return 'correct';
						}
						return 'incorrect';
					}
					if ( a && _tLocal.length === r.length ) {
						return 'correct';
					}
					return 'incorrect';
				} )();
			} )( data ),
			i =
				data === null ||
				data === void 0 ||
				null === ( t = data.questions ) ||
				void 0 === t
					? void 0
					: t.map( function ( e ) {
							return {
								label: e.answer,
								value: String( e.id ),
							};
						} );
		return (
			<React.Fragment>
				<div
					className={ 'ohmylms-question-types ohmylms-single-choice-question ohmylms-'.concat(
						o
					) }
				>
					<QuizQuestionHeader data={ data } index={ index } />
					<div className={ 'ohmylms-question-options-wrapper' }>
						<Controls.TextWP
							as={ 'p' }
							size={ 14 }
							variant={ 'muted' }
						>
							{ ( 0, I18n.__ )( 'Select single', 'ohmylms' ) }
						</Controls.TextWP>
						<Controls.SpacerWP marginBottom={ 2 } />
						<Controls.CardWP
							isBorderless={ ! 0 }
							variant={ 'secondary' }
							style={ {
								padding: '16px',
							} }
						>
							<Controls.FlexWP direction={ 'column' } gap={ 3 }>
								<Controls.RadioWP
									key={ index }
									selected={ ( () => {
										if (
											data !== null &&
											data !== void 0 &&
											data.given_answer
										) {
											if (
												data === null ||
												data === void 0
											) {
												return void 0;
											}
											return data.given_answer[ 0 ];
										}
										return null;
									} )() }
									disabled={ ! 0 }
									options={ i }
								/>
							</Controls.FlexWP>
						</Controls.CardWP>
						<Controls.SpacerWP />
						<Controls.CardWP
							style={ {
								padding: '16px',
							} }
						>
							<Controls.HeadingWP
								level={ 4 }
								size={ '14' }
								weight={ '400' }
							>
								{ ( 0, I18n.__ )( 'Answer of this question:' ) }
								<br />
								<strong>
									{ ( data === null ||
									data === void 0 ||
									null === ( n = data.questions ) ||
									void 0 === n ||
									null ===
										( n = n.find( function ( e ) {
											return (
												'1' ===
												( e === null || e === void 0
													? void 0
													: e.is_correct )
											);
										} ) ) ||
									void 0 === n
										? void 0
										: n.answer ) ||
										( 0, I18n.__ )( 'No correct answer' ) }
								</strong>
							</Controls.HeadingWP>
						</Controls.CardWP>
					</div>
				</div>
			</React.Fragment>
		);
	};
}
