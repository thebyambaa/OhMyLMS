/** Reconstructed React source. Runtime dependencies are explicit in components.json. */
import { createElement } from '@wordpress/element';
export function createAssignmentSettings( readRuntime ) {
	return function AssignmentSettings( props ) {
		const {
			Aa,
			Ca,
			Dn: DownloadResources,
			Ea,
			Fa,
			Gn: DeleteLearningItem,
			I: Controls,
			Kt,
			Ma,
			Oa,
			Pn: DripSettings,
			React,
			Rt,
			T: StoreModule,
			Ta,
			_n,
			b: I18n,
			cn: PrerequisiteSettings,
			g: ReactHooks,
			l,
			sn,
			y: WordPressData,
		} = readRuntime();
		var t;
		var n;
		var r;
		var a;
		var o;
		var i;
		var c;
		var u;
		var s;
		var d;
		var m;
		var p;
		var f;
		var assignment = props.assignment;
		var chapterId = props.chapterId;
		var setOpenModal = props.setOpenModal;
		var E =
			'cohort-based' ===
			( 0, WordPressData.useSelect )( function ( e ) {
				return e( StoreModule.default ).getCourseType();
			}, [] );
		var S = ( 0, WordPressData.useDispatch )( StoreModule.default );
		var byId = ( 0, WordPressData.useSelect )( function ( e ) {
			var _tLocal,
				_nLocal = e( StoreModule.default ).getCourseChapters();
			return (
				null === ( _tLocal = e( StoreModule.default ).getCourse() ) ||
					void 0 === _tLocal ||
					_tLocal.settings,
				{
					byId: _nLocal.byId,
				}
			 );
		}, [] ).byId;
		var x = byId && byId[ chapterId ];
		var C = ( function () {
			var e,
				_tLocal2 =
					( ( e = Ta().m( function _eLocal( _tLocal3 ) {
						var _nLocal2, _rLocal, _aLocal, _oLocal;
						return Ta().w( function ( _eLocal2 ) {
							for (;;) {
								switch ( _eLocal2.n ) {
									case 0:
										return (
											( _eLocal2.n = 1 ),
											l()( {
												path: '/ohmylms/v1/chapters/'
													.concat(
														chapterId,
														'/search-contents?term='
													)
													.concat( _tLocal3 ),
												method: 'GET',
												headers: {
													'Content-Type':
														'application/json',
												},
											} )
										 );
									case 1:
										return (
											( _nLocal2 = _eLocal2.v ),
											( _rLocal =
												x === null || x === void 0
													? void 0
													: x.content.map( String ) ),
											( _aLocal = _rLocal.indexOf(
												String( assignment.id )
											) ),
											( _oLocal = _nLocal2.data.filter(
												function ( _eLocal3 ) {
													return (
														String(
															_eLocal3 === null ||
																_eLocal3 ===
																	void 0
																? void 0
																: _eLocal3.value
														) !==
															String(
																assignment.id
															) &&
														_rLocal.indexOf(
															String(
																_eLocal3 ===
																	null ||
																	_eLocal3 ===
																		void 0
																	? void 0
																	: _eLocal3.value
															)
														) < _aLocal
													);
												}
											) ),
											_eLocal2.a( 2, {
												data: _oLocal,
											} )
										 );
								}
							}
						}, _eLocal );
					} ) ),
					function ( ..._args ) {
						var _tLocal4 = this,
							_nLocal3 = _args;
						return new Promise( function ( _rLocal2, _aLocal2 ) {
							var _oLocal2 = e.apply( _tLocal4, _nLocal3 );
							function _iLocal( _eLocal4 ) {
								Fa(
									_oLocal2,
									_rLocal2,
									_aLocal2,
									_iLocal,
									_lLocal,
									'next',
									_eLocal4
								);
							}
							function _lLocal( _eLocal5 ) {
								Fa(
									_oLocal2,
									_rLocal2,
									_aLocal2,
									_iLocal,
									_lLocal,
									'throw',
									_eLocal5
								);
							}
							_iLocal( void 0 );
						} );
					} );
			return function ( ..._args2 ) {
				return _tLocal2.apply( this, _args2 );
			};
		} )();
		var P = function ( e, _tLocal5, _nLocal4 ) {
			S.setAssignment(
				Aa(
					Aa( {}, assignment ),
					{},
					Ma(
						{},
						e,
						_nLocal4
							? Aa(
									Aa( {}, assignment[ e ] ),
									{},
									Ma( {}, _nLocal4, _tLocal5 )
								)
							: _tLocal5
					)
				)
			);
		};
		var O = [
			{
				value: 'day',
				label: ( 0, I18n.__ )( 'Day', 'ohmylms' ),
			},
			{
				value: 'week',
				label: ( 0, I18n.__ )( 'Week', 'ohmylms' ),
			},
			{
				value: 'Month',
				label: ( 0, I18n.__ )( 'Month', 'ohmylms' ),
			},
		];
		var k = ( 0, ReactHooks.useCallback )(
			function ( e ) {
				if ( ! ( Number( e ) < 1 ) ) {
					S.setAssignment(
						Aa(
							Aa( {}, assignment ),
							{},
							{
								drip_settings: Aa(
									Aa(
										{},
										assignment === null ||
											assignment === void 0
											? void 0
											: assignment.drip_settings
									),
									{},
									{
										days: e,
									}
								),
							}
						)
					);
				}
			},
			[ S, assignment ]
		);
		var j = ( 0, ReactHooks.useCallback )(
			function () {
				if (
					assignment !== null &&
					assignment !== void 0 &&
					assignment.id
				) {
					{
						S.deleteLessonFromChapter(
							assignment === null || assignment === void 0
								? void 0
								: assignment.id,
							chapterId
						);
						setOpenModal( ! 1 );
					}
				}
			},
			[ chapterId, assignment, S ]
		);
		return (
			<Controls.CardWP fullHeight={ ! 0 } isBorderless={ ! 0 }>
				<Controls.SpacerWP
					paddingX={ 5 }
					paddingTop={ 5 }
					paddingBottom={ 0 }
				>
					<Controls.FlexWP
						align={ 'center' }
						gap={ 3 }
						justify={ 'flex-start' }
					>
						<Rt />
						{ ( 0, I18n.__ )( 'Settings', 'ohmylms' ) }
					</Controls.FlexWP>
				</Controls.SpacerWP>
				{ chapterId && (
					<PrerequisiteSettings
						title={ ( 0, I18n.__ )( 'Prerequisites', 'ohmylms' ) }
						tooltip={ ( 0, I18n.__ )(
							'Information about prerequisites',
							'ohmylms'
						) }
						onChange={ function () {
							var e;
							S.setAssignment(
								Aa(
									Aa( {}, assignment ),
									{},
									{
										prerequisites: Aa(
											Aa(
												{},
												assignment === null ||
													assignment === void 0
													? void 0
													: assignment.prerequisites
											),
											{},
											{
												enable: ! (
													assignment !== null &&
													assignment !== void 0 &&
													null !==
														( e =
															assignment.prerequisites ) &&
													void 0 !== e &&
													e.enable
												),
											}
										),
									}
								)
							);
						} }
						isChecked={
							null !==
								( t =
									assignment === null ||
									assignment === void 0 ||
									null === ( n = assignment.prerequisites ) ||
									void 0 === n
										? void 0
										: n.enable ) &&
							void 0 !== t &&
							t
						}
						childTitle={ ( 0, I18n.__ )(
							'Members can access this content if they have completed all of the following content:',
							'ohmylms'
						) }
						childPlaceholder={ ( 0, I18n.__ )(
							'Type to search course modules',
							'ohmylms'
						) }
						childNotFoundMessage={ ( 0, I18n.__ )(
							'No Lessons Found',
							'ohmylms'
						) }
						isMultiple={ ! 0 }
						onSearch={ C }
						onChildChange={ function ( e ) {
							var _tLocal6 = e.map( function ( _eLocal7 ) {
								return {
									label: _eLocal7.label,
									value: _eLocal7.value,
								};
							} );
							S.setAssignment(
								Aa(
									Aa( {}, assignment ),
									{},
									{
										prerequisites: Aa(
											Aa(
												{},
												assignment === null ||
													assignment === void 0
													? void 0
													: assignment.prerequisites
											),
											{},
											{
												data: Oa( _tLocal6 ),
											}
										),
									}
								)
							);
						} }
						defaultValue={
							assignment === null ||
							assignment === void 0 ||
							null === ( r = assignment.prerequisites ) ||
							void 0 === r
								? void 0
								: r.data
						}
						showDivider={ ! 1 }
					/>
				) }
				<DripSettings
					onChange={ function () {
						var e;
						{
							var _tLocal7 = ! (
								assignment !== null &&
								assignment !== void 0 &&
								null !== ( e = assignment.drip_settings ) &&
								void 0 !== e &&
								e.enable
							);
							S.setAssignment(
								Aa(
									Aa( {}, assignment ),
									{},
									{
										drip_settings: Aa(
											Aa(
												{},
												assignment === null ||
													assignment === void 0
													? void 0
													: assignment.drip_settings
											),
											{},
											{
												enable: _tLocal7,
											},
											_tLocal7 && {
												type: E
													? 'cohort-start'
													: 'enrollment-from-x-days',
											}
										),
									}
								)
							);
						}
					} }
					isChecked={
						null !==
							( a =
								assignment === null ||
								assignment === void 0 ||
								null === ( o = assignment.drip_settings ) ||
								void 0 === o
									? void 0
									: o.enable ) &&
						void 0 !== a &&
						a
					}
					onDripFeedTypeChange={ function ( e ) {
						{
							var _tLocal8,
								_nLocal5,
								_rLocal3 = Aa(
									Aa(
										{},
										assignment === null ||
											assignment === void 0
											? void 0
											: assignment.drip_settings
									),
									{},
									{
										type: e,
									}
								);
							if ( 'specific-date' === e ) {
								delete _rLocal3.days;
								_rLocal3.date =
									( assignment === null ||
									assignment === void 0 ||
									null ===
										( _tLocal8 =
											assignment.drip_settings ) ||
									void 0 === _tLocal8
										? void 0
										: _tLocal8.date ) ||
									sn()( new Date() ).format(
										'YYYY-MM-DDTHH:mm:ss.SSSD'
									);
								_rLocal3.time =
									( assignment === null ||
									assignment === void 0 ||
									null ===
										( _nLocal5 =
											assignment.drip_settings ) ||
									void 0 === _nLocal5
										? void 0
										: _nLocal5.time ) ||
									sn()( new Date() ).format(
										'YYYY-MM-DDTHH:mm:ss.SSSD'
									);
							} else if (
								'cohort-from-x-days' === e ||
								'enrollment-from-x-days' === e
							) {
								var _aLocal3;
								delete _rLocal3.date;
								delete _rLocal3.time;
								_rLocal3.days =
									( assignment === null ||
									assignment === void 0 ||
									null ===
										( _aLocal3 =
											assignment.drip_settings ) ||
									void 0 === _aLocal3
										? void 0
										: _aLocal3.days ) || 1;
							} else if ( 'cohort-start' === e ) {
								{
									delete _rLocal3.days;
									delete _rLocal3.date;
									delete _rLocal3.time;
								}
							}
							S.setAssignment(
								Aa(
									Aa( {}, assignment ),
									{},
									{
										drip_settings: _rLocal3,
									}
								)
							);
						}
					} }
					handleDripDatePickerChange={ function ( e ) {
						S.setAssignment(
							Aa(
								Aa( {}, assignment ),
								{},
								{
									drip_settings: Aa(
										Aa(
											{},
											assignment === null ||
												assignment === void 0
												? void 0
												: assignment.drip_settings
										),
										{},
										{
											date: e
												? sn()( e ).format(
														'YYYY-MM-DDTHH:mm:ss.SSS'
													)
												: null,
										}
									),
								}
							)
						);
					} }
					handleDripTimePickerChange={ function ( e ) {
						S.setAssignment(
							Aa(
								Aa( {}, assignment ),
								{},
								{
									drip_settings: Aa(
										Aa(
											{},
											assignment === null ||
												assignment === void 0
												? void 0
												: assignment.drip_settings
										),
										{},
										{
											time: e
												? sn()( e ).format(
														'YYYY-MM-DDTHH:mm:ss.SSS'
													)
												: null,
										}
									),
								}
							)
						);
					} }
					handleDayChange={ k }
					dripFeedType={
						assignment === null ||
						assignment === void 0 ||
						null === ( i = assignment.drip_settings ) ||
						void 0 === i
							? void 0
							: i.type
					}
					dripDate={
						( assignment === null ||
						assignment === void 0 ||
						null === ( c = assignment.drip_settings ) ||
						void 0 === c
							? void 0
							: c.date ) ||
						sn()().startOf( 'day' ).format( 'YYYY-MM-DD' )
					}
					dripTime={
						( assignment === null ||
						assignment === void 0 ||
						null === ( u = assignment.drip_settings ) ||
						void 0 === u
							? void 0
							: u.time ) || new Date()
					}
					enrollmentFromXDays={
						assignment === null ||
						assignment === void 0 ||
						null === ( s = assignment.drip_settings ) ||
						void 0 === s
							? void 0
							: s.days
					}
					isCohortBased={ E }
				/>
				<Kt
					title={ ( 0, I18n.__ )( 'Time Limit', 'ohmylms' ) }
					tooltip={ ( 0, I18n.__ )(
						'Set a deadline for submitting this assignment.',
						'ohmylms'
					) }
					onChange={ function ( e ) {
						return P( 'enable_time_limit', e );
					} }
					isChecked={
						null !==
							( d =
								assignment === null || assignment === void 0
									? void 0
									: assignment.enable_time_limit ) &&
						void 0 !== d &&
						d
					}
					showDivider={ ! 1 }
					customClass={
						'ohmylms-assignment-settings-time-limit-button'
					}
					conditionalChild={
						<Ea
							isBorderless={ ! 0 }
							variant={ 'secondary' }
							padding={ '16px' }
							margin={ '12px 0 0' }
						>
							<Controls.InputWP
								value={
									( assignment === null ||
									assignment === void 0
										? void 0
										: assignment.time_limit ) || ''
								}
								type={ 'number' }
								min={ 1 }
								placeholder={ ( 0, I18n.__ )(
									'Enter time limit',
									'ohmylms'
								) }
								className={
									'ohmylms-assignment-settings-time-limit-input'
								}
								onChange={ function ( e ) {
									if ( /^\d*\.?\d*$/.test( e ) ) {
										P( 'time_limit', e );
									}
								} }
								onKeyDown={ function ( e ) {
									if (
										[
											'e',
											'E',
											'+',
											'-',
											'/',
											'\\',
											'.',
											',',
											'*',
											' ',
										].includes( e.key ) ||
										( /[a-zA-Z]/.test( e.key ) &&
											! [
												'Backspace',
												'Tab',
												'ArrowLeft',
												'ArrowRight',
												'Delete',
												'Enter',
											].includes( e.key ) )
									) {
										e.preventDefault();
									}
								} }
								onBlur={ function () {
									if (
										( assignment === null ||
										assignment === void 0
											? void 0
											: assignment.time_limit ) < 1
									) {
										P( 'time_limit', 1 );
									}
								} }
							/>
							<Controls.SpacerWP />
							<_n
								placeholder={ ( 0, I18n.__ )(
									'Select option',
									'ohmylms'
								) }
								customClass={
									'ohmylms-assignment-settings-time-limit-type-select'
								}
								options={ O }
								value={
									assignment === null || assignment === void 0
										? void 0
										: assignment.time_limit_type
								}
								onChange={ function ( e ) {
									return P( 'time_limit_type', e );
								} }
							/>
						</Ea>
					}
				/>
				<Controls.SpacerWP padding={ 4 } marginBottom={ 0 }>
					<Controls.HeadingWP level={ '4' }>
						{ ( 0, I18n.__ )(
							'Maximum Passing Points',
							'ohmylms'
						) }
					</Controls.HeadingWP>
					<Ca
						value={
							( assignment === null || assignment === void 0
								? void 0
								: assignment.total_points ) || ''
						}
						title={ ( 0, I18n.__ )(
							'Set the maximum points a student can score',
							'ohmylms'
						) }
						error={
							Number(
								assignment === null || assignment === void 0
									? void 0
									: assignment.total_points
							) < 1
						}
						errorMessage={ ( 0, I18n.__ )(
							'Total points should be greater than 0',
							'ohmylms'
						) }
						className={
							'ohmylms-assignment-settings-total-points-input'
						}
						handleChange={ function ( e ) {
							var _tLocal0 = e;
							if ( /^\d*\.?\d*$/.test( _tLocal0 ) ) {
								P( 'total_points', _tLocal0 );
							}
						} }
						onKeyDown={ function ( e ) {
							if (
								[
									'e',
									'E',
									'+',
									'-',
									'/',
									'\\',
									',',
									'*',
									' ',
								].includes( e.key ) ||
								( /[a-zA-Z]/.test( e.key ) &&
									! [
										'Backspace',
										'Tab',
										'ArrowLeft',
										'ArrowRight',
										'Delete',
										'Enter',
									].includes( e.key ) )
							) {
								e.preventDefault();
							}
						} }
						onBlur={ function () {
							if (
								( assignment === null || assignment === void 0
									? void 0
									: assignment.total_points ) < 1
							) {
								P( 'total_points', 1 );
							}
						} }
						variant={ 'secondary' }
					/>
				</Controls.SpacerWP>
				<Controls.SpacerWP padding={ 4 } marginBottom={ 0 }>
					<Controls.HeadingWP level={ '4' }>
						{ ( 0, I18n.__ )( 'Minimum Pass Points', 'ohmylms' ) }
					</Controls.HeadingWP>
					<Ca
						value={
							( assignment === null || assignment === void 0
								? void 0
								: assignment.maximum_pass_points ) || ''
						}
						title={ ( 0, I18n.__ )(
							'Set the minimum points required for the student to pass this assignment.',
							'ohmylms'
						) }
						error={
							Number(
								assignment === null || assignment === void 0
									? void 0
									: assignment.maximum_pass_points
							) < 0 ||
							Number(
								assignment === null || assignment === void 0
									? void 0
									: assignment.maximum_pass_points
							) >
								Number(
									assignment === null || assignment === void 0
										? void 0
										: assignment.total_points
								)
						}
						errorMessage={ ( 0, I18n.__ )(
							'Minimum pass points should be greater than 0 and less than or equal to total points',
							'ohmylms'
						) }
						className={
							'ohmylms-assignment-settings-pass-points-input'
						}
						handleChange={ function ( e ) {
							var _tLocal1 = e;
							if ( /^\d*\.?\d*$/.test( _tLocal1 ) ) {
								P( 'maximum_pass_points', _tLocal1 );
							}
						} }
						onKeyDown={ function ( e ) {
							if (
								[
									'e',
									'E',
									'+',
									'-',
									'/',
									'\\',
									',',
									'*',
									' ',
								].includes( e.key ) ||
								( /[a-zA-Z]/.test( e.key ) &&
									! [
										'Backspace',
										'Tab',
										'ArrowLeft',
										'ArrowRight',
										'Delete',
										'Enter',
									].includes( e.key ) )
							) {
								e.preventDefault();
							}
						} }
						onBlur={ function () {
							if (
								( assignment === null || assignment === void 0
									? void 0
									: assignment.maximum_pass_points ) < 1
							) {
								P( 'maximum_pass_points', 1 );
							}
						} }
						variant={ 'secondary' }
					/>
				</Controls.SpacerWP>
				<Kt
					title={ ( 0, I18n.__ )(
						'Assignment Submission Attempts',
						'ohmylms'
					) }
					onChange={ function ( e ) {
						return P( 'allow_upload_files', e );
					} }
					isChecked={
						null !==
							( m =
								assignment === null || assignment === void 0
									? void 0
									: assignment.allow_upload_files ) &&
						void 0 !== m &&
						m
					}
					customClass={
						'ohmylms-assignment-settings-submission-attempts-button'
					}
					showDivider={ ! 1 }
					conditionalChild={
						<Ca
							value={
								( assignment === null || assignment === void 0
									? void 0
									: assignment.number_of_files ) || ''
							}
							title={ ( 0, I18n.__ )(
								'Define the number of attempts that a student is allowed in this assignment.',
								'ohmylms'
							) }
							error={
								Number(
									assignment === null || assignment === void 0
										? void 0
										: assignment.number_of_files
								) < 1
							}
							errorMessage={ ( 0, I18n.__ )(
								'Number of files should be greater than 0',
								'ohmylms'
							) }
							className={
								'ohmylms-assignment-settings-number-of-files-input'
							}
							handleChange={ function ( e ) {
								var _tLocal10 = e;
								if ( /^\d*\.?\d*$/.test( _tLocal10 ) ) {
									P( 'number_of_files', _tLocal10 );
								}
							} }
							onKeyDown={ function ( e ) {
								if (
									[
										'e',
										'E',
										'+',
										'-',
										'/',
										'\\',
										'.',
										',',
										'*',
										' ',
									].includes( e.key ) ||
									( /[a-zA-Z]/.test( e.key ) &&
										! [
											'Backspace',
											'Tab',
											'ArrowLeft',
											'ArrowRight',
											'Delete',
											'Enter',
										].includes( e.key ) )
								) {
									e.preventDefault();
								}
							} }
							onBlur={ function () {
								if (
									( assignment === null ||
									assignment === void 0
										? void 0
										: assignment.number_of_files ) < 1
								) {
									P( 'number_of_files', 1 );
								}
							} }
							variant={ 'secondary' }
						/>
					}
				/>
				<Kt
					title={ ( 0, I18n.__ )(
						'Submission File Size Limit',
						'ohmylms'
					) }
					onChange={ function ( e ) {
						return P( 'enable_file_size_limit', e );
					} }
					isChecked={
						null !==
							( p =
								assignment === null || assignment === void 0
									? void 0
									: assignment.enable_file_size_limit ) &&
						void 0 !== p &&
						p
					}
					showDivider={ ! 1 }
					customClass={
						'ohmylms-assignment-settings-file-size-limit-button'
					}
					conditionalChild={
						<Ca
							value={
								( assignment === null || assignment === void 0
									? void 0
									: assignment.max_file_size_limit ) || ''
							}
							title={ ( 0, I18n.__ )(
								'Define the maximum file size attachment that a student can upload in MB',
								'ohmylms'
							) }
							error={
								Number(
									assignment === null || assignment === void 0
										? void 0
										: assignment.max_file_size_limit
								) < 1
							}
							errorMessage={ ( 0, I18n.__ )(
								'File size limit should be greater than 0',
								'ohmylms'
							) }
							className={
								'ohmylms-assignment-settings-file-size-limit-input'
							}
							handleChange={ function ( e ) {
								var _tLocal11 = e;
								if ( /^\d*\.?\d*$/.test( _tLocal11 ) ) {
									P( 'max_file_size_limit', _tLocal11 );
								}
							} }
							onKeyDown={ function ( e ) {
								if (
									[
										'e',
										'E',
										'+',
										'-',
										'/',
										'\\',
										',',
										'*',
										' ',
									].includes( e.key ) ||
									( /[a-zA-Z]/.test( e.key ) &&
										! [
											'Backspace',
											'Tab',
											'ArrowLeft',
											'ArrowRight',
											'Delete',
											'Enter',
										].includes( e.key ) )
								) {
									e.preventDefault();
								}
							} }
							onBlur={ function () {
								if (
									( assignment === null ||
									assignment === void 0
										? void 0
										: assignment.max_file_size_limit ) < 1
								) {
									P( 'max_file_size_limit', 1 );
								}
							} }
							variant={ 'secondary' }
						/>
					}
				/>
				<DownloadResources
					resources={
						( assignment === null ||
						assignment === void 0 ||
						null === ( f = assignment.download_resource ) ||
						void 0 === f
							? void 0
							: f.file ) || []
					}
					handleResources={ function ( e ) {
						var _tLocal12;
						S.setAssignment(
							Aa(
								Aa( {}, assignment ),
								{},
								{
									download_resource: Aa(
										Aa(
											{},
											assignment === null ||
												assignment === void 0
												? void 0
												: assignment.download_resource
										),
										{},
										{
											file: [].concat(
												Oa(
													( assignment === null ||
													assignment === void 0 ||
													null ===
														( _tLocal12 =
															assignment.download_resource ) ||
													void 0 === _tLocal12
														? void 0
														: _tLocal12.file ) || []
												),
												Oa( e )
											),
										}
									),
								}
							)
						);
					} }
					handleDeleteResource={ function ( e ) {
						var _tLocal13,
							_nLocal6 =
								assignment === null ||
								assignment === void 0 ||
								null ===
									( _tLocal13 =
										assignment.download_resource ) ||
								void 0 === _tLocal13 ||
								null === ( _tLocal13 = _tLocal13.file ) ||
								void 0 === _tLocal13
									? void 0
									: _tLocal13.filter( function ( _tLocal14 ) {
											return (
												( _tLocal14 === null ||
												_tLocal14 === void 0
													? void 0
													: _tLocal14.id ) !== e
											);
										} );
						S.setAssignment(
							Aa(
								Aa( {}, assignment ),
								{},
								{
									download_resource: Aa(
										Aa(
											{},
											assignment === null ||
												assignment === void 0
												? void 0
												: assignment.download_resource
										),
										{},
										{
											file: _nLocal6,
										}
									),
								}
							)
						);
					} }
					showDivider={ ! 1 }
					tooltipText={ ( 0, I18n.__ )(
						'Upload downloadable files or materials for students to access with this assignment.',
						'ohmylms'
					) }
				/>
				{ chapterId && (
					<Controls.SpacerWP
						paddingX={ 5 }
						paddingTop={ 5 }
						paddingBottom={ 0 }
					>
						<DeleteLearningItem
							label={ ( 0, I18n.__ )(
								'Delete Assignment',
								'ohmylms'
							) }
							onDelete={ j }
							alertTitle={ ( 0, I18n.__ )(
								'Delete Assignment',
								'ohmylms'
							) }
							alertDescription={ ( 0, I18n.__ )(
								'Are you sure you want to delete this assignment?',
								'ohmylms'
							) }
						/>
					</Controls.SpacerWP>
				) }
			</Controls.CardWP>
		);
	};
}
