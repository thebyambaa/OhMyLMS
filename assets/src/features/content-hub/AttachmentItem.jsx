import { createElement } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { Button, CheckboxControl, TextControl } from '@wordpress/components';
import { courseEditPath } from './hubRoutes.mjs';
import { editPath } from './api.mjs';
import { withEditorReturn, lessonEditorHref } from './editorNavigation.mjs';
const typeLabel = ( type ) =>
	( {
		lesson: __( 'Lesson', 'ohmylms' ),
		quiz: __( 'Quiz', 'ohmylms' ),
		assignment: __( 'Assignment', 'ohmylms' ),
	} )[ type ] || type;

/**
 * One attached lesson, quiz or assignment: where it is edited, whether it counts, and where else it is used.
 * @param root0
 * @param root0.item
 * @param root0.skills
 * @param root0.actions
 * @param root0.returnTo
 */
export function AttachmentItem( { item, skills, actions, returnTo } ) {
	const covered = item.skill_ids
		.map( ( id ) => skills.find( ( skill ) => skill.term_id === id )?.name )
		.filter( Boolean );
	return (
		<li className="ohmylms-catalog-item">
			<span className={ `ohmylms-catalog-type is-${ item.type }` }>
				{ typeLabel( item.type ) }
			</span>
			<a
				className="ohmylms-catalog-item-title"
				href={
					item.type === 'lesson'
						? lessonEditorHref(
								{
									...item,
									id: item.content_id,
								},
								returnTo
							)
						: `#${ withEditorReturn( editPath( item.type, item.content_id ), returnTo ) }`
				}
			>
				{ item.title }
			</a>
			{ item.status !== 'publish' && (
				<span className="ohmylms-catalog-flag is-warning">
					{ item.status === 'missing'
						? __( 'Unavailable', 'ohmylms' )
						: __( 'Not published', 'ohmylms' ) }
				</span>
			) }
			{ covered.length > 1 && (
				<span className="ohmylms-ext-muted">
					{ sprintf(
						/* translators: %d: number of skills. */ __(
							'Covers %d skills',
							'ohmylms'
						),
						covered.length
					) }
				</span>
			) }
			{ item.also_in.length > 0 && (
				<span className="ohmylms-ext-muted">
					{ __( 'Also in', 'ohmylms' ) }{ ' ' }
					{ item.also_in.map( ( course, index ) => (
						<span key={ course.id }>
							{ index > 0 && ', ' }
							<a href={ `#${ courseEditPath( course.id ) }` }>
								{ course.title }
							</a>
						</span>
					) ) }
				</span>
			) }
			<span className="ohmylms-catalog-item-controls">
				<CheckboxControl
					label={ __( 'Required', 'ohmylms' ) }
					checked={ item.required }
					onChange={ ( required ) =>
						actions.updateAttachment( item.id, {
							required,
						} )
					}
					__nextHasNoMarginBottom
				/>
				{ item.type === 'quiz' && (
					<TextControl
						label={ __( 'Pass %', 'ohmylms' ) }
						type="number"
						min={ 0 }
						max={ 100 }
						value={ item.pass_percent ?? 80 }
						onChange={ ( value ) =>
							actions.updateAttachment( item.id, {
								pass_percent: Math.max(
									0,
									Math.min( 100, Number( value ) || 0 )
								),
							} )
						}
						__nextHasNoMarginBottom
					/>
				) }
				<Button
					variant="link"
					isDestructive
					onClick={ () => actions.removeAttachment( item.id ) }
					aria-label={ sprintf(
						/* translators: %s: lesson or chapter title. */ __(
							'Remove %s from this course',
							'ohmylms'
						),
						item.title
					) }
				>
					{ __( 'Remove', 'ohmylms' ) }
				</Button>
			</span>
		</li>
	);
}
