import { showRequestError } from '../requestFeedback.mjs';
import { validateCoupon } from './model.mjs';
/** Reconstructed React source. Runtime dependencies are explicit in components.json. */
import { createElement } from '@wordpress/element';
export function createCouponModal( readRuntime ) {
	return function CouponModal( props ) {
		const {
			Ge: decodeEntities,
			I: Controls,
			Nm: SettingSelect,
			Pf: SettingField,
			Pre,
			React,
			Sre,
			b: I18n,
			g: ReactHooks,
			jf: CourseSelector,
			sH: DatePicker,
		} = readRuntime();
		var isOpen = props.isOpen,
			setIsOpen = props.setIsOpen,
			createCoupon =
				( props.isFetch,
				props.setIsFetch,
				props.fetchCourses,
				props.courses,
				props.setCoupons,
				props.createCoupon ),
			data = props.data,
			setSelectedData = props.setSelectedData,
			i = Pre( ( 0, ReactHooks.useState )( ! 1 ), 2 ),
			l = i[ 0 ],
			c = i[ 1 ],
			u = Pre(
				( 0, ReactHooks.useState )(
					( 0, I18n.__ )( 'Untitled', 'ohmylms' )
				),
				2
			),
			title = u[ 0 ],
			setTitle = u[ 1 ],
			m = Pre( ( 0, ReactHooks.useState )( '' ), 2 ),
			description = m[ 0 ],
			setDescription = m[ 1 ],
			v = Pre( ( 0, ReactHooks.useState )( 'percent' ), 2 ),
			discountType = v[ 0 ],
			setDiscountType = v[ 1 ],
			_ = Pre( ( 0, ReactHooks.useState )( 5 ), 2 ),
			amount = _[ 0 ],
			setAmount = _[ 1 ],
			S = Pre( ( 0, ReactHooks.useState )( 1 ), 2 ),
			perUserLimit = S[ 0 ],
			setPerUserLimit = S[ 1 ],
			C = Pre( ( 0, ReactHooks.useState )( 1 ), 2 ),
			usageLimit = C[ 0 ],
			setUsageLimit = C[ 1 ],
			k = Pre( ( 0, ReactHooks.useState )( '' ), 2 ),
			courseType = k[ 0 ],
			setCourseType = k[ 1 ],
			M = Pre( ( 0, ReactHooks.useState )( [] ), 2 ),
			selectedCourses = M[ 0 ],
			setSelectedCourses = M[ 1 ],
			N = Pre( ( 0, ReactHooks.useState )( new Date() ), 2 ),
			startDate = N[ 0 ],
			setStartDate = N[ 1 ],
			z = Pre(
				( 0, ReactHooks.useState )( function () {
					var e = new Date();
					return ( e.setDate( e.getDate() + 1 ), e );
				} ),
				2
			),
			endDate = z[ 0 ],
			setEndDate = z[ 1 ],
			V = Pre( ( 0, ReactHooks.useState )( ! 1 ), 2 ),
			saving = V[ 0 ],
			setSaving = V[ 1 ],
			U = Pre(
				( 0, ReactHooks.useState )(
					( 0, I18n.__ )(
						'Please enter 3 or more characters...',
						'ohmylms'
					)
				),
				2
			),
			q = ( U[ 0 ], U[ 1 ], Pre( ( 0, ReactHooks.useState )( ! 1 ), 2 ) ),
			valid = q[ 0 ],
			setValid = q[ 1 ];
		( 0, ReactHooks.useEffect )(
			function () {
				var e,
					t,
					n,
					r,
					a,
					o,
					_iLocal = ! 0;
				return (
					_iLocal &&
						( ( e = '' !== title.trim() ),
						( t = parseFloat( amount ) > 0 ),
						( n = parseInt( usageLimit, 10 ) > 0 ),
						( r = parseInt( perUserLimit, 10 ) > 0 ),
						( a = new Date( endDate ) > new Date( startDate ) ),
						( o =
							'selected_course' !== courseType ||
							selectedCourses.length > 0 ),
						setValid( e && t && n && r && a && o ) ),
					function () {
						_iLocal = ! 1;
					}
				 );
			},
			[
				title,
				amount,
				usageLimit,
				startDate,
				endDate,
				perUserLimit,
				courseType,
				selectedCourses,
			]
		);
		var closeModal = function () {
				setIsOpen( ! 1 );
				setSelectedData( null );
			},
			$ = function () {
				var e = Math.random()
					.toString( 36 )
					.substring( 2, 8 )
					.toUpperCase();
				return ''.concat( 'SALE' ).concat( e );
			},
			K = Pre(
				( 0, ReactHooks.useState )( function () {
					return $();
				} ),
				2
			),
			code = K[ 0 ],
			setCode = K[ 1 ],
			saveCoupon = async function () {
				if ( saving ) {
					return;
				}
				const coupon = {
					code,
					title,
					amount,
					discount_type: discountType,
					description,
					date_expires: {
						date: moment( endDate ).format( 'YYYY-MM-DDTHH:mm:ss' ),
						timezone:
							window.ohmylms_params?.timezone?.timezone_string,
						timezone_type:
							window.ohmylms_params?.timezone?.timezone_type,
					},
					date_start: {
						date: moment( startDate ).format(
							'YYYY-MM-DDTHH:mm:ss'
						),
						timezone:
							window.ohmylms_params?.timezone?.timezone_string,
						timezone_type:
							window.ohmylms_params?.timezone?.timezone_type,
					},
					individual_use: 'no',
					exclude_sale_items: [],
					course_id_type: courseType,
					course_ids: selectedCourses.map(
						( course ) => course.value
					),
					excluded_course_ids: [],
					usage_limit: usageLimit,
					usage_limit_per_user: perUserLimit,
				};
				if ( data?.id ) {
					coupon.id = data.id;
				}
				const errors = validateCoupon( coupon, I18n.__ );
				if ( Object.keys( errors ).length ) {
					showRequestError( Object.values( errors )[ 0 ] );
					return;
				}
				setSaving( true );
				try {
					await createCoupon( coupon );
				} catch ( error ) {
					showRequestError( error );
				} finally {
					setSaving( false );
				}
			},
			discountOptions = ( 0, ReactHooks.useMemo )( function () {
				return [
					{
						label: 'Percentage',
						value: 'percent',
					},
					{
						label: 'Flat Rate',
						value: 'flat-rate',
					},
				];
			}, [] );
		( 0, ReactHooks.useMemo )( function () {
			return [
				{
					label: 'All Courses',
					value: 'all',
				},
				{
					label: 'Specific Course',
					value: 'selected_course',
				},
			];
		}, [] );
		( 0, ReactHooks.useEffect )(
			function () {
				var e, t;
				if ( data ) {
					{
						setTitle(
							( data === null || data === void 0
								? void 0
								: data.title ) || 'Untitled'
						);
						setCode(
							( data === null || data === void 0
								? void 0
								: data.code ) || ''
						);
						setDescription(
							( data === null || data === void 0
								? void 0
								: data.description ) || ''
						);
						setDiscountType(
							( data === null || data === void 0
								? void 0
								: data.discount_type ) || 'percent'
						);
						setAmount(
							( data === null || data === void 0
								? void 0
								: data.amount ) || ''
						);
						setPerUserLimit(
							( data === null || data === void 0
								? void 0
								: data.usage_limit_per_user ) || 1
						);
						setUsageLimit(
							( data === null || data === void 0
								? void 0
								: data.usage_limit ) || ''
						);
						if (
							data !== null &&
							data !== void 0 &&
							data.course_id_type
						) {
							{
								setCourseType(
									data === null || data === void 0
										? void 0
										: data.course_id_type
								);
								setSelectedCourses(
									( data === null || data === void 0
										? void 0
										: data.course_ids ) || []
								);
							}
						} else {
							{
								setCourseType( 'all' );
								setSelectedCourses( [] );
							}
						}
						if (
							data !== null &&
							data !== void 0 &&
							data.date_expires
						) {
							setEndDate(
								new Date(
									data === null ||
										data === void 0 ||
										null === ( e = data.date_expires ) ||
										void 0 === e
										? void 0
										: e.date
								)
							);
						}
						if (
							data !== null &&
							data !== void 0 &&
							data.date_start
						) {
							setStartDate(
								new Date(
									data === null ||
										data === void 0 ||
										null === ( t = data.date_start ) ||
										void 0 === t
										? void 0
										: t.date
								)
							);
						}
					}
				}
			},
			[ data ]
		);
		const displayAmount = isNaN( amount )
			? '0'
			: Number( amount ).toFixed( 0 );
		return (
			<React.Fragment>
				{ isOpen && (
					<Controls.ModalWP
						title={ ( 0, I18n.__ )(
							' Coupon Settings',
							'ohmylms'
						) }
						onRequestClose={ closeModal }
						shouldCloseOnEsc={ ! 0 }
						shouldCloseOnClickOutside={ ! 0 }
						size={ 'large' }
					>
						<Controls.CardWP
							isBorderless={ ! 0 }
							variant={ 'secondary' }
						>
							<Controls.SpacerWP
								marginBottom={ 0 }
								marginTop={ 2 }
								padding={ 1 }
							>
								<SettingField
									title={ ( 0, I18n.__ )(
										'Coupon Title',
										'ohmylms'
									) }
									description={ ( 0, I18n.__ )(
										'An internal name to help you identify and manage this coupon.',
										'ohmylms'
									) }
									inputType={ 'text' }
									value={ decodeEntities( title ) }
									onChange={ function ( e ) {
										return setTitle( e );
									} }
								/>
								<Controls.SpacerWP padding={ 4 }>
									<Controls.FlexWP
										gap={ 8 }
										align={ 'flex-start' }
										justify={ 'space-between' }
									>
										<Controls.FlexItemWP isBlock={ ! 0 }>
											<Controls.HeadingWP level={ '4' }>
												{ ( 0, I18n.__ )(
													'Coupon Code',
													'ohmylms'
												) }
											</Controls.HeadingWP>
											<Controls.TextWP>
												{ ( 0, I18n.__ )(
													'A unique code that customers can enter during checkout to receive a discount.',
													'ohmylms'
												) }
											</Controls.TextWP>
										</Controls.FlexItemWP>
										<Controls.FlexItemWP
											isBlock={ ! 0 }
											className={
												'ohmylms-coupon-generate'
											}
										>
											<Controls.FlexWP gap={ 2 }>
												<Controls.FlexItemWP
													style={ {
														position: 'relative',
														width: 'calc(100% - 40px)',
													} }
												>
													<Controls.InputWP
														type={ 'text' }
														value={ code }
														onChange={ function (
															e
														) {
															var t = e.replace(
																/[^a-zA-Z0-9-_]/g,
																''
															);
															if (
																'' === t ||
																/[a-zA-Z]/.test(
																	t
																)
															) {
																setCode( t );
															}
														} }
													/>
													<Controls.ButtonWP
														className={ 'ohmylms-coupon-generate-btn '.concat(
															l && 'is-generating'
														) }
														onClick={ function () {
															c( ! 0 );
															setTimeout(
																function () {
																	var e = $();
																	setCode(
																		e
																	);
																	c( ! 1 );
																},
																800
															);
														} }
													>
														<Sre />
													</Controls.ButtonWP>
												</Controls.FlexItemWP>
												<CourseSelector.A
													textToCopy={ code }
												/>
											</Controls.FlexWP>
										</Controls.FlexItemWP>
									</Controls.FlexWP>
								</Controls.SpacerWP>
								<SettingField
									title={ ( 0, I18n.__ )(
										'Coupon Description',
										'ohmylms'
									) }
									description={ ( 0, I18n.__ )(
										'Explain the purpose or details of the coupon.',
										'ohmylms'
									) }
									inputType={ 'textarea' }
									placeholder={ ( 0, I18n.__ )(
										'Type here',
										'ohmylms'
									) }
									value={ decodeEntities( description ) }
									onChange={ function ( e ) {
										return setDescription( e );
									} }
								/>
							</Controls.SpacerWP>
						</Controls.CardWP>
						<Controls.SpacerWP marginTop={ 5 } />
						<Controls.CardWP
							isBorderless={ ! 0 }
							variant={ 'secondary' }
						>
							<Controls.SpacerWP marginBottom={ 0 } padding={ 1 }>
								<SettingSelect
									title={ ( 0, I18n.__ )(
										'Discount Type',
										'ohmylms'
									) }
									description={ ( 0, I18n.__ )(
										'Choose whether the discount is a percentage of the price or a fixed amount.',
										'ohmylms'
									) }
									onChange={ function ( e ) {
										return setDiscountType( e );
									} }
									value={ discountType }
									data={ discountOptions }
									staticSearch={ ! 0 }
								/>
								<SettingField
									title={ ( 0, I18n.__ )(
										'Discount Value',
										'ohmylms'
									) }
									description={ ( 0, I18n.__ )(
										'Enter the value of the discount based on the selected discount type.',
										'ohmylms'
									) }
									inputType={ 'number' }
									value={
										'percent' === discountType
											? displayAmount
											: amount
									}
									onChange={ function ( e ) {
										return setAmount( e );
									} }
									suffix={
										'percent' === discountType ? '%' : ''
									}
									min={ 0 }
									step={
										'percent' === discountType ? 1 : 0.01
									}
									max={
										'percent' === discountType ? 100 : null
									}
									onKeyDown={ function ( e ) {
										if ( ! (
											'.' !== e.key &&
											',' !== e.key &&
											'e' !== e.key
										) ) {
											if ( 'percent' === discountType ) {
												e.preventDefault();
											}
										}
									} }
								/>
								<SettingField
									title={ ( 0, I18n.__ )(
										'Usage Limit',
										'ohmylms'
									) }
									description={ ( 0, I18n.__ )(
										'Set how many times customers can use this coupon.',
										'ohmylms'
									) }
									inputType={ 'number' }
									value={ usageLimit }
									onChange={ function ( e ) {
										return setUsageLimit( e );
									} }
									min={ 0 }
								/>
								<SettingField
									title={ ( 0, I18n.__ )(
										'Usage Limit per User',
										'ohmylms'
									) }
									description={ ( 0, I18n.__ )(
										'Set how many times an individual customer can use this coupon.',
										'ohmylms'
									) }
									inputType={ 'number' }
									value={ perUserLimit }
									onChange={ function ( e ) {
										return setPerUserLimit( e );
									} }
									min={ 0 }
								/>
							</Controls.SpacerWP>
						</Controls.CardWP>
						<Controls.SpacerWP marginTop={ 5 } />
						<Controls.CardWP
							isBorderless={ ! 0 }
							variant={ 'secondary' }
						>
							<Controls.SpacerWP marginBottom={ 0 } padding={ 4 }>
								<Controls.FlexWP
									gap={ 8 }
									align={ 'flex-start' }
									justify={ 'space-between' }
								>
									<Controls.FlexItemWP isBlock={ ! 0 }>
										<Controls.HeadingWP level={ '4' }>
											{ ( 0, I18n.__ )(
												'Start Date',
												'ohmylms'
											) }
										</Controls.HeadingWP>
										<Controls.SpacerWP marginBottom={ 1 } />
										<Controls.TextWP>
											{ ( 0, I18n.__ )(
												'The date from which the coupon becomes active and can be used.',
												'ohmylms'
											) }
										</Controls.TextWP>
									</Controls.FlexItemWP>
									<Controls.FlexItemWP
										isBlock={ ! 0 }
										className={ 'coupon-datetime-picker' }
									>
										<Controls.FlexWP justify={ 'flex-end' }>
											{ React.createElement( DatePicker, {
												date: startDate,
												onChange( e ) {
													( function ( _eLocal ) {
														setStartDate( _eLocal );
													} )( e );
												},
												isInvalidDateCallback( e ) {
													var t = new Date();
													return (
														t.setHours(
															0,
															0,
															0,
															0
														),
														new Date( e ) < t
													 );
												},
												placeholder: ( 0, I18n.__ )(
													'Select Start Date'
												),
											} ) }
										</Controls.FlexWP>
									</Controls.FlexItemWP>
								</Controls.FlexWP>
							</Controls.SpacerWP>
						</Controls.CardWP>
						<Controls.CardWP
							isBorderless={ ! 0 }
							variant={ 'secondary' }
						>
							<Controls.SpacerWP
								marginTop={ 5 }
								marginBottom={ 0 }
								padding={ 4 }
							>
								<Controls.FlexWP
									gap={ 8 }
									align={ 'flex-start' }
									justify={ 'space-between' }
								>
									<Controls.FlexItemWP isBlock={ ! 0 }>
										<Controls.HeadingWP level={ '4' }>
											{ ( 0, I18n.__ )(
												'Expire Date',
												'ohmylms'
											) }
										</Controls.HeadingWP>
										<Controls.SpacerWP marginBottom={ 1 } />
										<Controls.TextWP>
											{ ( 0, I18n.__ )(
												'Add an expiry date of this coupon. Keep this blank for keeping the coupon validity unlimited.',
												'ohmylms'
											) }
										</Controls.TextWP>
									</Controls.FlexItemWP>
									<Controls.FlexItemWP
										isBlock={ ! 0 }
										className={ 'coupon-datetime-picker' }
									>
										<Controls.FlexWP justify={ 'flex-end' }>
											{ React.createElement( DatePicker, {
												date: endDate,
												onChange( e ) {
													( function ( _eLocal2 ) {
														setEndDate( _eLocal2 );
													} )( e );
												},
												isInvalidDateCallback( e ) {
													var t = new Date(
														startDate || new Date()
													);
													return (
														t.setHours(
															0,
															0,
															0,
															0
														),
														new Date( e ) < t
													 );
												},
												placeholder: ( 0, I18n.__ )(
													'Select Expire Date'
												),
											} ) }
										</Controls.FlexWP>
									</Controls.FlexItemWP>
								</Controls.FlexWP>
							</Controls.SpacerWP>
						</Controls.CardWP>
						<Controls.SpacerWP marginTop={ 5 }>
							<Controls.FlexWP
								justify={ 'flex-end' }
								align={ 'center' }
								gap={ 2 }
							>
								<Controls.ButtonWP
									variant={ 'secondary' }
									onClick={ closeModal }
								>
									{ ( 0, I18n.__ )( 'Cancel', 'ohmylms' ) }
								</Controls.ButtonWP>
								<Controls.ButtonWP
									variant={ 'primary' }
									onClick={ saveCoupon }
									isBusy={ saving }
									disabled={ ! code || ! valid || saving }
								>
									{ data !== null &&
									data !== void 0 &&
									data.id
										? ( 0, I18n.__ )( 'Update', 'ohmylms' )
										: ( 0, I18n.__ )(
												'Create',
												'ohmylms'
											) }
								</Controls.ButtonWP>
							</Controls.FlexWP>
						</Controls.SpacerWP>
					</Controls.ModalWP>
				) }
			</React.Fragment>
		);
	};
}
