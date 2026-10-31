import { createElement, useEffect, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import {
	Button,
	CheckboxControl,
	Modal,
	Notice,
	SelectControl,
	Spinner,
	TextControl,
} from '@wordpress/components';
import { listSkills, searchBank } from './api.mjs';
import {
	cleanFilters,
	DIFFICULTIES,
	flattenTree,
	selectableResults,
	skillTree,
	versionLabel,
} from './model.mjs';

/**
 * Pick existing bank questions for a quiz document. The caller inserts them as question blocks, and
 * saving the document places them by reference; the question content stays in the bank.
 * @param root0
 * @param root0.quizId      Quiz being edited; its questions are left out of the results.
 * @param root0.existingIds Questions already in the document.
 * @param root0.onClose
 * @param root0.onPick      Receives the chosen question IDs.
 */
export function BankPicker( { quizId, existingIds, onClose, onPick } ) {
	const [ filters, setFilters ] = useState( {
		search: '',
		skill: '',
		difficulty: '',
		status: 'approved',
	} );
	const [ items, setItems ] = useState( null );
	const [ skills, setSkills ] = useState( [] );
	const [ chosen, setChosen ] = useState( [] );
	const [ busy, setBusy ] = useState( false );
	const [ error, setError ] = useState( '' );
	useEffect( () => {
		listSkills()
			.then( ( data ) => setSkills( data.skills || [] ) )
			.catch( () => {} );
	}, [] );
	useEffect( () => {
		let active = true;
		const timer = setTimeout( () => {
			searchBank( {
				...cleanFilters( filters ),
				exclude_quiz: quizId,
				per_page: 50,
			} )
				.then(
					( data ) =>
						active &&
						setItems( selectableResults( data.items, existingIds ) )
				)
				.catch( ( cause ) => active && setError( cause.message ) );
		}, 250 );
		return () => {
			active = false;
			clearTimeout( timer );
		};
	}, [ filters, quizId, existingIds ] );
	async function add() {
		setBusy( true );
		setError( '' );
		try {
			await onPick( chosen );
			onClose();
		} catch ( cause ) {
			setError(
				cause.message || __( 'Could not add the questions.', 'ohmylms' )
			);
		} finally {
			setBusy( false );
		}
	}
	const set = ( key ) => ( value ) =>
		setFilters( {
			...filters,
			[ key ]: value,
		} );
	return (
		<Modal
			title={ __( 'Add questions from the bank', 'ohmylms' ) }
			onRequestClose={ onClose }
			size="large"
		>
			{ error && (
				<Notice status="error" isDismissible={ false }>
					{ error }
				</Notice>
			) }
			<div
				style={ {
					display: 'flex',
					flexWrap: 'wrap',
					gap: 12,
					alignItems: 'end',
				} }
			>
				<TextControl
					label={ __( 'Search', 'ohmylms' ) }
					value={ filters.search }
					onChange={ set( 'search' ) }
				/>
				<SelectControl
					label={ __( 'Skill', 'ohmylms' ) }
					value={ filters.skill }
					options={ [
						{
							value: '',
							label: __( 'Any skill', 'ohmylms' ),
						},
						...flattenTree( skillTree( skills ) ).map(
							( skill ) => ( {
								value: String( skill.id ),
								label: `${ '— '.repeat( skill.depth ) }${ skill.name }`,
							} )
						),
					] }
					onChange={ set( 'skill' ) }
				/>
				<SelectControl
					label={ __( 'Difficulty', 'ohmylms' ) }
					value={ filters.difficulty }
					options={ [
						{
							value: '',
							label: __( 'Any', 'ohmylms' ),
						},
						...DIFFICULTIES.map( ( value ) => ( {
							value,
							label: value,
						} ) ),
					] }
					onChange={ set( 'difficulty' ) }
				/>
				<SelectControl
					label={ __( 'Status', 'ohmylms' ) }
					value={ filters.status }
					options={ [
						{
							value: 'approved',
							label: __( 'Approved', 'ohmylms' ),
						},
						{
							value: '',
							label: __( 'All I can use', 'ohmylms' ),
						},
					] }
					onChange={ set( 'status' ) }
				/>
			</div>
			{ ( () => {
				if ( ! items ) {
					return <Spinner />;
				}
				if ( items.length === 0 ) {
					return <p>{ __( 'No matching questions.', 'ohmylms' ) }</p>;
				}
				return (
					<ul
						className="ohmylms-bank-picker-list"
						style={ {
							maxHeight: 360,
							overflow: 'auto',
						} }
					>
						{ items.map( ( item ) => (
							<li key={ item.id }>
								<CheckboxControl
									label={ `${ item.name } — ${ item.type } · ${ versionLabel( item ) }${ item.can_edit ? '' : ` · ${ __( 'shared, read-only', 'ohmylms' ) }` }` }
									checked={ chosen.includes( item.id ) }
									disabled={ item.alreadyInQuiz }
									onChange={ ( checked ) =>
										setChosen(
											checked
												? [ ...chosen, item.id ]
												: chosen.filter(
														( id ) => id !== item.id
													)
										)
									}
								/>
							</li>
						) ) }
					</ul>
				);
			} )() }
			<Button
				variant="primary"
				isBusy={ busy }
				disabled={ ! chosen.length }
				onClick={ add }
			>
				{ sprintf(
					/* translators: %d: number of selected questions. */ __(
						'Add %d question(s)',
						'ohmylms'
					),
					chosen.length
				) }
			</Button>
		</Modal>
	);
}
