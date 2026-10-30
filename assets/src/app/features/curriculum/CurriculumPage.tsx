import {
	Button,
	CheckboxControl,
	Modal,
	Notice,
	SelectControl,
	TextareaControl,
	TextControl,
} from '@wordpress/components';
import { useCallback, useEffect, useRef, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Link } from 'react-router-dom';
import { normalizeApiError } from '../../lib/api';
import { useUnsavedChangesGuard } from '../../hooks/useUnsavedChangesGuard';
import { useRequest } from '../../hooks/useRequest';
import {
	itemPath,
	moveItem,
	parentChoices,
	readTree,
	saveItem,
	type CurriculumItem,
	type ItemInput,
} from './api';

const empty: ItemInput = {
	name: '',
	item_type: 'subject',
	description: '',
	code: '',
	version: '',
	is_syllabus: false,
};
export default function CurriculumPage() {
	const load = useCallback(
		( signal: AbortSignal ) => readTree( signal ),
		[]
	);
	const request = useRequest( load );
	const [ editing, setEditing ] = useState< CurriculumItem | null >( null );
	const [ input, setInput ] = useState< ItemInput >( empty );
	const [ parent, setParent ] = useState( 0 );
	const [ search, setSearch ] = useState( '' );
	const [ busy, setBusy ] = useState( false );
	const [ error, setError ] = useState( '' );
	const [ message, setMessage ] = useState( '' );
	const [ baseline, setBaseline ] = useState( JSON.stringify( empty ) );
	const [ switchTo, setSwitchTo ] = useState< {
		item: CurriculumItem | null;
	} | null >( null );
	const dirty = JSON.stringify( input ) !== baseline;
	const guard = useUnsavedChangesGuard( dirty );
	const operation = useRef< AbortController | null >( null );
	useEffect( () => () => operation.current?.abort(), [] );
	function edit( item: CurriculumItem | null ) {
		setEditing( item );
		setInput(
			item
				? {
						name: item.name,
						item_type: item.item_type,
						description: item.description,
						code: item.code,
						version: item.version,
						is_syllabus: item.is_syllabus,
					}
				: empty
		);
		setParent( item?.parent_id ?? 0 );
		setBaseline(
			JSON.stringify(
				item
					? {
							name: item.name,
							item_type: item.item_type,
							description: item.description,
							code: item.code,
							version: item.version,
							is_syllabus: item.is_syllabus,
						}
					: empty
			)
		);
		setError( '' );
	}
	function choose( item: CurriculumItem | null ) {
		if ( dirty ) {
			setSwitchTo( { item } );
		} else {
			edit( item );
		}
	}
	async function run( work: ( signal: AbortSignal ) => Promise< unknown > ) {
		if ( operation.current ) {
			return;
		}
		const controller = new AbortController();
		operation.current = controller;
		setBusy( true );
		setError( '' );
		setMessage( '' );
		try {
			await work( controller.signal );
			if ( ! controller.signal.aborted ) {
				edit( null );
				request.retry();
				setMessage( __( 'Curriculum saved.', 'ohmylms' ) );
			}
		} catch ( failure ) {
			if ( ! controller.signal.aborted ) {
				setError( normalizeApiError( failure ).message );
			}
		} finally {
			if ( ! controller.signal.aborted ) {
				operation.current = null;
				setBusy( false );
			}
		}
	}
	const items = request.data ?? [];
	const choices = [
		{ value: '0', label: __( 'Top level', 'ohmylms' ) },
		...parentChoices( items, editing?.id ?? 0 ).map( ( item ) => ( {
			value: String( item.id ),
			label: itemPath( items, item ),
		} ) ),
	];
	return (
		<section aria-label={ __( 'Curriculum', 'ohmylms' ) }>
			<h1>{ __( 'Curriculum', 'ohmylms' ) }</h1>
			<p>
				<Link to="/content-hub/courses">
					{ __( 'Open courses', 'ohmylms' ) }
				</Link>{ ' ' }
				·{ ' ' }
				<Link to="/content-hub/skills">
					{ __( 'Open skills', 'ohmylms' ) }
				</Link>
			</p>
			{ request.loading && (
				<p role="status">{ __( 'Loading curriculum…', 'ohmylms' ) }</p>
			) }
			{ request.error && (
				<Notice status="error" isDismissible={ false }>
					{ request.error }
					<Button onClick={ request.retry }>
						{ __( 'Retry', 'ohmylms' ) }
					</Button>
				</Notice>
			) }
			{ error && (
				<Notice status="error" isDismissible={ false }>
					{ error }
				</Notice>
			) }
			{ message && (
				<Notice status="success" isDismissible={ false }>
					{ message }
				</Notice>
			) }
			<TextControl
				label={ __( 'Filter curriculum', 'ohmylms' ) }
				value={ search }
				onChange={ setSearch }
			/>
			<ul>
				{ items
					.filter( ( item ) =>
						itemPath( items, item )
							.toLocaleLowerCase()
							.includes( search.toLocaleLowerCase() )
					)
					.map( ( item ) => (
						<li key={ item.id }>
							<Button
								disabled={ busy }
								onClick={ () => choose( item ) }
							>
								{ itemPath( items, item ) }
							</Button>{ ' ' }
							({ item.item_type })
							{ item.course_id > 0 && (
								<>
									{ ' ' }
									·{ ' ' }
									<Link
										to={ `/content-hub/courses/${ item.course_id }` }
									>
										{ __(
											'Edit course structure',
											'ohmylms'
										) }
									</Link>
								</>
							) }
						</li>
					) ) }
			</ul>
			{ ! request.loading && ! request.error && ! items.length && (
				<p>{ __( 'No curriculum items yet.', 'ohmylms' ) }</p>
			) }
			<h2>
				{ editing
					? __( 'Edit curriculum item', 'ohmylms' )
					: __( 'New curriculum item', 'ohmylms' ) }
			</h2>
			<form
				onSubmit={ ( event ) => {
					event.preventDefault();
					void run( ( signal ) =>
						saveItem( input, editing, parent, signal )
					);
				} }
			>
				<fieldset
					disabled={ busy || request.loading || !! request.error }
				>
					<TextControl
						label={ __( 'Name', 'ohmylms' ) }
						value={ input.name }
						maxLength={ 190 }
						onChange={ ( name ) => setInput( { ...input, name } ) }
					/>
					<TextControl
						label={ __( 'Item type', 'ohmylms' ) }
						value={ input.item_type }
						maxLength={ 40 }
						onChange={ ( itemType ) =>
							setInput( { ...input, item_type: itemType } )
						}
						help={ __(
							'For example: subject, grade, topic or syllabus.',
							'ohmylms'
						) }
					/>
					<TextareaControl
						label={ __( 'Description', 'ohmylms' ) }
						value={ input.description }
						onChange={ ( description ) =>
							setInput( { ...input, description } )
						}
					/>
					<TextControl
						label={ __( 'Code', 'ohmylms' ) }
						value={ input.code }
						onChange={ ( code ) => setInput( { ...input, code } ) }
					/>
					<TextControl
						label={ __( 'Version', 'ohmylms' ) }
						value={ input.version }
						onChange={ ( version ) =>
							setInput( { ...input, version } )
						}
					/>
					<CheckboxControl
						label={ __( 'This item is a syllabus', 'ohmylms' ) }
						checked={ input.is_syllabus }
						onChange={ ( isSyllabus ) =>
							setInput( { ...input, is_syllabus: isSyllabus } )
						}
					/>
					{ ! editing && (
						<SelectControl
							label={ __( 'Parent', 'ohmylms' ) }
							value={ String( parent ) }
							options={ choices }
							onChange={ ( value ) =>
								setParent( Number( value ) )
							}
						/>
					) }
					<Button
						variant="primary"
						type="submit"
						disabled={ ! input.name.trim() }
						isBusy={ busy }
					>
						{ __( 'Save item', 'ohmylms' ) }
					</Button>
					{ editing && (
						<Button onClick={ () => choose( null ) }>
							{ __( 'New item', 'ohmylms' ) }
						</Button>
					) }
				</fieldset>
			</form>
			{ editing && (
				<fieldset disabled={ busy }>
					<legend>{ __( 'Move item', 'ohmylms' ) }</legend>
					<SelectControl
						label={ __( 'New parent', 'ohmylms' ) }
						value={ String( parent ) }
						options={ choices }
						onChange={ ( value ) => setParent( Number( value ) ) }
					/>
					<Button
						disabled={ dirty || parent === editing.parent_id }
						onClick={ () =>
							void run( ( signal ) =>
								moveItem( editing, parent, undefined, signal )
							)
						}
					>
						{ __( 'Move saved item', 'ohmylms' ) }
					</Button>
					<p>
						{ __(
							'Moving uses the saved item. Save any field edits first.',
							'ohmylms'
						) }
					</p>
				</fieldset>
			) }
			{ switchTo && (
				<Modal
					title={ __( 'Discard unsaved item edits?', 'ohmylms' ) }
					onRequestClose={ () => setSwitchTo( null ) }
				>
					<Button onClick={ () => setSwitchTo( null ) }>
						{ __( 'Keep editing', 'ohmylms' ) }
					</Button>
					<Button
						onClick={ () => {
							edit( switchTo.item );
							setSwitchTo( null );
						} }
					>
						{ __( 'Discard and switch', 'ohmylms' ) }
					</Button>
				</Modal>
			) }
			{ guard.pendingPath && (
				<Modal
					title={ __( 'Leave with unsaved changes?', 'ohmylms' ) }
					onRequestClose={ guard.stay }
				>
					<Button onClick={ guard.stay }>
						{ __( 'Keep editing', 'ohmylms' ) }
					</Button>
					<Button onClick={ guard.leave }>
						{ __( 'Discard edits and leave', 'ohmylms' ) }
					</Button>
				</Modal>
			) }
		</section>
	);
}
