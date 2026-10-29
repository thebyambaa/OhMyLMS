/**
 * SubscriptionStatus component (replaces recovered binding KQ).
 * Controls subscription status dropdown and update action.
 */
import { createElement } from '@wordpress/element';
import * as Controls from '../../shared/controls';
export function createSubscriptionStatus( readRuntime ) {
	return function SubscriptionStatus( { subscription, status } ) {
		const {
			React,
			T: StoreModule,
			b: I18n,
			g: ReactHooks,
			vn: SelectControl,
			y: WordPressData,
		} = readRuntime();
		const dispatch = WordPressData.useDispatch( StoreModule.default );
		const {
			updateSubscription,
			fetchSubscription,
			showNotification,
			updateSubscriptionState,
		} = dispatch;
		const [ loading, setLoading ] = ReactHooks.useState( false );
		const [ isOpen, setIsOpen ] = ReactHooks.useState( true );
		const handleUpdate = async () => {
			setLoading( true );
			try {
				const result = await updateSubscription( subscription );
				if ( result ) {
					showNotification(
						I18n.__( 'Updated Successfully', 'ohmylms' ),
						'success'
					);
					await fetchSubscription( subscription.id );
				}
			} catch {
				showNotification(
					I18n.__( 'Something went wrong!', 'ohmylms' ),
					'error'
				);
			} finally {
				setLoading( false );
			}
		};
		const statusOptions = [
			{
				value: 'pending',
				label: I18n.__( 'Pending', 'ohmylms' ),
			},
			{
				value: 'active',
				label: I18n.__( 'Active', 'ohmylms' ),
			},
			{
				value: 'on-hold',
				label: I18n.__( 'On-hold', 'ohmylms' ),
			},
			{
				value: 'pending-cancel',
				label: I18n.__( 'Pending cancel', 'ohmylms' ),
			},
			{
				value: 'cancelled',
				label: I18n.__( 'Cancelled', 'ohmylms' ),
			},
			{
				value: 'expired',
				label: I18n.__( 'Expired', 'ohmylms' ),
			},
		];
		return (
			<React.Fragment>
				<Controls.FlexWP
					gap={ 2 }
					justify="space-between"
					align="center"
				>
					<Controls.HeadingWP
						level={ 4 }
						size={ 18 }
						weight={ 500 }
						color="#000D25"
					>
						{ I18n.__( 'Subscription action', 'ohmylms' ) }
					</Controls.HeadingWP>
					<Controls.ButtonWP
						size="small"
						onClick={ () => setIsOpen( ! isOpen ) }
					>
						<svg
							style={ {
								transform: isOpen
									? 'rotate(0deg)'
									: 'rotate(180deg)',
							} }
							width="12"
							height="6"
							fill="none"
							viewBox="0 0 12 6"
							xmlns="http://www.w3.org/2000/svg"
						>
							<path
								fill="#000D25"
								d="M11.5 4.4L6 0 .5 4.4l.9 1.2L6 2l4.5 3.6 1-1.2z"
							/>
						</svg>
					</Controls.ButtonWP>
				</Controls.FlexWP>
				{ isOpen && (
					<React.Fragment>
						<Controls.SpacerWP marginTop={ 6 } marginBottom={ 0 } />
						<Controls.FlexWP
							gap={ 2 }
							justify="start"
							align="center"
						>
							<Controls.FlexItemWP
								style={ {
									width: 'calc(100% - 53px)',
								} }
							>
								<SelectControl.A
									defaultValue={ status }
									onChange={ ( val ) =>
										updateSubscriptionState( {
											status: val,
										} )
									}
									options={ statusOptions }
								/>
							</Controls.FlexItemWP>
							<Controls.ButtonWP
								variant="primary"
								onClick={ handleUpdate }
								loading={ loading }
								style={ {
									height: '40px',
									width: '53px',
									backgroundColor: '#6e42d34d',
								} }
							>
								<svg
									style={ {
										margin: '0 auto',
									} }
									width="7"
									height="12"
									fill="none"
									viewBox="0 0 7 12"
									xmlns="http://www.w3.org/2000/svg"
								>
									<path
										fill="#000D25"
										d="M2.018.5l4.4 5.5-4.4 5.5-1.2-.9 3.6-4.6-3.6-4.5 1.2-1z"
									/>
								</svg>
							</Controls.ButtonWP>
						</Controls.FlexWP>
					</React.Fragment>
				) }
			</React.Fragment>
		);
	};
}
