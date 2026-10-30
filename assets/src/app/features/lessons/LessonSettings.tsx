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
import { listLessons } from './api';
import { chooseMedia, mediaLibraryAvailable } from './media';
import { DRIP_TYPES, type Drip, type Lesson, type MediaField } from './model';

const DRIP_LABELS: Record< Drip[ 'type' ], () => string > = {
	'enrollment-from-x-days': () => __( 'Days after enrollment', 'ohmylms' ),
	'specific-date': () => __( 'On a specific date', 'ohmylms' ),
	'cohort-start': () => __( 'At cohort start', 'ohmylms' ),
	'cohort-from-x-days': () => __( 'Days after cohort start', 'ohmylms' ),
};

/**
 * Drip schedule of a lesson.
 *
 * @param props          Component props.
 * @param props.drip     Current schedule.
 * @param props.onChange Receives the next schedule.
 */
export function DripSettings( {
	drip,
	onChange,
}: {
	drip: Drip;
	onChange: ( drip: Drip ) => void;
} ) {
	const update = ( patch: Partial< Drip > ) =>
		onChange( { ...drip, ...patch } );
	return (
		<fieldset>
			<legend>{ __( 'Drip schedule', 'ohmylms' ) }</legend>
			<CheckboxControl
				label={ __( 'Release this lesson on a schedule', 'ohmylms' ) }
				checked={ drip.enable }
				onChange={ ( enable ) => update( { enable } ) }
			/>
			{ drip.enable && (
				<>
					<SelectControl< string >
						label={ __( 'Release lesson', 'ohmylms' ) }
						value={ drip.type }
						options={ DRIP_TYPES.map( ( value ) => ( {
							value,
							label: DRIP_LABELS[ value ](),
						} ) ) }
						onChange={ ( value ) => {
							const type = DRIP_TYPES.find(
								( item ) => item === value
							);
							if ( type ) {
								update( { type } );
							}
						} }
					/>
					{ 'specific-date' === drip.type && (
						<>
							<TextControl
								type="date"
								label={ __( 'Release date', 'ohmylms' ) }
								value={ drip.date }
								required
								onChange={ ( date ) => update( { date } ) }
							/>
							<TextControl
								type="time"
								label={ __( 'Release time', 'ohmylms' ) }
								value={ drip.time }
								help={ __(
									'Site time. Leave empty for midnight.',
									'ohmylms'
								) }
								onChange={ ( time ) => update( { time } ) }
							/>
						</>
					) }
					{ ( 'enrollment-from-x-days' === drip.type ||
						'cohort-from-x-days' === drip.type ) && (
						<TextControl
							type="number"
							min={ 0 }
							max={ 3650 }
							label={ __( 'Days', 'ohmylms' ) }
							value={ String( drip.days ) }
							onChange={ ( value ) =>
								update( {
									days: Math.min(
										3650,
										Math.max(
											0,
											Math.trunc( Number( value ) ) || 0
										)
									),
								} )
							}
						/>
					) }
					{ drip.type.startsWith( 'cohort' ) && (
						<p>
							{ __(
								'Cohort schedules use the first cohort of the course. Lessons stay open in courses without cohorts.',
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
 * Prerequisites of a lesson: other lessons a learner completes first. Existing quiz or assignment
 * prerequisites are shown and kept.
 *
 * @param props          Component props.
 * @param props.lesson   Lesson being edited.
 * @param props.onChange Receives the next prerequisites.
 */
export function PrerequisiteSettings( {
	lesson,
	onChange,
}: {
	lesson: Lesson;
	onChange: ( prerequisites: Lesson[ 'prerequisites' ] ) => void;
} ) {
	const { prerequisites } = lesson;
	const [ query, setQuery ] = useState( '' );
	const [ search, setSearch ] = useState< string | null >( null );
	return (
		<fieldset>
			<legend>{ __( 'Prerequisites', 'ohmylms' ) }</legend>
			<CheckboxControl
				label={ __(
					'Require other content to be completed first',
					'ohmylms'
				) }
				checked={ prerequisites.enable }
				onChange={ ( enable ) =>
					onChange( { ...prerequisites, enable } )
				}
			/>
			<ul>
				{ prerequisites.items.map( ( item ) => (
					<li key={ item.id }>
						{ item.title }{ ' ' }
						<Button
							variant="tertiary"
							isDestructive
							onClick={ () =>
								onChange( {
									...prerequisites,
									items: prerequisites.items.filter(
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
			{ ! prerequisites.items.length && (
				<p>{ __( 'No prerequisites.', 'ohmylms' ) }</p>
			) }
			<TextControl
				label={ __( 'Find a lesson to require', 'ohmylms' ) }
				value={ query }
				maxLength={ 100 }
				onChange={ setQuery }
			/>
			<Button
				variant="secondary"
				disabled={ prerequisites.items.length >= 50 }
				onClick={ () => setSearch( query ) }
			>
				{ __( 'Search lessons', 'ohmylms' ) }
			</Button>
			{ null !== search && (
				<PrerequisiteResults
					search={ search }
					exclude={ [
						lesson.id,
						...prerequisites.items.map( ( item ) => item.id ),
					] }
					onPick={ ( item ) =>
						onChange( {
							...prerequisites,
							items: [ ...prerequisites.items, item ],
						} )
					}
				/>
			) }
		</fieldset>
	);
}

function PrerequisiteResults( {
	search,
	exclude,
	onPick,
}: {
	search: string;
	exclude: number[];
	onPick: ( item: { id: number; title: string } ) => void;
} ) {
	const load = useCallback(
		( signal: AbortSignal ) => listLessons( 1, search, signal ),
		[ search ]
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
	return items.length ? (
		<ul aria-label={ __( 'Matching lessons', 'ohmylms' ) }>
			{ items.map( ( item ) => (
				<li key={ item.id }>
					<Button
						variant="link"
						onClick={ () =>
							onPick( { id: item.id, title: item.title } )
						}
					>
						{ sprintf(
							/* translators: %s: lesson title. */
							__( 'Require %s', 'ohmylms' ),
							item.title
						) }
					</Button>
				</li>
			) ) }
		</ul>
	) : (
		<p>{ __( 'No other lessons match.', 'ohmylms' ) }</p>
	);
}

const MEDIA: {
	field: MediaField;
	kind: 'image' | 'video' | 'audio';
	label: () => string;
}[] = [
	{
		field: 'cover_image_id',
		kind: 'image',
		label: () => __( 'Cover image', 'ohmylms' ),
	},
	{
		field: 'video_id',
		kind: 'video',
		label: () => __( 'Lesson video', 'ohmylms' ),
	},
	{
		field: 'audio_id',
		kind: 'audio',
		label: () => __( 'Lesson audio', 'ohmylms' ),
	},
];

/**
 * Cover image and uploaded video or audio, chosen or uploaded in the WordPress media library.
 *
 * @param props          Component props.
 * @param props.lesson   Lesson being edited.
 * @param props.onChange Receives the changed fields.
 */
export function MediaSettings( {
	lesson,
	onChange,
}: {
	lesson: Lesson;
	onChange: ( patch: Partial< Lesson > ) => void;
} ) {
	const available = mediaLibraryAvailable();
	const fields = MEDIA.filter(
		( row ) =>
			'cover_image_id' === row.field ||
			( 'video_id' === row.field && 'video' === lesson.type ) ||
			( 'audio_id' === row.field && 'audio' === lesson.type ) ||
			lesson[ row.field ] > 0
	);
	return (
		<fieldset>
			<legend>{ __( 'Lesson media', 'ohmylms' ) }</legend>
			{ ! available && (
				<Notice status="warning" isDismissible={ false }>
					{ __(
						'The media library is not available on this screen.',
						'ohmylms'
					) }
				</Notice>
			) }
			{ fields.map( ( row ) => {
				const id = lesson[ row.field ];
				const url = lesson.media_urls[ row.field ];
				return (
					<div key={ row.field }>
						<p>
							<strong>{ row.label() }</strong>
							{ ': ' }
							{ id > 0 ? (
								<a
									href={ url || undefined }
									target="_blank"
									rel="noopener noreferrer"
								>
									{ url ||
										sprintf(
											/* translators: %d: attachment ID. */
											__( 'File #%d', 'ohmylms' ),
											id
										) }
								</a>
							) : (
								__( 'None', 'ohmylms' )
							) }
						</p>
						<Button
							variant="secondary"
							disabled={ ! available }
							onClick={ () =>
								void chooseMedia(
									row.kind,
									row.label(),
									__( 'Use this file', 'ohmylms' )
								).then( ( file ) => {
									if ( file ) {
										onChange( {
											[ row.field ]: file.id,
											media_urls: {
												...lesson.media_urls,
												[ row.field ]: file.url,
											},
											...( 'cover_image_id' === row.field
												? {}
												: { external_url: '' } ),
										} );
									}
								} )
							}
						>
							{ id > 0
								? sprintf(
										/* translators: %s: media label such as Cover image. */
										__( 'Replace %s', 'ohmylms' ),
										row.label()
									)
								: sprintf(
										/* translators: %s: media label such as Cover image. */
										__( 'Choose %s', 'ohmylms' ),
										row.label()
									) }
						</Button>
						{ id > 0 && (
							<Button
								variant="tertiary"
								isDestructive
								onClick={ () =>
									onChange( {
										[ row.field ]: 0,
										media_urls: {
											...lesson.media_urls,
											[ row.field ]: '',
										},
									} )
								}
							>
								{ sprintf(
									/* translators: %s: media label such as Cover image. */
									__( 'Remove %s', 'ohmylms' ),
									row.label()
								) }
							</Button>
						) }
					</div>
				);
			} ) }
		</fieldset>
	);
}
