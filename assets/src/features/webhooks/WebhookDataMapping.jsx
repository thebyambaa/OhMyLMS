/** Reconstructed React source. Runtime dependencies are explicit in components.json. */
import { createElement } from '@wordpress/element';
import * as Controls from '../../shared/controls';
export function createWebhookDataMapping( readRuntime ) {
	return function WebhookDataMapping( props ) {
		const {
			D5,
			I5,
			Ps,
			React,
			T: StoreModule,
			W5,
			b: I18n,
			ks,
			y: WordPressData,
		} = readRuntime();
		var t = props.errors,
			n = ( 0, WordPressData.useSelect )( function ( e ) {
				return e( StoreModule.default ).selectWebhookFormData();
			}, [] ),
			r = ( 0, WordPressData.useDispatch )( StoreModule.default ),
			a = function ( e, _tLocal, _aLocal ) {
				var o = n.data_mapping.map( function ( _nLocal, _rLocal ) {
					return _rLocal === e
						? D5(
								D5( {}, _nLocal ),
								{},
								W5( {}, _tLocal, _aLocal )
							)
						: _nLocal;
				} );
				r.updateWebhookFormData( 'data_mapping', o );
			},
			o = function () {
				var e = [].concat( I5( n.data_mapping || [] ), [
					{
						key: '',
						value: 'user_id',
					},
				] );
				r.updateWebhookFormData( 'data_mapping', e );
			};
		return (
			<div>
				<div
					style={ {
						marginBottom: '24px',
					} }
				>
					<Controls.TextWP
						as={ 'h3' }
						size={ '16' }
						weight={ '600' }
						style={ {
							marginBottom: '8px',
						} }
					>
						{ ( 0, I18n.__ )( 'Field Mapping', 'ohmylms' ) }
					</Controls.TextWP>
					<Controls.TextWP
						as={ 'p' }
						size={ '14' }
						style={ {
							color: '#6B7280',
							marginBottom: '16px',
						} }
					>
						{ ( 0, I18n.__ )(
							'Map the fields you want to send in the webhook payload',
							'ohmylms'
						) }
					</Controls.TextWP>
				</div>
				<div
					style={ {
						marginTop: '24px',
					} }
				>
					<Controls.TextWP
						as={ 'h4' }
						size={ '15' }
						weight={ '600' }
						style={ {
							marginBottom: '16px',
						} }
					>
						{ ( 0, I18n.__ )( 'Configure Fields', 'ohmylms' ) }
					</Controls.TextWP>
					{ ( t === null || t === void 0
						? void 0
						: t.data_mapping ) && (
						<div
							style={ {
								padding: '12px',
								backgroundColor: '#FEE2E2',
								border: '1px solid #FCA5A5',
								borderRadius: '6px',
								marginBottom: '16px',
							} }
						>
							<Controls.TextWP
								style={ {
									color: '#DC2626',
									fontSize: '14px',
								} }
							>
								{ t.data_mapping }
							</Controls.TextWP>
						</div>
					) }
					<div
						style={ {
							backgroundColor: '#F9FAFB',
							border: '1px solid #E5E7EB',
							borderRadius: '8px',
							padding: '20px',
						} }
					>
						<Controls.FlexWP
							gap={ 16 }
							style={ {
								marginBottom: '16px',
							} }
						>
							<div
								style={ {
									flex: 1,
								} }
							>
								<Controls.TextWP
									style={ {
										fontWeight: '500',
										fontSize: '14px',
										color: '#374151',
									} }
								>
									{ ( 0, I18n.__ )( 'Key', 'ohmylms' ) }
								</Controls.TextWP>
							</div>
							<div
								style={ {
									flex: 1,
								} }
							>
								<Controls.TextWP
									style={ {
										fontWeight: '500',
										fontSize: '14px',
										color: '#374151',
									} }
								>
									{ ( 0, I18n.__ )( 'Value', 'ohmylms' ) }
								</Controls.TextWP>
							</div>
							<div
								style={ {
									width: '60px',
								} }
							/>
						</Controls.FlexWP>
						{ (
							( n === null || n === void 0
								? void 0
								: n.data_mapping ) || [
								{
									key: '',
									value: '',
								},
							]
						).map( function ( e, _tLocal2 ) {
							var i, l, c;
							return (
								<Controls.FlexWP
									key={ _tLocal2 }
									gap={ 16 }
									align={ 'center' }
									style={ {
										marginBottom: '12px',
									} }
								>
									<div
										style={ {
											flex: 1,
										} }
									>
										<Controls.InputWP
											placeholder={ ( 0, I18n.__ )(
												'Enter key name',
												'ohmylms'
											) }
											value={ e.key || '' }
											onChange={ function ( _eLocal ) {
												return a(
													_tLocal2,
													'key',
													_eLocal
												);
											} }
										/>
									</div>
									<div
										style={ {
											flex: 1,
										} }
									>
										<Controls.SelectWP
											placeholder={ ( 0, I18n.__ )(
												'Select value',
												'ohmylms'
											) }
											options={
												( ( l = [
													{
														label: ( 0, I18n.__ )(
															'User ID',
															'ohmylms'
														),
														value: 'user_id',
													},
													{
														label: ( 0, I18n.__ )(
															'User Email',
															'ohmylms'
														),
														value: 'user_email',
													},
													{
														label: ( 0, I18n.__ )(
															'User Name',
															'ohmylms'
														),
														value: 'user_name',
													},
													{
														label: ( 0, I18n.__ )(
															'Event Time',
															'ohmylms'
														),
														value: 'event_time',
													},
													{
														label: ( 0, I18n.__ )(
															'Site URL',
															'ohmylms'
														),
														value: 'site_url',
													},
												] ),
												( c = {
													course_purchase: [
														{
															label: ( 0,
															I18n.__ )(
																'Course ID',
																'ohmylms'
															),
															value: 'course_id',
														},
														{
															label: ( 0,
															I18n.__ )(
																'Course Title',
																'ohmylms'
															),
															value: 'course_title',
														},
														{
															label: ( 0,
															I18n.__ )(
																'Course Price',
																'ohmylms'
															),
															value: 'course_price',
														},
														{
															label: ( 0,
															I18n.__ )(
																'Order ID',
																'ohmylms'
															),
															value: 'order_id',
														},
														{
															label: ( 0,
															I18n.__ )(
																'Order Total',
																'ohmylms'
															),
															value: 'order_total',
														},
													],
													course_enrollment: [
														{
															label: ( 0,
															I18n.__ )(
																'Course ID',
																'ohmylms'
															),
															value: 'course_id',
														},
														{
															label: ( 0,
															I18n.__ )(
																'Course Title',
																'ohmylms'
															),
															value: 'course_title',
														},
														{
															label: ( 0,
															I18n.__ )(
																'Enrollment Date',
																'ohmylms'
															),
															value: 'enrollment_date',
														},
													],
													course_completion: [
														{
															label: ( 0,
															I18n.__ )(
																'Course ID',
																'ohmylms'
															),
															value: 'course_id',
														},
														{
															label: ( 0,
															I18n.__ )(
																'Course Title',
																'ohmylms'
															),
															value: 'course_title',
														},
														{
															label: ( 0,
															I18n.__ )(
																'Completion Date',
																'ohmylms'
															),
															value: 'completion_date',
														},
														{
															label: ( 0,
															I18n.__ )(
																'Completion Percentage',
																'ohmylms'
															),
															value: 'completion_percentage',
														},
													],
													lesson_completion: [
														{
															label: ( 0,
															I18n.__ )(
																'Lesson ID',
																'ohmylms'
															),
															value: 'lesson_id',
														},
														{
															label: ( 0,
															I18n.__ )(
																'Lesson Title',
																'ohmylms'
															),
															value: 'lesson_title',
														},
														{
															label: ( 0,
															I18n.__ )(
																'Course ID',
																'ohmylms'
															),
															value: 'course_id',
														},
														{
															label: ( 0,
															I18n.__ )(
																'Course Title',
																'ohmylms'
															),
															value: 'course_title',
														},
													],
													quiz_submission: [
														{
															label: ( 0,
															I18n.__ )(
																'Quiz ID',
																'ohmylms'
															),
															value: 'quiz_id',
														},
														{
															label: ( 0,
															I18n.__ )(
																'Quiz Title',
																'ohmylms'
															),
															value: 'quiz_title',
														},
														{
															label: ( 0,
															I18n.__ )(
																'Score',
																'ohmylms'
															),
															value: 'score',
														},
														{
															label: ( 0,
															I18n.__ )(
																'Course ID',
																'ohmylms'
															),
															value: 'course_id',
														},
													],
													quiz_achievement: [
														{
															label: ( 0,
															I18n.__ )(
																'Quiz ID',
																'ohmylms'
															),
															value: 'quiz_id',
														},
														{
															label: ( 0,
															I18n.__ )(
																'Quiz Title',
																'ohmylms'
															),
															value: 'quiz_title',
														},
														{
															label: ( 0,
															I18n.__ )(
																'Score',
																'ohmylms'
															),
															value: 'score',
														},
														{
															label: ( 0,
															I18n.__ )(
																'Course ID',
																'ohmylms'
															),
															value: 'course_id',
														},
													],
													assignment_submission: [
														{
															label: ( 0,
															I18n.__ )(
																'Assignment ID',
																'ohmylms'
															),
															value: 'assignment_id',
														},
														{
															label: ( 0,
															I18n.__ )(
																'Assignment Title',
																'ohmylms'
															),
															value: 'assignment_title',
														},
														{
															label: ( 0,
															I18n.__ )(
																'Submission Date',
																'ohmylms'
															),
															value: 'submission_date',
														},
														{
															label: ( 0,
															I18n.__ )(
																'Course ID',
																'ohmylms'
															),
															value: 'course_id',
														},
													],
													assignment_achievement: [
														{
															label: ( 0,
															I18n.__ )(
																'Assignment ID',
																'ohmylms'
															),
															value: 'assignment_id',
														},
														{
															label: ( 0,
															I18n.__ )(
																'Assignment Title',
																'ohmylms'
															),
															value: 'assignment_title',
														},
														{
															label: ( 0,
															I18n.__ )(
																'Marks',
																'ohmylms'
															),
															value: 'marks',
														},
														{
															label: ( 0,
															I18n.__ )(
																'Submission Date',
																'ohmylms'
															),
															value: 'submission_date',
														},
														{
															label: ( 0,
															I18n.__ )(
																'Course ID',
																'ohmylms'
															),
															value: 'course_id',
														},
													],
												} ),
												[].concat(
													l,
													I5(
														c[
															n === null ||
															n === void 0
																? void 0
																: n.trigger_event
														] || []
													)
												) )
											}
											value={ e.value || '' }
											onChange={ function ( _eLocal2 ) {
												return a(
													_tLocal2,
													'value',
													_eLocal2
												);
											} }
										/>
									</div>
									<div
										style={ {
											display: 'flex',
											gap: '8px',
										} }
									>
										<Controls.ButtonWP
											icon={ <Ps /> }
											onClick={ function () {
												return ( function ( _eLocal3 ) {
													if (
														n.data_mapping &&
														n.data_mapping.length >
															1
													) {
														var _tLocal3 =
															n.data_mapping.filter(
																function (
																	_tLocal4,
																	_nLocal2
																) {
																	return (
																		_nLocal2 !==
																		_eLocal3
																	);
																}
															);
														r.updateWebhookFormData(
															'data_mapping',
															_tLocal3
														);
													}
												} )( _tLocal2 );
											} }
											title={ ( 0, I18n.__ )(
												'Remove Field',
												'ohmylms'
											) }
											style={ {
												minWidth: '26px',
												padding: '2px',
											} }
											disabled={
												( ( n === null ||
												n === void 0 ||
												null ===
													( i = n.data_mapping ) ||
												void 0 === i
													? void 0
													: i.length ) || 0 ) <= 1
											}
										/>
										<Controls.ButtonWP
											icon={ React.createElement(
												ks,
												null
											) }
											onClick={ o }
											title={ ( 0, I18n.__ )(
												'Add Field',
												'ohmylms'
											) }
											style={ {
												minWidth: '26px',
												padding: '2px',
											} }
										/>
									</div>
								</Controls.FlexWP>
							);
						} ) }
					</div>
				</div>
			</div>
		);
	};
}
