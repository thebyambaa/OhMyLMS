/** Reconstructed React source. Runtime dependencies are explicit in components.json. */
import { createElement } from '@wordpress/element';
export function createReportMetricCard( readRuntime ) {
	return function ReportMetricCard( props ) {
		const { I: Controls, React } = readRuntime();
		var t = props.title,
			n = void 0 === t ? '' : t,
			r = props.numberSize,
			a = void 0 === r ? 44 : r,
			o = props.cardNumber,
			i = void 0 === o ? '' : o,
			l = props.icon,
			c = props.children;
		return (
			<React.Fragment>
				<Controls.FlexWP
					justify={ 'start' }
					direction={ 'column' }
					gap={ 2 }
				>
					<Controls.FlexWP
						justify={ 'start' }
						align={ 'center' }
						gap={ 1 }
					>
						{ l && (
							<Controls.FlexItemWP
								style={ {
									minWidth: '40px',
									textAlign: 'center',
								} }
							>
								<Controls.BadgeWP
									isBorderLess={ ! 0 }
									isRounded={ ! 0 }
									width={ '30px' }
									height={ '30px' }
								>
									{ l }
								</Controls.BadgeWP>
							</Controls.FlexItemWP>
						) }
						{ n && (
							<Controls.HeadingWP
								level={ 4 }
								size={ 13 }
								variant={ 'muted' }
							>
								{ n }
							</Controls.HeadingWP>
						) }
					</Controls.FlexWP>
					{ c || (
						<Controls.TextWP
							style={ {
								marginLeft: '40px',
							} }
							size={ a }
						>
							{ i < 10 ? '0'.concat( i ) : i }
						</Controls.TextWP>
					) }
				</Controls.FlexWP>
			</React.Fragment>
		);
	};
}
