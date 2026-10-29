/** Reconstructed React source. Runtime dependencies are explicit in components.json. */
import { createElement } from '@wordpress/element';
import { Table } from '../../shared/table';
import { EditIcon } from '../../shared/icons';
import * as Controls from '../../shared/controls';
import { PriceDisplay } from '../../shared/utils';
import { EmptyState, NoDataIllustration } from '../../shared/media';
export function createRecentCourses( readRuntime ) {
	return function RecentCourses( props ) {
		const {
			Br,
			Ne,
			React,
			T: StoreModule,
			b: I18n,
			bG,
			f: Router,
			g: ReactHooks,
			q,
			sn,
			v,
			y: WordPressData,
			yG: CourseListItem,
		} = readRuntime();
		var t = props.popularCourses,
			n = props.dataLoading,
			r = props.currency,
			a = props.currency_pos,
			o = props.handleAddCourse,
			i =
				( ( 0, WordPressData.useSelect )( function ( e ) {
					return e( StoreModule.default ).selectCourses();
				}, [] ),
				( function ( e, _tLocal ) {
					return (
						( function ( _eLocal ) {
							if ( Array.isArray( _eLocal ) ) {
								return _eLocal;
							}
						} )( e ) ||
						( function ( _eLocal2, _tLocal2 ) {
							var _nLocal =
								_eLocal2 === null || _eLocal2 === void 0
									? null
									: ( 'undefined' !== typeof Symbol &&
											_eLocal2[ Symbol.iterator ] ) ||
										_eLocal2[ '@@iterator' ];
							if ( _nLocal !== null && _nLocal !== void 0 ) {
								var _rLocal,
									_aLocal,
									_oLocal,
									_iLocal,
									l = [],
									c = ! 0,
									u = ! 1;
								try {
									if (
										( ( _oLocal = ( _nLocal =
											_nLocal.call( _eLocal2 ) ).next ),
										0 === _tLocal2 )
									) {
										if ( Object( _nLocal ) !== _nLocal ) {
											return;
										}
										c = ! 1;
									} else {
										for (
											;
											! ( c = ( _rLocal =
												_oLocal.call( _nLocal ) )
												.done ) &&
											( l.push( _rLocal.value ),
											l.length !== _tLocal2 );
											c = ! 0
										) {}
									}
								} catch ( _eLocal3 ) {
									u = ! 0;
									_aLocal = _eLocal3;
								} finally {
									try {
										if (
											! c &&
											( ( _nullableValue ) =>
												_nullableValue !== null &&
												_nullableValue !== void 0 )(
												_nLocal.return
											) &&
											( ( _iLocal = _nLocal.return() ),
											Object( _iLocal ) !== _iLocal )
										) {
											return;
										}
									} finally {
										if ( u ) {
											throw _aLocal;
										}
									}
								}
								return l;
							}
						} )( e, _tLocal ) ||
						( function ( _eLocal4, _tLocal3 ) {
							if ( _eLocal4 ) {
								if ( 'string' === typeof _eLocal4 ) {
									return bG( _eLocal4, _tLocal3 );
								}
								var _nLocal2 = {}.toString
									.call( _eLocal4 )
									.slice( 8, -1 );
								return (
									'Object' === _nLocal2 &&
										_eLocal4.constructor &&
										( _nLocal2 =
											_eLocal4.constructor.name ),
									( () => {
										if (
											'Map' === _nLocal2 ||
											'Set' === _nLocal2
										) {
											return Array.from( _eLocal4 );
										}
										if (
											'Arguments' === _nLocal2 ||
											/^(?:Ui|I)nt(?:8|16|32)(?:Clamped)?Array$/.test(
												_nLocal2
											)
										) {
											return bG( _eLocal4, _tLocal3 );
										}
										return void 0;
									} )()
								 );
							}
						} )( e, _tLocal ) ||
						( function () {
							throw new TypeError(
								'Invalid attempt to destructure non-iterable instance.\nIn order to be iterable, non-array objects must have a [Symbol.iterator]() method.'
							);
						} )()
					);
				} )( ( 0, ReactHooks.useState )( null ), 2 ) ),
			l = i[ 0 ],
			c = i[ 1 ],
			u = ( 0, Router.Zp )(),
			s = [
				{
					title: 'Course Details',
					dataIndex: 'courseDetails',
					key: 'courseDetails',
					width: '380px',
					render( e, _tLocal4 ) {
						var _nLocal3 = l === _tLocal4.id;
						return (
							<CourseListItem
								course={ _tLocal4 }
								isHover={ _nLocal3 }
							/>
						);
					},
				},
				{
					title: 'Date',
					dataIndex: 'date',
					key: 'date',
					render( e ) {
						return (
							<Controls.BadgeWP
								variant={ 'secondary' }
								isBorderLess={ ! 0 }
							>
								{ sn()( e ).format( 'MMMM DD, YYYY' ) || '-' }
							</Controls.BadgeWP>
						);
					},
				},
				{
					title: (
						<React.Fragment>
							{ ( 0, I18n.__ )( 'Students ', 'ohmylms' ) }
							<Controls.TextWP
								as={ 'em' }
								size={ '12' }
								variant={ 'muted' }
							>
								{ ( 0, I18n.__ )( 'last 30 days', 'ohmylms' ) }
							</Controls.TextWP>
						</React.Fragment>
					),
					dataIndex: 'total_enrollment',
					key: 'total_enrollment',
					render( e, _tLocal5 ) {
						return (
							<React.Fragment>
								{ n && (
									<Controls.SkeletonWP
										paragraph={ {
											rows: 1,
										} }
										active={ ! 0 }
									/>
								) }
								<Controls.BadgeWP
									variant={ 'secondary' }
									isBorderLess={ ! 0 }
								>
									<v.Link
										to={ '/courses/'.concat(
											_tLocal5 === null ||
												_tLocal5 === void 0
												? void 0
												: _tLocal5.id,
											'/students'
										) }
									>
										{ ( _tLocal5 === null ||
										_tLocal5 === void 0
											? void 0
											: _tLocal5.total_enrolled_students ) ||
											0 }{ ' ' }
									</v.Link>
								</Controls.BadgeWP>
							</React.Fragment>
						);
					},
				},
				{
					title: (
						<React.Fragment>
							{ ( 0, I18n.__ )( 'Total Sales ', 'ohmylms' ) }
							<Controls.TextWP
								as={ 'em' }
								size={ '12' }
								variant={ 'muted' }
							>
								{ ( 0, I18n.__ )( 'last 30 days', 'ohmylms' ) }
							</Controls.TextWP>
						</React.Fragment>
					),
					dataIndex: 'total_sale',
					key: 'total_sale',
					render( e, _tLocal6 ) {
						return (
							<React.Fragment>
								{ n && (
									<Controls.SkeletonWP
										paragraph={ {
											rows: 1,
										} }
										active={ ! 0 }
									/>
								) }
								<Controls.FlexWP
									justify={ 'flex-start' }
									align={ 'center' }
									gap={ 3 }
								>
									<Controls.BadgeWP
										variant={ 'secondary' }
										isBorderLess={ ! 0 }
									>
										{ ( _tLocal6 === null ||
										_tLocal6 === void 0
											? void 0
											: _tLocal6.total_sales_count ) ||
											0 }
									</Controls.BadgeWP>
									<span
										className={ 'sales-increase '.concat(
											Number(
												_tLocal6 === null ||
													_tLocal6 === void 0
													? void 0
													: _tLocal6.sales_growth_rate
											) < 0
												? 'decrease'
												: ''
										) }
										style={ {
											fontSize: '12px',
											color: '#33A646',
										} }
									>
										<svg
											width={ '12' }
											height={ '12' }
											fill={ 'none' }
											viewBox={ '0 0 12 14' }
											xmlns={
												'http://www.w3.org/2000/svg'
											}
										>
											<path
												stroke={ 'currentColor' }
												strokeLinecap={ 'round' }
												strokeLinejoin={ 'round' }
												strokeWidth={ '2' }
												d={ 'M1 6l5-5 5 5' }
											/>
											<path
												stroke={ '#33A646' }
												strokeLinecap={ 'round' }
												strokeWidth={ '2' }
												d={ 'M6 13V1' }
											/>
										</svg>
										{ ' ' }
										{ Math.abs(
											_tLocal6 === null ||
												_tLocal6 === void 0
												? void 0
												: _tLocal6.sales_growth_rate
										) }
										{ '%' }
									</span>
								</Controls.FlexWP>
							</React.Fragment>
						);
					},
				},
				{
					title: 'Price',
					dataIndex: 'price',
					key: 'price',
					render( e, _tLocal7 ) {
						return 'free' !==
							( _tLocal7 === null || _tLocal7 === void 0
								? void 0
								: _tLocal7.price ) ? (
							<Controls.BadgeWP
								variant={ 'secondary' }
								isBorderLess={ ! 0 }
							>
								<PriceDisplay
									currency={ r || '$' }
									currency_pos={ a || 'left' }
									price={ Number(
										( _tLocal7 === null ||
										_tLocal7 === void 0
											? void 0
											: _tLocal7.price ) || '0'
									) }
								/>
							</Controls.BadgeWP>
						) : (
							<Controls.BadgeWP
								variant={ 'secondary' }
								isBorderLess={ ! 0 }
							>
								{ ( 0, I18n.__ )( 'Free', 'ohmylms' ) }
							</Controls.BadgeWP>
						);
					},
				},
				{
					title: 'Action',
					dataIndex: 'action',
					key: 'action',
					render( e, _tLocal8 ) {
						return (
							<Controls.DropdownMenuWP
								controls={ [
									{
										title: ( 0, I18n.__ )(
											'View',
											'ohmylms'
										),
										onClick() {
											return window.open(
												_tLocal8 === null ||
													_tLocal8 === void 0
													? void 0
													: _tLocal8.url,
												'_blank'
											);
										},
										icon: <Br />,
									},
									{
										title: ( 0, I18n.__ )(
											'Edit',
											'ohmylms'
										),
										onClick() {
											return u(
												'/course-edit/'.concat(
													_tLocal8 === null ||
														_tLocal8 === void 0
														? void 0
														: _tLocal8.id
												)
											);
										},
										icon: (
											<span>
												<EditIcon />
											</span>
										),
									},
								] }
								icon={ <q.Icon icon={ Ne.A } /> }
							/>
						);
					},
				},
			];
		return (
			<React.Fragment>
				<Table
					rowKey={ 'id' }
					columns={ s }
					dataSource={ t || [] }
					pagination={ ! 1 }
					loading={ n }
					scroll={ {
						x: 'max-content',
					} }
					onRowMouseEnter={ function ( e ) {
						return c( e === null || e === void 0 ? void 0 : e.id );
					} }
					onRowMouseLeave={ function () {
						return c( null );
					} }
					locale={ {
						emptyText: (
							<EmptyState
								icon={ <NoDataIllustration /> }
								title={ ( 0, I18n.__ )(
									'No courses yet!',
									'ohmylms'
								) }
								description={ ( 0, I18n.__ )(
									'Start building your first course and it’ll show up here as soon as you hit publish.',
									'ohmylms'
								) }
								ctaText={ ( 0, I18n.__ )(
									'Add Course',
									'ohmylms'
								) }
								ctaHandler={ o }
							/>
						),
					} }
				/>
			</React.Fragment>
		);
	};
}
