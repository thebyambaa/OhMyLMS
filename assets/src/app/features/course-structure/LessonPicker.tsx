import { Button, Modal, TextControl } from '@wordpress/components';
import { useCallback, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { findLessons } from './api';
import { useRequest } from '../../hooks/useRequest';

export function LessonPicker( {
	course,
	onSelect,
	onClose,
}: {
	course: number;
	onSelect: ( lesson: { id: number; title: string } ) => void;
	onClose: () => void;
} ) {
	const [ query, setQuery ] = useState( '' );
	const [ search, setSearch ] = useState( '' );
	const [ page, setPage ] = useState( 1 );
	const load = useCallback(
		( signal: AbortSignal ) => findLessons( course, search, page, signal ),
		[ course, search, page ]
	);
	const request = useRequest( load );
	return (
		<Modal
			title={ __( 'Attach an existing lesson', 'ohmylms' ) }
			onRequestClose={ onClose }
		>
			<form
				onSubmit={ ( event ) => {
					event.preventDefault();
					setPage( 1 );
					setSearch( query );
				} }
			>
				<TextControl
					label={ __( 'Search lessons', 'ohmylms' ) }
					value={ query }
					onChange={ setQuery }
					maxLength={ 100 }
				/>
				<Button type="submit">{ __( 'Search', 'ohmylms' ) }</Button>
			</form>
			{ request.loading && (
				<p role="status">{ __( 'Loading lessons…', 'ohmylms' ) }</p>
			) }
			{ request.error && (
				<p role="alert">
					{ request.error }
					<Button onClick={ request.retry }>
						{ __( 'Retry', 'ohmylms' ) }
					</Button>
				</p>
			) }
			{ request.data && (
				<>
					<ul>
						{ request.data.items.map( ( lesson ) => (
							<li key={ lesson.id }>
								<Button onClick={ () => onSelect( lesson ) }>
									{ lesson.title }
								</Button>
							</li>
						) ) }
					</ul>
					{ ! request.data.items.length && (
						<p>{ __( 'No editable lessons found.', 'ohmylms' ) }</p>
					) }
					<Button
						disabled={ page <= 1 }
						onClick={ () => setPage( page - 1 ) }
					>
						{ __( 'Previous', 'ohmylms' ) }
					</Button>
					<span>{ page }</span>
					<Button
						disabled={ page >= request.data.pages }
						onClick={ () => setPage( page + 1 ) }
					>
						{ __( 'Next', 'ohmylms' ) }
					</Button>
				</>
			) }
		</Modal>
	);
}
