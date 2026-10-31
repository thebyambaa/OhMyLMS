import {
	Button,
	CheckboxControl,
	Notice,
	SelectControl,
	TextControl,
} from '@wordpress/components';
import { useCallback, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { useRequest } from '../../hooks/useRequest';
import { listPrerequisiteOptions } from './api';
import { chooseFiles, mediaLibraryAvailable } from './media';
import { DRIP_TYPES, LIMITS } from './model';
import type {
	DeliverySettings as Delivery,
	Drip,
	FormErrors,
	PrerequisiteItem,
	PrerequisiteType,
	Resource,
} from './types';

interface Props {
	assignmentId: number;
	value: Delivery;
	errors: FormErrors;
	disabled: boolean;
	onChange: ( patch: Partial< Delivery > ) => void;
}

function FieldError( { id, message }: { id: string; message?: string } ) {
	return message ? (
		<p id={ id } role="alert">
			{ message }
		</p>
	) : null;
}

function typeLabel( type: PrerequisiteType ): string {
	switch ( type ) {
		case 'lesson':
			return __( 'Lesson', 'ohmylms' );
		case 'quiz':
			return __( 'Quiz', 'ohmylms' );
		case 'assignment':
			return __( 'Assignment', 'ohmylms' );
		default:
			return __( 'Content', 'ohmylms' );
	}
}

function dripLabel( type: Drip[ 'type' ] ): string {
	switch ( type ) {
		case 'specific-date':
			return __( 'On a date', 'ohmylms' );
		case 'cohort-start':
			return __( 'When the cohort starts', 'ohmylms' );
		case 'cohort-from-x-days':
			return __( 'Days after the cohort starts', 'ohmylms' );
		default:
			return __( 'Days after enrolment', 'ohmylms' );
	}
}

function move< T >( rows: T[], from: number, to: number ): T[] {
	if ( to < 0 || to >= rows.length ) {
		return rows;
	}
	const next = [ ...rows ];
	const [ row ] = next.splice( from, 1 );
	next.splice( to, 0, row as T );
	return next;
}

/**
 * Files and links learners download from the assignment page.
 *
 * @param props          Component props.
 * @param props.value    Resources.
 * @param props.error    Validation message.
 * @param props.disabled Whether editing is locked.
 * @param props.onChange Receives the next list.
 */
export function ResourceSettings( {
	value,
	error,
	disabled,
	onChange,
}: {
	value: Resource[];
	error?: string;
	disabled: boolean;
	onChange: ( resources: Resource[] ) => void;
} ) {
	const available = mediaLibraryAvailable();
	const full = value.length >= LIMITS.resources;
	const update = ( index: number, patch: Partial< Resource > ) =>
		onChange(
			value.map( ( row, rowIndex ) =>
				rowIndex === index ? { ...row, ...patch } : row
			)
		);
	return (
		<fieldset
			disabled={ disabled }
			aria-describedby="oml-asg-resources-help"
		>
			<legend>{ __( 'Resources', 'ohmylms' ) }</legend>
			<p id="oml-asg-resources-help">
				{ __(
					'Learners download these from the assignment page. Removing a file here does not delete it from the media library.',
					'ohmylms'
				) }
			</p>
			<FieldError id="oml-asg-resources-error" message={ error } />
			{ value.length ? (
				<ol>
					{ value.map( ( row, index ) => (
						<li
							key={ `${ row.type }-${ row.attachment_id }-${ index }` }
						>
							<p>
								<strong>
									{ 'file' === row.type
										? __( 'File', 'ohmylms' )
										: __( 'Link', 'ohmylms' ) }
								</strong>
								{ 'file' === row.type && (
									<>
										{ ': ' }
										<a
											href={ row.url }
											target="_blank"
											rel="noreferrer"
										>
											{ row.url.split( '/' ).pop() ||
												row.url }
										</a>
										{ row.size ? ` (${ row.size })` : '' }
									</>
								) }
							</p>
							<TextControl
								label={ sprintf(
									/* translators: %d: resource number. */
									__( 'Name of resource %d', 'ohmylms' ),
									index + 1
								) }
								value={ row.name }
								maxLength={ LIMITS.resourceName }
								onChange={ ( name ) =>
									update( index, { name } )
								}
							/>
							{ 'link' === row.type && (
								<>
									<TextControl
										type="url"
										label={ sprintf(
											/* translators: %d: resource number. */
											__(
												'Address of resource %d',
												'ohmylms'
											),
											index + 1
										) }
										value={ row.url }
										onChange={ ( url ) =>
											update( index, { url } )
										}
									/>
									<TextControl
										label={ sprintf(
											/* translators: %d: resource number. */
											__(
												'Size label of resource %d (optional)',
												'ohmylms'
											),
											index + 1
										) }
										value={ row.size }
										maxLength={ LIMITS.resourceSize }
										onChange={ ( size ) =>
											update( index, { size } )
										}
									/>
								</>
							) }
							<Button
								variant="tertiary"
								disabled={ 0 === index }
								onClick={ () =>
									onChange( move( value, index, index - 1 ) )
								}
							>
								{ sprintf(
									/* translators: %s: resource name. */
									__( 'Move %s up', 'ohmylms' ),
									row.name || __( 'resource', 'ohmylms' )
								) }
							</Button>
							<Button
								variant="tertiary"
								disabled={ index === value.length - 1 }
								onClick={ () =>
									onChange( move( value, index, index + 1 ) )
								}
							>
								{ sprintf(
									/* translators: %s: resource name. */
									__( 'Move %s down', 'ohmylms' ),
									row.name || __( 'resource', 'ohmylms' )
								) }
							</Button>
							<Button
								variant="tertiary"
								isDestructive
								onClick={ () =>
									onChange(
										value.filter(
											( _, rowIndex ) =>
												rowIndex !== index
										)
									)
								}
							>
								{ sprintf(
									/* translators: %s: resource name. */
									__( 'Remove %s', 'ohmylms' ),
									row.name || __( 'resource', 'ohmylms' )
								) }
							</Button>
						</li>
					) ) }
				</ol>
			) : (
				<p>{ __( 'No resources.', 'ohmylms' ) }</p>
			) }
			{ ! available && (
				<Notice status="warning" isDismissible={ false }>
					{ __(
						'The media library is not available on this screen; you can still add links.',
						'ohmylms'
					) }
				</Notice>
			) }
			<Button
				variant="secondary"
				disabled={ ! available || full }
				onClick={ () =>
					void chooseFiles(
						__( 'Assignment resources', 'ohmylms' ),
						__( 'Add to the assignment', 'ohmylms' )
					).then( ( files ) => {
						if ( files.length ) {
							onChange(
								[
									...value,
									...files.map( ( file ) => ( {
										type: 'file' as const,
										attachment_id: file.id,
										name: file.name,
										url: file.url,
										size: file.size,
									} ) ),
								].slice( 0, LIMITS.resources )
							);
						}
					} )
				}
			>
				{ __( 'Add files', 'ohmylms' ) }
			</Button>{ ' ' }
			<Button
				variant="secondary"
				disabled={ full }
				onClick={ () =>
					onChange( [
						...value,
						{
							type: 'link',
							attachment_id: 0,
							name: '',
							url: '',
							size: '',
						},
					] )
				}
			>
				{ __( 'Add a link', 'ohmylms' ) }
			</Button>
		</fieldset>
	);
}

function PrerequisiteResults( {
	assignmentId,
	search,
	exclude,
	onPick,
}: {
	assignmentId: number;
	search: string;
	exclude: number[];
	onPick: ( item: PrerequisiteItem ) => void;
} ) {
	const load = useCallback(
		( signal: AbortSignal ) =>
			listPrerequisiteOptions( assignmentId, search, 1, signal ),
		[ assignmentId, search ]
	);
	const request = useRequest( load );
	if ( request.loading ) {
		return <p role="status">{ __( 'Searching…', 'ohmylms' ) }</p>;
	}
	if ( request.error || ! request.data ) {
		return (
			<p role="alert">
				{ request.error }{ ' ' }
				<Button variant="link" onClick={ request.retry }>
					{ __( 'Retry', 'ohmylms' ) }
				</Button>
			</p>
		);
	}
	const items = request.data.items.filter(
		( item ) => ! exclude.includes( item.id )
	);
	return (
		<>
			{ 'library' === request.data.scope && (
				<p>
					{ __(
						'This assignment is not in a course yet, so your whole library is searched.',
						'ohmylms'
					) }
				</p>
			) }
			{ items.length ? (
				<ul aria-label={ __( 'Matching content', 'ohmylms' ) }>
					{ items.map( ( item ) => (
						<li key={ item.id }>
							<Button
								variant="link"
								onClick={ () => onPick( item ) }
							>
								{ sprintf(
									/* translators: 1: content kind, 2: title. */
									__( 'Require %1$s “%2$s”', 'ohmylms' ),
									typeLabel( item.type ).toLowerCase(),
									item.title
								) }
							</Button>
						</li>
					) ) }
				</ul>
			) : (
				<p>{ __( 'Nothing else matches.', 'ohmylms' ) }</p>
			) }
		</>
	);
}

/**
 * Lessons, quizzes or assignments a learner completes before submitting.
 *
 * @param props              Component props.
 * @param props.assignmentId Assignment ID (the picker searches its courses).
 * @param props.value        Prerequisites.
 * @param props.error        Validation message.
 * @param props.disabled     Whether editing is locked.
 * @param props.onChange     Receives the next prerequisites.
 */
export function PrerequisiteSettings( {
	assignmentId,
	value,
	error,
	disabled,
	onChange,
}: {
	assignmentId: number;
	value: Delivery[ 'prerequisites' ];
	error?: string;
	disabled: boolean;
	onChange: ( prerequisites: Delivery[ 'prerequisites' ] ) => void;
} ) {
	const [ query, setQuery ] = useState( '' );
	const [ search, setSearch ] = useState< string | null >( null );
	return (
		<fieldset disabled={ disabled }>
			<legend>{ __( 'Prerequisites', 'ohmylms' ) }</legend>
			<CheckboxControl
				label={ __(
					'Learners complete other content before submitting',
					'ohmylms'
				) }
				help={ __(
					'Learners can still read the assignment; the submission stays locked until every item is completed.',
					'ohmylms'
				) }
				checked={ value.enable }
				onChange={ ( enable ) => onChange( { ...value, enable } ) }
			/>
			<FieldError id="oml-asg-prerequisites-error" message={ error } />
			{ value.items.length ? (
				<ul>
					{ value.items.map( ( item ) => (
						<li key={ item.id }>
							{ typeLabel( item.type ) }: { item.title }
							{ 'missing' === item.status &&
								` ${ __( '(removed)', 'ohmylms' ) }` }
							{ 'draft' === item.status &&
								` ${ __( '(draft)', 'ohmylms' ) }` }{ ' ' }
							<Button
								variant="tertiary"
								isDestructive
								onClick={ () =>
									onChange( {
										...value,
										items: value.items.filter(
											( row ) => row.id !== item.id
										),
									} )
								}
							>
								{ sprintf(
									/* translators: %s: prerequisite title. */
									__( 'Remove prerequisite %s', 'ohmylms' ),
									item.title
								) }
							</Button>
						</li>
					) ) }
				</ul>
			) : (
				<p>{ __( 'No prerequisites.', 'ohmylms' ) }</p>
			) }
			<TextControl
				label={ __( 'Find a lesson, quiz or assignment', 'ohmylms' ) }
				value={ query }
				maxLength={ 100 }
				onChange={ setQuery }
			/>
			<Button
				variant="secondary"
				disabled={ value.items.length >= LIMITS.prerequisites }
				onClick={ () => setSearch( query ) }
			>
				{ __( 'Search', 'ohmylms' ) }
			</Button>
			{ null !== search && (
				<PrerequisiteResults
					assignmentId={ assignmentId }
					search={ search }
					exclude={ value.items.map( ( item ) => item.id ) }
					onPick={ ( item ) =>
						onChange( {
							...value,
							items: [ ...value.items, item ],
						} )
					}
				/>
			) }
		</fieldset>
	);
}

/**
 * When the assignment opens to learners.
 *
 * @param props          Component props.
 * @param props.value    Release schedule.
 * @param props.error    Validation message.
 * @param props.disabled Whether editing is locked.
 * @param props.onChange Receives the next schedule.
 */
export function DripSettings( {
	value,
	error,
	disabled,
	onChange,
}: {
	value: Drip;
	error?: string;
	disabled: boolean;
	onChange: ( drip: Drip ) => void;
} ) {
	const update = ( patch: Partial< Drip > ) =>
		onChange( { ...value, ...patch } );
	return (
		<fieldset disabled={ disabled }>
			<legend>{ __( 'Release', 'ohmylms' ) }</legend>
			<CheckboxControl
				label={ __( 'Open the assignment later', 'ohmylms' ) }
				help={ __(
					'Until it opens, learners see the date it opens and cannot submit.',
					'ohmylms'
				) }
				checked={ value.enable }
				onChange={ ( enable ) => update( { enable } ) }
			/>
			<FieldError id="oml-asg-drip-error" message={ error } />
			{ value.enable && (
				<>
					<SelectControl< string >
						label={ __( 'Opens', 'ohmylms' ) }
						value={ value.type }
						options={ DRIP_TYPES.map( ( type ) => ( {
							value: type,
							label: dripLabel( type ),
						} ) ) }
						onChange={ ( next ) => {
							const type = DRIP_TYPES.find(
								( item ) => item === next
							);
							if ( type ) {
								update( { type } );
							}
						} }
					/>
					{ 'specific-date' === value.type && (
						<>
							<TextControl
								type="date"
								label={ __( 'Date', 'ohmylms' ) }
								value={ value.date }
								required
								aria-invalid={ error ? true : undefined }
								onChange={ ( date ) => update( { date } ) }
							/>
							<TextControl
								type="time"
								label={ __( 'Time', 'ohmylms' ) }
								value={ value.time }
								help={ __(
									'Site time. Empty means midnight.',
									'ohmylms'
								) }
								onChange={ ( time ) => update( { time } ) }
							/>
						</>
					) }
					{ ( 'enrollment-from-x-days' === value.type ||
						'cohort-from-x-days' === value.type ) && (
						<TextControl
							type="number"
							min={ 0 }
							max={ LIMITS.dripDays }
							label={ __( 'Days', 'ohmylms' ) }
							value={ String( value.days ) }
							onChange={ ( days ) =>
								update( {
									days: Math.min(
										LIMITS.dripDays,
										Math.max(
											0,
											Math.trunc( Number( days ) ) || 0
										)
									),
								} )
							}
						/>
					) }
					{ value.type.startsWith( 'cohort' ) && (
						<p>
							{ __(
								'Cohort releases use the course’s first cohort; in courses without cohorts the assignment stays open.',
								'ohmylms'
							) }
						</p>
					) }
				</>
			) }
		</fieldset>
	);
}

/**
 * Resources, prerequisites, release and comments of an assignment.
 *
 * @param props              Component props.
 * @param props.assignmentId
 * @param props.value
 * @param props.errors
 * @param props.disabled
 * @param props.onChange
 */
export default function DeliverySettings( {
	assignmentId,
	value,
	errors,
	disabled,
	onChange,
}: Props ) {
	return (
		<>
			<ResourceSettings
				value={ value.resources }
				error={ errors.resources }
				disabled={ disabled }
				onChange={ ( resources ) => onChange( { resources } ) }
			/>
			<PrerequisiteSettings
				assignmentId={ assignmentId }
				value={ value.prerequisites }
				error={ errors.prerequisites }
				disabled={ disabled }
				onChange={ ( prerequisites ) => onChange( { prerequisites } ) }
			/>
			<DripSettings
				value={ value.drip }
				error={ errors.drip }
				disabled={ disabled }
				onChange={ ( drip ) => onChange( { drip } ) }
			/>
			<fieldset disabled={ disabled }>
				<legend>{ __( 'Comments', 'ohmylms' ) }</legend>
				<CheckboxControl
					label={ __( 'Comment threads on submissions', 'ohmylms' ) }
					help={ __(
						'The learner and instructors can comment on each submission; the other side is emailed. Existing comments stay visible when this is off.',
						'ohmylms'
					) }
					checked={ value.comments_enabled }
					onChange={ ( comments ) =>
						onChange( { comments_enabled: comments } )
					}
				/>
			</fieldset>
		</>
	);
}
