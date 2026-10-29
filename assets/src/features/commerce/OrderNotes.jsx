import { showRequestError } from '../requestFeedback.mjs';
/**
 * OrderNotes component (replaces recovered binding fQ).
 * Displays order notes timeline and handles adding new notes.
 */
import { createElement } from '@wordpress/element';
import * as Controls from '../../shared/controls';
import { decodeHtmlEntities } from '../../shared/utils';
export function createOrderNotes( readRuntime ) {
	return function OrderNotes( { notes = [], order } ) {
		const {
			JY: formatOrderDate,
			React,
			T: StoreModule,
			W: TextareaControl,
			b: I18n,
			g: ReactHooks,
			q: ExperimentalScrollable,
			y: WordPressData,
		} = readRuntime();
		const [ noteContent, setNoteContent ] = ReactHooks.useState( '' );
		const [ submitting, setSubmitting ] = ReactHooks.useState( false );
		const [ isOpen, setIsOpen ] = ReactHooks.useState( true );
		const dispatch = WordPressData.useDispatch( StoreModule.default );
		const { setOrderNote, saveOrderNote, fetchOrder } = dispatch;
		const handleAddNote = async () => {
			if ( submitting || ! noteContent.trim() || ! order ) {
				return;
			}
			setSubmitting( true );
			try {
				const result = await saveOrderNote( noteContent, order );
				if ( result ) {
					await fetchOrder( order.id );
					setNoteContent( '' );
				}
			} catch ( err ) {
				showRequestError( 'Failed to add order note:', err );
			} finally {
				setSubmitting( false );
			}
		};
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
						{ I18n.__( 'Order Notes', 'ohmylms' ) }
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
						{ notes && notes.length > 0 && (
							<React.Fragment>
								<Controls.SpacerWP
									marginTop={ 6 }
									marginBottom={ 0 }
								/>
								<ExperimentalScrollable.__experimentalScrollable
									style={ {
										maxHeight: 500,
									} }
								>
									{ notes.map( ( note, index ) => (
										<div key={ index }>
											<Controls.SpacerWP
												marginBottom={ 0 }
												marginTop={
													index === 0 ? 0 : 4
												}
												paddingX={ 2 }
											>
												<Controls.CardWP
													isBorderless={ true }
													variant="muted"
													style={ {
														borderRadius: '7px',
													} }
												>
													<Controls.SpacerWP
														marginBottom={ 0 }
														padding={ 4 }
													>
														<Controls.TextWP
															variant="muted"
															color="#000D25"
															weight={ 400 }
															size={ 13 }
														>
															{ decodeHtmlEntities(
																note.content
															) }
														</Controls.TextWP>
													</Controls.SpacerWP>
												</Controls.CardWP>
											</Controls.SpacerWP>
											<Controls.SpacerWP
												marginBottom={ 1 }
											/>
											<Controls.FlexWP
												align="center"
												gap={ 3 }
												justify="space-between"
											>
												<Controls.TextWP
													as="time"
													variant="muted"
													size={ 13 }
													color="#8C929B"
												>
													{ note?.date_created?.date
														? formatOrderDate(
																note
																	.date_created
																	.date
															)
														: '' }
												</Controls.TextWP>
											</Controls.FlexWP>
										</div>
									) ) }
								</ExperimentalScrollable.__experimentalScrollable>
							</React.Fragment>
						) }
						<Controls.SpacerWP marginTop={ 6 } marginBottom={ 0 } />
						<div>
							<TextareaControl.A
								rows={ 3 }
								value={ noteContent }
								placeholder="Add a note"
								onChange={ ( val ) => {
									setNoteContent( val );
									setOrderNote( val );
								} }
								style={ {
									marginBottom: '10px',
								} }
							/>
							<Controls.ButtonWP
								variant="primary"
								style={ {
									marginRight: '10px',
								} }
								onClick={ handleAddNote }
								loading={ submitting }
								iconPosition="end"
							>
								{ I18n.__( 'Add Note', 'ohmylms' ) }
							</Controls.ButtonWP>
						</div>
					</React.Fragment>
				) }
			</React.Fragment>
		);
	};
}
