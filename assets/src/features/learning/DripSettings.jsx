/** Reconstructed React source. Runtime dependencies are explicit in components.json. */
import { createElement } from '@wordpress/element';
export function createDripSettings( readRuntime ) {
	return function DripSettings( props ) {
		const { I: Controls, Kt, React, Rn, b: I18n, xn } = readRuntime();
		var onChange = props.onChange,
			isChecked = props.isChecked,
			onDripFeedTypeChange = props.onDripFeedTypeChange,
			handleDripDatePickerChange = props.handleDripDatePickerChange,
			handleDripTimePickerChange = props.handleDripTimePickerChange,
			handleDayChange = props.handleDayChange,
			dripFeedType = props.dripFeedType,
			dripDate = props.dripDate,
			dripTime = props.dripTime,
			enrollmentFromXDays = props.enrollmentFromXDays,
			isCohortBased = props.isCohortBased,
			padding = props.padding,
			p = void 0 === padding ? 4 : padding;
		return (
			( function ( e, t ) {
				if ( e === null || e === void 0 ) {
					return {};
				}
				var n,
					r,
					a = ( function ( _eLocal, _tLocal ) {
						if ( _eLocal === null || _eLocal === void 0 ) {
							return {};
						}
						var _nLocal = {};
						for ( var _rLocal in _eLocal ) {
							if ( {}.hasOwnProperty.call( _eLocal, _rLocal ) ) {
								if ( -1 !== _tLocal.indexOf( _rLocal ) ) {
									continue;
								}
								_nLocal[ _rLocal ] = _eLocal[ _rLocal ];
							}
						}
						return _nLocal;
					} )( e, t );
				if ( Object.getOwnPropertySymbols ) {
					var o = Object.getOwnPropertySymbols( e );
					for ( r = 0; r < o.length; r++ ) {
						n = o[ r ];
						if (
							-1 === t.indexOf( n ) &&
							{}.propertyIsEnumerable.call( e, n )
						) {
							a[ n ] = e[ n ];
						}
					}
				}
			} )( props, xn ),
			(
				<React.Fragment>
					<Kt
						title={ ( 0, I18n.__ )( 'Drip Settings', 'ohmylms' ) }
						tooltip={ ( 0, I18n.__ )(
							'Schedule this lesson to unlock after a set number of days or a specific date.',
							'ohmylms'
						) }
						customClass={
							'ohmylms-lesson-settings-drip-feed-button'
						}
						onChange={ onChange }
						isChecked={ isChecked }
						isItProFeature={ ! 0 }
						spacerPadding={ p }
						conditionalChild={
							<React.Fragment>
								<Controls.SpacerWP marginBottom={ 3 } />
								<Rn
									onDripFeedTypeChange={
										onDripFeedTypeChange
									}
									handleDripDatePickerChange={
										handleDripDatePickerChange
									}
									handleDripTimePickerChange={
										handleDripTimePickerChange
									}
									handleDayChange={ handleDayChange }
									dripFeedType={ dripFeedType }
									dripDate={ dripDate }
									dripTime={ dripTime }
									enrollmentFromXDays={ enrollmentFromXDays }
									isCohortBased={ isCohortBased }
								/>
							</React.Fragment>
						}
					/>
				</React.Fragment>
			 )
		 );
	};
}
