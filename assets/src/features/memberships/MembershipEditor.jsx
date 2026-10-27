import { createElement, Fragment } from '@wordpress/element';
import { validateMembership } from './validateMembership.mjs';
export function createMembershipEditor( readRuntime ) {
	return function MembershipEditor( {
		isOpen,
		setIsOpen,
		isFetch,
		setIsFetch,
		isLoading,
	} ) {
		const {
			I: Controls,
			I8: MembershipCourses,
			w8: MembershipDetails,
			He: UpgradeModal,
			T: StoreModule,
			b: I18n,
			g: ReactHooks,
			y: WordPressData,
		} = readRuntime();
		const plan = WordPressData.useSelect(
			( select ) =>
				select( StoreModule.default ).selectMembershipPlanData(),
			[]
		);
		const actions = WordPressData.useDispatch( StoreModule.default );
		const [ saving, setSaving ] = ReactHooks.useState( false );
		const [ step, setStep ] = ReactHooks.useState( '1' );
		const [ errors, setErrors ] = ReactHooks.useState( {} );
		const [ showUpgrade, setShowUpgrade ] = ReactHooks.useState( false );
		const validate = ( candidate ) => {
			const nextErrors = validateMembership( candidate, I18n.__ );
			setErrors( nextErrors );
			return Object.keys( nextErrors ).length === 0;
		};
		ReactHooks.useEffect( () => () => actions.clearMembershipPlan(), [] );
		ReactHooks.useEffect( () => {
			validate( plan );
		}, [ plan ] );
		const close = () => {
			setIsOpen( false );
			setErrors( {} );
		};
		async function nextOrSave() {
			if ( saving || ! validate( plan ) ) {
				return;
			}
			if ( step === '1' ) {
				setStep( '2' );
				return;
			}
			setSaving( true );
			try {
				await actions.addMembershipPlan( plan, plan?.id );
				// The existing store reports request failures through notification state.
				if (
					WordPressData.select(
						StoreModule.default
					).getNotificationStatus() === 'success'
				) {
					close();
					setIsFetch( ! isFetch );
				}
			} finally {
				setSaving( false );
			}
		}
		const items = [
			{
				key: '1',
				label: (
					<Controls.TextWP as="span" size="16" weight="500">
						{ I18n.__( 'Details', 'ohmylms' ) }
					</Controls.TextWP>
				),
				children: (
					<Controls.CardWP isBorderless>
						<Controls.SpacerWP
							marginBottom={ 0 }
							padding={ 3 }
							marginTop={ 4 }
						>
							<MembershipDetails
								errors={ errors }
								setErrors={ setErrors }
								validate={ validate }
							/>
							{ window.ohmylms.extensions.membershipPanels( {
								plan,
								onChange: actions.updateMembershipPlan,
							} ) }
						</Controls.SpacerWP>
					</Controls.CardWP>
				),
			},
			{
				key: '2',
				label: (
					<Controls.TextWP as="span" size="16" weight="500">
						{ I18n.__( 'Contents', 'ohmylms' ) }
					</Controls.TextWP>
				),
				children: (
					<Controls.CardWP isBorderless>
						<Controls.SpacerWP
							marginBottom={ 0 }
							padding={ 3 }
							marginTop={ 4 }
						>
							<MembershipCourses />
						</Controls.SpacerWP>
					</Controls.CardWP>
				),
			},
		];
		return (
			<Fragment>
				{ isOpen && (
					<Controls.ModalWP
						title={ I18n.__( 'Add Plan', 'ohmylms' ) }
						style={ {
							width: '830px',
							background: '#F5F5F5',
						} }
						onRequestClose={ close }
						shouldCloseOnEsc
						shouldCloseOnClickOutside
						className="ohmylms-full-height-modal ohmylms-membership-modal"
						size="fill"
					>
						{ isLoading ? (
							<Controls.SkeletonWP rows={ 10 } />
						) : (
							<Fragment>
								<Controls.TabsWP
									key={ step }
									items={ items }
									activekey={ step }
									onChange={ setStep }
									className="ohmylms-tab-has-custom-navigation"
								/>
								<Controls.DividerWP marginStart={ 4 } />
								<Controls.SpacerWP marginTop={ 4 }>
									<Controls.FlexWP
										justify="flex-end"
										align="center"
										gap={ 2 }
									>
										<Controls.ButtonWP
											variant="secondary"
											onClick={ close }
											className="ohmylms-membership-plan-cancel-button"
										>
											{ I18n.__( 'Cancel', 'ohmylms' ) }
										</Controls.ButtonWP>
										<Controls.ButtonWP
											variant="primary"
											onClick={ nextOrSave }
											disabled={
												saving ||
												Object.keys( errors ).length > 0
											}
											isBusy={ saving }
											className="ohmylms-membership-plan-save-button"
										>
											{ step === '1'
												? I18n.__( 'Next', 'ohmylms' )
												: I18n.__( 'Save', 'ohmylms' ) }
										</Controls.ButtonWP>
									</Controls.FlexWP>
								</Controls.SpacerWP>
							</Fragment>
						) }
						{ showUpgrade && (
							<UpgradeModal.default
								isOpen={ showUpgrade }
								onClose={ setShowUpgrade }
							/>
						) }
					</Controls.ModalWP>
				) }
			</Fragment>
		);
	};
}
