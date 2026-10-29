/** Reconstructed React source. Runtime dependencies are explicit in components.json. */
import { createElement } from '@wordpress/element';
import { useNotifications } from '../../shared/notices';
export function createCouponsPage( readRuntime ) {
	return function CouponsPage() {
		const {
			Gre,
			HG,
			I: Controls,
			React,
			YG: PageHeader,
			b: I18n,
		} = readRuntime();
		HG( 'ohmylms', 'coupons' );
		var e = useNotifications().contextHolder;
		return (
			<React.Fragment>
				{ e }
				<Controls.ContainerWP>
					<PageHeader
						title={ ( 0, I18n.__ )( 'Coupon', 'ohmylms' ) }
						showAddButton={ ! 1 }
					/>
					<Gre />
				</Controls.ContainerWP>
			</React.Fragment>
		);
	};
}
