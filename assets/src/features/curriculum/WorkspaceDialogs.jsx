import { createElement, useEffect, useState } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import {
	Button,
	Modal,
	Notice,
	RadioControl,
	Spinner,
} from '@wordpress/components';
import * as api from './api.mjs';
import { groupLabel } from './syllabus.mjs';

/**
 * Deleting a topic. Linked courses, skills, question banks and exams are never deleted, only their place in
 * the structure, and the skills in deleted chapters stay in the skill library. A topic with sub-topics needs
 * a choice for them.
 * @param root0
 * @param root0.item
 * @param root0.pending
 * @param root0.onConfirm
 * @param root0.onClose
 */
export function DeleteTopicDialog( { item, pending, onConfirm, onClose } ) {
	const [ info, setInfo ] = useState( null );
	const [ error, setError ] = useState( '' );
	const [ strategy, setStrategy ] = useState( 'promote' );
	useEffect( () => {
		let current = true;
		api.loadItem( item.id )
			.then( ( response ) => current && setInfo( response.dependents ) )
			.catch(
				( cause ) =>
					current &&
					setError(
						cause?.message ||
							__(
								'Could not check what this affects.',
								'ohmylms'
							)
					)
			);
		return () => {
			current = false;
		};
	}, [ item.id ] );
	const branch = info && info.children > 0 && strategy === 'delete';
	const bundles = info
		? ( branch ? info.bundles : info.own_bundles ) || 0
		: 0;
	return (
		<Modal
			title={ sprintf(
				/* translators: %s: curriculum item name. */ __(
					'Delete “%s”?',
					'ohmylms'
				),
				item.name
			) }
			onRequestClose={ onClose }
			className="ohmylms-content-hub-dialog"
		>
			{ error && (
				<Notice status="error" isDismissible={ false }>
					{ error }
				</Notice>
			) }
			{ ! info && ! error && <Spinner /> }
			{ info && (
				<>
					{ info.children > 0 && (
						<RadioControl
							label={ sprintf(
								/* translators: %d: number of curriculum folders. */ _n(
									'This folder has %d folder.',
									'This folder has %d folders.',
									info.children,
									'ohmylms'
								),
								info.children
							) }
							selected={ strategy }
							options={ [
								{
									value: 'promote',
									label: __(
										'Move its folders up one level and delete only this folder',
										'ohmylms'
									),
								},
								{
									value: 'delete',
									label: sprintf(
										/* translators: %d: number of curriculum folders. */ _n(
											'Delete this folder and everything under it (%d folder in total)',
											'Delete this folder and everything under it (%d folders in total)',
											info.descendants + 1,
											'ohmylms'
										),
										info.descendants + 1
									),
								},
							] }
							onChange={ setStrategy }
						/>
					) }
					<ul className="ohmylms-cur-delete-effects">
						<li>
							{ __(
								'Skills in the deleted folder stay in the skill library, with their lessons and questions.',
								'ohmylms'
							) }
						</li>
						{ bundles > 0 && (
							<li>
								{ sprintf(
									/* translators: %d: number of course bundles. */ _n(
										'It will be removed from %d course bundle.',
										'It will be removed from %d course bundles.',
										bundles,
										'ohmylms'
									),
									bundles
								) }
							</li>
						) }
					</ul>
				</>
			) }
			<div className="ohmylms-content-hub-dialog-actions">
				<Button variant="tertiary" onClick={ onClose }>
					{ __( 'Cancel', 'ohmylms' ) }
				</Button>
				<Button
					variant="primary"
					isDestructive
					isBusy={ pending }
					disabled={ ! info || pending }
					onClick={ () =>
						onConfirm( {
							children: info.children ? strategy : '',
							confirm: true,
						} )
					}
				>
					{ __( 'Delete folder', 'ohmylms' ) }
				</Button>
			</div>
		</Modal>
	);
}

/**
 * Deleting a chapter (a skill group). Its skills stay in the skill library; the course loses the chapter.
 * @param root0
 * @param root0.group
 * @param root0.pending
 * @param root0.onConfirm
 * @param root0.onClose
 */
export function DeleteChapterDialog( { group, pending, onConfirm, onClose } ) {
	const skills = group.skills.length;
	return (
		<Modal
			title={ sprintf(
				/* translators: %s: curriculum item name. */ __(
					'Delete “%s”?',
					'ohmylms'
				),
				groupLabel( group )
			) }
			onRequestClose={ onClose }
			className="ohmylms-content-hub-dialog"
		>
			<p>
				{ skills
					? sprintf(
							/* translators: %d: number of selected questions. */ _n(
								'This folder holds %d skill. The skill stays in the skill library, with its lessons and questions, but leaves this syllabus and its course.',
								'This folder holds %d skills. The skills stay in the skill library, with their lessons and questions, but leave this syllabus and its course.',
								skills,
								'ohmylms'
							),
							skills
						)
					: __( 'This folder is empty.', 'ohmylms' ) }
			</p>
			<div className="ohmylms-content-hub-dialog-actions">
				<Button variant="tertiary" onClick={ onClose }>
					{ __( 'Cancel', 'ohmylms' ) }
				</Button>
				<Button
					variant="primary"
					isDestructive
					isBusy={ pending }
					disabled={ pending }
					onClick={ onConfirm }
				>
					{ __( 'Delete folder', 'ohmylms' ) }
				</Button>
			</div>
		</Modal>
	);
}
