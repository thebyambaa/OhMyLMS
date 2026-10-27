/** Reconstructed React source. Runtime dependencies are explicit in components.json. */
import { createElement } from '@wordpress/element';
export function createScormImport( readRuntime ) {
	return function ScormImport( props ) {
		const {
			Gte,
			I: Controls,
			Qne,
			React,
			T: StoreModule,
			b: I18n,
			g: ReactHooks,
			y: WordPressData,
		} = readRuntime();
		var t = props.onBack,
			n = props.onContinue,
			r = props.isLoading,
			a =
				( ( 0, WordPressData.useDispatch )( StoreModule.default ),
				( 0, WordPressData.useSelect )( function ( e ) {
					return e( StoreModule.default ).getSetupWizardData();
				}, [] ) ),
			o = Qne( ( 0, ReactHooks.useState )( null ), 2 ),
			i = o[ 0 ],
			l = o[ 1 ],
			c = Qne( ( 0, ReactHooks.useState )( null ), 2 ),
			u = c[ 0 ],
			s = c[ 1 ];
		return (
			<React.Fragment>
				<Gte
					level={ a === null || a === void 0 ? void 0 : a.level }
					currentStep={
						'experienced' ===
							( a === null || a === void 0 ? void 0 : a.level ) ||
						'intermediate' ===
							( a === null || a === void 0 ? void 0 : a.level )
							? 2
							: 0
					}
					isShowIndicator={ ! 0 }
				/>
				<Controls.ContainerWP>
					<div
						className={
							'ohmylms-setup-wizard-level-selection-wrapper ohmylms-setup-wizard-card-wrapper'
						}
					>
						<div className={ 'ohmylms-setup-wizard__container' }>
							<div className={ 'ohmylms-setup-wizard__header' }>
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
										'Your data is safe. We migrate with care.',
										'ohmylms'
									) }
								</Controls.TextWP>
							</div>
							<Controls.FlexWP direction={ 'column' } gap={ 6 }>
								<Controls.CardWP
									isBorderless={ ! 0 }
									style={ {
										width: '768px',
										backgroundColor: 'transparent',
									} }
								>
									<Controls.SpacerWP
										padding={ 6 }
										marginBottom={ 0 }
									>
										<Controls.FlexWP
											gap={ 4 }
											direction={ 'column' }
											items={ 'start' }
										>
											<Controls.HeadingWP
												as={ 'h3' }
												size={ '18' }
												color={ '#000D25' }
												weight={ '600' }
											>
												{ ( 0, I18n.__ )(
													'Upload your SCORM file',
													'ohmylms'
												) }
											</Controls.HeadingWP>
											<div
												style={ {
													backgroundColor: '#FFFFFF',
													borderRadius: '8px',
													width: '100%',
													height: '240px',
													display: 'flex',
													alignItems: 'center',
													justifyContent: 'center',
													border: u
														? '1px solid red'
														: '1px solid #EBEBEB',
												} }
											>
												<Controls.FormFileUploadWP
													accept={ '.zip' }
													onChange={ function ( e ) {
														var _tLocal =
															e.target.files[ 0 ];
														if ( ! _tLocal ) {
															return (
																l( null ),
																void s( null )
															 );
														}
														if (
															[
																'application/zip',
																'application/x-zip-compressed',
																'multipart/x-zip',
															].includes(
																_tLocal.type
															) ||
															_tLocal.name
																.toLowerCase()
																.endsWith(
																	'.zip'
																)
														) {
															{
																l( _tLocal );
																s( null );
															}
														} else {
															{
																l( null );
																s(
																	( 0,
																	I18n.__ )(
																		'Invalid file format. Please upload a valid ZIP file containing a SCORM package.',
																		'ohmylms'
																	)
																);
															}
														}
													} }
													render={ function ( e ) {
														var _tLocal2 =
															e.openFileDialog;
														return (
															<Controls.ButtonWP
																variant={
																	'white'
																}
																onClick={
																	_tLocal2
																}
																style={ {
																	border: '1px solid #E0E0E0',
																	boxShadow:
																		'0 1px 2px rgba(0,0,0,0.05)',
																} }
															>
																<Controls.FlexWP
																	gap={ 2 }
																	items={
																		'center'
																	}
																>
																	<svg
																		width={
																			'14'
																		}
																		height={
																			'14'
																		}
																		viewBox={
																			'0 0 14 14'
																		}
																		fill={
																			'none'
																		}
																		xmlns={
																			'http://www.w3.org/2000/svg'
																		}
																	>
																		<path
																			d={
																				'M6.16669 3.5V10.5C6.16669 11.4665 6.9502 12.25 7.91669 12.25C8.88319 12.25 9.66669 11.4665 9.66669 10.5V3.20833C9.66669 2.564 9.14436 2.04167 8.50002 2.04167C7.85569 2.04167 7.33335 2.564 7.33335 3.20833V9.33333C7.33335 9.65567 7.59469 9.91667 7.91669 9.91667C8.23869 9.91667 8.50002 9.65567 8.50002 9.33333V3.5H9.66669V9.33333C9.66669 10.3 8.88335 11.0833 7.91669 11.0833C6.95002 11.0833 6.16669 10.3 6.16669 9.33333V3.20833C6.16669 1.91917 7.21085 0.875 8.50002 0.875C9.78919 0.875 10.8334 1.91917 10.8334 3.20833V10.5C10.8334 12.1108 9.52752 13.4167 7.91669 13.4167C6.30585 13.4167 5.00002 12.1108 5.00002 10.5V3.5H6.16669Z'
																			}
																			fill={
																				'#687784'
																			}
																		/>
																	</svg>
																	{ i
																		? i.name
																		: ( 0,
																			I18n.__ )(
																				'Add files',
																				'ohmylms'
																			) }
																</Controls.FlexWP>
															</Controls.ButtonWP>
														);
													} }
												/>
											</div>
											{ u ? (
												<Controls.TextWP
													as={ 'p' }
													size={ '14' }
													color={ 'red' }
													style={ {
														marginTop: '-8px',
													} }
												>
													{ u }
												</Controls.TextWP>
											) : (
												<Controls.TextWP
													as={ 'p' }
													size={ '14' }
													color={ '#687784' }
													style={ {
														marginTop: '-8px',
													} }
												>
													{ ( 0, I18n.__ )(
														'Upload your SCORM package (.zip) to import your course. Your content will remain private until you confirm the migration.',
														'ohmylms'
													) }
												</Controls.TextWP>
											) }
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
								onClick={ t }
							>
								{ ( 0, I18n.__ )( 'Back', 'ohmylms' ) }
							</Controls.ButtonWP>
							<Controls.FlexWP
								items={ 'center' }
								justify={ 'end' }
								gap={ 6 }
							>
								<Controls.TextWP
									as={ 'span' }
									size={ '14' }
									color={ '#687784' }
									style={ {
										textDecoration: 'underline',
										cursor: 'pointer',
									} }
									onClick={ function () {
										return n( null );
									} }
								>
									{ ( 0, I18n.__ )(
										'Skip this step',
										'ohmylms'
									) }
								</Controls.TextWP>
								<Controls.ButtonWP
									variant={ 'primary' }
									onClick={ function () {
										return n( i );
									} }
									disabled={ ! i || r }
									isBusy={ r }
								>
									{ ( 0, I18n.__ )( 'Continue', 'ohmylms' ) }
								</Controls.ButtonWP>
							</Controls.FlexWP>
						</Controls.FlexWP>
					</Controls.SpacerWP>
				</Controls.ContainerWP>
			</React.Fragment>
		);
	};
}
