import { showRequestError } from '../requestFeedback.mjs';
/** Reconstructed React source. Runtime dependencies are explicit in components.json. */
import { createElement } from '@wordpress/element';
export function createCourseImport( readRuntime ) {
	return function CourseImport( props ) {
		const {
			Gte,
			I: Controls,
			Kne: MemoScormImport,
			React,
			T: StoreModule,
			X0,
			Xne,
			Yne: MemoCourseMigration,
			b: I18n,
			ere,
			g: ReactHooks,
			ire,
			nre,
			rre,
			y: WordPressData,
		} = readRuntime();
		var t,
			n,
			r,
			a,
			o = props.onTabChange,
			i = props.onWizardSkip,
			l = ( 0, WordPressData.useDispatch )( StoreModule.default ),
			c = ( 0, WordPressData.useSelect )( function ( e ) {
				return e( StoreModule.default ).getSetupWizardData();
			}, [] ),
			u = rre(
				( 0, ReactHooks.useState )(
					! (
						c === null ||
						c === void 0 ||
						! c.skipPlatformSelection ||
						c === null ||
						c === void 0 ||
						! c.selectedPlatform
					)
				),
				2
			),
			s = u[ 0 ],
			d = u[ 1 ],
			m = rre( ( 0, ReactHooks.useState )( ! 1 ), 2 ),
			p = m[ 0 ],
			f = m[ 1 ],
			v = function () {
				if ( c !== null && c !== void 0 && c.skipPlatformSelection ) {
					{
						l.setSetupWizardData( {
							skipPlatformSelection: ! 1,
							selectedPlatform: null,
						} );
						o( 'wizard-niche' );
					}
				} else {
					d( ! 1 );
				}
			},
			h = ( function () {
				var e,
					_tLocal =
						( ( e = ere().m( function _eLocal( _tLocal2 ) {
							var _nLocal, _rLocal;
							return ere().w(
								function ( _eLocal2 ) {
									for (;;) {
										switch ( ( _eLocal2.p = _eLocal2.n ) ) {
											case 0:
												if ( _tLocal2 ) {
													_eLocal2.n = 1;
													break;
												}
												return (
													o( 'wizard-completion' ),
													_eLocal2.a( 2 )
												 );
											case 1:
												return (
													f( ! 0 ),
													( _eLocal2.p = 2 ),
													( _eLocal2.n = 3 ),
													l.importScormCourse(
														_tLocal2
													)
												 );
											case 3:
												if (
													( ( _nullableValue ) =>
														_nullableValue !==
															null &&
														_nullableValue !==
															void 0 )(
														( _nLocal = _eLocal2.v )
													) &&
													_nLocal.success
												) {
													o( 'wizard-completion' );
												}
												_eLocal2.n = 5;
												break;
											case 4:
												_eLocal2.p = 4;
												_rLocal = _eLocal2.v;
												showRequestError( _rLocal );
											// Falls through to the shared async state cleanup.
											case 5:
												return (
													( _eLocal2.p = 5 ),
													f( ! 1 ),
													_eLocal2.f( 5 )
												 );
											case 6:
												return _eLocal2.a( 2 );
										}
									}
								},
								_eLocal,
								null,
								[ [ 2, 4, 5, 6 ] ]
							);
						} ) ),
						function ( ..._args ) {
							var _tLocal3 = this,
								_nLocal2 = _args;
							return new Promise( function ( _rLocal2, _aLocal ) {
								var _oLocal = e.apply( _tLocal3, _nLocal2 );
								function _iLocal( _eLocal3 ) {
									nre(
										_oLocal,
										_rLocal2,
										_aLocal,
										_iLocal,
										_lLocal,
										'next',
										_eLocal3
									);
								}
								function _lLocal( _eLocal4 ) {
									nre(
										_oLocal,
										_rLocal2,
										_aLocal,
										_iLocal,
										_lLocal,
										'throw',
										_eLocal4
									);
								}
								_iLocal( void 0 );
							} );
						} );
				return function ( ..._args2 ) {
					return _tLocal.apply( this, _args2 );
				};
			} )(),
			_ = [].concat(
				Xne(
					null !== ( t = window ) &&
						void 0 !== t &&
						null !== ( t = t.ohmylms_params ) &&
						void 0 !== t &&
						t.is_tutor_lms_active
						? [
								{
									label: ( 0, I18n.__ )(
										'Tutor LMS',
										'ohmylms'
									),
									value: 'tutorLMS',
									icon: ire + 'tutor_icon.svg',
								},
							]
						: []
				),
				Xne(
					null !== ( n = window ) &&
						void 0 !== n &&
						null !== ( n = n.ohmylms_params ) &&
						void 0 !== n &&
						n.is_learndash_lms_active
						? [
								{
									label: ( 0, I18n.__ )(
										'LearnDash',
										'ohmylms'
									),
									value: 'learnDash',
									icon: ire + 'learndash_icon.svg',
								},
							]
						: []
				),
				Xne(
					null !== ( r = window ) &&
						void 0 !== r &&
						null !== ( r = r.ohmylms_params ) &&
						void 0 !== r &&
						r.is_learnpress_active
						? [
								{
									label: ( 0, I18n.__ )(
										'LearnPress',
										'ohmylms'
									),
									value: 'learnPress',
									icon: ire + 'learnpress_icon.svg',
								},
							]
						: []
				),
				Xne(
					null !== ( a = window ) &&
						void 0 !== a &&
						null !== ( a = a.ohmylms_params ) &&
						void 0 !== a &&
						a.is_masterstudy_active
						? [
								{
									label: ( 0, I18n.__ )(
										'MasterStudy LMS',
										'ohmylms'
									),
									value: 'masterStudy',
									icon: ire + 'masterstudy_icon.svg',
								},
							]
						: []
				)
			);
		return ( () => {
			if ( s ) {
				if (
					'scorm' ===
					( c === null || c === void 0 ? void 0 : c.selectedPlatform )
				) {
					return (
						<MemoScormImport
							onBack={ v }
							onContinue={ h }
							isLoading={ p }
						/>
					);
				}
				return (
					<MemoCourseMigration onTabChange={ o } handleBack={ v } />
				);
			}
			return (
				<React.Fragment>
					<Gte
						level={ c === null || c === void 0 ? void 0 : c.level }
						currentStep={
							'experienced' ===
								( c === null || c === void 0
									? void 0
									: c.level ) ||
							'intermediate' ===
								( c === null || c === void 0
									? void 0
									: c.level )
								? 2
								: 0
						}
						isShowIndicator={ ! 0 }
						onSkip={ function () {
							return i === null || i === void 0
								? void 0
								: i( 'course-creation' );
						} }
					/>
					<Controls.ContainerWP>
						<div
							className={
								'ohmylms-setup-wizard-level-selection-wrapper ohmylms-setup-wizard-card-wrapper'
							}
						>
							<div
								className={ 'ohmylms-setup-wizard__container' }
							>
								<div
									className={ 'ohmylms-setup-wizard__header' }
								>
									<Controls.HeadingWP
										as={ 'h2' }
										color={ '#000d25' }
										size={ '24' }
										align={ 'center' }
										weight={ '600' }
									>
										{ ( 0, I18n.__ )(
											'🤝 Your Migration Assistant',
											'ohmylms'
										) }
									</Controls.HeadingWP>
									<Controls.TextWP
										as={ 'p' }
										size={ '18' }
										color={ '#687784' }
										align={ 'center' }
										weight={ '400' }
										style={ {
											maxWidth: '400px',
											margin: 'auto',
										} }
									>
										{ ( 0, I18n.__ )(
											'Your data is safe. We migrate with care. ',
											'ohmylms'
										) }
									</Controls.TextWP>
								</div>
								<Controls.FlexWP
									direction={ 'column' }
									gap={ 6 }
								>
									<Controls.CardWP
										isBorderless={ ! 0 }
										style={ {
											width: '768px',
										} }
									>
										<Controls.SpacerWP
											padding={ 6 }
											marginBottom={ 0 }
										>
											<Controls.FlexWP
												gap={ 3 }
												direction={ 'column' }
											>
												<X0.A
													as={ 'h3' }
													size={ '18' }
													color={ '#000D25' }
													weight={ '600' }
												>
													{ ( 0, I18n.__ )(
														'Which platform do you want to migrate from?',
														'ohmylms'
													) }
												</X0.A>
												<Controls.FlexWP
													flexWrap={ 'wrap' }
													gap={ 3 }
													align={ 'start' }
													justify={ 'start' }
												>
													{ _.map(
														function (
															e,
															_tLocal4
														) {
															return (
																<Controls.CardWP
																	key={
																		_tLocal4
																	}
																	isBorderless={
																		! 0
																	}
																	style={ {
																		cursor: 'pointer',
																		border: '1px solid '.concat(
																			c.selectedPlatform ===
																				e.value
																				? '#6E42D3'
																				: 'transparent'
																		),
																		boxShadow:
																			'0 2px 3px 0 rgba(147, 130, 171, 0.05), 0 4px 5px 0 rgba(85, 85, 85, 0.04), 0 4px 5px 0 rgba(85, 85, 85, 0.03), 0 16px 16px 0 rgba(85, 85, 85, 0.02)',
																		position:
																			'relative',
																		width: '256px',
																	} }
																	onClick={ function () {
																		return l.setSetupWizardData(
																			{
																				selectedPlatform:
																					e.value,
																			}
																		);
																	} }
																>
																	<Controls.SpacerWP
																		padding={
																			4
																		}
																		marginBottom={
																			0
																		}
																	>
																		{ c.selectedPlatform ===
																			e.value && (
																			<div
																				className={
																					'ohmylms-setup-wizard__check'
																				}
																				style={ {
																					position:
																						'absolute',
																					height: '16px',
																					width: '16px',
																					right: '10px',
																					top: '10px',
																				} }
																			>
																				<svg
																					style={ {
																						display:
																							'block',
																						width: '100%',
																						height: '100%',
																					} }
																					fill={
																						'none'
																					}
																					preserveAspectRatio={
																						'none'
																					}
																					viewBox={
																						'0 0 16 16'
																					}
																				>
																					<rect
																						fill={
																							'#6E42D3'
																						}
																						height={
																							'14'
																						}
																						rx={
																							'7'
																						}
																						stroke={
																							'#6E42D3'
																						}
																						strokeWidth={
																							'2'
																						}
																						width={
																							'14'
																						}
																						x={
																							'1'
																						}
																						y={
																							'1'
																						}
																					/>
																					<path
																						d={
																							'M10.7113 5.06584L6.44327 9.33378L4.48718 7.37764C4.19258 7.08304 3.71485 7.08299 3.4202 7.37759C3.12556 7.67223 3.12556 8.14991 3.4202 8.44456L5.90976 10.9342C6.05124 11.0757 6.24313 11.1552 6.44322 11.1552H6.44327C6.64335 11.1552 6.83524 11.0757 6.97673 10.9343L11.7782 6.13286C12.0729 5.83821 12.0729 5.36053 11.7782 5.06589C11.4836 4.77124 11.0059 4.77119 10.7113 5.06584Z'
																						}
																						fill={
																							'white'
																						}
																					/>
																				</svg>
																			</div>
																		) }
																		<Controls.FlexWP
																			direction={
																				'column'
																			}
																			items={
																				'center'
																			}
																			justify={
																				'center'
																			}
																			gap={
																				5
																			}
																		>
																			<img
																				src={
																					e.icon
																				}
																				alt={
																					e.label
																				}
																				style={ {
																					width: '29px',
																					height: '29px',
																				} }
																			/>
																			<Controls.TextWP
																				as={
																					'p'
																				}
																				size={
																					'16'
																				}
																				weight={
																					'500'
																				}
																				color={
																					'#000D25'
																				}
																			>
																				{
																					e.label
																				}
																			</Controls.TextWP>
																		</Controls.FlexWP>
																	</Controls.SpacerWP>
																</Controls.CardWP>
															);
														}
													) }
												</Controls.FlexWP>
											</Controls.FlexWP>
										</Controls.SpacerWP>
									</Controls.CardWP>
								</Controls.FlexWP>
							</div>
						</div>
						<Controls.SpacerWP marginBottom={ 0 } marginTop={ 6 }>
							<Controls.FlexWP
								items={ 'center' }
								justify={ 'between' }
								gap={ 4 }
								style={ {
									maxWidth: '846px',
									justifyContent: 'space-between',
									margin: '0 auto',
								} }
							>
								<Controls.ButtonWP
									variant={ 'secondary' }
									onClick={ function () {
										o( 'wizard-niche' );
									} }
								>
									{ ( 0, I18n.__ )( 'Back', 'ohmylms' ) }
								</Controls.ButtonWP>
								<Controls.FlexWP
									items={ 'center' }
									justify={ 'end' }
									gap={ 3 }
								>
									<Controls.ButtonWP
										variant={ 'primary' }
										onClick={ function () {
											d( ! 0 );
										} }
										disabled={ ! c.selectedPlatform }
									>
										{ ( 0, I18n.__ )(
											'Continue',
											'ohmylms'
										) }
									</Controls.ButtonWP>
								</Controls.FlexWP>
							</Controls.FlexWP>
						</Controls.SpacerWP>
					</Controls.ContainerWP>
				</React.Fragment>
			);
		} )();
	};
}
