import { Button, Notice } from '@wordpress/components';
import { useCallback, useEffect, useMemo, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { Link, useNavigate, useSearchParams } from 'react-router-dom';
import { ListTableSelect } from '../../../ui/ListTable';
import { normalizeApiError } from '../../lib/api';
import { createView, deleteView, getContext, listViews } from './api';
import {
	cellTone,
	groupScopes,
	LEGEND_SOURCES,
	paramsFromQuery,
	queryFromParams,
	sectionLabel,
	sourceLabel,
	stateLabel,
	type GradebookQuery,
} from './model';
import type {
	CellState,
	ClassRef,
	GradebookContext,
	SavedView,
	Section,
} from './types';
import { useLoad } from './useLoad';
import './gradebook.css';

/** Route of each gradebook section (Content Hub › Gradebook, `&section=`). */
export const SECTION_PATHS: Record< Section, string > = {
	course: '/content-hub/gradebook',
	roster: '/content-hub/gradebook/roster',
	student: '/content-hub/gradebook/student',
	mastery: '/content-hub/gradebook/mastery',
	rollup: '/content-hub/gradebook/rollup',
};

/**
 * The gradebook query kept in the route's search parameters (and so in the admin address).
 *
 * @return Query, `update` (a change resets the page unless it names one) and `linkTo` for other sections.
 */
export function useGradebookQuery() {
	const [ params, setParams ] = useSearchParams();
	const key = params.toString();
	const query = useMemo(
		() => queryFromParams( new URLSearchParams( key ) ),
		[ key ]
	);
	const update = useCallback(
		( change: Partial< GradebookQuery >, replace = false ) => {
			setParams(
				paramsFromQuery( {
					...query,
					page: 1,
					...change,
				} ),
				{ replace }
			);
		},
		[ query, setParams ]
	);
	const linkTo = useCallback(
		( section: Section, change: Partial< GradebookQuery > ) =>
			`${ SECTION_PATHS[ section ] }?${ paramsFromQuery( {
				...queryFromParams( new URLSearchParams() ),
				scope: query.scope,
				...change,
			} ).toString() }`,
		[ query.scope ]
	);
	return { query, update, linkTo, search: key };
}

/**
 * Scopes, courses and classes of the current scope.
 *
 * @param scope Scope id ('' for the user's default).
 */
export function useGradebookContext( scope: string ) {
	return useLoad< GradebookContext >( `context|${ scope }`, ( signal ) =>
		getContext( scope, signal )
	);
}

/**
 * Scope select (all schools, a school, a class), hidden for a single scope.
 *
 * @param props          Props.
 * @param props.context  Context.
 * @param props.onChange Called with the scope id.
 */
export function ScopeSelect( {
	context,
	onChange,
}: {
	context: GradebookContext;
	onChange: ( scope: string ) => void;
} ) {
	if ( context.scopes.length < 2 ) {
		return null;
	}
	const { top, groups } = groupScopes( context.scopes );
	return (
		<>
			<label
				htmlFor="ohmylms-gradebook-scope"
				className="screen-reader-text"
			>
				{ __( 'School or class', 'ohmylms' ) }
			</label>
			<select
				id="ohmylms-gradebook-scope"
				value={ context.scope.id }
				onChange={ ( event ) => onChange( event.target.value ) }
			>
				{ top.map( ( option ) => (
					<option key={ option.value } value={ option.value }>
						{ option.label }
					</option>
				) ) }
				{ groups.map( ( group ) => (
					<optgroup key={ group.label } label={ group.label }>
						{ group.options.map( ( option ) => (
							<option key={ option.value } value={ option.value }>
								{ option.label }
							</option>
						) ) }
					</optgroup>
				) ) }
			</select>
		</>
	);
}

/**
 * Saved views of the current user for a section, Share (copies the address) and Export CSV.
 *
 * @param props          Props.
 * @param props.section  Section.
 * @param props.search   Current route query.
 * @param props.onExport Builds and downloads the CSV; null hides the button.
 */
export function ViewActions( {
	section,
	search,
	onExport,
}: {
	section: Section;
	search: string;
	onExport: ( () => Promise< void > | void ) | null;
} ) {
	const navigate = useNavigate();
	const views = useLoad< SavedView[] >( 'views', ( signal ) =>
		listViews( signal )
	);
	const [ picked, setPicked ] = useState( '' );
	const [ message, setMessage ] = useState< {
		status: 'success' | 'error' | 'info';
		text: string;
	} | null >( null );
	const [ busy, setBusy ] = useState( false );
	const [ naming, setNaming ] = useState( false );
	const [ draft, setDraft ] = useState( '' );
	const mine = ( views.data ?? [] ).filter(
		( view ) => view.section === section
	);

	async function save() {
		const name = draft.trim();
		if ( ! name ) {
			return;
		}
		setBusy( true );
		try {
			const view = await createView( name, section, search );
			setNaming( false );
			setPicked( view.id );
			views.reload();
			setMessage( {
				status: 'success',
				text: sprintf(
					/* translators: %s: view name. */
					__( 'Saved the view “%s”.', 'ohmylms' ),
					view.name
				),
			} );
		} catch ( error ) {
			setMessage( {
				status: 'error',
				text: normalizeApiError( error ).message,
			} );
		} finally {
			setBusy( false );
		}
	}

	async function remove() {
		const view = mine.find( ( candidate ) => candidate.id === picked );
		if ( ! view ) {
			setMessage( {
				status: 'info',
				text: __( 'Pick a saved view first.', 'ohmylms' ),
			} );
			return;
		}
		setBusy( true );
		try {
			await deleteView( view.id );
			setPicked( '' );
			views.reload();
			setMessage( {
				status: 'success',
				text: sprintf(
					/* translators: %s: view name. */
					__( 'Deleted the view “%s”.', 'ohmylms' ),
					view.name
				),
			} );
		} catch ( error ) {
			setMessage( {
				status: 'error',
				text: normalizeApiError( error ).message,
			} );
		} finally {
			setBusy( false );
		}
	}

	async function share() {
		const url = window.location.href;
		try {
			await window.navigator.clipboard.writeText( url );
			setMessage( {
				status: 'success',
				text: __(
					'Link copied. People who open it see only what their own access allows.',
					'ohmylms'
				),
			} );
		} catch {
			setMessage( {
				status: 'info',
				text: sprintf(
					/* translators: %s: address of this view. */
					__( 'Copy this link: %s', 'ohmylms' ),
					url
				),
			} );
		}
	}

	return (
		<div className="ohmylms-gradebook-actions">
			<label
				htmlFor={ `ohmylms-gradebook-views-${ section }` }
				className="screen-reader-text"
			>
				{ __( 'Saved views', 'ohmylms' ) }
			</label>
			<select
				id={ `ohmylms-gradebook-views-${ section }` }
				value={ picked }
				onChange={ ( event ) => {
					const view = mine.find(
						( candidate ) => candidate.id === event.target.value
					);
					setPicked( event.target.value );
					if ( view ) {
						navigate(
							`${ SECTION_PATHS[ view.section ] }?${ view.query }`
						);
					}
				} }
			>
				<option value="">{ __( 'Saved views…', 'ohmylms' ) }</option>
				{ mine.map( ( view ) => (
					<option key={ view.id } value={ view.id }>
						{ view.name }
					</option>
				) ) }
			</select>
			{ naming ? (
				<form
					className="ohmylms-gradebook-actions__name"
					onSubmit={ ( event ) => {
						event.preventDefault();
						save();
					} }
				>
					<label
						htmlFor={ `ohmylms-gradebook-view-name-${ section }` }
						className="screen-reader-text"
					>
						{ __( 'Name of the view', 'ohmylms' ) }
					</label>
					<input
						id={ `ohmylms-gradebook-view-name-${ section }` }
						type="text"
						maxLength={ 80 }
						value={ draft }
						// The field appears because the user asked to name the view.
						// eslint-disable-next-line jsx-a11y/no-autofocus
						autoFocus
						onChange={ ( event ) => setDraft( event.target.value ) }
						onKeyDown={ ( event ) => {
							if ( 'Escape' === event.key ) {
								setNaming( false );
							}
						} }
					/>
					<Button
						variant="secondary"
						size="compact"
						type="submit"
						disabled={ busy || ! draft.trim() }
					>
						{ __( 'Save', 'ohmylms' ) }
					</Button>
					<Button
						variant="tertiary"
						size="compact"
						onClick={ () => setNaming( false ) }
					>
						{ __( 'Cancel', 'ohmylms' ) }
					</Button>
				</form>
			) : (
				<Button
					variant="secondary"
					size="compact"
					onClick={ () => {
						setDraft( sectionLabel( section ) );
						setNaming( true );
					} }
					disabled={ busy }
				>
					{ __( 'Save view', 'ohmylms' ) }
				</Button>
			) }
			<Button
				variant="secondary"
				size="compact"
				onClick={ remove }
				disabled={ busy || ! picked }
			>
				{ __( 'Delete view', 'ohmylms' ) }
			</Button>
			<Button variant="secondary" size="compact" onClick={ share }>
				{ __( 'Share', 'ohmylms' ) }
			</Button>
			{ onExport && (
				<Button
					variant="primary"
					size="compact"
					disabled={ busy }
					onClick={ async () => {
						setBusy( true );
						try {
							await onExport();
						} catch ( error ) {
							setMessage( {
								status: 'error',
								text: normalizeApiError( error ).message,
							} );
						} finally {
							setBusy( false );
						}
					} }
				>
					{ __( 'Export CSV', 'ohmylms' ) }
				</Button>
			) }
			{ message && (
				<Notice
					status={ message.status }
					onRemove={ () => setMessage( null ) }
					className="ohmylms-gradebook-actions__notice"
				>
					{ message.text }
				</Notice>
			) }
		</div>
	);
}

/**
 * Source badge of an enrolment.
 * @param root0
 * @param root0.source
 */
export function SourceBadge( { source }: { source: string } ) {
	return (
		<span className={ `ohmylms-gradebook-source is-${ source }` }>
			{ sourceLabel( source ) }
		</span>
	);
}

/**
 * Roster chips; each opens Roster × Assignments.
 *
 * @param props         Props.
 * @param props.rosters Classes.
 * @param props.linkTo  Address builder.
 */
export function RosterChips( {
	rosters,
	linkTo,
}: {
	rosters: readonly ClassRef[];
	linkTo: ( section: Section, change: Partial< GradebookQuery > ) => string;
} ) {
	if ( ! rosters.length ) {
		return (
			<span className="ohmylms-gradebook-muted">
				{ __( 'individual', 'ohmylms' ) }
			</span>
		);
	}
	return (
		<>
			{ rosters.map( ( roster ) => (
				<Link
					key={ roster.id }
					className="ohmylms-gradebook-chip"
					to={ linkTo( 'roster', { classId: roster.id } ) }
					tabIndex={ -1 }
				>
					{ roster.name }
				</Link>
			) ) }
		</>
	);
}

const LEGEND_STATES: CellState[] = [
	'score',
	'pending',
	'missing',
	'none',
	'excused',
	'not_assigned',
];

/**
 * Legend of sources and cell states.
 * @param root0
 * @param root0.sources
 */
export function Legend( { sources = true }: { sources?: boolean } ) {
	return (
		<p className="ohmylms-gradebook-legend">
			<span className="screen-reader-text">
				{ __( 'Legend', 'ohmylms' ) }
			</span>
			{ sources &&
				LEGEND_SOURCES.map( ( source ) => (
					<SourceBadge key={ source } source={ source } />
				) ) }
			{ LEGEND_STATES.map( ( state ) => (
				<span
					key={ state }
					className={ `ohmylms-gradebook-swatch is-${ cellTone( {
						state,
						score: 'score' === state ? 9 : null,
						max: 'score' === state ? 10 : null,
						percent: 'score' === state ? 90 : null,
					} ) }` }
				>
					{ stateLabel( state ) }
				</span>
			) ) }
			<span className="ohmylms-gradebook-swatch is-overridden">
				{ __( 'Set manually', 'ohmylms' ) }
			</span>
		</p>
	);
}

/**
 * Loading and error states of a view.
 *
 * @param props         Props.
 * @param props.loading Loading.
 * @param props.error   Error message.
 * @param props.retry   Retry.
 * @param props.label   What is loading.
 */
export function LoadState( {
	loading,
	error,
	retry,
	label,
}: {
	loading: boolean;
	error: string;
	retry: () => void;
	label: string;
} ) {
	return (
		<>
			{ error && (
				<Notice status="error" isDismissible={ false }>
					{ error }{ ' ' }
					<Button variant="link" onClick={ retry }>
						{ __( 'Try again', 'ohmylms' ) }
					</Button>
				</Notice>
			) }
			{ loading && (
				<p role="status" className="ohmylms-gradebook-muted">
					{ label }
				</p>
			) }
		</>
	);
}

/**
 * Select of the scope's courses.
 *
 * @param props          Props.
 * @param props.context  Context.
 * @param props.value    Course id.
 * @param props.onChange Called with the course id.
 * @param props.optional Offers "All courses" (0).
 */
export function CourseSelect( {
	context,
	value,
	onChange,
	optional = false,
}: {
	context: GradebookContext;
	value: number;
	onChange: ( course: number ) => void;
	optional?: boolean;
} ) {
	return (
		<ListTableSelect
			label={ __( 'Course', 'ohmylms' ) }
			value={ String( value ) }
			options={ [
				...( optional || ! value
					? [
							{
								value: '0',
								label: optional
									? __(
											'All learners of the school or class',
											'ohmylms'
										)
									: __( 'Choose a course', 'ohmylms' ),
							},
						]
					: [] ),
				...context.courses.map( ( course ) => ( {
					value: String( course.id ),
					label: course.title,
				} ) ),
			] }
			onChange={ ( next ) => onChange( Number( next ) ) }
		/>
	);
}

/**
 * Pick the first course of the scope when none is chosen (replacing the address).
 *
 * @param context Context.
 * @param course  Chosen course.
 * @param choose  Sets the course.
 */
export function useDefaultCourse(
	context: GradebookContext | null,
	course: number,
	choose: ( course: number ) => void
) {
	useEffect( () => {
		const first = context?.courses[ 0 ];
		if ( first && ! course ) {
			choose( first.id );
		}
		// Only when the context or the course changes.
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [ context, course ] );
}
