/** Reconstructed React source. Runtime dependencies are explicit in components.json. */
import { createElement } from '@wordpress/element';
export function createAnalyticsDateFilter( readRuntime ) {
	return function AnalyticsDateFilter( props ) {
		const {
			I: Controls,
			React,
			b: I18n,
			g: ReactHooks,
			qU,
		} = readRuntime();
		var t = props.defaultValue,
			n = props.placeholder,
			r = props.onChange,
			a = props.direction,
			o = void 0 === a ? 'end' : a,
			i = props.onRangeChange,
			l = qU( ( 0, ReactHooks.useState )( t ), 2 ),
			c = l[ 0 ],
			u = l[ 1 ],
			s = qU(
				( 0, ReactHooks.useState )( {
					startDate: null,
					endDate: null,
				} ),
				2
			),
			d = s[ 0 ],
			m = s[ 1 ],
			p = ( 0, ReactHooks.useMemo )( function () {
				return [
					{
						value: 'all',
						label: ( 0, I18n.__ )( 'All Times', 'ohmylms' ),
					},
					{
						value: 'last_30_days',
						label: ( 0, I18n.__ )( 'Last 30 days', 'ohmylms' ),
					},
					{
						value: 'current_month',
						label: ( 0, I18n.__ )( 'Current month', 'ohmylms' ),
					},
					{
						value: 'previous_month',
						label: ( 0, I18n.__ )( 'Previous month', 'ohmylms' ),
					},
					{
						value: 'current_year',
						label: ( 0, I18n.__ )( 'Current year', 'ohmylms' ),
					},
					{
						value: 'last_12_months',
						label: ( 0, I18n.__ )( 'Last 12 months', 'ohmylms' ),
					},
					{
						value: 'custom_range',
						label: ( 0, I18n.__ )( 'Custom Range', 'ohmylms' ),
					},
				];
			}, [] ),
			f = ( 0, ReactHooks.useCallback )(
				function ( e ) {
					u( e );
					if ( r ) {
						r( e );
					}
				},
				[ r ]
			),
			v = ( 0, ReactHooks.useCallback )(
				function ( e ) {
					var _tLocal = qU( e, 2 ),
						_nLocal = _tLocal[ 0 ],
						_rLocal = _tLocal[ 1 ];
					m( {
						startDate: _nLocal,
						endDate: _rLocal,
					} );
					if ( i ) {
						i( e );
					}
				},
				[ r ]
			);
		return (
			<React.Fragment>
				<Controls.FlexWP
					justify={ 'flex-start' }
					align={ 'center' }
					gap={ 2 }
					direction={ 'start' === o ? 'row-reverse' : 'row' }
				>
					<Controls.FlexItemWP>
						<Controls.SelectWP
							value={ c !== null && c !== void 0 ? c : '' }
							placeholder={ n }
							onChange={ f }
							options={ p }
						/>
					</Controls.FlexItemWP>
					{ 'custom_range' === c && (
						<Controls.FlexItemWP
							style={ {
								border: '1px solid #c8d2e9',
								height: '40px',
							} }
						>
							<Controls.DateRangePickerWP
								initialStartDate={ d.startDate }
								initialEndDate={ d.endDate }
								onChange={ v }
							/>
						</Controls.FlexItemWP>
					) }
				</Controls.FlexWP>
			</React.Fragment>
		);
	};
}
