import { Button, TextControl } from '@wordpress/components';
import { useCallback, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { Link } from 'react-router-dom';
import { WidefatTable } from '../../components/WidefatTable';
import { useRequest } from '../../hooks/useRequest';
import {
	listQuestions,
	questionEditorPath,
	type QuestionSummary,
} from '../questions/public';
import {
	appendInline,
	canSwap,
	findInline,
	removeInline,
	swapInline,
} from './inlineRefs';
import type { InlineQuestion, InlineStatus } from './model';

interface Known {
	id: number;
	title: string;
	status: InlineStatus;
}

function statusLabel( status: InlineStatus ): string {
	switch ( status ) {
		case 'approved':
			return __( 'Approved', 'ohmylms' );
		case 'not_approved':
			return __( 'Not approved: learners do not see it yet', 'ohmylms' );
		case 'exam_only':
			return __( 'Exam only: hidden in lessons', 'ohmylms' );
		default:
			return __( 'Missing', 'ohmylms' );
	}
}

function Results( {
	search,
	onPick,
}: {
	search: string;
	onPick: ( question: QuestionSummary ) => void;
} ) {
	const load = useCallback(
		( signal: AbortSignal ) =>
			listQuestions(
				{
					page: 1,
					search,
					type: '',
					difficulty: '',
					status: 'approved',
				},
				signal
			),
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
	const items = request.data.items.filter( ( item ) => ! item.secure );
	return items.length ? (
		<ul aria-label={ __( 'Approved practice questions', 'ohmylms' ) }>
			{ items.map( ( item ) => (
				<li key={ item.id }>
					<Button variant="link" onClick={ () => onPick( item ) }>
						{ sprintf(
							/* translators: %s: question name. */
							__( 'Insert “%s”', 'ohmylms' ),
							item.name
						) }
					</Button>{ ' ' }
					({ item.type_label })
				</li>
			) ) }
		</ul>
	) : (
		<p>{ __( 'No approved practice question matches.', 'ohmylms' ) }</p>
	);
}

/**
 * The lesson's inline question checks: list them in reading order, insert approved practice questions,
 * reorder and remove them, and open each in the question editor. Changes edit the lesson content and are
 * saved with the lesson. Inline checks never block lesson completion.
 *
 * @param props          Component props.
 * @param props.content  Current lesson content.
 * @param props.saved    The server's list for the saved content (titles and status).
 * @param props.disabled Whether editing is locked.
 * @param props.onChange Receives the next content.
 */
export default function InlineQuestions( {
	content,
	saved,
	disabled,
	onChange,
}: {
	content: string;
	saved: InlineQuestion[];
	disabled: boolean;
	onChange: ( content: string ) => void;
} ) {
	const [ picked, setPicked ] = useState< Record< string, Known > >( {} );
	const [ query, setQuery ] = useState( '' );
	const [ search, setSearch ] = useState< string | null >( null );
	const refs = findInline( content );
	const known = ( uuid: string, id: number ): Known | null => {
		const fromSaved = saved.find(
			( row ) =>
				( uuid && row.uuid === uuid ) ||
				( id && row.question_id === id )
		);
		if ( fromSaved && fromSaved.question_id ) {
			return {
				id: fromSaved.question_id,
				title: fromSaved.title,
				status: fromSaved.status,
			};
		}
		return picked[ uuid ] ?? null;
	};
	return (
		<section aria-labelledby="oml-lesson-inline">
			<h2 id="oml-lesson-inline">
				{ __( 'Inline questions', 'ohmylms' ) }
			</h2>
			<p>
				{ __(
					'Learners answer these checks inside the lesson. Answers count as practice and never block lesson completion. Only approved, non-exam questions are shown to learners.',
					'ohmylms'
				) }
			</p>
			{ refs.length ? (
				<WidefatTable
					caption={ __(
						'Inline questions in this lesson',
						'ohmylms'
					) }
				>
					<thead>
						<tr>
							<th scope="col">{ __( 'Position', 'ohmylms' ) }</th>
							<th scope="col">{ __( 'Question', 'ohmylms' ) }</th>
							<th scope="col">{ __( 'Status', 'ohmylms' ) }</th>
							<th scope="col">{ __( 'Actions', 'ohmylms' ) }</th>
						</tr>
					</thead>
					<tbody>
						{ refs.map( ( ref, index ) => {
							const question = known( ref.uuid, ref.questionId );
							const name =
								question?.title ||
								( ref.questionId
									? sprintf(
											/* translators: %d: question ID. */
											__( 'Question #%d', 'ohmylms' ),
											ref.questionId
										)
									: __( 'Unknown question', 'ohmylms' ) );
							const id = question?.id || ref.questionId;
							return (
								<tr key={ `${ ref.start }-${ ref.uuid }` }>
									<td>{ index + 1 }</td>
									<td>
										{ id ? (
											<Link
												to={ questionEditorPath( id ) }
											>
												{ name }
											</Link>
										) : (
											<span>{ name }</span>
										) }
										{ 'shortcode' === ref.kind && (
											<>
												<br />
												{ __(
													'Text shortcode: open the full editor to turn it into a block before moving it.',
													'ohmylms'
												) }
											</>
										) }
									</td>
									<td>
										{ question
											? statusLabel( question.status )
											: __(
													'Saved with the lesson',
													'ohmylms'
												) }
									</td>
									<td>
										<Button
											variant="tertiary"
											disabled={
												disabled ||
												! canSwap(
													refs,
													index,
													index - 1
												)
											}
											onClick={ () =>
												onChange(
													swapInline(
														content,
														index,
														index - 1
													)
												)
											}
										>
											{ sprintf(
												/* translators: %s: question name. */
												__( 'Move %s up', 'ohmylms' ),
												name
											) }
										</Button>
										<Button
											variant="tertiary"
											disabled={
												disabled ||
												! canSwap(
													refs,
													index,
													index + 1
												)
											}
											onClick={ () =>
												onChange(
													swapInline(
														content,
														index,
														index + 1
													)
												)
											}
										>
											{ sprintf(
												/* translators: %s: question name. */
												__( 'Move %s down', 'ohmylms' ),
												name
											) }
										</Button>
										<Button
											variant="tertiary"
											isDestructive
											disabled={ disabled }
											onClick={ () =>
												onChange(
													removeInline(
														content,
														index
													)
												)
											}
										>
											{ sprintf(
												/* translators: %s: question name. */
												__( 'Remove %s', 'ohmylms' ),
												name
											) }
										</Button>
									</td>
								</tr>
							);
						} ) }
					</tbody>
				</WidefatTable>
			) : (
				<p>
					{ __( 'This lesson has no inline questions.', 'ohmylms' ) }
				</p>
			) }
			<TextControl
				label={ __( 'Find an approved practice question', 'ohmylms' ) }
				value={ query }
				maxLength={ 100 }
				disabled={ disabled }
				onChange={ setQuery }
			/>
			<Button
				variant="secondary"
				disabled={ disabled }
				onClick={ () => setSearch( query ) }
			>
				{ __( 'Search questions', 'ohmylms' ) }
			</Button>
			{ null !== search && ! disabled && (
				<Results
					search={ search }
					onPick={ ( question ) => {
						setPicked( ( current ) => ( {
							...current,
							[ question.uuid.toLowerCase() ]: {
								id: question.id,
								title: question.name,
								status: 'approved',
							},
						} ) );
						onChange(
							appendInline(
								content,
								question.id,
								question.uuid.toLowerCase()
							)
						);
					} }
				/>
			) }
		</section>
	);
}
