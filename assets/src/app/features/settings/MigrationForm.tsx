import {
	Button,
	Modal,
	Notice,
	SelectControl,
	Spinner,
} from '@wordpress/components';
import { useCallback, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { Link } from 'react-router-dom';
import { ListTable } from '../../../ui/ListTable';
import { useOperation } from '../../hooks/useOperation';
import { useRequest } from '../../hooks/useRequest';
import {
	listSourceCourses,
	listSources,
	migrateCourse,
	type SourceCourse,
} from './migration';

function Courses( { source }: { source: string } ) {
	const [ page, setPage ] = useState( 1 );
	const [ text, setText ] = useState( '' );
	const [ search, setSearch ] = useState( '' );
	const [ confirm, setConfirm ] = useState< SourceCourse | null >( null );
	const [ message, setMessage ] = useState( '' );
	const operation = useOperation();
	const load = useCallback(
		( signal: AbortSignal ) =>
			listSourceCourses( source, page, search, signal ),
		[ source, page, search ]
	);
	const request = useRequest( load );
	const items = request.data?.items ?? [];

	function migrate() {
		if ( ! confirm ) {
			return;
		}
		const course = confirm;
		void operation.run(
			( signal ) => migrateCourse( source, course.id, signal ),
			() => {
				setMessage(
					sprintf(
						/* translators: %s: course title. */
						__(
							'%s was copied into a new draft course.',
							'ohmylms'
						),
						course.title
					)
				);
				setConfirm( null );
				request.retry();
			}
		);
	}

	return (
		<section aria-label={ __( 'Source courses', 'ohmylms' ) }>
			{ message && (
				<Notice status="success" onRemove={ () => setMessage( '' ) }>
					{ message }
				</Notice>
			) }
			{ request.loading && ! request.data && (
				<p role="status">
					<Spinner /> { __( 'Loading courses…', 'ohmylms' ) }
				</p>
			) }
			{ request.error && (
				<Notice status="error" isDismissible={ false }>
					{ request.error }
				</Notice>
			) }
			{ ! request.error && (
				<ListTable< SourceCourse >
					caption={ __( 'Source courses', 'ohmylms' ) }
					formLabel={ __( 'Search source courses', 'ohmylms' ) }
					busy={ request.loading }
					footer={ false }
					rows={ items }
					rowKey={ ( course ) => course.id }
					emptyMessage={
						request.loading
							? ''
							: __(
									'No courses found in this plugin.',
									'ohmylms'
								)
					}
					search={ {
						label: __( 'Search source courses', 'ohmylms' ),
						submitLabel: __( 'Search', 'ohmylms' ),
						value: text,
						onChange: setText,
						onSubmit: () => {
							setPage( 1 );
							setSearch( text );
						},
					} }
					pagination={ {
						page,
						totalPages: request.data?.totalPages ?? 1,
						onChange: setPage,
					} }
					columns={ [
						{
							id: 'course',
							label: __( 'Course', 'ohmylms' ),
							primary: true,
							render: ( course ) => (
								<strong>{ course.title }</strong>
							),
						},
						{
							id: 'status',
							label: __( 'Status', 'ohmylms' ),
							render: ( course ) => course.status,
						},
						{
							id: 'migration',
							label: __( 'Migration', 'ohmylms' ),
							render: ( course ) =>
								null === course.migrated_to ? (
									<Button
										variant="secondary"
										disabled={ operation.busy }
										onClick={ () => {
											operation.clearError();
											setConfirm( course );
										} }
									>
										{ __( 'Migrate', 'ohmylms' ) }
									</Button>
								) : (
									<Link
										to={ `/courses/${ course.migrated_to }` }
									>
										{ __(
											'Migrated: open the copy',
											'ohmylms'
										) }
									</Link>
								),
						},
					] }
				/>
			) }
			{ confirm && (
				<Modal
					title={ __( 'Migrate course', 'ohmylms' ) }
					onRequestClose={ () => {
						if ( ! operation.busy ) {
							setConfirm( null );
						}
					} }
					shouldCloseOnClickOutside={ false }
				>
					{ operation.error && (
						<Notice status="error" isDismissible={ false }>
							{ operation.error }
						</Notice>
					) }
					<p>
						{ sprintf(
							/* translators: %s: course title. */
							__(
								'Copy %s with its lessons and quizzes into a new draft course? The original course is not changed.',
								'ohmylms'
							),
							confirm.title
						) }
					</p>
					<Button
						variant="primary"
						isBusy={ operation.busy }
						disabled={ operation.busy }
						onClick={ migrate }
					>
						{ __( 'Migrate', 'ohmylms' ) }
					</Button>{ ' ' }
					<Button
						variant="tertiary"
						disabled={ operation.busy }
						onClick={ () => setConfirm( null ) }
					>
						{ __( 'Cancel', 'ohmylms' ) }
					</Button>
				</Modal>
			) }
		</section>
	);
}

/**
 * Migration tab (`/migrations`): copy courses from another LMS plugin that is active on this site.
 * Nothing loads before the tab is first opened.
 *
 * @param props        Component props.
 * @param props.active Whether the tab is selected.
 */
export function MigrationForm( { active }: { active: boolean } ) {
	return active ? <Migration /> : null;
}

function Migration() {
	const loadSources = useCallback(
		( signal: AbortSignal ) => listSources( signal ),
		[]
	);
	const sources = useRequest( loadSources );
	const available = ( sources.data ?? [] ).filter( ( one ) => one.available );
	const [ chosen, setChosen ] = useState( '' );
	const source = available.some( ( one ) => one.id === chosen )
		? chosen
		: ( available[ 0 ]?.id ?? '' );

	return (
		<>
			<h3>{ __( 'Migration', 'ohmylms' ) }</h3>
			<p>
				{ __(
					'Copy courses from another LMS plugin. Each course becomes a new draft; the source plugin must be active.',
					'ohmylms'
				) }
			</p>
			{ sources.loading && (
				<p role="status">
					<Spinner /> { __( 'Loading sources…', 'ohmylms' ) }
				</p>
			) }
			{ sources.error && (
				<Notice status="error" isDismissible={ false }>
					{ sources.error }{ ' ' }
					<Button variant="link" onClick={ sources.retry }>
						{ __( 'Try again', 'ohmylms' ) }
					</Button>
				</Notice>
			) }
			{ sources.data && ! available.length && (
				<Notice status="info" isDismissible={ false }>
					{ sprintf(
						/* translators: %s: list of supported plugins. */
						__(
							'None of the supported plugins is active: %s.',
							'ohmylms'
						),
						sources.data.map( ( one ) => one.title ).join( ', ' )
					) }
				</Notice>
			) }
			{ available.length > 0 && (
				<>
					<SelectControl
						__nextHasNoMarginBottom
						label={ __( 'Source plugin', 'ohmylms' ) }
						value={ source }
						options={ available.map( ( one ) => ( {
							value: one.id,
							label: one.title,
						} ) ) }
						onChange={ setChosen }
					/>
					<Courses key={ source } source={ source } />
				</>
			) }
		</>
	);
}
