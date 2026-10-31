import {
	Button,
	Modal,
	Notice,
	RadioControl,
	SelectControl,
	TextControl,
} from '@wordpress/components';
import { useEffect, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { useOperation } from '../../hooks/useOperation';
import { normalizeApiError } from '../../lib/api';
import {
	issueCertificate,
	listCourseOptions,
	searchLearners,
	type IssuedCertificate,
	type LearnerChoice,
} from './issuedApi';
import type { CourseChoice } from './types';

interface Props {
	templates: ReadonlyArray< { id: number; name: string } >;
	onClose: () => void;
	onIssued: ( item: IssuedCertificate ) => void;
}

/**
 * Search, then choose one result. Results are replaced on every search; the choice is kept only while
 * it is still listed.
 *
 * @param props
 * @param props.label    Field label.
 * @param props.search   Runs the search.
 * @param props.options  Results.
 * @param props.selected Chosen id.
 * @param props.onSelect Chooses an id.
 * @param props.busy     A search is running.
 * @param props.disabled The form is busy.
 */
function SearchChoice( {
	label,
	search,
	options,
	selected,
	onSelect,
	busy,
	disabled,
}: {
	label: string;
	search: ( text: string ) => void;
	options: ReadonlyArray< { value: string; label: string } >;
	selected: number;
	onSelect: ( id: number ) => void;
	busy: boolean;
	disabled: boolean;
} ) {
	const [ text, setText ] = useState( '' );
	return (
		<fieldset>
			<legend>{ label }</legend>
			<TextControl
				label={ sprintf(
					/* translators: %s: what is searched, for example "Learner". */
					__( 'Search: %s', 'ohmylms' ),
					label
				) }
				hideLabelFromVision
				value={ text }
				maxLength={ 100 }
				disabled={ disabled }
				onChange={ setText }
				onKeyDown={ ( event ) => {
					if ( 'Enter' === event.key ) {
						event.preventDefault();
						search( text );
					}
				} }
			/>
			<Button
				variant="secondary"
				disabled={ disabled || busy }
				isBusy={ busy }
				onClick={ () => search( text ) }
			>
				{ __( 'Search', 'ohmylms' ) }
			</Button>
			{ options.length ? (
				<RadioControl
					label={ label }
					hideLabelFromVision
					selected={ selected ? String( selected ) : '' }
					options={ [ ...options ] }
					onChange={ ( value ) => onSelect( Number( value ) ) }
				/>
			) : (
				! busy && <p>{ __( 'No matches.', 'ohmylms' ) }</p>
			) }
		</fieldset>
	);
}

/**
 * Issue a certificate by hand: a learner, a course and the course's template (or another one).
 * Completion is not required; a learner who already has a valid certificate for the course is refused
 * by the server (re-issue it from the list instead).
 *
 * @param props
 * @param props.templates Template choices.
 * @param props.onClose   Closes the dialog.
 * @param props.onIssued  Called with the new certificate.
 */
export function IssueCertificateDialog( {
	templates,
	onClose,
	onIssued,
}: Props ) {
	const operation = useOperation();
	const [ learners, setLearners ] = useState< LearnerChoice[] >( [] );
	const [ courses, setCourses ] = useState< CourseChoice[] >( [] );
	const [ learner, setLearner ] = useState( 0 );
	const [ course, setCourse ] = useState( 0 );
	const [ template, setTemplate ] = useState( 0 );
	const [ searching, setSearching ] = useState< string | null >( null );
	const [ searchError, setSearchError ] = useState< string | null >( null );
	const [ missing, setMissing ] = useState( false );

	async function find< T >(
		kind: string,
		load: ( signal: AbortSignal ) => Promise< T[] >,
		apply: ( items: T[] ) => void
	) {
		const controller = new AbortController();
		setSearching( kind );
		setSearchError( null );
		try {
			apply( await load( controller.signal ) );
		} catch ( failure ) {
			setSearchError( normalizeApiError( failure ).message );
		} finally {
			setSearching( null );
		}
	}
	useEffect( () => {
		void find(
			'course',
			( signal ) => listCourseOptions( '', signal ),
			setCourses
		);
		// Load the first courses once.
	}, [] );

	function submit() {
		if ( ! learner || ! course ) {
			setMissing( true );
			return;
		}
		setMissing( false );
		void operation.run(
			( signal ) =>
				issueCertificate(
					{
						user_id: learner,
						course_id: course,
						certificate_id: template,
					},
					signal
				),
			onIssued
		);
	}

	return (
		<Modal
			title={ __( 'Issue certificate', 'ohmylms' ) }
			onRequestClose={ () => ! operation.busy && onClose() }
			shouldCloseOnClickOutside={ false }
		>
			<form
				onSubmit={ ( event ) => {
					event.preventDefault();
					submit();
				} }
				noValidate
			>
				<p>
					{ __(
						'The learner does not need to have completed the course. The certificate uses the name and course title as they are now.',
						'ohmylms'
					) }
				</p>
				{ operation.error && (
					<Notice status="error" isDismissible={ false }>
						{ operation.error }
					</Notice>
				) }
				{ searchError && (
					<Notice status="error" isDismissible={ false }>
						{ searchError }
					</Notice>
				) }
				<SearchChoice
					label={ __( 'Learner', 'ohmylms' ) }
					busy={ 'learner' === searching }
					disabled={ operation.busy }
					selected={ learner }
					onSelect={ setLearner }
					options={ learners.map( ( item ) => ( {
						value: String( item.id ),
						label: sprintf(
							/* translators: 1: learner name, 2: e-mail address. */
							__( '%1$s (%2$s)', 'ohmylms' ),
							item.name,
							item.email
						),
					} ) ) }
					search={ ( text ) =>
						void find(
							'learner',
							( signal ) => searchLearners( text, signal ),
							( items ) => {
								setLearners( items );
								if (
									! items.some(
										( item ) => item.id === learner
									)
								) {
									setLearner( 0 );
								}
							}
						)
					}
				/>
				<SearchChoice
					label={ __( 'Course', 'ohmylms' ) }
					busy={ 'course' === searching }
					disabled={ operation.busy }
					selected={ course }
					onSelect={ setCourse }
					options={ courses.map( ( item ) => ( {
						value: String( item.id ),
						label: item.title || __( '(no title)', 'ohmylms' ),
					} ) ) }
					search={ ( text ) =>
						void find(
							'course',
							( signal ) => listCourseOptions( text, signal ),
							( items ) => {
								setCourses( items );
								if (
									! items.some(
										( item ) => item.id === course
									)
								) {
									setCourse( 0 );
								}
							}
						)
					}
				/>
				<SelectControl
					label={ __( 'Template', 'ohmylms' ) }
					value={ String( template ) }
					disabled={ operation.busy }
					options={ [
						{
							value: '0',
							label: __( 'The course’s template', 'ohmylms' ),
						},
						...templates.map( ( item ) => ( {
							value: String( item.id ),
							label: item.name,
						} ) ),
					] }
					onChange={ ( value ) => setTemplate( Number( value ) ) }
				/>
				{ missing && (
					<p role="alert">
						{ __( 'Choose a learner and a course.', 'ohmylms' ) }
					</p>
				) }
				<Button
					type="submit"
					variant="primary"
					disabled={ operation.busy }
					isBusy={ operation.busy }
				>
					{ __( 'Issue certificate', 'ohmylms' ) }
				</Button>{ ' ' }
				<Button
					variant="tertiary"
					disabled={ operation.busy }
					onClick={ onClose }
				>
					{ __( 'Cancel', 'ohmylms' ) }
				</Button>
			</form>
		</Modal>
	);
}
