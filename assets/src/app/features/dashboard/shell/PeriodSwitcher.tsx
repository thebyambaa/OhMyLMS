import { Button } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { PERIODS, periodLabel } from './model';
import type { PeriodDays } from './model';

/**
 * 7, 30 or 90 days, as pressed toggle buttons.
 *
 * @param props          Component props.
 * @param props.value    Current period.
 * @param props.onChange Called with the chosen period.
 */
export function PeriodSwitcher( {
	value,
	onChange,
}: {
	value: PeriodDays;
	onChange: ( period: PeriodDays ) => void;
} ) {
	return (
		<div
			role="group"
			aria-label={ __( 'Period', 'ohmylms' ) }
			className="ohmylms-dashboard-period"
		>
			{ PERIODS.map( ( days ) => (
				<Button
					key={ days }
					__next40pxDefaultSize
					variant={ days === value ? 'primary' : 'secondary' }
					aria-pressed={ days === value }
					onClick={ () => onChange( days ) }
				>
					{ periodLabel( days ) }
				</Button>
			) ) }
		</div>
	);
}
