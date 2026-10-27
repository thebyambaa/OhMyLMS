/** Reconstructed React source. Runtime dependencies are explicit in components.json. */
import { createElement } from '@wordpress/element';
export function createCourseBasics( readRuntime ) {
	return function CourseBasics() {
		const {
			Az,
			Dz,
			He,
			I: Controls,
			Iz,
			Kt,
			Pz,
			React,
			T: StoreModule,
			b: I18n,
			g: ReactHooks,
			kz,
			mz,
			vz,
			wz,
			y: WordPressData,
			zz,
		} = readRuntime();
		var e = ( 0, WordPressData.useDispatch )( StoreModule.default );
		var t = ( 0, WordPressData.useSelect )( function ( _eLocal ) {
			return _eLocal( StoreModule.default ).getCourse();
		}, [] );
		var r = ( function ( _eLocal2, _tLocal ) {
			return (
				( function ( _eLocal3 ) {
					if ( Array.isArray( _eLocal3 ) ) {
						return _eLocal3;
					}
				} )( _eLocal2 ) ||
				( function ( _eLocal4, _tLocal2 ) {
					var _nLocal =
						_eLocal4 === null || _eLocal4 === void 0
							? null
							: ( 'undefined' !== typeof Symbol &&
									_eLocal4[ Symbol.iterator ] ) ||
								_eLocal4[ '@@iterator' ];
					if ( _nLocal !== null && _nLocal !== void 0 ) {
						var _rLocal,
							a,
							o,
							i,
							l = [],
							c = ! 0,
							u = ! 1;
						try {
							if (
								( ( o = ( _nLocal = _nLocal.call( _eLocal4 ) )
									.next ),
								0 === _tLocal2 )
							) {
								if ( Object( _nLocal ) !== _nLocal ) {
									return;
								}
								c = ! 1;
							} else {
								for (
									;
									! ( c = ( _rLocal = o.call( _nLocal ) )
										.done ) &&
									( l.push( _rLocal.value ),
									l.length !== _tLocal2 );
									c = ! 0
								) {}
							}
						} catch ( _eLocal5 ) {
							u = ! 0;
							a = _eLocal5;
						} finally {
							try {
								if (
									! c &&
									( ( _nullableValue ) =>
										_nullableValue !== null &&
										_nullableValue !== void 0 )(
										_nLocal.return
									) &&
									( ( i = _nLocal.return() ),
									Object( i ) !== i )
								) {
									return;
								}
							} finally {
								if ( u ) {
									throw a;
								}
							}
						}
						return l;
					}
				} )( _eLocal2, _tLocal ) ||
				( function ( _eLocal6, _tLocal3 ) {
					if ( _eLocal6 ) {
						if ( 'string' === typeof _eLocal6 ) {
							return zz( _eLocal6, _tLocal3 );
						}
						var _nLocal2 = {}.toString
							.call( _eLocal6 )
							.slice( 8, -1 );
						return (
							'Object' === _nLocal2 &&
								_eLocal6.constructor &&
								( _nLocal2 = _eLocal6.constructor.name ),
							( () => {
								if (
									'Map' === _nLocal2 ||
									'Set' === _nLocal2
								) {
									return Array.from( _eLocal6 );
								}
								if (
									'Arguments' === _nLocal2 ||
									/^(?:Ui|I)nt(?:8|16|32)(?:Clamped)?Array$/.test(
										_nLocal2
									)
								) {
									return zz( _eLocal6, _tLocal3 );
								}
								return void 0;
							} )()
						 );
					}
				} )( _eLocal2, _tLocal ) ||
				( function () {
					throw new TypeError(
						'Invalid attempt to destructure non-iterable instance.\nIn order to be iterable, non-array objects must have a [Symbol.iterator]() method.'
					);
				} )()
			);
		} )( ( 0, ReactHooks.useState )( ! 1 ), 2 );
		var a = r[ 0 ];
		var o = r[ 1 ];
		var availability = t.availability;
		var _availableDate = t.available_date;
		var _accessType = t.access_type;
		var _hasCapacity = t.has_capacity;
		var capacity = t.capacity;
		var level = t.level;
		var _enableReviews = t.enable_reviews;
		var slug = t.slug;
		var _isCohort = t.is_cohort;
		return (
			<React.Fragment>
				<He.default isOpen={ a } onClose={ o } />
				<Controls.SpacerWP marginBottom={ 0 } paddingY={ 6 }>
					<Controls.CardWP isBorderless={ ! 0 }>
						<Controls.SpacerWP marginBottom={ 0 } padding={ 5 }>
							{ React.createElement( mz, null ) }
						</Controls.SpacerWP>
					</Controls.CardWP>
					<Controls.SpacerWP marginBottom={ 6 } />
					<Controls.CardWP
						isBorderless={ ! 0 }
						className={
							'ohmylms-basic-tab ohmylms-course-settings'
						}
					>
						{ ! _isCohort && (
							<Controls.SpacerWP marginBottom={ 0 } padding={ 5 }>
								{ React.createElement( vz, {
									capacity,
									capacityEnabled: _hasCapacity,
									handleCapacityEnabled( _nLocal3 ) {
										e.setCourse(
											Dz(
												Dz( {}, t ),
												{},
												{
													has_capacity: _nLocal3,
												}
											)
										);
									},
									handleCapacityChange( _nLocal4 ) {
										if ( isNaN( _nLocal4 ) ) {
											e.setCourse(
												Dz(
													Dz( {}, t ),
													{},
													{
														capacity: 0,
													}
												)
											);
										} else {
											e.setCourse(
												Dz(
													Dz( {}, t ),
													{},
													{
														capacity: _nLocal4,
													}
												)
											);
										}
									},
								} ) }
							</Controls.SpacerWP>
						) }
						<Controls.SpacerWP marginBottom={ 0 } padding={ 5 }>
							{ React.createElement( wz, {
								access: _accessType,
								handleAccessChange( _nLocal5 ) {
									e.setCourse(
										Dz(
											Dz( {}, t ),
											{},
											{
												access_type: _nLocal5,
											}
										)
									);
								},
							} ) }
						</Controls.SpacerWP>
						<Controls.SpacerWP marginBottom={ 0 } padding={ 5 }>
							<Pz
								durationEnabled={ availability }
								handleDurationEnabled={ function ( _nLocal6 ) {
									e.setCourse(
										Dz(
											Dz( {}, t ),
											{},
											{
												availability: _nLocal6,
											}
										)
									);
								} }
								availableDate={ _availableDate }
								handleDurationChange={ function ( _nLocal7 ) {
									e.setCourse(
										Dz(
											Dz( {}, t ),
											{},
											{
												available_date: _nLocal7,
											}
										)
									);
								} }
							/>
						</Controls.SpacerWP>
						<Controls.SpacerWP marginBottom={ 0 } padding={ 5 }>
							{ React.createElement( kz, {
								experienceLevel: level,
								handleExperienceLevelChange( _nLocal8 ) {
									e.setCourse(
										Dz(
											Dz( {}, t ),
											{},
											{
												level: _nLocal8,
											}
										)
									);
								},
							} ) }
						</Controls.SpacerWP>
						<Controls.SpacerWP marginBottom={ 0 } padding={ 5 }>
							<Iz
								slug={ slug }
								handleSlugChange={ function ( _nLocal9 ) {
									if (
										'' ===
										( _nLocal9 === null ||
										_nLocal9 === void 0
											? void 0
											: _nLocal9.trim() )
									) {
										e.setIsValidCourseSettings( ! 1 );
									} else {
										e.setIsValidCourseSettings( ! 0 );
									}
									e.setCourse(
										Dz(
											Dz( {}, t ),
											{},
											{
												slug: _nLocal9,
											}
										)
									);
								} }
							/>
						</Controls.SpacerWP>
						<Controls.SpacerWP marginBottom={ 0 } padding={ 5 }>
							<Az
								reviewEnabled={ _enableReviews }
								handleReviewEnabled={ function ( _nLocal0 ) {
									e.setCourse(
										Dz(
											Dz( {}, t ),
											{},
											{
												enable_reviews: _nLocal0,
											}
										)
									);
								} }
							/>
						</Controls.SpacerWP>
						<Controls.SpacerWP marginBottom={ 0 } padding={ 5 }>
							<Kt
								title={ ( 0, I18n.__ )(
									'Sequential Lesson Access',
									'ohmylms'
								) }
								isChecked={
									'yes' ===
									( t === null || t === void 0
										? void 0
										: t.sequential_mode )
								}
								onChange={ function ( _rLocal2 ) {
									e.setCourse(
										Dz(
											Dz( {}, t ),
											{},
											{
												sequential_mode: _rLocal2
													? 'yes'
													: 'no',
											}
										)
									);
								} }
								spacerPadding={ 0 }
								description={ ( 0, I18n.__ )(
									'Students must complete each lesson in order before the next one unlocks.',
									'ohmylms'
								) }
							/>
						</Controls.SpacerWP>
					</Controls.CardWP>
				</Controls.SpacerWP>
			</React.Fragment>
		);
	};
}
