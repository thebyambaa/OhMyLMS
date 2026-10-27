/** @jsx createElement */
import { createElement, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { Button, SelectControl, TextControl } from '@wordpress/components';
import {
	addGroup,
	assignItem,
	deleteGroup,
	groupsOf,
	itemsIn,
	moveGroup,
	renameGroup,
} from './groups.mjs';

/**
 * Lesson groups of each unit: create, rename, reorder, delete, and move items between groups.
 * The program is edited in place by the parent; this component only produces the next program.
 * @param {Object}                    root0
 * @param {Object}                    root0.program  Program draft.
 * @param {Object[]}                  root0.chapters Units of the course: [{id, name}].
 * @param {(program: Object) => void} root0.onChange Called with the next program.
 * @param {() => string}              root0.newId    Returns a new UUID.
 * @param {boolean}                   root0.disabled Disables the controls while saving.
 */
export function LessonGroups( {
	program,
	chapters,
	onChange,
	newId,
	disabled,
} ) {
	const [ titles, setTitles ] = useState( {} );

	if ( ! chapters.length ) {
		return (
			<p className="description">
				{ __(
					'Add units to the course to arrange its lessons in groups.',
					'ohmylms'
				) }
			</p>
		);
	}

	const itemRow = ( item, chapterId ) => (
		<li key={ item.id } className="ohmylms-lesson-group-item">
			<span>
				<strong>{ item.name || `#${ item.content_id }` }</strong>
				<small> · { item.type }</small>
			</span>
			<SelectControl
				label={ sprintf(
					/* translators: %s: lesson, quiz or skill name. */
					__( 'Group for %s', 'ohmylms' ),
					item.name || `#${ item.content_id }`
				) }
				hideLabelFromVision
				disabled={ disabled }
				value={ item.group_id || '' }
				options={ [
					{ label: __( 'No group', 'ohmylms' ), value: '' },
					...groupsOf( program, chapterId ).map( ( group ) => ( {
						label:
							group.title || __( '(unnamed group)', 'ohmylms' ),
						value: group.id,
					} ) ),
				] }
				onChange={ ( groupId ) =>
					onChange( assignItem( program, item.id, groupId ) )
				}
			/>
		</li>
	);

	return (
		<div className="ohmylms-lesson-groups">
			<p className="description">
				{ __(
					'Group a unit’s lessons, quizzes and practice into named sections. Learners see the groups on the course page after the learning program is published.',
					'ohmylms'
				) }
			</p>
			{ chapters.map( ( chapter ) => {
				const groups = groupsOf( program, chapter.id );
				const loose = itemsIn( program, chapter.id );
				const title = titles[ chapter.id ] || '';
				const create = () => {
					const next = addGroup(
						program,
						chapter.id,
						title,
						newId()
					);
					if ( next !== program ) {
						onChange( next );
						setTitles( { ...titles, [ chapter.id ]: '' } );
					}
				};
				return (
					<section
						key={ chapter.id }
						className="ohmylms-lesson-groups-unit"
						aria-label={ chapter.name }
					>
						<h4>{ chapter.name }</h4>
						{ groups.map( ( group, index ) => {
							const members = itemsIn(
								program,
								chapter.id,
								group.id
							);
							return (
								<div
									key={ group.id }
									className="ohmylms-lesson-group"
								>
									<div className="ohmylms-lesson-group-head">
										<TextControl
											label={ __(
												'Group name',
												'ohmylms'
											) }
											hideLabelFromVision
											disabled={ disabled }
											value={ group.title }
											maxLength={ 120 }
											onChange={ ( value ) =>
												onChange(
													renameGroup(
														program,
														group.id,
														value
													)
												)
											}
										/>
										<Button
											icon="arrow-up-alt2"
											label={ __(
												'Move group up',
												'ohmylms'
											) }
											disabled={ disabled || ! index }
											onClick={ () =>
												onChange(
													moveGroup(
														program,
														group.id,
														-1
													)
												)
											}
										/>
										<Button
											icon="arrow-down-alt2"
											label={ __(
												'Move group down',
												'ohmylms'
											) }
											disabled={
												disabled ||
												index === groups.length - 1
											}
											onClick={ () =>
												onChange(
													moveGroup(
														program,
														group.id,
														1
													)
												)
											}
										/>
										<Button
											icon="trash"
											isDestructive
											label={ __(
												'Delete group (its content stays in the unit)',
												'ohmylms'
											) }
											disabled={ disabled }
											onClick={ () =>
												onChange(
													deleteGroup(
														program,
														group.id
													)
												)
											}
										/>
									</div>
									{ members.length ? (
										<ul>
											{ members.map( ( item ) =>
												itemRow( item, chapter.id )
											) }
										</ul>
									) : (
										<p className="description">
											{ __(
												'Empty. Learners do not see empty groups.',
												'ohmylms'
											) }
										</p>
									) }
								</div>
							);
						} ) }
						{ loose.length > 0 && (
							<div className="ohmylms-lesson-group">
								<strong>
									{ groups.length
										? __( 'Not in a group', 'ohmylms' )
										: __( 'Content', 'ohmylms' ) }
								</strong>
								<ul>
									{ loose.map( ( item ) =>
										itemRow( item, chapter.id )
									) }
								</ul>
							</div>
						) }
						<div className="ohmylms-learning-editor-add">
							<TextControl
								label={ __( 'New group name', 'ohmylms' ) }
								disabled={ disabled }
								value={ title }
								maxLength={ 120 }
								onChange={ ( value ) =>
									setTitles( {
										...titles,
										[ chapter.id ]: value,
									} )
								}
								onKeyDown={ ( event ) => {
									if ( event.key === 'Enter' ) {
										event.preventDefault();
										create();
									}
								} }
							/>
							<Button
								variant="secondary"
								disabled={ disabled || ! title.trim() }
								onClick={ create }
							>
								{ __( 'Add group', 'ohmylms' ) }
							</Button>
						</div>
					</section>
				);
			} ) }
		</div>
	);
}
