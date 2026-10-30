import { Button, Modal, Notice, TextControl } from '@wordpress/components';
import { useCallback, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { useRequest } from '../../hooks/useRequest';
import { listSkills } from '../content-hub';

export function LibraryPicker( {
	busy,
	error,
	onChoose,
	onClose,
}: {
	busy: boolean;
	error: string;
	onChoose: ( id: number ) => Promise< boolean >;
	onClose: () => void;
} ) {
	const [ query, setQuery ] = useState( '' );
	const [ search, setSearch ] = useState( '' );
	const [ page, setPage ] = useState( 1 );
	const load = useCallback(
		( signal: AbortSignal ) => listSkills( page, search, signal ),
		[ page, search ]
	);
	const request = useRequest( load );
	return (
		<Modal
			title={ __( 'Choose a library skill', 'ohmylms' ) }
			onRequestClose={ () => {
				if ( ! busy ) {
					onClose();
				}
			} }
		>
			<form
				onSubmit={ ( event ) => {
					event.preventDefault();
					setPage( 1 );
					setSearch( query );
				} }
			>
				<TextControl
					label={ __( 'Search skills', 'ohmylms' ) }
					value={ query }
					maxLength={ 100 }
					disabled={ busy }
					onChange={ setQuery }
				/>
				<Button type="submit" disabled={ busy }>
					{ __( 'Search', 'ohmylms' ) }
				</Button>
			</form>
			{ ( error || request.error ) && (
				<Notice status="error" isDismissible={ false }>
					{ error || request.error }
				</Notice>
			) }
			{ request.error && (
				<Button onClick={ request.retry }>
					{ __( 'Retry', 'ohmylms' ) }
				</Button>
			) }
			{ request.loading && (
				<p role="status">{ __( 'Loading skills…', 'ohmylms' ) }</p>
			) }
			{ request.data && (
				<>
					<ul>
						{ request.data.items.map( ( skill ) => (
							<li key={ skill.id }>
								<Button
									disabled={ busy || skill.archived }
									onClick={ () =>
										void onChoose( skill.id ).then(
											( saved ) => {
												if ( saved ) {
													onClose();
												}
											}
										)
									}
								>
									{ skill.code } { skill.name }
								</Button>
							</li>
						) ) }
					</ul>
					<Button
						disabled={ busy || page <= 1 }
						onClick={ () => setPage( page - 1 ) }
					>
						{ __( 'Previous', 'ohmylms' ) }
					</Button>
					<span>{ page }</span>
					<Button
						disabled={ busy || page >= request.data.totalPages }
						onClick={ () => setPage( page + 1 ) }
					>
						{ __( 'Next', 'ohmylms' ) }
					</Button>
				</>
			) }
		</Modal>
	);
}
