import { useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { api, boot } from './api';
import type { ContentItem } from './types';

/**
 * Lists courses, lessons and quizzes. Lessons open in Gutenberg.
 *
 * @param props        Props.
 * @param props.filter course, lesson or quiz.
 */
export function Content( { filter }: { filter: string } ) {
	const links = boot().links;
	const [ type, setType ] = useState( filter || 'course' );
	const [ status, setStatus ] = useState( '' );
	const [ search, setSearch ] = useState( '' );
	const [ items, setItems ] = useState< ContentItem[] >( [] );
	useEffect( () => {
		if ( filter ) {
			setType( filter );
		}
	}, [ filter ] );
	useEffect( () => {
		void api< { items: ContentItem[] } >(
			`/content?type=${ type }&status=${ status }&search=${ encodeURIComponent( search ) }`
		).then( ( body ) => setItems( body.items ) );
	}, [ type, status, search ] );
	return (
		<section>
			<div className="oml-dash-actions">
				<a className="oml-dash-btn" href={ links.courses }>
					{ __( 'Course builder', 'ohmylms' ) }
				</a>
				<a className="oml-dash-btn" href={ links.quizzes }>
					{ __( 'Quiz builder', 'ohmylms' ) }
				</a>
				<a className="oml-dash-btn" href={ links.skills }>
					{ __( 'Skills', 'ohmylms' ) }
				</a>
			</div>
			<div className="oml-dash-actions">
				<button
					type="button"
					className="oml-dash-btn"
					onClick={ () => setType( 'course' ) }
				>
					{ __( 'Courses', 'ohmylms' ) }
				</button>
				<button
					type="button"
					className="oml-dash-btn"
					onClick={ () => setType( 'lesson' ) }
				>
					{ __( 'Lessons', 'ohmylms' ) }
				</button>
				<button
					type="button"
					className="oml-dash-btn"
					onClick={ () => setType( 'quiz' ) }
				>
					{ __( 'Quizzes & exams', 'ohmylms' ) }
				</button>
				<select
					value={ status }
					onChange={ ( event ) => setStatus( event.target.value ) }
				>
					<option value="">{ __( 'Any status', 'ohmylms' ) }</option>
					<option value="publish">
						{ __( 'Published', 'ohmylms' ) }
					</option>
					<option value="draft">{ __( 'Draft', 'ohmylms' ) }</option>
				</select>
				<input
					placeholder={ __( 'Search', 'ohmylms' ) }
					value={ search }
					onChange={ ( event ) => setSearch( event.target.value ) }
				/>
			</div>
			<table className="oml-dash-table">
				<tbody>
					{ items.map( ( item ) => (
						<tr key={ item.id }>
							<td>{ item.title }</td>
							<td>{ item.status }</td>
							<td>
								{ item.edit ? (
									<a href={ item.edit }>
										{ item.type === 'lesson'
											? __(
													'Edit in Gutenberg',
													'ohmylms'
												)
											: __( 'Edit', 'ohmylms' ) }
									</a>
								) : null }
							</td>
						</tr>
					) ) }
				</tbody>
			</table>
			{ ! items.length && (
				<p className="oml-dash-note">
					{ __( 'Nothing matches.', 'ohmylms' ) }
				</p>
			) }
		</section>
	);
}
