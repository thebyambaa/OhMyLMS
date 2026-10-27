/** Reconstructed React source. Runtime dependencies are explicit in components.json. */
import { createElement } from '@wordpress/element';
export function createCourseCohortSettings( readRuntime ) {
	return function CourseCohortSettings() {
		const {
			I: Controls,
			React,
			T: StoreModule,
			gH,
			pH,
			vH,
			wH,
			y: WordPressData,
			yH,
		} = readRuntime();
		var e = ( 0, WordPressData.useDispatch )( StoreModule.default ),
			t = ( 0, WordPressData.useSelect )( function ( _eLocal ) {
				return _eLocal( StoreModule.default ).getCourse();
			}, [] ),
			n =
				Array.isArray(
					t === null || t === void 0 ? void 0 : t.cohort
				) && t.cohort.length > 0
					? t.cohort
					: [
							{
								start_date: '',
								end_date: '',
								enrollment_deadline: '',
								has_capacity: ! 1,
								capacity: '',
							},
						],
			r = n[ 0 ],
			a = ( r === null || r === void 0 ? void 0 : r.start_date ) || '',
			o = ( r === null || r === void 0 ? void 0 : r.end_date ) || '',
			i =
				( r === null || r === void 0
					? void 0
					: r.enrollment_deadline ) || '',
			l = ( r === null || r === void 0 ? void 0 : r.has_capacity ) || ! 1,
			c = ( r === null || r === void 0 ? void 0 : r.capacity ) || '';
		return (
			<React.Fragment>
				<Controls.SpacerWP marginBottom={ 0 } paddingY={ 6 }>
					{ React.createElement( pH, {
						onChange( _aLocal, _oLocal ) {
							var _iLocal = wH(
								wH( {}, r ),
								{},
								{
									end_date: _oLocal,
									start_date: _aLocal,
								}
							);
							e.setCourse(
								wH(
									wH( {}, t ),
									{},
									{
										cohort: [ _iLocal ].concat(
											yH( n.slice( 1 ) )
										),
									}
								)
							);
						},
						startDate: a,
						endDate: o,
					} ) }
					<Controls.SpacerWP marginBottom={ 6 } />
					<Controls.CardWP isBorderless={ ! 0 }>
						<Controls.SpacerWP marginBottom={ 0 } padding={ 5 }>
							{ React.createElement( vH, {
								date: i,
								onChange( _aLocal2 ) {
									var _oLocal2 = wH(
										wH( {}, r ),
										{},
										{
											enrollment_deadline: _aLocal2,
										}
									);
									e.setCourse(
										wH(
											wH( {}, t ),
											{},
											{
												cohort: [ _oLocal2 ].concat(
													yH( n.slice( 1 ) )
												),
											}
										)
									);
								},
								startDate: a,
							} ) }
						</Controls.SpacerWP>
					</Controls.CardWP>
					<Controls.SpacerWP marginBottom={ 6 } />
					<Controls.CardWP isBorderless={ ! 0 }>
						<Controls.SpacerWP marginBottom={ 0 } padding={ 5 }>
							{ React.createElement( gH, {
								onEnable( _aLocal3 ) {
									var _oLocal3 = wH(
										wH( {}, r ),
										{},
										{
											has_capacity: _aLocal3,
										}
									);
									e.setCourse(
										wH(
											wH( {}, t ),
											{},
											{
												cohort: [ _oLocal3 ].concat(
													yH( n.slice( 1 ) )
												),
											}
										)
									);
								},
								onChange( _aLocal4 ) {
									var _oLocal4 = wH(
										wH( {}, r ),
										{},
										{
											capacity: isNaN( _aLocal4 )
												? 0
												: _aLocal4,
										}
									);
									e.setCourse(
										wH(
											wH( {}, t ),
											{},
											{
												cohort: [ _oLocal4 ].concat(
													yH( n.slice( 1 ) )
												),
											}
										)
									);
								},
								capacity: c,
								hasCapacity: l,
							} ) }
						</Controls.SpacerWP>
					</Controls.CardWP>
				</Controls.SpacerWP>
			</React.Fragment>
		);
	};
}
