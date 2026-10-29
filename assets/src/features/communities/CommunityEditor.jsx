import { showRequestError } from '../requestFeedback.mjs';
import { createElement } from '@wordpress/element';
import { validateCommunity } from './model.mjs';
import * as Controls from '../../shared/controls';
export function createCommunityEditor( readRuntime ) {
	return function CommunityEditor( {
		isOpen,
		setIsOpen,
		isLoading,
		onSuccess,
	} ) {
		const {
			React,
			He: ProDialog,
			T: StoreModule,
			b: I18n,
			g: ReactHooks,
			y: WordPressData,
			h9: CommunityDetails,
			k9: CommunityCourses,
		} = readRuntime();
		const { createCommunity, setCommunity } = WordPressData.useDispatch(
			StoreModule.default
		);
		const community = WordPressData.useSelect(
			( select ) => select( StoreModule.default ).selectCommunity(),
			[]
		);
		const [ saving, setSaving ] = ReactHooks.useState( false );
		const [ activeTab, setActiveTab ] = ReactHooks.useState( '1' );
		const [ errors, setErrors ] = ReactHooks.useState( {} );
		const [ showPro, setShowPro ] = ReactHooks.useState( false );
		const savingRef = ReactHooks.useRef( false );
		function validate( value ) {
			const nextErrors = validateCommunity( value, I18n.__ );
			setErrors( nextErrors );
			return Object.keys( nextErrors ).length === 0;
		}
		function close() {
			if ( savingRef.current ) {
				return;
			}
			setIsOpen( false );
			setErrors( {} );
		}
		async function save() {
			if ( savingRef.current || isLoading || ! validate( community ) ) {
				return;
			}
			if ( activeTab === '1' ) {
				setActiveTab( '2' );
				return;
			}
			savingRef.current = true;
			setSaving( true );
			try {
				const response = await createCommunity( community );
				if ( response?.status === 'success' ) {
					setIsOpen( false );
					setErrors( {} );
					onSuccess?.();
				}
			} catch ( error ) {
				showRequestError( 'Error creating community:', error );
			} finally {
				savingRef.current = false;
				setSaving( false );
			}
		}
		ReactHooks.useEffect( () => () => setCommunity( {} ), [] );
		ReactHooks.useEffect( () => {
			validate( community );
		}, [ community ] );
		const tabs = [
			{
				key: '1',
				label: (
					<Controls.TextWP as="span" size="16" weight="500">
						{ I18n.__( 'Details', 'ohmylms' ) }{ ' ' }
					</Controls.TextWP>
				),
				children: (
					<Controls.CardWP isBorderless>
						<Controls.SpacerWP
							marginBottom={ 0 }
							padding={ 3 }
							marginTop={ 4 }
						>
							<CommunityDetails
								errors={ errors }
								setErrors={ setErrors }
								validate={ validate }
							/>
						</Controls.SpacerWP>
					</Controls.CardWP>
				),
			},
			{
				key: '2',
				label: (
					<Controls.TextWP as="span" size="16" weight="500">
						{ I18n.__( 'Courses', 'ohmylms' ) }{ ' ' }
					</Controls.TextWP>
				),
				children: (
					<Controls.CardWP isBorderless>
						<Controls.SpacerWP
							marginBottom={ 0 }
							padding={ 3 }
							marginTop={ 4 }
						>
							<CommunityCourses />
						</Controls.SpacerWP>
					</Controls.CardWP>
				),
			},
		];
		return (
			<React.Fragment>
				{ isOpen && (
					<Controls.ModalWP
						title={ I18n.__( 'Add Community', 'ohmylms' ) }
						style={ {
							width: '830px',
							background: '#F5F5F5',
						} }
						onRequestClose={ close }
						shouldCloseOnEsc
						shouldCloseOnClickOutside
						className="ohmylms-full-height-modal"
						size="fill"
					>
						{ isLoading ? (
							<Controls.SkeletonWP rows={ 10 } />
						) : (
							<React.Fragment>
								<Controls.TabsWP
									items={ tabs }
									activekey={ activeTab }
									onChange={ setActiveTab }
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
										>
											{ I18n.__( 'Cancel', 'ohmylms' ) }
										</Controls.ButtonWP>
										<Controls.ButtonWP
											variant="primary"
											onClick={ save }
											disabled={
												Object.keys( errors ).length >
													0 || saving
											}
											loading={ saving }
										>
											{ ( () => {
												if ( activeTab === '1' ) {
													return I18n.__(
														'Next',
														'ohmylms'
													);
												}
												if ( saving ) {
													return I18n.__(
														'Creating…',
														'ohmylms'
													);
												}
												return I18n.__(
													'Save',
													'ohmylms'
												);
											} )() }
										</Controls.ButtonWP>
									</Controls.FlexWP>
								</Controls.SpacerWP>
							</React.Fragment>
						) }
						{ showPro && (
							<React.Fragment>
								<ProDialog.default
									isOpen={ showPro }
									onClose={ setShowPro }
								/>
							</React.Fragment>
						) }
					</Controls.ModalWP>
				) }
			</React.Fragment>
		);
	};
}
