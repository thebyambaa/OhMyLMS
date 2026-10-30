import {
	Button,
	Modal,
	Notice,
	SelectControl,
	TextControl,
} from '@wordpress/components';
import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

export function MoveDialog( {
	title,
	options,
	current,
	busy,
	error,
	onMove,
	onClose,
}: {
	title: string;
	options: { value: string; label: string }[];
	current: number;
	busy: boolean;
	error: string;
	onMove: ( destination: number, position: number ) => Promise< boolean >;
	onClose: () => void;
} ) {
	const [ target, setTarget ] = useState( String( current ) );
	const [ position, setPosition ] = useState( '1' );
	return (
		<Modal
			title={ title }
			onRequestClose={ () => {
				if ( ! busy ) {
					onClose();
				}
			} }
		>
			{ error && (
				<Notice status="error" isDismissible={ false }>
					{ error }
				</Notice>
			) }
			<form
				onSubmit={ ( event ) => {
					event.preventDefault();
					void onMove(
						Number( target ),
						Number( position ) - 1
					).then( ( saved ) => {
						if ( saved ) {
							onClose();
						}
					} );
				} }
			>
				<fieldset disabled={ busy }>
					<SelectControl
						label={ __( 'Destination', 'ohmylms' ) }
						value={ target }
						options={ options }
						onChange={ setTarget }
					/>
					<TextControl
						label={ __( 'Position (starting at 1)', 'ohmylms' ) }
						type="number"
						min={ 1 }
						max={ 5001 }
						value={ position }
						onChange={ setPosition }
					/>
					<Button
						variant="primary"
						type="submit"
						disabled={
							! Number.isSafeInteger( Number( position ) ) ||
							Number( position ) < 1 ||
							Number( position ) > 5001 ||
							! target
						}
					>
						{ __( 'Move', 'ohmylms' ) }
					</Button>
				</fieldset>
			</form>
		</Modal>
	);
}
